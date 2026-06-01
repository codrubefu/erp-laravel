<?php

namespace App\Payments\Http\Resources;

use App\Subscription\Http\Resources\SubscriptionResource;
use App\Users\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'payment_type_id' => $this->payment_type_id,
            'payment_type' => $this->paymentTypeName(),
            'model_type' => $this->model_type,
            'subscription_id' => $this->subscription_id,
            'amount' => $this->amount,
            'paid_at' => $this->paid_at,
            'admin_id' => $this->admin_id,
            'admin' => new UserResource($this->whenLoaded('admin')),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
