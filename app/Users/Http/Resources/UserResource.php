<?php

namespace App\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'active' => $this->active,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'groups' => $this->whenLoaded('groups'),
            'locations' => $this->whenLoaded('locations'),
            'subscriptions' => $this->whenLoaded('subscriptions'),
            'subscription_history' => $this->whenLoaded('subscriptions', function (): array {
                return $this->subscriptions->map(function ($subscription): array {
                    $startDate = $subscription->pivot?->start_date;
                    $expiresAt = $subscription->pivot?->expires_at;
                    $today = now()->toDateString();

                    return [
                        'id' => $subscription->pivot?->id,
                        'subscription_id' => $subscription->id,
                        'name' => $subscription->name,
                        'start_date' => $startDate,
                        'expires_at' => $expiresAt,
                        'is_active' => $subscription->is_active
                            && ($startDate === null || $startDate <= $today)
                            && ($expiresAt === null || $expiresAt >= $today),
                    ];
                })->all();
            }),
            'active_subscriptions' => $this->whenLoaded('activeSubscriptions'),
            'has_active_subscription' => $this->whenLoaded(
                'activeSubscriptions',
                fn () => $this->activeSubscriptions->isNotEmpty()
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
