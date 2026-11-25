<?php

namespace Tests\Feature;

use App\Service\Models\Service;
use App\Users\Jobs\GenerateMemberExport;
use App\Users\Models\AuditLog;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\LocationGroup;
use App\Users\Models\MemberExport;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use App\Users\Services\MemberExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MemberExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_export_contains_every_filtered_member_in_list_order(): void
    {
        [$admin, $token] = $this->admin();
        foreach (range(1, 20) as $number) {
            $this->member($admin, ['first_name' => 'Client', 'last_name' => sprintf('Member%02d', 21 - $number)]);
        }
        $this->member($admin, ['first_name' => 'Other', 'last_name' => 'Person']);
        User::factory()->create(['first_name' => 'Client', 'last_name' => 'Foreign', 'organization_id' => Organization::factory()]);
        $staff = $this->member($admin, ['first_name' => 'Client', 'last_name' => 'Staff']);
        $this->attachRights($staff, ['users.view']);

        $rows = $this->rows($this->export($token, ['fields' => ['last_name'], 'search' => 'Client', 'per_page' => 5, 'page' => 2]));

        $this->assertSame(['Nume', ...array_map(fn (int $n): string => sprintf('Member%02d', $n), range(1, 20))], array_column($rows, 0));
    }

    public function test_export_applies_location_filters(): void
    {
        [$admin, $token] = $this->admin();
        $north = LocationGroup::query()->create(['name' => 'North', 'organization_id' => $admin->organization_id]);
        $annex = $this->location($admin, 'Annex', $north);
        $depot = $this->location($admin, 'Depot');
        $this->member($admin, ['last_name' => 'Alpha', 'first_name' => 'Client'])->locations()->sync([$annex->id]);
        $this->member($admin, ['last_name' => 'Beta', 'first_name' => 'Client'])->locations()->sync([$depot->id]);
        $this->member($admin, ['last_name' => 'Gamma', 'first_name' => 'Client']);

        $names = fn (array $query): array => array_slice(array_column($this->rows($this->export(
            $token,
            ['fields' => ['last_name'], 'search' => 'Client', ...$query],
        )), 0), 1);

        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $names([]));
        $this->assertSame(['Alpha'], $names(['location_group_id' => $north->id]));
        $this->assertSame(['Beta'], $names(['location_id' => $depot->id]));
        $this->assertSame(['Gamma'], $names(['location_id' => 'none']));
    }

    public function test_export_applies_and_audits_active_service_filter(): void
    {
        [$admin, $token] = $this->admin();
        $service = Service::query()->create([
            'organization_id' => $admin->organization_id, 'name' => 'Service', 'price' => 10, 'currency' => 'RON', 'is_active' => true,
        ]);
        $this->member($admin, ['last_name' => 'Alpha', 'first_name' => 'Client'])->services()->attach($service->id, ['status' => 'active']);
        $this->member($admin, ['last_name' => 'Beta', 'first_name' => 'Client']);

        foreach (['1' => 'Alpha', '0' => 'Beta'] as $value => $name) {
            $rows = $this->rows($this->export($token, ['fields' => ['last_name'], 'search' => 'Client', 'active_service' => (string) $value]));

            $this->assertSame([['Nume'], [$name]], $rows);
            $audit = AuditLog::query()->where('event_type', AuditLog::MEMBERS_EXPORTED)->latest('id')->firstOrFail();
            $this->assertSame((string) $value, $audit->new_values['filters']['active_service']);
        }
    }

    public function test_export_applies_and_audits_active_status_filter(): void
    {
        [$admin, $token] = $this->admin();
        $this->member($admin, ['last_name' => 'Alpha', 'first_name' => 'Client']);
        $this->member($admin, ['last_name' => 'Beta', 'first_name' => 'Client', 'active' => false]);

        $rows = $this->rows($this->export($token, ['fields' => ['last_name'], 'search' => 'Client', 'active' => '0']));

        $this->assertSame([['Nume'], ['Beta']], $rows);
        $audit = AuditLog::query()->where('event_type', AuditLog::MEMBERS_EXPORTED)->sole();
        $this->assertSame('0', $audit->new_values['filters']['active']);
    }

    public function test_export_respects_location_access(): void
    {
        [$admin, $token] = $this->admin();
        $own = $this->location($admin, 'Own');
        $foreign = $this->location($admin, 'Foreign');
        $admin->locations()->sync([$own->id]);
        $this->member($admin, ['last_name' => 'Visible', 'first_name' => 'Client'])->locations()->sync([$own->id]);
        $this->member($admin, ['last_name' => 'Hidden', 'first_name' => 'Client'])->locations()->sync([$foreign->id]);

        $rows = $this->rows($this->export($token, ['fields' => ['last_name'], 'search' => 'Client']));

        $this->assertSame([['Nume'], ['Visible']], $rows);
    }

    public function test_export_contains_only_requested_fields_in_fixed_order(): void
    {
        [$admin, $token] = $this->admin();
        $this->member($admin, ['last_name' => 'Țăranu', 'first_name' => 'Ștefan', 'cnp' => '1960229123456', 'ci' => 'XT 123456']);

        $content = $this->content($this->export($token, ['fields' => ['first_name', 'last_name'], 'search' => 'Țăranu']));
        $this->assertSame("\xEF\xBB\xBFNume;Prenume\nȚăranu;Ștefan\n", $content);

        $rows = $this->rows($this->export($token, ['fields' => ['ci', 'cnp'], 'search' => 'Țăranu']));
        $this->assertSame([['CNP', 'CI (serie si numar)'], ['="1960229123456"', 'XT 123456']], $rows);
    }

    public function test_export_headers_and_statuses_follow_the_requested_language(): void
    {
        [$admin, $token] = $this->admin();
        $this->member($admin, ['last_name' => 'Client', 'active' => false]);
        $query = ['fields' => ['last_name', 'status'], 'search' => 'Client'];

        $this->assertSame([['Nume', 'Status'], ['Client', 'Inactiv']], $this->rows($this->export($token, $query)));
        $this->assertSame([['Last name', 'Status'], ['Client', 'Inactive']], $this->rows($this->export($token, [...$query, 'lang' => 'en'])));
        $this->assertSame([['Прізвище', 'Статус'], ['Client', 'Неактивний']], $this->rows($this->export($token, [...$query, 'lang' => 'uk'])));
    }

    public function test_export_is_safe_to_open_in_excel(): void
    {
        [$admin, $token] = $this->admin();
        $this->member($admin, [
            'last_name' => '=HYPERLINK("http://evil.test";"x")',
            'first_name' => '@SUM(A1)',
            'address' => '-2+3',
            'phone' => '0722 123 456',
            'user_code' => '000123',
            'email' => 'excel@example.com',
        ]);
        $this->member($admin, ['last_name' => 'Zed', 'phone' => '+40722123456', 'user_code' => '+cmd|calc', 'email' => 'excel2@example.com']);

        $rows = $this->rows($this->export($token, [
            'fields' => ['user_code', 'last_name', 'first_name', 'phone', 'address'],
            'search' => 'excel',
        ]));

        $this->assertSame(['="000123"', "'=HYPERLINK(\"http://evil.test\";\"x\")", "'@SUM(A1)", '="0722 123 456"', "'-2+3"], $rows[1]);
        $this->assertSame("'+cmd|calc", $rows[2][0]);
        $this->assertSame('="+40722123456"', $rows[2][3]);
    }

    public function test_export_contains_relations_and_registration_date(): void
    {
        [$admin, $token] = $this->admin();
        $north = LocationGroup::query()->create(['name' => 'North', 'organization_id' => $admin->organization_id]);
        $member = $this->member($admin, ['last_name' => 'Client', 'created_at' => '2026-03-04 10:00:00']);
        $member->locations()->sync([
            $this->location($admin, 'Annex', $north)->id,
            $this->location($admin, 'Office', $north)->id,
            $this->location($admin, 'Depot')->id,
        ]);
        $member->services()->attach($this->service('Current'), ['status' => 'active', 'start_date' => now()->subDay()]);
        $member->services()->attach($this->service('Outdated'), [
            'status' => 'active', 'start_date' => now()->subDays(40), 'expires_at' => now()->subDays(10),
        ]);

        $rows = $this->rows($this->export($token, [
            'fields' => ['location_groups', 'locations', 'active_services', 'created_at'],
            'search' => 'Client',
        ]));

        $this->assertSame([
            ['Filiala', 'Organizatie', 'Servicii active', 'Data inregistrarii'],
            ['North', 'Annex, Depot, Office', 'Current', '2026-03-04'],
        ], $rows);
    }

    public function test_export_validates_input(): void
    {
        [, $token] = $this->admin();

        $this->export($token, [])->assertUnprocessable()->assertJsonValidationErrors('fields');
        $this->export($token, ['fields' => ['password']])->assertUnprocessable()->assertJsonValidationErrors('fields.0');
        $this->export($token, ['fields' => ['email'], 'lang' => 'xx'])->assertUnprocessable()->assertJsonValidationErrors('lang');
        $this->export($token, ['fields' => ['email'], 'location_id' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $this->assertDatabaseMissing('audit_logs', ['event_type' => AuditLog::MEMBERS_EXPORTED]);
    }

    public function test_export_requires_users_view(): void
    {
        $this->getJson('/api/clients/exports')->assertUnauthorized();

        [, $token] = $this->admin(['groups.view']);
        $this->export($token, ['fields' => ['email']])->assertForbidden();
    }

    public function test_export_is_audited_without_member_data(): void
    {
        [$admin, $token] = $this->admin();
        $this->member($admin, ['last_name' => 'Client', 'cnp' => '1960229123456']);

        $this->export($token, ['fields' => ['cnp', 'last_name'], 'search' => 'Client'])->assertAccepted();

        $log = AuditLog::query()->where('event_type', AuditLog::MEMBERS_EXPORTED)->sole();
        $this->assertSame($admin->id, $log->changed_by);
        $this->assertSame($admin->organization_id, (int) $log->organization_id);
        $this->assertNull($log->subject_user_id);
        $this->assertSame(['last_name', 'cnp'], $log->new_values['fields']);
        $this->assertSame(['search' => 'Client'], $log->new_values['filters']);
        $this->assertSame(1, $log->new_values['row_count']);
        $this->assertStringNotContainsString('1960229123456', json_encode($log->getAttributes()));
    }

    public function test_search_text_that_looks_like_a_cnp_is_masked_in_the_audit_log(): void
    {
        [, $token] = $this->admin();

        $this->export($token, ['fields' => ['last_name'], 'search' => '1960229123456'])->assertAccepted();

        $log = AuditLog::query()->where('event_type', AuditLog::MEMBERS_EXPORTED)->sole();
        $this->assertStringNotContainsString('1960229123456', json_encode($log->getAttributes()));
    }

    public function test_export_query_count_does_not_scale_with_members(): void
    {
        [$admin, $token] = $this->admin();
        $service = $this->service('Current');
        $location = $this->location($admin, 'Annex');
        foreach (range(1, 60) as $number) {
            $member = $this->member($admin, ['last_name' => "Client{$number}"]);
            $member->services()->attach($service, ['status' => 'active', 'start_date' => now()->subDay()]);
            $member->locations()->sync([$location->id]);
        }

        DB::enableQueryLog();
        $rows = $this->rows($this->export($token, ['fields' => ['last_name', 'location_groups', 'locations', 'active_services'], 'search' => 'Client']));

        $this->assertCount(61, $rows);
        // Request, job and download together; far below one query per member.
        $this->assertLessThan(30, count(DB::getQueryLog()));
    }

    public function test_export_is_queued_and_generated_as_the_requester(): void
    {
        Queue::fake();
        [$admin, $token] = $this->admin();
        $own = $this->location($admin, 'Own');
        $hidden = $this->location($admin, 'Hidden');
        $admin->locations()->sync([$own->id]);
        $this->member($admin, ['last_name' => 'Visible', 'first_name' => 'Client'])->locations()->sync([$own->id]);
        $this->member($admin, ['last_name' => 'Hidden', 'first_name' => 'Client'])->locations()->sync([$hidden->id]);
        User::factory()->create(['first_name' => 'Client', 'last_name' => 'Foreign', 'organization_id' => Organization::factory()]);

        $response = $this->export($token, ['fields' => ['last_name'], 'search' => 'Client'])
            ->assertAccepted()->assertJsonPath('data.status', 'pending');
        $id = $response->json('data.id');
        Queue::assertPushed(GenerateMemberExport::class, fn (GenerateMemberExport $job): bool => $job->exportId === $id);
        $this->get("/api/clients/exports/{$id}/download")->assertConflict();

        // A worker has no authenticated user, and must have none afterwards.
        Auth::forgetUser();
        (new GenerateMemberExport($id))->handle(app(MemberExportService::class));
        $this->assertNull(Auth::user());

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/clients/exports')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'ready')
            ->assertJsonPath('data.0.total', 1)
            ->assertJsonPath('data.0.processed', 1);
        $this->assertSame("\xEF\xBB\xBFNume\nVisible\n", $this->get("/api/clients/exports/{$id}/download")->assertOk()->streamedContent());
    }

    public function test_redelivered_job_does_not_generate_the_export_again(): void
    {
        Queue::fake();
        [, $token] = $this->admin();
        $id = $this->export($token, ['fields' => ['email']])->json('data.id');
        MemberExport::query()->findOrFail($id)->update(['status' => 'processing']);

        (new GenerateMemberExport($id))->handle(app(MemberExportService::class));

        $this->assertSame('processing', MemberExport::query()->findOrFail($id)->status);
        $this->assertDatabaseMissing('audit_logs', ['event_type' => AuditLog::MEMBERS_EXPORTED]);
    }

    public function test_export_fails_when_the_requester_lost_access_before_it_ran(): void
    {
        foreach (['right', 'active'] as $lost) {
            [$admin] = $this->admin();
            $id = MemberExport::query()->create([
                'organization_id' => $admin->organization_id, 'requested_by' => $admin->id, 'fields' => ['cnp'],
                'filters' => [], 'language' => 'ro', 'status' => 'pending', 'expires_at' => now()->addDay(),
            ])->id;
            $lost === 'right' ? $admin->groups()->detach() : $admin->update(['active' => false]);
            Auth::forgetUser();

            GenerateMemberExport::dispatchSync($id);

            $this->assertSame('failed', MemberExport::query()->findOrFail($id)->status, $lost);
            $this->assertNull(Auth::user());
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseMissing('audit_logs', ['event_type' => AuditLog::MEMBERS_EXPORTED]);
    }

    public function test_only_the_requester_can_see_and_download_an_export(): void
    {
        [$admin, $token] = $this->admin();
        $id = $this->export($token, ['fields' => ['cnp']])->json('data.id');

        [, $colleagueToken] = $this->admin(attributes: ['organization_id' => $admin->organization_id]);
        [, $strangerToken] = $this->admin(attributes: ['organization_id' => Organization::factory()]);
        foreach ([$colleagueToken, $strangerToken] as $otherToken) {
            Auth::forgetUser();
            $this->withHeader('Authorization', "Bearer {$otherToken}");
            $this->getJson("/api/clients/exports/{$id}/download")->assertNotFound();
            $this->getJson('/api/clients/exports')->assertOk()->assertJsonCount(0, 'data');
        }
        $this->getJson('/api/clients/exports/not-a-uuid/download')->assertNotFound();

        Auth::forgetUser();
        $this->withHeader('Authorization', "Bearer {$token}");
        $this->getJson("/api/clients/exports/{$id}/download")->assertOk();
        $admin->groups()->detach();
        $this->getJson("/api/clients/exports/{$id}/download")->assertForbidden();
    }

    public function test_expired_exports_are_unavailable_and_pruned(): void
    {
        [, $token] = $this->admin();
        $id = $this->export($token, ['fields' => ['email']])->json('data.id');
        $this->assertCount(1, Storage::disk('local')->allFiles());
        app(MemberExportService::class)->prune();
        $this->assertCount(1, Storage::disk('local')->allFiles());

        $this->travel(25)->hours();
        $this->withHeader('Authorization', "Bearer {$token}");
        $this->getJson("/api/clients/exports/{$id}/download")->assertNotFound();
        $this->getJson('/api/clients/exports')->assertOk()->assertJsonCount(0, 'data');

        app(MemberExportService::class)->prune();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('member_exports', 0);
    }

    public function test_prune_removes_orphaned_files(): void
    {
        $disk = Storage::disk('local');
        $disk->put('member-exports/1/orphan.csv', 'x');
        $disk->put('gdpr/1/other.json', 'x');

        $this->travel(26)->hours();
        app(MemberExportService::class)->prune();

        $this->assertSame(['gdpr/1/other.json'], $disk->allFiles());
    }

    public function test_stalled_export_is_reported_as_failed(): void
    {
        Queue::fake();
        [, $token] = $this->admin();
        MemberExport::query()->findOrFail($this->export($token, ['fields' => ['email']])->json('data.id'))->update(['status' => 'processing']);

        $this->getJson('/api/clients/exports')->assertJsonPath('data.0.status', 'processing');
        $this->travel(11)->minutes();
        $this->getJson('/api/clients/exports')->assertJsonPath('data.0.status', 'failed');
    }

    public function test_search_text_is_not_stored_in_clear(): void
    {
        [, $token] = $this->admin();
        $this->export($token, ['fields' => ['email'], 'search' => '1960229123456'])->assertAccepted();

        $this->assertStringNotContainsString('1960229123456', json_encode(DB::table('member_exports')->first()));
    }

    private function export(string $token, array $query): TestResponse
    {
        return $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/clients/exports', $query);
    }

    private function content(TestResponse $response): string
    {
        $response->assertAccepted()->assertJsonPath('data.status', 'ready');

        return $this->get('/api/clients/exports/'.$response->json('data.id').'/download')->assertOk()->streamedContent();
    }

    private function rows(TestResponse $response): array
    {
        $content = substr($this->content($response), 3);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function admin(array $rights = ['users.view'], array $attributes = []): array
    {
        // Requests in one test share the authenticated user, which scopes the next login.
        Auth::forgetUser();
        $admin = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Root', 'email' => fake()->unique()->userName().'@admin.test', ...$attributes]);
        $this->attachRights($admin, $rights);
        $token = $this->postJson('/api/login', [
            'email' => $admin->email,
            'organization_id' => $admin->organization_id,
            'password' => 'password',
        ])->json('token');

        return [$admin, $token];
    }

    private function attachRights(User $user, array $rights): void
    {
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Test Group']);
        foreach ($rights as $right) {
            $group->rights()->attach(Right::query()->firstOrCreate(['name' => $right], ['label' => $right]));
        }
        $user->groups()->attach($group);
    }

    private function member(User $admin, array $attributes): User
    {
        return User::factory()->create(['organization_id' => $admin->organization_id, ...$attributes]);
    }

    private function location(User $admin, string $name, ?LocationGroup $group = null): Location
    {
        return Location::query()->create([
            'name' => $name,
            'organization_id' => $admin->organization_id,
            'location_group_id' => $group?->id,
        ]);
    }

    private function service(string $name): Service
    {
        return Service::query()->create([
            'name' => $name, 'description' => 'Test service', 'price' => 10, 'currency' => 'RON',
            'duration_days' => null, 'max_users' => 1000, 'is_active' => true,
        ]);
    }
}
