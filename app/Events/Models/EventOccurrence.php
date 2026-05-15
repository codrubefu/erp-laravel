<?php

namespace App\Events\Models;

use App\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'event_id',
    'occurrence_date',
    'start_datetime',
    'end_datetime',
    'status',
])]
class EventOccurrence extends Model
{
    use HasFactory;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_occurrence_user')
            ->withPivot(['status', 'registered_at', 'notes'])
            ->withTimestamps();
    }

    public function activeParticipants(): BelongsToMany
    {
        return $this->participants()->wherePivotIn('status', ['registered', 'attended']);
    }

    protected function casts(): array
    {
        return [
            'occurrence_date' => 'date:Y-m-d',
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
        ];
    }
}
