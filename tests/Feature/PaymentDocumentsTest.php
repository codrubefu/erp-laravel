<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Payments\Services\ReceiptService;
use App\Service\Models\Service;
use App\Service\Models\ServiceUser;
use App\Service\Services\PaymentNoteService;
use App\Users\Models\Organization;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_note_shows_client_id_and_company_data_without_internal_details(): void
    {
        $assignment = $this->assignment(['bill_number' => 'BILL000007']);
        $user = $assignment->user;

        $html = app(PaymentNoteService::class)->html($assignment);

        $this->assertStringContainsString("SPA ID: {$user->id}<", $html);
        $this->assertStringNotContainsString('SPA ID: CARD-123', $html);
        $this->assertStringContainsString('CARD-123', $html);

        foreach (['Nota plata service', 'Extern', 'Independent', 'Comanda service'] as $removed) {
            $this->assertStringNotContainsString($removed, $html);
        }

        $this->assertCompanyData($html);
    }

    public function test_receipt_shows_payment_note_number_and_company_data_without_internal_details(): void
    {
        $assignment = $this->assignment(['bill_number' => 'BILL000007', 'invoice_number' => 'INV000003']);
        $payment = $this->payment($assignment);

        $html = app(ReceiptService::class)->html($payment);

        $this->assertStringContainsString('nota de plata nr. BILL000007', $html);
        $this->assertStringContainsString('factura nr. INV000003', $html);
        $this->assertStringNotContainsString('contravaloare factura nr.', $html);
        $this->assertStringNotContainsString("plata #{$payment->id}", $html);
        $this->assertStringNotContainsString('metoda', $html);
        $this->assertStringNotContainsString('Adresa', $html);
        $this->assertStringNotContainsString('Provider:', $html);

        $this->assertCompanyData($html);
    }

    public function test_receipt_uses_the_number_printed_on_a_payment_note_without_bill_number(): void
    {
        $assignment = $this->assignment();

        $html = app(ReceiptService::class)->html($this->payment($assignment));

        $this->assertStringContainsString('nota de plata nr. '.$assignment->noteNumber(), $html);
        $this->assertStringContainsString(
            '>'.$assignment->noteNumber().'<',
            app(PaymentNoteService::class)->html($assignment),
        );
    }

    private function assertCompanyData(string $html): void
    {
        foreach (['ACME SPA SRL', 'Str. Exemplu 1', 'RO123456', 'J22/1/2020', 'RO49AAAA', 'Banca Test'] as $value) {
            $this->assertStringContainsString($value, $html);
        }
    }

    private function assignment(array $attributes = []): ServiceUser
    {
        $organization = Organization::factory()->create([
            'name' => 'ACME SPA SRL',
            'address' => 'Str. Exemplu 1',
            'cui' => 'RO123456',
            'nr_reg_com' => 'J22/1/2020',
            'cont' => 'RO49AAAA',
            'bank' => 'Banca Test',
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'user_code' => 'CARD-123',
        ]);
        $service = Service::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Abonament',
            'description' => 'Abonament',
            'price' => 3000,
            'currency' => 'RON',
            'duration_days' => null,
            'max_users' => 20,
            'is_active' => true,
        ]);

        return ServiceUser::query()->create([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'status' => 'active',
            'start_date' => '2026-11-01',
            ...$attributes,
        ]);
    }

    private function payment(ServiceUser $assignment): Payment
    {
        return Payment::query()->create([
            'organization_id' => $assignment->service->organization_id,
            'first_name' => 'Nicusor',
            'last_name' => 'Dan',
            'payment_type_id' => Payment::TYPE_CASH,
            'status' => Payment::STATUS_CONFIRMED,
            'receipt_number' => 'CH000060',
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => $assignment->id,
            'amount' => 3000,
            'paid_at' => '2026-11-04 17:03:00',
            'admin_id' => $assignment->user_id,
        ]);
    }
}
