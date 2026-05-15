<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\BearerTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly BearerTokenService $tokens)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->active || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return response()->json([
            'token' => $this->tokens->create($user),
            'token_type' => 'Bearer',
            'user' => $user->load(['groups.rights', 'locations']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load(['groups.rights', 'locations']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->attributes->get('access_token');

        if ($accessToken instanceof PersonalAccessToken) {
            $this->tokens->revoke($accessToken);
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
