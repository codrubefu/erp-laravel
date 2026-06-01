<?php

namespace App\Subscription\Jobs;

use App\Sms\Models\SmsMessage;
use App\Sms\Services\SmsPortalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SendExpiringSubscriptionSms implements ShouldQueue
{
    use Queueable;

    public function handle(SmsPortalService $smsPortalService): void
    {
        $noticeDays = max(0, (int) config('subscriptions.expiration_notice_days', 1));
        $targetDate = now()->addDays($noticeDays)->toDateString();
        DB::table('subscription_user')
            ->join('users', 'users.id', '=', 'subscription_user.user_id')
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_user.subscription_id')
            ->whereDate('subscription_user.expires_at', $targetDate)
            ->where('subscriptions.is_active', true)
            ->where('users.active', true)
            ->whereNotNull('users.phone')
            ->whereRaw("TRIM(users.phone) <> ''")
            ->select([
                'subscription_user.id as subscription_user_id',
                'subscription_user.expires_at',
                'users.id as user_id',
                'users.phone as user_phone',
                'subscriptions.id as subscription_id',
                'subscriptions.name as subscription_name',
            ])
            ->orderBy('subscription_user.id')
            ->chunkById(100, function ($assignments) use ($smsPortalService): void {
                foreach ($assignments as $assignment) {
                    $this->sendNotice($assignment, $smsPortalService);
                }
            }, 'subscription_user.id', 'subscription_user_id');
    }

    private function sendNotice(object $assignment, SmsPortalService $smsPortalService): void
    {
        $message = $this->message($assignment->subscription_name, $assignment->expires_at);
        $smsMessage = SmsMessage::query()->firstOrCreate(
            [
                'type' => SmsMessage::TYPE_SUBSCRIPTION_EXPIRING,
                'subscription_user_id' => $assignment->subscription_user_id,
            ],
            [
                'user_id' => $assignment->user_id,
                'subscription_id' => $assignment->subscription_id,
                'destination' => trim($assignment->user_phone),
                'message' => $message,
                'status' => SmsMessage::STATUS_PENDING,
            ]
        );

        if ($smsMessage->status === SmsMessage::STATUS_SENT) {
            return;
        }

        $destination = trim($assignment->user_phone);
        $sent = $smsPortalService->send($destination, $message);

        $smsMessage->forceFill([
            'user_id' => $assignment->user_id,
            'subscription_id' => $assignment->subscription_id,
            'destination' => $destination,
            'message' => $message,
            'status' => $sent ? SmsMessage::STATUS_SENT : SmsMessage::STATUS_FAILED,
            'sent_at' => $sent ? Carbon::now() : null,
        ])->save();
    }

    private function message(string $subscriptionName, string $expiresAt): string
    {
        return strtr((string) config('subscriptions.expiration_notice_message'), [
            ':subscription' => $subscriptionName,
            ':expires_at' => $expiresAt,
        ]);
    }
}
