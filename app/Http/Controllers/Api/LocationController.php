<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $locations = Location::query()
            ->withCount('users')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return LocationResource::collection($locations);
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userIds = $data['user_ids'] ?? [];
        unset($data['user_ids']);

        $location = DB::transaction(function () use ($data, $userIds): Location {
            $location = Location::query()->create($data);
            $location->users()->sync($userIds);

            return $location;
        });

        return (new LocationResource($location->load('users')->loadCount('users')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Location $location): LocationResource
    {
        return new LocationResource($location->load('users')->loadCount('users'));
    }

    public function update(UpdateLocationRequest $request, Location $location): LocationResource
    {
        $data = $request->validated();
        $userIds = $data['user_ids'] ?? null;
        unset($data['user_ids']);

        DB::transaction(function () use ($location, $data, $userIds): void {
            $location->update($data);

            if ($userIds !== null) {
                $location->users()->sync($userIds);
            }
        });

        return new LocationResource($location->load('users')->loadCount('users'));
    }

    public function destroy(Location $location): JsonResponse
    {
        DB::transaction(function () use ($location): void {
            $location->users()->detach();
            $location->delete();
        });

        return response()->json(status: 204);
    }
}
