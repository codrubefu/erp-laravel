<?php

namespace App\Payments\Services;

use App\Payments\Models\Payment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptService
{
    public function download(Payment $payment): StreamedResponse
    {
        $content = implode("\n", [
            'CHITANTA '.$payment->receipt_number,
            'Data: '.$payment->confirmed_at?->format('Y-m-d H:i:s'),
            'Platitor: '.$payment->first_name.' '.$payment->last_name,
            'Suma: '.$payment->amount,
            'Referinta: '.$payment->external_reference,
        ])."\n";

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $payment->receipt_number.'.txt', ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
