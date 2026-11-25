<?php

namespace App\Users\Services;

use App\Service\Services\ServiceLifecycleService;
use App\Users\Jobs\GenerateMemberExport;
use App\Users\Models\AuditLog;
use App\Users\Models\MemberExport;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportService
{
    /** Exportable fields, in column order. */
    public const FIELDS = [
        'user_code', 'last_name', 'first_name', 'phone', 'email', 'address', 'cnp', 'ci',
        'status', 'location_groups', 'locations', 'active_services', 'created_at',
    ];

    public const LANGUAGES = ['ro', 'en', 'uk'];

    private const RELATION_FIELDS = ['location_groups', 'locations', 'active_services'];

    /** Digit-only values Excel would otherwise turn into numbers. */
    private const TEXT_FIELDS = ['user_code', 'phone', 'cnp'];

    private const DISK = 'local';

    private const DIRECTORY = 'member-exports';

    private const LIFETIME_HOURS = 24;

    private const CHUNK_SIZE = 1000;

    public function __construct(
        private readonly ServiceLifecycleService $serviceLifecycle,
        private readonly BusinessActivityLogger $activityLogger,
        private readonly UserListQuery $userListQuery,
    ) {
    }

    public function request(User $actor, array $fields, array $filters, string $language): MemberExport
    {
        $export = MemberExport::query()->create([
            'organization_id' => $actor->organization_id,
            'requested_by' => $actor->getKey(),
            'fields' => array_values(array_intersect(self::FIELDS, $fields)),
            'filters' => $filters,
            'language' => $language,
            'status' => MemberExport::PENDING,
            'expires_at' => now()->addHours(self::LIFETIME_HOURS),
        ]);

        GenerateMemberExport::dispatch($export->id);

        return $export->refresh();
    }

    /** Must run with $actor authenticated, so that the organization and location scopes apply. */
    public function generate(MemberExport $export, User $actor): void
    {
        // Fixing the ids first keeps the rows stable while members change during the export.
        $ids = $this->userListQuery->build($export->filters, onlyRight: 'profile.view')
            ->pluck('users.id')
            ->map(fn (mixed $id): int => (int) $id);

        $this->activityLogger->record(AuditLog::MEMBERS_EXPORTED, null, $actor, [], [
            'fields' => $export->fields,
            'filters' => $this->auditFilters($export->filters),
            'row_count' => $ids->count(),
        ], $actor);

        $export->update(['total' => $ids->count()]);

        $stream = tmpfile();

        try {
            $this->writeCsv($stream, $export, $ids);
            rewind($stream);

            $path = $this->directory($export).'/'.$export->id.'.csv';
            if (! Storage::disk(self::DISK)->writeStream($path, $stream)) {
                throw new RuntimeException('The export file could not be stored.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $export->update([
            'status' => MemberExport::READY,
            'path' => $path,
            'expires_at' => now()->addHours(self::LIFETIME_HOURS),
        ]);
    }

    public function download(MemberExport $export): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($export->path), 404);

        return Storage::disk(self::DISK)->download(
            $export->path,
            'membri-'.$export->created_at->toDateString().'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private'],
        );
    }

    /** Deletes expired exports, and files whose record no longer exists. */
    public function prune(): void
    {
        $disk = Storage::disk(self::DISK);

        MemberExport::query()->where('expires_at', '<=', now())->get()->each(function (MemberExport $export) use ($disk): void {
            if ($export->path !== null) {
                $disk->delete($export->path);
            }
            $export->delete();
        });

        $oldest = now()->subHours(self::LIFETIME_HOURS + 1)->getTimestamp();
        foreach ($disk->allFiles(self::DIRECTORY) as $file) {
            if ($disk->lastModified($file) < $oldest) {
                $disk->delete($file);
            }
        }
    }

    private function directory(MemberExport $export): string
    {
        return self::DIRECTORY.'/'.$export->organization_id;
    }

    private function writeCsv(mixed $stream, MemberExport $export, SupportCollection $ids): void
    {
        $fields = $export->fields;
        $columns = ['id', ...array_map(
            fn (string $field): string => $field === 'status' ? 'active' : $field,
            array_values(array_diff($fields, self::RELATION_FIELDS)),
        )];

        // Excel needs the BOM to read UTF-8 and ";" as the Romanian list separator.
        fwrite($stream, "\xEF\xBB\xBF");
        $this->writeRow($stream, array_map(
            fn (string $field): string => trans("member_export.fields.{$field}", [], $export->language),
            $fields,
        ));

        foreach ($ids->chunk(self::CHUNK_SIZE) as $chunk) {
            $users = User::query()->whereKey($chunk->values()->all())->get($columns);
            $this->loadRelations($users, $fields);
            $byId = $users->keyBy('id');

            foreach ($chunk as $id) {
                if ($user = $byId->get($id)) {
                    $this->writeRow($stream, array_map(
                        fn (string $field): string => $this->cell($field, $this->value($user, $field, $export->language)),
                        $fields,
                    ));
                }
            }

            $export->increment('processed', $chunk->count());
        }
    }

    private function loadRelations(Collection $users, array $fields): void
    {
        if (in_array('active_services', $fields, true)) {
            $this->serviceLifecycle->refreshForUsers($users);
            $users->load('activeServices');
        }

        if (in_array('location_groups', $fields, true)) {
            $users->load('locations.locationGroup');
        } elseif (in_array('locations', $fields, true)) {
            $users->load('locations');
        }
    }

    private function value(User $user, string $field, string $language): string
    {
        return match ($field) {
            'status' => trans('member_export.status.'.($user->active ? 'active' : 'inactive'), [], $language),
            'location_groups' => $this->names($user->locations->pluck('locationGroup.name')),
            'locations' => $this->names($user->locations->pluck('name')),
            'active_services' => $this->names($user->activeServices->pluck('name')),
            'created_at' => (string) $user->created_at?->toDateString(),
            default => (string) $user->getAttribute($field),
        };
    }

    private function names(SupportCollection $names): string
    {
        return $names->filter()->unique()->sort()->implode(', ');
    }

    private function cell(string $field, string $value): string
    {
        if (in_array($field, self::TEXT_FIELDS, true) && preg_match('#^[+0-9][0-9 ()./-]*$#D', $value)) {
            return "=\"{$value}\"";
        }

        // Neutralise formula injection.
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
    }

    private function writeRow(mixed $stream, array $row): void
    {
        fputcsv($stream, $row, ';', '"', '');
    }

    private function auditFilters(array $filters): array
    {
        if (isset($filters['search'])) {
            $filters['search'] = preg_replace('/\d{13}/', '***', $filters['search']);
        }

        return $filters;
    }
}
