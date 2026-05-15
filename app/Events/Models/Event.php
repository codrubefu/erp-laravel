<?php

namespace App\Events\Models;

use App\Subscription\Models\Subscription;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title',
    'description',
    'location',
    'start_time',
    'end_time',
    'recurrence_type',
    'recurrence_days',
    'monthly_day',
    'start_date',
    'end_date',
    'requires_active_subscription',
    'required_subscription_id',
    'max_participants',
    'status',
])]
class Event extends Model
{
    use HasFactory, SoftDeletes;

    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class);
    }

    public function requiredSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'required_subscription_id');
    }

    protected function casts(): array
    {
        return [
            'recurrence_days' => 'array',
            'monthly_day' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'requires_active_subscription' => 'boolean',
            'max_participants' => 'integer',
        ];
    }
}
