<?php

namespace App\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_days' => $this->recurrence_days,
            'monthly_day' => $this->monthly_day,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'requires_active_subscription' => $this->requires_active_subscription,
            'required_subscription_id' => $this->required_subscription_id,
            'required_subscription' => $this->whenLoaded('requiredSubscription'),
            'max_participants' => $this->max_participants,
            'status' => $this->status,
            'occurrences_count' => $this->whenCounted('occurrences'),
            'occurrences' => EventOccurrenceResource::collection($this->whenLoaded('occurrences')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
