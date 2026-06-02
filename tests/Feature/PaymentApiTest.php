<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Subscription\Models\Subscription;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_payments_with_related_details(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['payments.view']);
        $subscription = Subscription::query()->create($this->subscriptionData(['name' => 'Gold']));
        Payment::query()->create($this->paymentData([
            'model_id' => $subscription->id,
            'admin_id' => $admin->id,
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/payments')
            ->assertOk()
            ->assertJsonPath('data.0.payment_type', 'card')
            ->assertJsonPath('data.0.amount', '25.50')
            ->assertJsonPath('data.0.admin.id', $admin->id)
            ->assertJsonPath('data.0.subscription.name', 'Gold');
    }

    public function test_user_with_create_right_can_create_subscription_payment_for_authenticated_admin(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['payments.create']);
        $subscription = Subscription::query()->create($this->subscriptionData());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/payments', [
                'first_name' => 'Jane',
                'last_name' => 'Client',
                'payment_type_id' => Payment::TYPE_CASH,
                'model_id' => $subscription->id,
                'amount' => 99.99,
                'paid_at' => '2026-06-01 10:15:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.model_type', Payment::MODEL_TYPE_SUBSCRIPTION)
            ->assertJsonPath('data.payment_type', 'cash')
            ->assertJsonPath('data.admin_id', $admin->id)
            ->assertJsonPath('data.subscription.id', $subscription->id);

        $this->assertDatabaseHas('payments', [
            'first_name' => 'Jane',
            'last_name' => 'Client',
            'payment_type_id' => Payment::TYPE_CASH,
            'model_type' => Payment::MODEL_TYPE_SUBSCRIPTION,
            'model_id' => $subscription->id,
            'admin_id' => $admin->id,
        ]);
    }

    public function test_create_payment_validates_required_supported_fields(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['payments.create']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/payments', [
                'payment_type_id' => 9,
                'model_type' => Payment::MODEL_TYPE_SUBSCRIPTION,
                'amount' => -1,
                'paid_at' => 'not-a-date',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'payment_type_id',
                'model_id',
                'amount',
                'paid_at',
            ]);
    }

    public function test_user_with_update_right_can_attach_subscription_model_to_payment(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['payments.update']);
        $originalSubscription = Subscription::query()->create($this->subscriptionData(['name' => 'Original']));
        $selectedSubscription = Subscription::query()->create($this->subscriptionData(['name' => 'Selected']));
        $payment = Payment::query()->create($this->paymentData([
            'model_id' => $originalSubscription->id,
            'admin_id' => $admin->id,
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/payments/{$payment->id}/attach-model", [
                'model_type' => Payment::MODEL_TYPE_SUBSCRIPTION,
                'model_id' => $selectedSubscription->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.model_type', Payment::MODEL_TYPE_SUBSCRIPTION)
            ->assertJsonPath('data.model_id', $selectedSubscription->id)
            ->assertJsonPath('data.subscription.name', 'Selected');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'model_type' => Payment::MODEL_TYPE_SUBSCRIPTION,
            'model_id' => $selectedSubscription->id,
        ]);
    }

    public function test_user_without_payment_right_cannot_create_payment(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['payments.view']);
        $subscription = Subscription::query()->create($this->subscriptionData());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/payments', $this->paymentData([
                'model_id' => $subscription->id,
            ]))
            ->assertForbidden();
    }

    private function subscriptionData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Enterprise',
            'description' => 'Enterprise subscription',
            'price' => 99.99,
            'currency' => 'EUR',
            'duration_days' => null,
            'max_users' => 25,
            'is_active' => true,
        ], $overrides);
    }

    private function paymentData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'John',
            'last_name' => 'Member',
            'payment_type_id' => Payment::TYPE_CARD,
            'model_type' => Payment::MODEL_TYPE_SUBSCRIPTION,
            'model_id' => null,
            'amount' => 25.50,
            'paid_at' => '2026-06-01 12:00:00',
            'admin_id' => null,
        ], $overrides);
    }

    private function authenticatedUserWithRights(array $rightNames): array
    {
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $group = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Test Group',
        ]);

        foreach ($rightNames as $rightName) {
            $right = Right::query()->create([
                'name' => $rightName,
                'label' => $rightName,
            ]);
            $group->rights()->attach($right);
        }

        $user->groups()->attach($group);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
            'password' => 'password',
        ])->json('token');

        return [$user, $token];
    }
}
