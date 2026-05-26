<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Models\Organization;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    public function showBySlug(string $slug): JsonResponse
    {
        $organization = Organization::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $organization->id,
                'slug' => $organization->slug,
                'name' => $organization->name,
            ],
        ]);
    }
}
