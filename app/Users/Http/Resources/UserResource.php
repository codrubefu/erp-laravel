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
            'active_subscriptions' => $this->whenLoaded('activeSubscriptions'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
