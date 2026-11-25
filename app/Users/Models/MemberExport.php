<?php

namespace App\Users\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'requested_by', 'fields', 'filters', 'language', 'status', 'total', 'processed', 'path', 'expires_at'])]
class MemberExport extends Model
{
    use HasUuids;

    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const READY = 'ready';
    public const FAILED = 'failed';

    private const STALLED_AFTER_MINUTES = 10;

    /** An export is personal: the requester's location access decided its content. */
    public function scopeAvailableTo(Builder $query, User $user): void
    {
        $query->where('organization_id', $user->organization_id)
            ->where('requested_by', $user->getKey())
            ->where('expires_at', '>', now());
    }

    /** Progress updates are the heartbeat; without them the worker died without reporting. */
    public function effectiveStatus(): string
    {
        $stalled = $this->status === self::PROCESSING
            && $this->updated_at->lt(now()->subMinutes(self::STALLED_AFTER_MINUTES));

        return $stalled ? self::FAILED : $this->status;
    }

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            // The search text may contain personal data.
            'filters' => 'encrypted:array',
            'total' => 'integer',
            'processed' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
