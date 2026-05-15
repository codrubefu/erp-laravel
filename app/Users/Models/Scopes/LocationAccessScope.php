<?php

namespace App\Users\Models\Scopes;

use App\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class LocationAccessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $authenticatedUser = Auth::user();

        if (! $authenticatedUser instanceof User) {
            return;
        }

        $locationIds = $authenticatedUser->locations()->pluck('locations.id');

        if ($locationIds->isEmpty()) {
            return;
        }

        $builder->whereHas('locations', fn (Builder $query) => $query->whereIn('locations.id', $locationIds));
    }
}