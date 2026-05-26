<?php

namespace App\Subscription\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Users\Models\User;

#[Fillable([
    'name',
    'description',
    'price',
    'currency',
    'duration_days',
    'max_users',
    'is_active',
])]
class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\Factory<static>> */
    use HasFactory, SoftDeletes;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['id', 'start_date', 'expires_at'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'max_users' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
