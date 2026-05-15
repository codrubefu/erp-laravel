<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Right;
use Illuminate\Http\JsonResponse;

class AccessControlController extends Controller
{
    public function rights(): JsonResponse
    {
        return response()->json([
            'rights' => Right::query()
                ->orderBy('name')
                ->get(),
        ]);
    }
}
