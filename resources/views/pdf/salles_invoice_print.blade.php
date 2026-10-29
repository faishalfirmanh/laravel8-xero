<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>

    <style>
        @page {
            margin: 28px 36px;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1a1a1a;
            font-size: 11px;
            line-height: 1.4;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* ===== HEADER ===== */

        .header-table td {
            vertical-align: top;
        }

        .invoice-title {
            font-size: 30px;
            font-weight: bold;
            color: #1a1a1a;
            margin: 0 0 22px 0;
        }

        .bill-to-name {
            font-size: 12px;
            font-weight: bold;
            color: #1a1a1a;
        }

        .meta-label {
            font-size: 10px;
            font-weight: bold;
            color: #333;
            padding-top: 6px;
        }

        .meta-value {
            font-size: 11px;
            color: #1a1a1a;
            padding-bottom: 1px;
        }

        .company-logo {
            max-width: 90px;
            height: auto;
            margin-bottom: 8px;
        }

        .company-info {
            font-size: 11px;
            color: #1a1a1a;
            text-align: right;
            line-height: 1.5;
        }

        /* ===== TABEL ITEM / RIWAYAT BAYAR ===== */

        .data-table {
            margin-top: 25px;
            margin-bottom: 4px;
        }

        .data-table th {
            border-bottom: 1px solid #999;
            padding: 5px 6px;
            font-size: 10px;
            text-transform: uppercase;
            color: #333;
            font-weight: bold;
            text-align: left;
        }

        .data-table th.text-right {
            text-align: right;
        }

        .data-table td {
            padding: 4px 6px;
            font-size: 11px;
            border-bottom: 1px solid #eee;
            color: #1a1a1a;
        }

        .amount-blue {
            color: #3d76f1;
        }

        .section-heading {
            font-size: 11px;
            font-weight: bold;
            margin-top: 18px;
            margin-bottom: 2px;
        }

        /* ===== TOTAL ===== */

        .totals-table {
            margin-top: 8px;
        }

        .totals-table td {
            padding: 4px 6px;
            font-size: 11px;
        }

        .totals-table .label {
            text-align: right;
            font-weight: bold;
            color: #1a1a1a;
        }

        .totals-table .value {
            text-align: right;
            color: #3d76f1;
            font-weight: bold;
            width: 130px;
        }

        .totals-table .line-top td {
            border-top: 1px solid #999;
            padding-top: 6px;
        }

        .due-date {
            font-size: 11px;
            font-weight: bold;
            margin-top: 14px;
            margin-bottom: 14px;
        }

        /* ===== FOOTER ===== */

        .bank-list {
            font-size: 10px;
            line-height: 1.6;
            margin-bottom: 14px;
        }

        .terms-box {
            font-size: 9.5px;
            color: #444;
        }

        .terms-box ol {
            padding-left: 14px;
            margin: 4px 0;
        }

        .terms-box li {
            margin-bottom: 3px;
        }

        .signature-box {
            margin-top: 16px;
            font-size: 11px;
        }

        .registered-office {
            margin-top: 25px;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            font-size: 9px;
            color: #777;
            text-align: center;
        }

        .invoice-desc {
            display: block !important;
            line-height: 1.5 !important;
        }

        /* ===== PAYMENT ===== */

        .payment-overpay {
            color: #d9534f;
            font-weight: bold;
        }

        .payment-bank {
            color: #1a1a1a;
        }
    </style>
</head>

<body>

    @php

        /*
        |--------------------------------------------------------------------------
        | FORMAT NUMBER
        |--------------------------------------------------------------------------
        */

        $fmt = function ($num) {
            if ($num === null || $num === '') {
                return '';
            }

            $num = (float) $num;

            $formatted = number_format(abs($num), 2);

            return $num < 0
                ? "({$formatted})"
                : $formatted;
        };


        /*
        |--------------------------------------------------------------------------
        | FORMAT DATE
        |--------------------------------------------------------------------------
        */

        $fmtDate = function ($val) {

            if (!$val) {
                return null;
            }

            try {
                return \Carbon\Carbon::parse($val)->translatedFormat('d M Y');
            } catch (\Exception $e) {
                return $val;
            }
        };


        /*
        |--------------------------------------------------------------------------
        | DATA
        |--------------------------------------------------------------------------
        */

        $detailItems = $invoice->getDetailById ?? collect();

        $payments = $invoice->getPayment ?? collect();
        $kembaliOverpay    = $invoice->getFeeOverpay ?? collect();
        $subTotal = $invoice->invoice_total;

        $totalIDR = $invoice->invoice_total
            ?? $invoice->total_payment_rupiah
            ?? $subTotal;

        $amountPaid = $invoice->invoice_amount;

        $amountDue = $invoice->less_nominal;


        /*
        |--------------------------------------------------------------------------
        | PISAH PEMBAYARAN
        |--------------------------------------------------------------------------
        |
        | nominal_receive > 0
        |   = pembayaran masuk melalui bank
        |
        | nominal_spend > 0
        |   = pemakaian / apply overpayment
        |
        */

        $bankPayments = $payments->filter(function ($payment) {

            return (float) $payment->nominal_receive > 0;

        });


        $overpayPayments = $payments->filter(function ($payment) {

            return (float) $payment->nominal_spend > 0;

        });

    @endphp


    {{-- =========================================================
         HEADER
    ========================================================== --}}

    <table class="header-table">

        <tr>

            <td width="35%">

                <h1 class="invoice-title">
                    INVOICE
                </h1>

                <div class="bill-to-name">
                    {{ $invoice->contact_name }}
                </div>
                @if ($invoice->va_number)
                    <div style="border: 2px solid #333; border-radius: 8px; padding: 15px; margin-top: 10px; display: inline-block;">
                        <h4>No Virtual Account</h4>
                        <h5 class="mb-0 font-weight-bold">
                            {{ $invoice->va_number }}
                        </h5>
                    </div>
                @endif
            </td>


            <td width="30%">

                <table>

                    <tr>
                        <td class="meta-label">
                            Invoice Date
                        </td>
                    </tr>

                    <tr>
                        <td class="meta-value">
                            {{ $fmtDate($invoice->issue_date ?? null) ?? $date }}
                        </td>
                    </tr>


                    <tr>
                        <td class="meta-label">
                            Invoice Number
                        </td>
                    </tr>

                    <tr>
                        <td class="meta-value">
                            {{ $invoice->invoice_number }}
                        </td>
                    </tr>


                    <tr>
                        <td class="meta-label">
                            Reference
                        </td>
                    </tr>

                    <tr>
                        <td class="meta-value">
                            {{ $invoice->reference ?? '-' }}
                        </td>
                    </tr>

                </table>

            </td>


            <td width="35%" class="text-right">

                @php
                    $img_path = $invoice->getTravel
                        ? $invoice->getTravel->location_path_image
                        : 'assets/img/nam_min.webp';
                @endphp

                <img
                    style="height: 100px; width: auto; object-fit: contain;"
                    src="{{ public_path($img_path) }}"
                    class="company-logo"
                />

                <div class="company-info">

                    {{ $invoice->getTravel
                        ? $invoice->getTravel->full_name
                        : '-'
                    }}

                    <br>

                    {{ $invoice->getTravel
                        ? ($invoice->getTravel->address
                            ? $invoice->getTravel->address
                            : '-')
                        : '-'
                    }}

                </div>

            </td>

        </tr>

    </table>


    {{-- =========================================================
         DETAIL INVOICE
    ========================================================== --}}

    <table class="data-table">

        <thead>

            <tr>

                <th width="46%">
                    Penjelasan
                </th>

                <th width="14%" class="text-right">
                    Qty
                </th>

                <th width="20%" class="text-right">
                    Harga Satuan
                </th>

                <th width="20%" class="text-right">
                    Total
                </th>

            </tr>

        </thead>


        <tbody>

            @foreach($detailItems as $item)

                @php
                    $cek_bold = $item->qty < 1
                        ? 'font-weight: bold; font-size:16px;'
                        : '';
                @endphp

                <tr>

                    <td
                        class="invoice-desc"
                        style="{{ $cek_bold }}"
                    >
                        {!! nl2br(e($item->desc)) !!}
                    </td>


                    <td class="text-right">

                        @if ($item->qty > 0)
                            {{ $item->qty }}
                        @endif

                    </td>


                    <td class="text-right">

                        @if ($item->qty > 0)
                            {{ $fmt($item->unit_price ?? null) }}
                        @endif

                    </td>


                    <td class="text-right amount-blue">

                        @if ($item->qty > 0)
                            {{ $fmt($item->total_amount_each_row ?? null) }}
                        @endif

                    </td>

                </tr>

            @endforeach


            {{-- TOTAL TAGIHAN --}}

            <tr>

                <td colspan="2"></td>

                <td class="text-right">
                    <strong>
                        Total Tagihan
                    </strong>
                </td>

                <td class="text-right amount-blue">
                    <strong>
                        {{ $fmt($subTotal) }}
                    </strong>
                </td>

            </tr>

        </tbody>

    </table>


    {{-- =========================================================
         RIWAYAT PEMBAYARAN MELALUI BANK
    ========================================================== --}}

    @if($bankPayments->count() > 0)

        <div class="section-heading">
            Riwayat Pembayaran
        </div>


        <table class="data-table">

            <thead>

                <tr>
                    <th width="20%">
                        Tanggal
                    </th>
                    <th width="40%">
                        Bank
                    </th>
                    <th>Keterangan</th>
                    <th width="40%" class="text-right">
                        Nominal
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($bankPayments as $item_pay)
                    <tr>
                        <td>
                            {{ $fmtDate($item_pay->date_transaction ?? null) ?? '-' }}
                        </td>
                        <td class="payment-bank">
                            {{ $item_pay->name_bank ?? '-' }}
                        </td>
                        <td>
                            {{ $item_pay->reference_detail ?? '-' }}
                        </td>
                        <td class="text-right amount-blue">
                            {{ $fmt($item_pay->nominal_receive) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

     @if($kembaliOverpay->count() > 0)
        <div class="section-heading">Riwayat Pengembalian Dana Berlebih</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="20%">Tanggal</th>
                    <th width="20%">Bank</th>
                    <th width="20%" class="text-right">Penjelasan</th>
                    <th width="40%" class="text-right">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($kembaliOverpay as $item_pay)
                    <tr>
                      
                        <td>{{ $fmtDate($item_pay->date_transaction ?? null) ?? '-' }}</td>
                        <td>{{ $item_pay->name_bank }}</td>
                        <td>{{ $item_pay->bank_tujuan_jamaah_rek }}</td>
                        <td class="text-right amount-blue">{{ $fmt($item_pay->nominal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif


    {{-- =========================================================
         RIWAYAT PEMAKAIAN OVERPAYMENT
    ========================================================== --}}

    @if($overpayPayments->count() > 0)

        <div class="section-heading">
            Riwayat Pemakaian Uang Deposit
        </div>


        <table class="data-table">

            <thead>

                <tr>

                    <th width="20%">
                        Tanggal
                    </th>

                    <th width="40%">
                        Keterangan
                    </th>

                    <th width="40%" class="text-right">
                        Nominal
                    </th>

                </tr>

            </thead>


            <tbody>

                @foreach($overpayPayments as $item_pay)

                    <tr>

                        <td>
                            {{ $fmtDate($item_pay->date_transaction ?? null) ?? '-' }}
                        </td>


                        <td>

                            <span class="payment-overpay">
                                Overpayment
                            </span>

                            @if(!empty($item_pay->name_bank))
                                - {{ $item_pay->name_bank }}
                            @endif

                        </td>


                        <td class="text-right amount-blue">
                            {{ $fmt($item_pay->nominal_spend) }}
                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    @endif


    {{-- =========================================================
         TOTAL PEMBAYARAN
    ========================================================== --}}

    <table class="totals-table" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <tbody>
            <tr>
                <td style="width: 45%;"></td>
                <td class="label" style="width: 30%; padding: 8px 12px; text-align: right;">
                    Total Tagihan
                </td>
                <td class="value" style="width: 25%; padding: 8px 12px; text-align: right; white-space: nowrap;">
                    {{ $fmt($subTotal) }}
                </td>
            </tr>

            @if ($invoice->code_curr == 'SAR')
                @php
                    $tot_rp = $invoice->nominal_currency * $invoice->invoice_total;
                @endphp
                <tr>
                    <td></td>
                    <td class="label" style="padding: 8px 12px; text-align: right;">
                        Tagihan Dalam Rupiah
                    </td>
                    <td class="value" style="padding: 8px 12px; text-align: right; white-space: nowrap;">
                        Rp {{ $fmt($tot_rp) }}
                    </td>
                </tr>
            @endif

            <tr>
                <td></td>
                <td class="label" style="padding: 8px 12px; text-align: right;">
                    Total Pembayaran
                </td>
                <td class="value" style="padding: 8px 12px; text-align: right; white-space: nowrap;">
                    {{ $fmt($amountPaid) }}
                </td>
            </tr>

            <tr style="border-top: 1px solid #ddd;">
                <td></td>
                <td class="label" style="padding: 10px 12px; text-align: right; font-weight: bold;">
                    Tagihan Belum Dibayar
                </td>
                <td class="value" style="padding: 10px 12px; text-align: right; font-weight: bold; white-space: nowrap;">
                    {{ $fmt($amountDue) }}
                </td>
            </tr>

            @if ($invoice->getOverPay)
                <tr style="border-top: 1px dashed #aaa;">
                    <td></td>
                    <td class="label" style="padding: 10px 12px; text-align: right;">
                        Lebih Bayar
                    </td>
                    <td class="value" style="padding: 10px 12px; text-align: right; font-weight: bold; color: #198754; white-space: nowrap;">
                        {{ $fmt($invoice->getOverPay->nominal_overpayment) }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>


    {{-- =========================================================
         QR CODE
         POSISI DIPERTAHANKAN
    ========================================================== --}}

    <div style="margin-top:-30px;">

        <img
            src="{{ $qrCode }}"
            width="120"
            height="120"
            alt="QR Code"
        >


        <div
            style="
                font-size: 10px;
                margin-top: 5px;
            "
        >

            @if ($invoice->va_number)

                Pembayaran melalui VA :
                <b>{{ $invoice->va_number }}</b>

            @endif

        </div>

    </div>


    {{-- =========================================================
         JATUH TEMPO
    ========================================================== --}}

    <div class="due-date">

        Jatuh Tempo:
        {{ $fmtDate($invoice->due_date ?? null) ?? '-' }}

    </div>


    {{-- =========================================================
         FOOTER
    ========================================================== --}}

    <div class="footer-section">


        {{-- BANK ACCOUNT --}}

        {{-- <div class="bank-list">

            BCA 614-077-750-0 an. PT AN NAMIROH TRAVELINDO
            <br>

            MANDIRI 142-001-628-348-2 an. PT AN NAMIROH TRAVELINDO
            <br>

            MUAMALAT 704-001-354-1 an. AN NAMIROH TRAVELINDO PT
            <br>

            BNI 70-888-00-889 an. AN NAMIROH TRAVELINDO PT
            <br>

            BSI 706-901-888-7 an. AN NAMIROH TRAVELINDO PT
            <br>

            BRI 0586-0100-0710-308 an. PT AN NAMRIOH TRAVELINDO

        </div> --}}


        {{-- TERMS --}}

        <div class="terms-box">

            <ol>

                <li>
                    Harga dapat berubah mengikuti kurs USD/SAR,
                    biaya akomodasi, tiket, serta kebijakan pemerintah
                    Indonesia dan Arab Saudi.
                </li>

                <li>
                    Pemesanan wajib memenuhi kuota kursi (full seat);
                    jika tidak terpenuhi, harga akan disesuaikan
                    dan dapat dikenakan penalti.
                </li>

                <li>
                    DP blok seat sebesar Rp3.000.000 per jamaah.
                </li>

                <li>
                    Deposit 50% dibayarkan H-45 sebelum keberangkatan.
                </li>

                <li>
                    Pelunasan paling lambat H-30 sebelum keberangkatan.
                </li>

            </ol>

        </div>


        {{-- SIGNATURE --}}

        <div class="signature-box">

            Hormat kami,

            <br>
            <br>
            <br>

            <strong>
                {{ $invoice->name_created_user ?? 'Nuril Hidayati' }}
            </strong>

            <br>

           {{ $invoice->jabatan_name }}

        </div>


        {{-- REGISTERED OFFICE --}}

        <div class="registered-office">

            Registered Office:
            {{ $invoice->getTravel
                ? ($invoice->getTravel->address
                    ? $invoice->getTravel->address
                    : '-')
                : '-'
            }}

        </div>

    </div>


</body>

</html>