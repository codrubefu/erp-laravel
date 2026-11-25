<?php

namespace App\Users\Jobs;

use App\Users\Models\MemberExport;
use App\Users\Models\User;
use App\Users\Services\MemberExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

class GenerateMemberExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    // A long export outlives retry_after, so the queue may deliver it again; the claim below ignores that delivery.
    public int $tries = 2;

    public function __construct(public readonly string $exportId) {}

    public function handle(MemberExportService $exports): void
    {
        $claimed = MemberExport::query()
            ->whereKey($this->exportId)
            ->where('status', MemberExport::PENDING)
            ->update(['status' => MemberExport::PROCESSING]);

        if ($claimed === 0) {
            return;
        }

        $export = MemberExport::query()->findOrFail($this->exportId);
        $previousUser = Auth::user();

        try {
            $actor = User::query()->withoutGlobalScopes()
                ->where('organization_id', $export->organization_id)
                ->where('active', true)
                ->find($export->requested_by)
                ?? throw new RuntimeException('The export requester is no longer available.');

            // The organization and location scopes only apply to an authenticated user.
            Auth::setUser($actor);

            if (! $actor->hasRight('users.view')) {
                throw new RuntimeException('The export requester may no longer view users.');
            }

            $exports->generate($export, $actor);
        } catch (Throwable $exception) {
            $this->fail($exception);
        } finally {
            // Workers are long-lived: never leak this user into the next job.
            $previousUser === null ? Auth::forgetUser() : Auth::setUser($previousUser);
        }
    }

    public function failed(?Throwable $exception): void
    {
        MemberExport::query()
            ->whereKey($this->exportId)
            ->whereIn('status', [MemberExport::PENDING, MemberExport::PROCESSING])
            ->update(['status' => MemberExport::FAILED]);
    }
}
