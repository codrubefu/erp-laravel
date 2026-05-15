<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\StoreGroupRequest;
use App\Users\Http\Requests\UpdateGroupRequest;
use App\Users\Http\Resources\GroupResource;
use App\Users\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $groups = Group::query()
            ->with('rights')
            ->withCount('users')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return GroupResource::collection($groups);
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $data = $request->validated();
        $rightIds = $data['right_ids'] ?? [];
        unset($data['right_ids']);

        $group = DB::transaction(function () use ($data, $rightIds): Group {
            $group = Group::query()->create($data);
            $group->rights()->sync($rightIds);

            return $group;
        });

        return (new GroupResource($group->load('rights')->loadCount('users')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Group $group): GroupResource
    {
        return new GroupResource($group->load('rights')->loadCount('users'));
    }

    public function update(UpdateGroupRequest $request, Group $group): GroupResource
    {
        $data = $request->validated();
        $rightIds = $data['right_ids'] ?? null;
        unset($data['right_ids']);

        DB::transaction(function () use ($group, $data, $rightIds): void {
            $group->update($data);

            if ($rightIds !== null) {
                $group->rights()->sync($rightIds);
            }
        });

        return new GroupResource($group->load('rights')->loadCount('users'));
    }

    public function destroy(Group $group): JsonResponse
    {
        if ($group->users()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a group that still has users.',
            ], 422);
        }

        DB::transaction(function () use ($group): void {
            $group->rights()->detach();
            $group->delete();
        });

        return response()->json(status: 204);
    }
}
