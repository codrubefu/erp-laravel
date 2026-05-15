<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Right;
use Illuminate\Http\JsonResponse;

class AccessControlController extends Controller
{
    public function groups(): JsonResponse
    {
        return response()->json([
            'groups' => Group::query()
                ->with('rights')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function rights(): JsonResponse
    {
        return response()->json([
            'rights' => Right::query()
                ->orderBy('name')
                ->get(),
        ]);
    }
}
