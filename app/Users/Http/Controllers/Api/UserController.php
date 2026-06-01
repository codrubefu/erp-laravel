<?php

namespace App\Users\Http\Controllers\Api;

use App\Subscription\Models\Subscription;
use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\SyncUserSubscriptionsRequest;
use App\Users\Http\Requests\StoreUserRequest;
use App\Users\Http\Requests\UpdateUserRequest;
use App\Users\Http\Resources\UserResource;
use App\Users\Models\User;
use App\Users\Models\Scopes\LocationAccessScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
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
            ->with(['groups.rights', 'locations', 'activeSubscriptions'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where('user_code', 'like', "%{$search}%");
            })
            ->orderBy('user_code')
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
            ->with(['groups.rights', 'locations', 'activeSubscriptions'])
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
            ->orderBy('last_name')
            ->orderBy('first_name')
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

        return (new UserResource($user->load(['groups.rights', 'locations', 'subscriptions', 'activeSubscriptions'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['groups.rights', 'locations', 'subscriptions', 'activeSubscriptions']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
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
                $user->subscriptions()->detach();
                $this->attachSubscriptionAssignments($user, $subscriptionAssignments);
            }
        });

        return new UserResource($user->load(['groups.rights', 'locations', 'subscriptions', 'activeSubscriptions']));
    }

    public function syncSubscriptions(SyncUserSubscriptionsRequest $request, User $user): UserResource
    {
        DB::transaction(function () use ($request, $user): void {
            $user->subscriptions()->detach();
            $this->attachSubscriptionAssignments($user, $this->subscriptionAssignments($request->validated()));
        });

        return new UserResource($user->load(['groups.rights', 'locations', 'subscriptions', 'activeSubscriptions']));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
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

    private function attachSubscriptionAssignments(User $user, array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $subscription = Subscription::query()->findOrFail($assignment['id']);
            $startDate = CarbonImmutable::parse($assignment['start_date'])->startOfDay();

            $user->subscriptions()->attach($subscription->id, [
                'start_date' => $startDate->toDateString(),
                'expires_at' => $this->subscriptionExpiresAt($subscription, $startDate),
            ]);
        }
    }

    private function subscriptionExpiresAt(Subscription $subscription, CarbonImmutable $startDate): ?string
    {
        if ($subscription->duration_days !== null) {
            return $startDate->addDays($subscription->duration_days)->toDateString();
        }

        return null;
    }
}
