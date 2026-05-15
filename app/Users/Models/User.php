<?php

namespace App\Users\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Subscription\Models\Subscription;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Scopes\LocationAccessScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'last_name', 'phone', 'active', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::addGlobalScope(new LocationAccessScope());
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(PersonalAccessToken::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)->withTimestamps();
    }

    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class)->withTimestamps();
    }

    public function activeSubscriptions(): BelongsToMany
    {
        return $this->subscriptions()->where('subscriptions.is_active', true);
    }

    public function eventOccurrences(): BelongsToMany
    {
        return $this->belongsToMany(EventOccurrence::class, 'event_occurrence_user')
            ->withPivot(['status', 'registered_at', 'notes'])
            ->withTimestamps();
    }

    public function hasRight(string $right): bool
    {
        return $this->groups()
            ->whereHas('rights', fn ($query) => $query->where('name', $right))
            ->exists();
    }

    public function hasAnyRight(array $rights): bool
    {
        return $this->groups()
            ->whereHas('rights', fn ($query) => $query->whereIn('name', $rights))
            ->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
