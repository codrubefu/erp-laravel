<?php

namespace App\Events\Services;

use App\Events\Models\EventOccurrence;
use App\Subscription\Models\Subscription;
use App\Users\Models\User;

class EventEligibilityService
{
    public function canUserJoinOccurrence(User $user, EventOccurrence $occurrence): bool
    {
        $event = $occurrence->event()->with('requiredSubscription')->firstOrFail();

        if (! $event->requires_active_subscription) {
            return true;
        }

        return $this->hasActiveSubscription($user, $event->requiredSubscription);
    }

    public function hasActiveSubscription(User $user, ?Subscription $requiredSubscription = null): bool
    {
        $query = $user->activeSubscriptions();

        if ($requiredSubscription) {
            $query->where('subscriptions.id', $requiredSubscription->id);
        }

        return $query->exists();
    }
}
