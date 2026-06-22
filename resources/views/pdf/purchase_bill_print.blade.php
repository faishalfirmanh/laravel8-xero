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
    </style>
</head>

<body>

    @php
        // Helper format angka: kosong jika null, kurung jika negatif (gaya akuntansi).
        $fmt = function ($num) {
            if ($num === null || $num === '') return '';
            $num = (float) $num;
            $formatted = number_format(abs($num), 2);
            return $num < 0 ? "({$formatted})" : $formatted;
        };

        // Helper format tanggal: terima string/Carbon, fallback aman jika gagal parse.
        $fmtDate = function ($val) {
            if (!$val) return null;
            try {
                return \Carbon\Carbon::parse($val)->translatedFormat('d M Y');
            } catch (\Exception $e) {
                return $val;
            }
        };

        $detailItems = $invoice->getDetailById ?? collect();
        $payments    = $invoice->getPayment ?? collect();

        $subTotal   = $invoice->invoice_total;
        $totalIDR   = $invoice->invoice_total ?? $invoice->total_payment_rupiah ?? $subTotal;
        $amountPaid = $invoice->invoice_amount;
        $amountDue  = $invoice->less_nominal;
    @endphp

    <table class="header-table">
        <tr>
            <td width="35%">
                <h1 class="invoice-title">INVOICE</h1>
                <div class="bill-to-name">
                    {{ $invoice->contact_name }}
                </div>
            </td>
            <td width="30%">
                <table>
                    <tr>
                        <td class="meta-label">Invoice Date</td>
                    </tr>
                    <tr>
                        <td class="meta-value">
                            {{ $fmtDate($invoice->issue_date ?? null) ?? $date }}
                        </td>
                    </tr>
                    <tr>
                        <td class="meta-label">Invoice Number</td>
                    </tr>
                    <tr>
                        <td class="meta-value">
                            {{ $invoice->invoice_number }}
                        </td>
                    </tr>
                    <tr>
                        <td class="meta-label">Reference</td>
                    </tr>
                    <tr>
                        <td class="meta-value">
                            {{ $invoice->reference ?? '-' }}
                        </td>
                    </tr>
                </table>
            </td>
            <td width="35%" class="text-right">
                <img src="{{ public_path('assets/img/logo-namiroh-hd.png') }}" class="company-logo" />
                <div class="company-info">
                    PT An Namiroh Travelindo<br>
                    Jalan Gajah Mada<br>
                    Mojokerto Jawa Timur 61382<br>
                    Indonesia
                </div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="46%">Description</th>
                <th width="14%" class="text-right">Quantity</th>
                <th width="20%" class="text-right">Unit Price</th>
                <th width="20%" class="text-right">Amount IDR</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detailItems as $item)
            <tr>
                <td>
                    {{ optional($item->getItems)->nama_paket ?? $item->desc ?? '-' }}
                </td>
                <td class="text-right">{{ $fmt($item->qty ?? null) }}</td>
                <td class="text-right">{{ $fmt($item->unit_price ?? null) }}</td>
                <td class="text-right amount-blue">{{ $fmt($item->total_amount_each_row ?? null) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($payments->count() > 0)
    <div class="section-heading">Riwayat Pembayaran</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="20%">Tanggal</th>
                <th width="40%">Bank</th>
                <th width="40%" class="text-right">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $item_pay)
            <tr>
                <td>{{ $fmtDate($item_pay->date_transaction ?? null) ?? '-' }}</td>
                <td>{{ $item_pay->name_bank ?? '-' }}</td>
                <td class="text-right amount-blue">{{ $fmt($item_pay->nominal_receive ?? null) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <table class="totals-table">
        <tr>
            <td width="60%"></td>
            <td width="20%" class="label">Subtotal</td>
            <td width="20%" class="value">{{ $fmt($subTotal) }}</td>
        </tr>
        <tr class="line-top">
            <td></td>
            <td class="label">TOTAL IDR</td>
            <td class="value">{{ $fmt($totalIDR) }}</td>
        </tr>
        <tr>
            <td></td>
            <td class="label">Less Amount Paid</td>
            <td class="value">{{ $fmt($amountPaid) }}</td>
        </tr>
        <tr class="line-top">
            <td></td>
            <td class="label">AMOUNT DUE IDR</td>
            <td class="value">{{ $fmt($amountDue) }}</td>
        </tr>
    </table>

    <div class="due-date">
        Due Date: {{ $fmtDate($invoice->due_date ?? null) ?? '-' }}
    </div>

    <div class="footer-section">
        <div class="bank-list">
            BCA 614-077-750-0 an. PT AN NAMIROH TRAVELINDO<br>
            MANDIRI 142-001-628-348-2 an. PT AN NAMIROH TRAVELINDO<br>
            MUAMALAT 704-001-354-1 an. AN NAMIROH TRAVELINDO PT<br>
            BNI 70-888-00-889 an. AN NAMIROH TRAVELINDO PT<br>
            BSI 706-901-888-7 an. AN NAMIROH TRAVELINDO PT<br>
            BRI 0586-0100-0710-308 an. PT AN NAMRIOH TRAVELINDO
        </div>

        <div class="terms-box">
            <ol>
                <li>Harga dapat berubah mengikuti kurs USD/SAR, biaya akomodasi, tiket, serta kebijakan pemerintah Indonesia dan Arab Saudi.</li>
                <li>Pemesanan wajib memenuhi kuota kursi (full seat); jika tidak terpenuhi, harga akan disesuaikan dan dapat dikenakan penalti.</li>
                <li>DP blok seat sebesar Rp3.000.000 per jamaah.</li>
                <li>Deposit 50% dibayarkan H-45 sebelum keberangkatan.</li>
                <li>Pelunasan paling lambat H-30 sebelum keberangkatan.</li>
            </ol>
        </div>

        <div class="signature-box">
            Hormat kami,<br><br><br>
            <strong>{{ $invoice->name_created_user ?? 'Nuril Hidayati' }}</strong><br>
            Devisi keuangan
        </div>

        <div class="registered-office">
            Registered Office: Jalan Gajah Mada, Mojokerto, Jawa Timur, 61382, Indonesia.
        </div>
    </div>

</body>

</html>