<?php

namespace App\Users\Models;

use App\Users\Models\Organization;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Payments\Models\Payment;
use App\Articles\Models\Article;
use App\Subscription\Models\Subscription;
use App\Subscription\Models\SubscriptionUser;
use App\Subscription\Services\SubscriptionLifecycleService;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use App\Users\Models\Scopes\LocationAccessScope;
use App\Users\Services\OrganizationAccessService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Notifications\Models\NotificationPreference;
use App\Notifications\Models\PushDevice;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['user_code', 'first_name', 'last_name', 'phone', 'active', 'email', 'password', 'organization_id', 'notification_consents', 'push_token'])]
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
            ->using(SubscriptionUser::class)
            ->withPivot(['id', 'status', 'start_date', 'expires_at', 'accesses_used', 'activated_at', 'suspended_at', 'resume_at', 'status_reason', 'activation_payment_id'])
            ->withTimestamps();
    }

    public function activeSubscriptions(): BelongsToMany
    {
        $this->subscriptionAssignments()->with('subscription')->get()
            ->each(fn (SubscriptionUser $assignment) => app(SubscriptionLifecycleService::class)->refresh($assignment));

        return $this->subscriptions()
            ->where('subscriptions.is_active', true)
            ->wherePivot('status', 'active');
    }

    public function subscriptionAssignments(): HasMany
    {
        return $this->hasMany(SubscriptionUser::class);
    }

    public function eventOccurrences(): BelongsToMany
    {
        return $this->belongsToMany(EventOccurrence::class, 'event_occurrence_user')
            ->withPivot(['status', 'registered_at', 'notes'])
            ->withTimestamps();
    }

    public function hasRight(string $right): bool
    {
        if (app(OrganizationAccessService::class)->isRightDisabledForOrganization($right, $this->organization_id)) {
            return false;
        }

        return $this->groups()
            ->whereHas('rights', fn ($query) => $query->where('name', $right))
            ->exists();
    }

    public function hasAnyRight(array $rights): bool
    {
        $rights = app(OrganizationAccessService::class)->availableRightNames($rights, $this->organization_id);

        if ($rights === []) {
            return false;
        }

        return $this->groups()
            ->whereHas('rights', fn ($query) => $query->whereIn('name', $rights))
            ->exists();
    }

    public function consentsTo(string $channel): bool
    {
        return $this->consentsToScope($channel, 'all');
    }

    public function consentsToScope(string $channel, string $scope): bool
    {
        $optedOut = $this->notificationPreferences()->where('channel', $channel)
            ->whereIn('scope', ['all', $scope])->where('subscribed', false)->exists();

        return ! $optedOut && (bool) ($this->notification_consents[$channel] ?? false)
            && match ($channel) {
                'sms' => filled($this->phone),
                'mail' => filled($this->email),
                'push' => $this->pushDevices()->exists() || filled($this->push_token),
                default => false,
            };
    }

    public function notificationPreferences(): HasMany { return $this->hasMany(NotificationPreference::class); }
    public function pushDevices(): HasMany { return $this->hasMany(PushDevice::class); }

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
            'notification_consents' => 'array',
        ];
    }
}
