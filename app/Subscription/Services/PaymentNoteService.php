<?php

namespace App\Subscription\Services;

use App\Subscription\Models\SubscriptionUser;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PaymentNoteService
{
    public function download(SubscriptionUser $assignment): Response
    {
        $assignment->loadMissing(['subscription', 'user']);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->renderHtml($assignment), 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($assignment).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function renderHtml(SubscriptionUser $assignment): string
    {
        $template = File::get($this->templatePath());
        $subscription = $assignment->subscription;
        $user = $assignment->user;
        $fullName = trim("{$user->last_name} {$user->first_name}") ?: $user->email;
        $issuedAt = now();
        $amount = number_format((float) $subscription->price, 2, '.', ',');
        $startDate = $this->formatDate($assignment->start_date ?? $issuedAt);
        $expiresAt = $assignment->expires_at ? $this->formatDate($assignment->expires_at) : 'fara expirare';
        $noteNumber = sprintf('%09d', $assignment->id);
        $cardCode = $user->user_code ?: sprintf('USR%08d', $user->id);
        $orderDetails = sprintf(
            'Comanda abonament #%d | User #%d | Abonament #%d | Start: %s | Expira: %s',
            $assignment->id,
            $user->id,
            $subscription->id,
            $startDate,
            $expiresAt,
        );

        $replacements = [
            'width: 50%;' => 'width: 100%;',
            '<section class="copy left">' => '<section class="copy">',
            '<title>Nota de plata - George Oana</title>' => '<title>Nota de plata - '.$this->e($fullName).'</title>',
            '260162053' => $this->e($noteNumber),
            '28-Jun-2026&nbsp;&nbsp;22:12' => $this->e($issuedAt->format('d-M-Y')).'&nbsp;&nbsp;'.$this->e($issuedAt->format('H:i')),
            '<strong>SPA ID: 47684</strong>' => '<strong>SPA ID: '.$this->e($user->user_code ?: (string) $user->id).'</strong>',
            '<strong class="name">GEORGE OANA</strong>' => '<strong class="name">'.$this->e(Str::upper($fullName)).'</strong>',
            'Corbeanca Romania' => $this->e($user->email),
            'Bank Card Web' => 'Nota plata abonament',
            'Abonament Fitness 12 luni' => $this->e($subscription->name),
            '2,307.00' => $this->e($amount),
            '<div class="plain">George Oana</div>' => '<div class="plain">'.$this->e($fullName).'</div>',
            'CM0000038920&nbsp; Data Activ : 28-Jun-2026' => $this->e($cardCode).'&nbsp; Data Activ : '.$this->e($startDate),
            'Sales Order from Website ~3892' => $this->e($orderDetails),
        ];

        return strtr($template, $replacements);
    }

    private function templatePath(): string
    {
        $paths = [
            storage_path('note-plata.html'),
            storage_path('nota-plata.html'),
        ];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        abort(500, 'Payment note template was not found.');
    }

    private function filename(SubscriptionUser $assignment): string
    {
        return 'nota-plata-abonament-'.$assignment->id.'.pdf';
    }

    private function formatDate(Carbon|string|null $date): string
    {
        return $date ? Carbon::parse($date)->format('d-M-Y') : now()->format('d-M-Y');
    }

    private function e(string $value): string
    {
        return e($value, false);
    }
}
