<?php

namespace App\Users\Services;

use App\Users\Models\PersonalAccessToken;
use App\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BearerTokenService
{
    public function create(User $user, string $name = 'api-token', array $abilities = ['*']): string
    {
        $plainTextToken = Str::random(80);

        $user->accessTokens()->create([
            'name' => $name,
            'token' => $this->hash($plainTextToken),
            'abilities' => $abilities,
        ]);

        return $plainTextToken;
    }

    public function findValidToken(string $plainTextToken): ?PersonalAccessToken
    {
        $accessToken = PersonalAccessToken::query()
            ->with('user')
            ->where('token', $this->hash($plainTextToken))
            ->first();

        if (! $accessToken || ! $accessToken->user) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        $accessToken->forceFill(['last_used_at' => Carbon::now()])->save();

        return $accessToken;
    }

    public function revoke(PersonalAccessToken $accessToken): void
    {
        $accessToken->delete();
    }

    private function hash(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }
}
