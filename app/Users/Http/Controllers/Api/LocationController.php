<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\StoreLocationRequest;
use App\Users\Http\Requests\UpdateLocationRequest;
use App\Users\Http\Resources\LocationResource;
use App\Users\Models\Location;
use App\Users\Models\LocationGroup;
use App\Users\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function __construct(private readonly OrganizationAccessService $organizationAccess)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'location_group_id' => ['nullable', 'regex:/^(none|\d{1,18})$/'],
            'sort_by' => ['nullable', Rule::in(['name', 'location_group', 'users_count', 'updated_at'])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $locationGroupId = $filters['location_group_id'] ?? null;
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';

        $locations = Location::query()
            ->with('locationGroup')
            ->withCount('users')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($locationGroupId === 'none', fn ($query) => $query->whereNull('location_group_id'))
            ->when(
                $locationGroupId !== null && $locationGroupId !== 'none',
                fn ($query) => $query->where('location_group_id', (int) $locationGroupId),
            )
            ->orderBy(
                $sortBy === 'location_group'
                    ? LocationGroup::query()->select('name')->whereColumn('location_groups.id', 'locations.location_group_id')
                    : $sortBy,
                $sortDir,
            )
            // SQL Server rejects the same column twice in ORDER BY.
            ->when($sortBy !== 'name', fn ($query) => $query->orderBy('name', 'asc'))
            ->orderBy('id', 'asc')
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

        return (new LocationResource($location->load(['users', 'locationGroup'])->loadCount('users')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Location $location): LocationResource
    {
        return new LocationResource($location->load(['users', 'locationGroup'])->loadCount('users'));
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

        return new LocationResource($location->load(['users', 'locationGroup'])->loadCount('users'));
    }

    public function destroy(Location $location): JsonResponse
    {
        if ($error = $this->organizationAccess->deleteBlockedByManyToManyResponse($location, ['users', 'articles'])) {
            return response()->json($error, 422);
        }

        DB::transaction(function () use ($location): void {
            $location->users()->detach();
            $location->delete();
        });

        return response()->json(status: 204);
    }
}
