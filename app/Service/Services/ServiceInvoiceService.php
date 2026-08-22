<?php

namespace App\Service\Services;

use App\Service\Models\ServiceUser;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ServiceInvoiceService
{
    public function download(ServiceUser $assignment): Response
    {
        return response($this->pdf($assignment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($assignment).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function pdf(ServiceUser $assignment): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->renderHtml($assignment), 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();

        return $pdf->output();
    }

    public function filename(ServiceUser $assignment): string
    {
        return 'factura-'.($assignment->invoice_number ?: $assignment->id).'.pdf';
    }

    private function renderHtml(ServiceUser $assignment): string
    {
        $assignment->loadMissing(['service.organization', 'user.organization']);
        $service = $assignment->service;
        $user = $assignment->user;
        $fullName = trim("{$user->last_name} {$user->first_name}") ?: $user->email;
        $amount = number_format((float) $service->price, 2, '.', ',');
        $issuedAt = $assignment->updated_at ?? $assignment->created_at ?? now();

        return view('services.invoice', [
            'assignment' => $assignment,
            'service' => $service,
            'user' => $user,
            'organization' => $service->organization ?? $user->organization,
            'fullName' => $fullName,
            'issuedAt' => Carbon::parse($issuedAt),
            'amount' => $amount,
            'invoiceNumber' => $assignment->invoice_number ?: sprintf('INV%09d', $assignment->id),
        ])->render();
    }
}
