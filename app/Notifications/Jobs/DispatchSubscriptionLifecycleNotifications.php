<?php

namespace App\Notifications\Jobs;

use App\Notifications\Events\NotificationRequested;
use App\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DispatchSubscriptionLifecycleNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function handle(): void
    {
        $noticeDate = now()->addDays(max(0, (int) config('subscriptions.expiration_notice_days', 1)))->toDateString();
        DB::table('subscription_user')->join('subscriptions', 'subscriptions.id', '=', 'subscription_user.subscription_id')
            ->where('subscriptions.is_active', true)
            ->where(function ($query) use ($noticeDate): void {
                $query->whereDate('subscription_user.expires_at', $noticeDate)
                    ->orWhereDate('subscription_user.expires_at', '<', now()->toDateString());
            })
            ->select('subscription_user.*', 'subscriptions.name as subscription_name')->orderBy('subscription_user.id')
            ->chunkById(100, function ($rows) use ($noticeDate): void {
                foreach ($rows as $row) {
                    $type = $row->expires_at === $noticeDate ? NotificationRequested::SUBSCRIPTION_EXPIRING : NotificationRequested::SUBSCRIPTION_EXPIRED;
                    $user = User::withoutGlobalScopes()->find($row->user_id);
                    if ($user?->active) NotificationRequested::dispatch($user, $type, "{$type}:{$row->id}:{$row->expires_at}", ['subscription' => $row->subscription_name, 'expires_at' => $row->expires_at]);
                }
            }, 'subscription_user.id', 'id');
    }
}
