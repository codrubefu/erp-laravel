<?php

namespace App\Users\Services;

use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrganizationAccessService
{
    public function disabledPatternsForOrganization(?int $organizationId): array
    {
        if ($organizationId === null) {
            return [];
        }

        $disabledGroups = config("organization-access.disabled_right_groups.{$organizationId}", []);
        $rightGroups = config('organization-access.right_groups', []);

        return collect(Arr::wrap($disabledGroups))
            ->flatMap(fn (string $group): array => Arr::wrap($rightGroups[$group] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isRightDisabledForOrganization(string $rightName, ?int $organizationId): bool
    {
        foreach ($this->disabledPatternsForOrganization($organizationId) as $pattern) {
            if (Str::is($pattern, $rightName)) {
                return true;
            }
        }

        return false;
    }

    public function availableRightNames(array $rightNames, ?int $organizationId): array
    {
        return collect($rightNames)
            ->reject(fn (string $rightName): bool => $this->isRightDisabledForOrganization($rightName, $organizationId))
            ->values()
            ->all();
    }

    public function applyAvailableRightsFilter(Builder|Relation $query, ?int $organizationId): Builder|Relation
    {
        foreach ($this->disabledPatternsForOrganization($organizationId) as $pattern) {
            $query->where('name', 'not like', $this->patternToSqlLike($pattern));
        }

        return $query;
    }

    public function disabledRightIds(Collection $rightIds, ?int $organizationId): Collection
    {
        if ($rightIds->isEmpty()) {
            return collect();
        }

        return Right::query()
            ->whereIn('id', $rightIds->all())
            ->get(['id', 'name'])
            ->filter(fn (Right $right): bool => $this->isRightDisabledForOrganization($right->name, $organizationId))
            ->pluck('id')
            ->values();
    }

    public function loadUserAccessRelations(User $user): User
    {
        return $user->load([
            'groups.rights' => fn ($query) => $this->applyAvailableRightsFilter($query, $user->organization_id),
            'locations',
            'activeSubscriptions',
        ]);
    }

    private function patternToSqlLike(string $pattern): string
    {
        return str_replace('*', '%', $pattern);
    }
}
