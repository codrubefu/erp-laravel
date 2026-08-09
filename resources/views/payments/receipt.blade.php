<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <style>
        body {
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 13px;
            line-height: 1.5;
            margin: 36px;
        }

        .header {
            border-bottom: 2px solid #111827;
            margin-bottom: 28px;
            padding-bottom: 18px;
        }

        .title {
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 6px;
            text-transform: uppercase;
        }

        .subtitle {
            color: #4b5563;
            margin: 0;
        }

        .grid {
            display: table;
            margin-bottom: 24px;
            width: 100%;
        }

        .column {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }

        .label {
            color: #6b7280;
            font-size: 11px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .value {
            font-size: 15px;
            font-weight: 700;
            margin: 3px 0 14px;
        }

        table {
            border-collapse: collapse;
            margin-top: 18px;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #d1d5db;
            padding: 11px 8px;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            color: #374151;
            font-size: 11px;
            text-transform: uppercase;
        }

        .amount {
            font-size: 18px;
            font-weight: 700;
            text-align: right;
        }

        .footer {
            border-top: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 11px;
            margin-top: 36px;
            padding-top: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Chitanta {{ $payment->receipt_number }}</h1>
        <p class="subtitle">Document generat automat pentru plata confirmata.</p>
    </div>

    <div class="grid">
        <div class="column">
            <div class="label">Platitor</div>
            <div class="value">{{ trim($payment->first_name.' '.$payment->last_name) }}</div>

            <div class="label">Metoda plata</div>
            <div class="value">{{ str_replace('_', ' ', $payment->paymentTypeName() ?? '-') }}</div>

            <div class="label">Status</div>
            <div class="value">{{ $payment->status }}</div>
        </div>
        <div class="column">
            <div class="label">Data confirmarii</div>
            <div class="value">{{ $payment->confirmed_at?->format('d.m.Y H:i') ?? '-' }}</div>

            <div class="label">Referinta</div>
            <div class="value">{{ $payment->external_reference ?? '-' }}</div>

            <div class="label">Locatie</div>
            <div class="value">{{ $payment->location?->name ?? '-' }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Descriere</th>
                <th style="text-align: right;">Suma</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    Plata {{ $payment->model_type ? 'pentru '.$payment->model_type : '' }}
                    @if ($payment->model_id)
                        #{{ $payment->model_id }}
                    @endif
                </td>
                <td class="amount">{{ number_format((float) $payment->amount, 2, ',', '.') }} RON</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Operator: {{ trim(($payment->admin?->first_name ?? '').' '.($payment->admin?->last_name ?? '')) ?: ($payment->admin?->email ?? '-') }}<br>
        Generat la {{ now()->format('d.m.Y H:i') }}.
    </div>
</body>
</html>
