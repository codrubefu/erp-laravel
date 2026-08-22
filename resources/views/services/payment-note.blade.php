<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Nota de plata - {{ $fullName }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eee;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 25mm 15mm 12mm;
        }

        .copy {
            width: 100%;
            min-height: 260mm;
            position: relative;
        }

        .vertical-no,
        .vertical-id {
            position: absolute;
            right: 0;
            writing-mode: vertical-rl;
            font: bold 8pt "Times New Roman", serif;
        }

        .vertical-no {
            top: 0;
        }

        .vertical-id {
            top: 18mm;
        }

        h1 {
            font-size: 16pt;
            text-align: center;
            margin: 0 0 5mm;
            font-weight: 700;
        }

        .meta {
            font-size: 9pt;
            line-height: 1.45;
            margin-left: 4mm;
        }

        .meta strong.name {
            font-size: 12pt;
            text-transform: uppercase;
        }

        .italic {
            font-style: italic;
        }

        .bolditalic {
            font-weight: 700;
            font-style: italic;
        }

        .items {
            margin-top: 6mm;
            border-top: 2px solid #111;
            font-family: "Times New Roman", serif;
            font-size: 8pt;
        }

        .itemrow {
            display: grid;
            grid-template-columns: 8mm 1fr 12mm 25mm 25mm;
            background: #efefef;
            align-items: center;
            min-height: 7mm;
        }

        .itemrow div {
            padding: 1mm;
        }

        .right {
            text-align: right;
        }

        .desc {
            font-weight: 700;
            font-family: Arial, sans-serif;
        }

        .benef {
            display: grid;
            grid-template-columns: 22mm 1fr 14mm 1fr;
            font-size: 7.5pt;
            font-style: italic;
            padding: 2mm 0 0;
        }

        .benef .plain {
            font-style: normal;
        }

        .activation {
            font-size: 7.4pt;
            font-style: italic;
            margin: 2mm 0 8mm;
        }

        .total {
            border-top: 2px solid #111;
            padding-top: 4mm;
            display: flex;
            justify-content: flex-end;
            gap: 22mm;
            font-weight: 700;
            font-size: 11pt;
        }

        .order {
            margin-top: 8mm;
            font-size: 9.5pt;
        }

        .footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            border-top: 1.5px solid #111;
            padding-top: 2mm;
        }

        .company {
            font-weight: 700;
            font-style: italic;
            font-size: 10pt;
            margin-left: 3mm;
        }

        .addr {
            font-size: 7.5pt;
            line-height: 1.45;
            margin: 1mm 0 0 5mm;
        }

        .code {
            font: italic 6.5pt "Times New Roman", serif;
            margin: 8mm 0 1mm 5mm;
        }

        .software {
            margin-left: 5mm;
            font: 7pt "Times New Roman", serif;
        }

        @media print {
            body {
                background: #fff;
            }

            .page {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="copy">
            <div class="vertical-no">No.</div>
            <div class="vertical-id">{{ $noteNumber }}</div>

            <h1>NOTA DE PLATA</h1>

            <div class="meta">
                {{ $issuedAt->format('d-M-Y') }}&nbsp;&nbsp;{{ $issuedAt->format('H:i') }}<br><br>
                <strong>SPA ID: {{ $user->user_code ?: $user->id }}</strong><br>
                <strong class="name">{{ $fullName }}</strong><br>
                {{ $user->email ?: '-' }}<br><br>
                <span class="italic">Nota plata service</span><br>
                <span class="bolditalic">Extern</span><br>
                <span class="italic">Independent</span>
            </div>

            <div class="items">
                <div class="itemrow">
                    <div>21%</div>
                    <div class="desc">{{ $service->name }}</div>
                    <div class="right"><b>1.00</b></div>
                    <div class="right"><b>{{ $amount }}</b></div>
                    <div class="right"><b>{{ $amount }}</b></div>
                </div>
            </div>

            <div class="benef">
                <div>Beneficiar</div>
                <div class="plain">{{ $fullName }}</div>
                <div>Card:</div>
                <div class="plain">{{ $cardCode }}&nbsp; Data Activ : {{ $startDate }}</div>
            </div>

            <div class="activation">activare card</div>
            <div class="total"><span>TOTAL PLATA:</span><span>{{ $amount }}</span></div>
            <div class="order">{{ $orderDetails }}</div>

            <div class="footer">
                <div class="company">{{ $organization?->name ?? 'Organizatie' }}</div>
                <div class="addr">
                    {{ $organization?->address ?? '-' }}<br>
                    Email: {{ $organization?->email ?? '-' }}&nbsp;&nbsp; Web: {{ $organization?->web ?? '-' }}<br>
                    Tel: {{ $organization?->phone ?? '-' }}<br><br>
                    C.U.I. {{ $organization?->cui ?? '-' }}&nbsp;&nbsp;&nbsp;&nbsp;
                    Nr.Reg.Com. {{ $organization?->nr_reg_com ?? '-' }}<br>
                    Capital social: {{ $organization?->capital ?? '-' }}<br>
                    Cont: {{ $organization?->cont ?? '-' }}<br>
                    Banca: {{ $organization?->bank ?? '-' }}
                </div>
                <div class="code">{{ $assignment->bill_number ?: $noteNumber }}</div>
                <div class="software">Document generat automat din ERP.</div>
            </div>
        </section>
    </div>
</body>
</html>
