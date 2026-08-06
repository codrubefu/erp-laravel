<?php

namespace App\Notifications\Services;

use App\Notifications\Models\NotificationDelivery;
use App\Sms\Services\SmsPortalService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class NotificationSender
{
    /** @return array{provider:string,external_id:?string} */
    public function send(NotificationDelivery $delivery): array
    {
        $user = $delivery->user;
        $message = $this->render($delivery);

        return match ($delivery->channel) {
            'sms' => $this->sms($user->phone, $message),
            'mail' => $this->mail($user->email, $message),
            'push' => $this->push($user->push_token, $message),
            default => throw new RuntimeException("Unsupported notification channel: {$delivery->channel}"),
        };
    }

    private function render(NotificationDelivery $delivery): string
    {
        $template = (string) config("notifications.templates.{$delivery->template}", $delivery->template);
        foreach ($delivery->payload as $key => $value) {
            $template = str_replace(':'.$key, (string) $value, $template);
        }
        return $template;
    }

    private function sms(?string $to, string $message): array
    {
        if (! $to || ! app(SmsPortalService::class)->send($to, $message)) throw new RuntimeException('SMS provider rejected the message.');
        return ['provider' => 'smsportal', 'external_id' => null];
    }

    private function mail(?string $to, string $message): array
    {
        if (! $to) throw new RuntimeException('User has no e-mail address.');
        Mail::raw($message, fn ($mail) => $mail->to($to)->subject((string) config('notifications.subject')));
        return ['provider' => (string) config('mail.default'), 'external_id' => null];
    }

    private function push(?string $token, string $message): array
    {
        if (! $token) throw new RuntimeException('User has no push token.');
        $response = Http::withToken((string) config('services.push.token'))->post((string) config('services.push.endpoint'), ['token' => $token, 'message' => $message]);
        if ($response->failed()) throw new RuntimeException('Push provider rejected the message.');
        return ['provider' => 'push', 'external_id' => $response->json('id')];
    }
}
