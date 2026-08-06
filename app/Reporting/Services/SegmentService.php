<?php

namespace App\Reporting\Services;

use App\Reporting\Models\Segment;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SegmentService
{
    /**
     * Builds the tenant-safe member query reused by reports, announcements and campaigns.
     */
    public function members(Segment $segment): Builder
    {
        $criteria = $segment->criteria;
        $query = User::query()->where('users.organization_id', $segment->organization_id);

        if (array_key_exists('active', $criteria)) {
            $query->where('users.active', (bool) $criteria['active']);
        }
        if (isset($criteria['location_id'])) {
            $query->whereHas('locations', fn (Builder $q) => $q->whereKey($criteria['location_id']));
        }
        if (isset($criteria['subscription_type'])) {
            $query->whereHas('subscriptions', fn (Builder $q) => $q->where('subscriptions.type', $criteria['subscription_type']));
        }
        if (($criteria['expired'] ?? false) === true) {
            $query->whereHas('subscriptionAssignments', fn (Builder $q) => $q->where('expires_at', '<', now()));
        }
        if (isset($criteria['expires_in_days'])) {
            $query->whereHas('subscriptionAssignments', fn (Builder $q) => $q
                ->whereBetween('expires_at', [now(), now()->addDays((int) $criteria['expires_in_days'])]));
        }

        return $query->distinct();
    }
}
