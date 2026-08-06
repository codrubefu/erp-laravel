<?php

namespace App\Notifications\Events;

use App\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationRequested
{
    use Dispatchable, SerializesModels;

    public const SUBSCRIPTION_ACTIVATED = 'subscription.activated';
    public const SUBSCRIPTION_EXPIRING = 'subscription.expiring';
    public const SUBSCRIPTION_EXPIRED = 'subscription.expired';
    public const SCHEDULE_CHANGED = 'schedule.changed';
    public const URGENT_ANNOUNCEMENT = 'announcement.urgent';
    public const RESUMED = 'activity.resumed';

    public function __construct(
        public User $user,
        public string $type,
        public string $key,
        public array $payload = [],
    ) {}
}
