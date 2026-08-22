<?php

namespace App\Payments\Services;

use App\Payments\Models\Payment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class ReceiptService
{
    public function download(Payment $payment): Response
    {
        $payment->loadMissing(['admin', 'location']);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->renderHtml($payment), 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$payment->receipt_number.'.pdf"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function renderHtml(Payment $payment): string
    {
        $template = File::get(storage_path('chitanta.html'));
        $paidAt = $payment->confirmed_at ?? $payment->paid_at ?? $payment->created_at ?? now();
        $payerName = trim($payment->first_name.' '.$payment->last_name) ?: 'Client #'.$payment->id;
        $amount = number_format((float) $payment->amount, 2, '.', ',');
        $amountText = $this->amountInWords((float) $payment->amount);
        $cashier = trim(($payment->admin?->first_name ?? '').' '.($payment->admin?->last_name ?? ''))
            ?: ($payment->admin?->email ?? 'Casier #'.($payment->admin_id ?? '-'));
        $description = $this->description($payment);

        $html = strtr($template, [
            '<title>Chitanta RO-TRADITIONAL</title>' => '<title>Chitanta '.$this->e($payment->receipt_number ?? (string) $payment->id).'</title>',
            'CH202600035' => $this->e($payment->receipt_number ?? sprintf('CH%09d', $payment->id)),
            '12-Aug-2026' => $this->e($this->formatDate($paidAt)),
            'Sat Frumusani&nbsp; Frumusani 19' => $this->e($this->payerAddress($payment)),
            '302.50' => $this->e($amount),
            'treisutedoileisicincizecibani' => $this->e($amountText),
            'FA120260100169 din 01-Aug-2026' => $this->e($description),
            'Marius Rus' => $this->e($cashier),
            '14-4-1' => $this->e('PAY-'.$payment->id.'-'.$paidAt->format('Ymd')),
        ]);

        $payer = $this->e($payerName).'&nbsp;&nbsp; Ref: '.$this->e($payment->external_reference ?? '-');

        return preg_replace(
            '/RO-TRADITIONAL SRL&nbsp;&nbsp; C\.U\.I\. RO16864160&nbsp;&nbsp;\s*Nr\.Reg\.Com\. J51\/525\/2004/',
            $payer,
            $html,
            1,
        ) ?? $html;
    }

    private function description(Payment $payment): string
    {
        $parts = [];

        if ($payment->model_type === Payment::MODEL_TYPE_SUBSCRIPTION_USER && $payment->model_id) {
            $assignment = DB::table('subscription_user')
                ->join('subscriptions', 'subscriptions.id', '=', 'subscription_user.subscription_id')
                ->where('subscription_user.id', $payment->model_id)
                ->select('subscriptions.id', 'subscriptions.name', 'subscription_user.start_date', 'subscription_user.expires_at')
                ->first();

            if ($assignment) {
                $parts[] = 'abonament '.$assignment->name.' #'.$assignment->id;
                if ($assignment->start_date) {
                    $parts[] = 'start '.$this->formatDate($assignment->start_date);
                }
                if ($assignment->expires_at) {
                    $parts[] = 'expira '.$this->formatDate($assignment->expires_at);
                }
            }
        } elseif ($payment->model_type === Payment::MODEL_TYPE_EVENT_OCCURRENCE_USER && $payment->model_id) {
            $event = DB::table('event_occurrence_user')
                ->join('event_occurrences', 'event_occurrences.id', '=', 'event_occurrence_user.event_occurrence_id')
                ->join('events', 'events.id', '=', 'event_occurrences.event_id')
                ->where('event_occurrence_user.id', $payment->model_id)
                ->select('events.title', 'event_occurrences.start_datetime')
                ->first();

            if ($event) {
                $parts[] = 'eveniment '.$event->title;
                if ($event->start_datetime) {
                    $parts[] = 'data '.$this->formatDate($event->start_datetime);
                }
            }
        }

        $parts[] = 'plata #'.$payment->id;
        $parts[] = 'metoda '.str_replace('_', ' ', $payment->paymentTypeName() ?? '-');

        return implode(' | ', $parts);
    }

    private function payerAddress(Payment $payment): string
    {
        $items = [];
        if ($payment->location?->name) {
            $items[] = 'Locatie: '.$payment->location->name;
        }
        if ($payment->provider) {
            $items[] = 'Provider: '.$payment->provider;
        }
        if ($payment->provider_transaction_id) {
            $items[] = 'Tranzactie: '.$payment->provider_transaction_id;
        }

        return $items ? implode(' | ', $items) : 'Plata interna #'.$payment->id;
    }

    private function amountInWords(float $amount): string
    {
        $lei = (int) floor($amount);
        $bani = (int) round(($amount - $lei) * 100);

        return trim($this->numberInRomanian($lei).' lei'.($bani > 0 ? ' si '.$this->numberInRomanian($bani).' bani' : ''));
    }

    private function numberInRomanian(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $units = ['', 'unu', 'doi', 'trei', 'patru', 'cinci', 'sase', 'sapte', 'opt', 'noua'];
        $teens = [10 => 'zece', 11 => 'unsprezece', 12 => 'doisprezece', 13 => 'treisprezece', 14 => 'paisprezece', 15 => 'cincisprezece', 16 => 'saisprezece', 17 => 'saptesprezece', 18 => 'optsprezece', 19 => 'nouasprezece'];

        if ($number < 10) {
            return $units[$number];
        }
        if ($number < 20) {
            return $teens[$number];
        }
        if ($number < 100) {
            $tens = intdiv($number, 10);
            $rest = $number % 10;
            $text = ($tens === 2 ? 'douazeci' : $units[$tens].'zeci');

            return $rest ? $text.' si '.$units[$rest] : $text;
        }
        if ($number < 1000) {
            $hundreds = intdiv($number, 100);
            $rest = $number % 100;
            $text = $hundreds === 1 ? 'o suta' : $units[$hundreds].' sute';

            return $rest ? $text.' '.$this->numberInRomanian($rest) : $text;
        }

        $thousands = intdiv($number, 1000);
        $rest = $number % 1000;
        $text = $thousands === 1 ? 'o mie' : $this->numberInRomanian($thousands).' mii';

        return $rest ? $text.' '.$this->numberInRomanian($rest) : $text;
    }

    private function formatDate(Carbon|string $date): string
    {
        return Carbon::parse($date)->format('d-M-Y');
    }

    private function e(string $value): string
    {
        return e($value, false);
    }
}
