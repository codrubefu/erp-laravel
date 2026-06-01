<?php

namespace App\Sms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmsMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'subscription_id' => $this->subscription_id,
            'subscription_user_id' => $this->subscription_user_id,
            'type' => $this->type,
            'destination' => $this->destination,
            'message' => $this->message,
            'status' => $this->status,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->id,
                'user_code' => $this->user->user_code,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),
            'subscription' => $this->whenLoaded('subscription', fn (): ?array => $this->subscription === null ? null : [
                'id' => $this->subscription->id,
                'name' => $this->subscription->name,
                'is_active' => $this->subscription->is_active,
            ]),
        ];
    }
}