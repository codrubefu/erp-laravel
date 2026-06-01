<?php

namespace App\Users\Models;

use App\Users\Models\Organization;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Payments\Models\Payment;
use App\Articles\Models\Article;
use App\Subscription\Models\Subscription;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use App\Users\Models\Scopes\LocationAccessScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['user_code', 'first_name', 'last_name', 'phone', 'active', 'email', 'password', 'organization_id'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    use LogsModelChanges;
    use BelongsToAuthenticatedOrganization;
    use SetsOrganizationFromAuthenticatedUser;


    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
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

    public function registeredPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'admin_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'created_by');
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
        return $this->belongsToMany(Subscription::class)
            ->withPivot(['id', 'start_date', 'expires_at'])
            ->withTimestamps();
    }

    public function activeSubscriptions(): BelongsToMany
    {
        return $this->subscriptions()
            ->where('subscriptions.is_active', true)
            ->where(function ($query): void {
                $query->where('subscription_user.start_date', '<=', now()->toDateString())
                    ->orWhereNull('subscription_user.start_date');
            })
            ->where(function ($query): void {
                $query->where('subscription_user.expires_at', '>=', now()->toDateString())
                    ->orWhereNull('subscription_user.expires_at');
            });
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
