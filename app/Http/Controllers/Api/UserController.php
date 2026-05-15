<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with(['groups.rights', 'locations'])
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
        unset($data['group_ids']);
        unset($data['location_ids']);

        $user = DB::transaction(function () use ($data, $groupIds, $locationIds): User {
            $user = User::query()->create($data);
            $user->groups()->sync($groupIds);
            $user->locations()->sync($locationIds);

            return $user;
        });

        return (new UserResource($user->load(['groups.rights', 'locations'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['groups.rights', 'locations']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        $groupIds = $data['group_ids'] ?? null;
        $locationIds = $data['location_ids'] ?? null;
        unset($data['group_ids']);
        unset($data['location_ids']);

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $groupIds, $locationIds): void {
            $user->update($data);

            if ($groupIds !== null) {
                $user->groups()->sync($groupIds);
            }

            if ($locationIds !== null) {
                $user->locations()->sync($locationIds);
            }
        });

        return new UserResource($user->load(['groups.rights', 'locations']));
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
}
