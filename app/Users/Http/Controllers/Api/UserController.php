<?php

namespace App\Users\Http\Controllers\Api;

use App\Notifications\Events\NotificationRequested;
use App\Subscription\Models\Subscription;
use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\SyncUserSubscriptionsRequest;
use App\Users\Http\Requests\StoreUserRequest;
use App\Users\Http\Requests\UpdateUserRequest;
use App\Users\Http\Resources\ActivityResource;
use App\Users\Http\Resources\UserResource;
use App\Users\Models\AuditLog;
use App\Users\Models\Scopes\LocationAccessScope;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;
use App\Users\Services\OrganizationAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(
        private readonly OrganizationAccessService $organizationAccess,
        private readonly BusinessActivityLogger $activityLogger,
    )
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->userList($request);
    }

    public function administrators(Request $request): AnonymousResourceCollection
    {
        return $this->userList($request, hasGroups: true, exceptOnlyRight: 'profile.view');
    }

    public function clients(Request $request): AnonymousResourceCollection
    {
        return $this->userList($request, onlyRight: 'profile.view');
    }

    public function searchByUserCode(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->withoutGlobalScope(LocationAccessScope::class)
            ->with($this->userRelationsForResponse($request->user()?->organization_id))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where('user_code', 'like', "%{$search}%");
            })
            ->orderBy('user_code', 'asc')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    private function userList(
        Request $request,
        ?bool $hasGroups = null,
        ?string $onlyRight = null,
        ?string $exceptOnlyRight = null,
    ): AnonymousResourceCollection
    {
        $users = User::query()
            ->with($this->userRelationsForResponse($request->user()?->organization_id))
            ->when($hasGroups === true, fn ($query) => $query->has('groups'))
            ->when($hasGroups === false, fn ($query) => $query->doesntHave('groups'))
            ->when($onlyRight !== null, function ($query) use ($onlyRight): void {
                $query->where(function ($query) use ($onlyRight): void {
                    $query->whereDoesntHave('groups.rights')
                        ->orWhere(function ($query) use ($onlyRight): void {
                            $query->whereHas('groups.rights', fn ($query) => $query->where('name', $onlyRight))
                                ->whereDoesntHave('groups.rights', fn ($query) => $query->where('name', '!=', $onlyRight));
                        });
                });
            })
            ->when($exceptOnlyRight !== null, function ($query) use ($exceptOnlyRight): void {
                $query->where(function ($query) use ($exceptOnlyRight): void {
                    $query->whereDoesntHave('groups.rights', fn ($query) => $query->where('name', $exceptOnlyRight))
                        ->orWhereHas('groups.rights', fn ($query) => $query->where('name', '!=', $exceptOnlyRight));
                });
            })
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('user_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $groupIds = $data['group_ids'] ?? [];
        $locationIds = $data['location_ids'] ?? [];
        $subscriptionAssignments = $this->subscriptionAssignments($data);
        unset($data['group_ids']);
        unset($data['location_ids']);
        unset($data['subscription_ids']);
        unset($data['subscriptions']);

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        $user = DB::transaction(function () use ($data, $groupIds, $locationIds, $subscriptionAssignments): User {
            $user = User::query()->create($data);
            $user->groups()->sync($groupIds);
            $user->locations()->sync($locationIds);
            $this->attachSubscriptionAssignments($user, $subscriptionAssignments);

            return $user;
        });

        return (new UserResource($this->loadUserForResponse($user, $request->user()?->organization_id, true)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->abortIfUserIsNotVisible($user);

        return new UserResource($this->loadUserForResponse($user, request()->user()?->organization_id, true));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->abortIfUserIsNotVisible($user);

        $data = $request->validated();
        $groupIds = $data['group_ids'] ?? null;
        $locationIds = $data['location_ids'] ?? null;
        $hasSubscriptionAssignments = array_key_exists('subscription_ids', $data) || array_key_exists('subscriptions', $data);
        $subscriptionAssignments = $this->subscriptionAssignments($data);
        unset($data['group_ids']);
        unset($data['location_ids']);
        unset($data['subscription_ids']);
        unset($data['subscriptions']);

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $groupIds, $locationIds, $hasSubscriptionAssignments, $subscriptionAssignments): void {
            $user->update($data);

            if ($groupIds !== null) {
                $user->groups()->sync($groupIds);
            }

            if ($locationIds !== null) {
                $user->locations()->sync($locationIds);
            }

            if ($hasSubscriptionAssignments) {
                $previous = $user->subscriptions()->get()->keyBy('id');
                $assignedIds = collect($subscriptionAssignments)->pluck('id');
                $user->subscriptions()->detach();
                $this->attachSubscriptionAssignments($user, $subscriptionAssignments, $previous->keys()->all());
                $previous->except($assignedIds->all())->each(function (Subscription $subscription) use ($user): void {
                    $this->activityLogger->record(AuditLog::SUBSCRIPTION_SUSPENDED, $user, $subscription, [], [
                        'subscription_id' => $subscription->id,
                    ]);
                });
            }
        });

        return new UserResource($this->loadUserForResponse($user, $request->user()?->organization_id, true));
    }

    public function syncSubscriptions(SyncUserSubscriptionsRequest $request, User $user): UserResource
    {
        $this->abortIfUserIsNotVisible($user);

        DB::transaction(function () use ($request, $user): void {
            $previous = $user->subscriptions()->get()->keyBy('id');
            $assignments = $this->subscriptionAssignments($request->validated());
            $assignedIds = collect($assignments)->pluck('id');
            $user->subscriptions()->detach();
            $this->attachSubscriptionAssignments($user, $assignments, $previous->keys()->all());

            $previous->except($assignedIds->all())->each(function (Subscription $subscription) use ($user): void {
                $this->activityLogger->record(AuditLog::SUBSCRIPTION_SUSPENDED, $user, $subscription, [], [
                    'subscription_id' => $subscription->id,
                ]);
            });
        });

        return new UserResource($this->loadUserForResponse($user, request()->user()?->organization_id, true));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->abortIfUserIsNotVisible($user);

        if ($request->user()?->is($user)) {
            return response()->json([
                'message' => 'You cannot delete your own user account.',
            ], 422);
        }

        DB::transaction(function () use ($user): void {
            $user->accessTokens()->delete();
            $user->groups()->detach();
            $user->locations()->detach();
            $user->delete();
        });

        return response()->json(status: 204);
    }

    private function subscriptionAssignments(array $data): array
    {
        if (array_key_exists('subscriptions', $data)) {
            return collect($data['subscriptions'])
                ->map(fn (array $subscription): array => [
                    'id' => $subscription['id'],
                    'start_date' => $subscription['start_date'] ?? now()->toDateString(),
                ])
                ->all();
        }

        return collect($data['subscription_ids'] ?? [])
            ->map(fn (int $subscriptionId): array => [
                'id' => $subscriptionId,
                'start_date' => now()->toDateString(),
            ])
            ->all();
    }

    private function userRelationsForResponse(?int $organizationId, bool $includeSubscriptions = false): array
    {
        $relations = [
            'groups.rights' => fn ($query) => $this->organizationAccess->applyAvailableRightsFilter($query, $organizationId),
            'locations',
            'activeSubscriptions',
        ];

        if ($includeSubscriptions) {
            $relations[] = 'subscriptions';
        }

        return $relations;
    }

    private function loadUserForResponse(User $user, ?int $organizationId, bool $includeSubscriptions = false): User
    {
        return $user->load($this->userRelationsForResponse($organizationId, $includeSubscriptions));
    }

    private function abortIfUserIsNotVisible(User $user): void
    {
        abort_unless(User::query()->whereKey($user->getKey())->exists(), 404);
    }

    private function attachSubscriptionAssignments(User $user, array $assignments, array $renewedSubscriptionIds = []): void
    {
        foreach ($assignments as $assignment) {
            $subscription = Subscription::query()->findOrFail($assignment['id']);
            $startDate = CarbonImmutable::parse($assignment['start_date'])->startOfDay();

            $user->subscriptions()->attach($subscription->id, [
                'start_date' => $startDate->toDateString(),
                'expires_at' => $this->subscriptionExpiresAt($subscription, $startDate),
            ]);

            DB::afterCommit(function () use ($user, $subscription, $startDate): void {
                NotificationRequested::dispatch(
                    $user->fresh(),
                    NotificationRequested::SUBSCRIPTION_ACTIVATED,
                    "subscription.activated:{$user->id}:{$subscription->id}:{$startDate->toDateString()}",
                    ['subscription' => $subscription->name],
                );
            });

            $this->activityLogger->record(
                in_array($subscription->id, $renewedSubscriptionIds, true)
                    ? AuditLog::SUBSCRIPTION_RENEWED
                    : AuditLog::SUBSCRIPTION_ASSIGNED,
                $user,
                $subscription,
                [],
                ['subscription_id' => $subscription->id, 'start_date' => $startDate->toDateString()],
            );
        }
    }

    public function activity(Request $request, User $user): AnonymousResourceCollection
    {
        $this->abortIfUserIsNotVisible($user);

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $activities = AuditLog::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('subject_user_id', $user->id)
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('event_type', $type))
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return ActivityResource::collection($activities);
    }

    private function subscriptionExpiresAt(Subscription $subscription, CarbonImmutable $startDate): ?string
    {
        if ($subscription->duration_days !== null) {
            return $startDate->addDays($subscription->duration_days)->toDateString();
        }

        return null;
    }
}
