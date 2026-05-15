<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRightRequest;
use App\Http\Requests\UpdateRightRequest;
use App\Http\Resources\RightResource;
use App\Models\Right;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class RightController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $rights = Right::query()
            ->withCount('groups')
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

        return RightResource::collection($rights);
    }

    public function store(StoreRightRequest $request): JsonResponse
    {
        $right = Right::query()->create($request->validated());

        return (new RightResource($right->loadCount('groups')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Right $right): RightResource
    {
        return new RightResource($right->loadCount('groups'));
    }

    public function update(UpdateRightRequest $request, Right $right): RightResource
    {
        $right->update($request->validated());

        return new RightResource($right->loadCount('groups'));
    }

    public function destroy(Right $right): JsonResponse
    {
        if ($right->groups()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a right assigned to groups.',
            ], 422);
        }

        DB::transaction(fn () => $right->delete());

        return response()->json(status: 204);
    }
}
