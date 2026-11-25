<?php

namespace App\Users\Services;

use App\Service\Services\ServiceLifecycleService;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** The filtered and ordered user list, shared by the list endpoints and the member export. */
class UserListQuery
{
    public const FILTER_RULES = [
        'location_id' => ['nullable', 'regex:/^(none|\d{1,18})$/'],
        'location_group_id' => ['nullable', 'regex:/^(none|\d{1,18})$/'],
        'active' => ['nullable', 'boolean'],
        'active_service' => ['nullable', 'boolean'],
    ];

    public function __construct(private readonly ServiceLifecycleService $serviceLifecycle)
    {
    }

    public function build(
        array $filters,
        ?bool $hasGroups = null,
        ?string $onlyRight = null,
        ?string $exceptOnlyRight = null,
    ): Builder {
        $locationId = $filters['location_id'] ?? null;
        $locationGroupId = $filters['location_group_id'] ?? null;
        $active = $filters['active'] ?? null;
        $activeService = $filters['active_service'] ?? null;
        $search = (string) ($filters['search'] ?? '');
        $hasLocationId = $locationId !== null && $locationId !== 'none';

        return User::query()
            ->when($locationId === 'none', fn ($query) => $query->whereDoesntHave('locations'))
            ->when($hasLocationId || $locationGroupId !== null, function ($query) use ($hasLocationId, $locationId, $locationGroupId): void {
                // One whereHas, so both filters apply to the same location.
                $query->whereHas('locations', function ($query) use ($hasLocationId, $locationId, $locationGroupId): void {
                    $query
                        ->when($hasLocationId, fn ($query) => $query->where('locations.id', (int) $locationId))
                        ->when($locationGroupId === 'none', fn ($query) => $query->whereNull('locations.location_group_id'))
                        ->when(
                            $locationGroupId !== null && $locationGroupId !== 'none',
                            fn ($query) => $query->where('locations.location_group_id', (int) $locationGroupId),
                        );
                });
            })
            ->when($active !== null, fn ($query) => $query->where('users.active', (bool) $active))
            ->when(
                $activeService !== null,
                fn ($query) => $this->serviceLifecycle->filterUsersByActiveService($query, (bool) $activeService),
            )
            ->when($hasGroups === true, fn ($query) => $query->has('groups'))
            ->when($hasGroups === false, fn ($query) => $query->doesntHave('groups'))
            ->when($onlyRight !== null, function ($query) use ($onlyRight): void {
                $query->where(function ($query) use ($onlyRight): void {
                    $query->whereDoesntHave('groups.rights')
                        ->orWhere(function ($query) use ($onlyRight): void {
                            $query->whereHas('groups.rights', fn ($query) => $query->where('name', $onlyRight))
                                ->whereDoesntHave('groups.rights', fn ($query) => $query->where('name', '!=', $onlyRight));
                        });
                });
            })
            ->when($exceptOnlyRight !== null, function ($query) use ($exceptOnlyRight): void {
                $query->where(function ($query) use ($exceptOnlyRight): void {
                    $query->whereDoesntHave('groups.rights', fn ($query) => $query->where('name', $exceptOnlyRight))
                        ->orWhereHas('groups.rights', fn ($query) => $query->where('name', '!=', $exceptOnlyRight));
                });
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('user_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc');
    }
}
