<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\SyncUserSubscriptionsRequest;
use App\Users\Http\Requests\StoreUserRequest;
use App\Users\Http\Requests\UpdateUserRequest;
use App\Users\Http\Resources\UserResource;
use App\Users\Models\User;
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
        return $this->userList($request, true);
    }

    public function clients(Request $request): AnonymousResourceCollection
    {
        return $this->userList($request, false);
    }

    private function userList(Request $request, ?bool $hasGroups = null): AnonymousResourceCollection
    {
        $users = $this->accessibleUsersQuery($request)
            ->with(['groups.rights', 'locations', 'activeSubscriptions'])
            ->when($hasGroups === true, fn ($query) => $query->has('groups'))
            ->when($hasGroups === false, fn ($query) => $query->doesntHave('groups'))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
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
        $subscriptionIds = $data['subscription_ids'] ?? [];
        unset($data['group_ids']);
        unset($data['location_ids']);
        unset($data['subscription_ids']);

        $user = DB::transaction(function () use ($data, $groupIds, $locationIds, $subscriptionIds): User {
            $user = User::query()->create($data);
            $user->groups()->sync($groupIds);
            $user->locations()->sync($locationIds);
            $user->subscriptions()->sync($subscriptionIds);

            return $user;
        });

        return (new UserResource($user->load(['groups.rights', 'locations', 'subscriptions', 'activeSubscriptions'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->ensureUserIsAccessible(request(), $user);

        return new UserResource($user->load(['groups.rights', 'locations', 'subscriptions', 'activeSubscriptions']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->ensureUserIsAccessible($request, $user);

        $data = $request->validated();
        $groupIds = $data['group_ids'] ?? null;
        $locationIds = $data['location_ids'] ?? null;
        $subscriptionIds = $data['subscription_ids'] ?? null;
        unset($data['group_ids']);
        unset($data['location_ids']);
        unset($data['subscription_ids']);

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $groupIds, $locationIds, $subscriptionIds): void {
            $user->update($data);

            if ($groupIds !== null) {
                $user->groups()->sync($groupIds);
            }

            if ($locationIds !== null) {
                $user->locations()->sync($locationIds);
            }

            if ($subscriptionIds !== null) {
                $user->subscriptions()->sync($subscriptionIds);
            }
        });

        return new UserResource($user->load(['groups.rights', 'locations', 'subscriptions', 'activeSubscriptions']));
    }

    public function syncSubscriptions(SyncUserSubscriptionsRequest $request, User $user): UserResource
    {
        $this->ensureUserIsAccessible($request, $user);

        DB::transaction(function () use ($request, $user): void {
            $user->subscriptions()->sync($request->validated('subscription_ids'));
        });

        return new UserResource($user->load(['groups.rights', 'locations', 'subscriptions', 'activeSubscriptions']));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->ensureUserIsAccessible($request, $user);

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

    private function accessibleUsersQuery(Request $request)
    {
        $locationIds = $request->user()?->locations()->pluck('locations.id');

        return User::query()->when(
            $locationIds !== null && $locationIds->isNotEmpty(),
            fn ($query) => $query->whereHas('locations', fn ($query) => $query->whereIn('locations.id', $locationIds))
        );
    }

    private function ensureUserIsAccessible(Request $request, User $user): void
    {
        abort_unless(
            $this->accessibleUsersQuery($request)->whereKey($user->getKey())->exists(),
            404
        );
    }
}
