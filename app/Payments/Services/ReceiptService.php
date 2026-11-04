<?php

namespace App\Payments\Services;

use App\Payments\Models\Payment;
use App\Service\Models\ServiceUser;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReceiptService
{
    public function download(Payment $payment): Response
    {
        return response($this->pdf($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($payment).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function pdf(Payment $payment): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->html($payment), 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();

        return $pdf->output();
    }

    public function filename(Payment $payment): string
    {
        return ($payment->receipt_number ?: 'chitanta-'.$payment->id).'.pdf';
    }

    public function html(Payment $payment): string
    {
        $payment->loadMissing(['admin.organization', 'location']);

        $paidAt = $payment->confirmed_at ?? $payment->paid_at ?? $payment->created_at ?? now();
        $payerName = trim($payment->first_name.' '.$payment->last_name) ?: 'Client #'.$payment->id;
        $amount = number_format((float) $payment->amount, 2, '.', ',');
        $amountText = $this->amountInWords((float) $payment->amount);
        $cashier = trim(($payment->admin?->first_name ?? '').' '.($payment->admin?->last_name ?? ''))
            ?: ($payment->admin?->email ?? 'Casier #'.($payment->admin_id ?? '-'));
        $description = $this->description($payment);
        $receiptNumber = $payment->receipt_number ?? sprintf('CH%09d', $payment->id);
        $receiptSeries = preg_replace('/\d+$/', '', $receiptNumber) ?: 'CH';

        return view('payments.receipt', [
            'payment' => $payment,
            'organization' => $this->organization($payment),
            'paidAt' => $paidAt,
            'paidAtFormatted' => $this->formatDate($paidAt),
            'payerName' => $payerName,
            'amount' => $amount,
            'amountText' => $amountText,
            'cashier' => $cashier,
            'description' => $description,
            'receiptNumber' => $receiptNumber,
            'receiptSeries' => $receiptSeries,
            'archiveCode' => 'PAY-'.$payment->id.'-'.$paidAt->format('Ymd'),
        ])->render();
    }

    private function organization(Payment $payment): mixed
    {
        if ($payment->model_type === Payment::MODEL_TYPE_SERVICE_USER && $payment->model_id) {
            $organization = DB::table('service_user')
                ->join('services', 'services.id', '=', 'service_user.service_id')
                ->join('organizations', 'organizations.id', '=', 'services.organization_id')
                ->where('service_user.id', $payment->model_id)
                ->select('organizations.*')
                ->first();

            if ($organization) {
                return $organization;
            }
        }

        if ($payment->organization_id) {
            $organization = DB::table('organizations')->where('id', $payment->organization_id)->first();
            if ($organization) {
                return $organization;
            }
        }

        return $payment->admin?->organization;
    }

    private function description(Payment $payment): string
    {
        $parts = [];

        if ($payment->model_type === Payment::MODEL_TYPE_SERVICE_USER && $payment->model_id) {
            $assignment = ServiceUser::query()->with('service')->find($payment->model_id);

            if ($assignment) {
                $parts[] = 'nota de plata nr. '.$assignment->noteNumber();
                if ($assignment->invoice_number) {
                    $parts[] = 'factura nr. '.$assignment->invoice_number;
                }
                $parts[] = 'serviciu '.$assignment->service?->name.' #'.$assignment->service_id;
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

        return implode(' | ', $parts);
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

}
