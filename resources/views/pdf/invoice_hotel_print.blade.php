Berikut adalah versi **"Compact Mode"** agar muat dalam 1 halaman.

Saya melakukan beberapa penyesuaian agresif untuk menghemat ruang vertikal:

1. **Margin Halaman**: Diatur manual agar lebih tipis (`20px`).
2. **Font Size**: Diperkecil menjadi `12px` (standar) dan `10px` (untuk footer/syarat).
3. **Padding Tabel**: Dikurangi drastis dari `10px` menjadi `4px`.
4. **Logo**: Ukuran diperkecil sedikit.
5. **Spasi Antar Elemen**: Margin antar section diperkecil.

Silakan copy-paste kode ini:

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /* 1. SETUP HALAMAN & MARGIN TIPIS */
        @page {
            margin: 20px 30px; /* Atas/Bawah 20px, Kiri/Kanan 30px */
        }

        body {
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 12px; /* Font dasar lebih kecil */
            line-height: 1.3;
        }

        /* Utility */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; }

        /* 2. HEADER COMPACT */
        .top-header {
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 5px;
            margin-bottom: 10px; /* Kurangi margin bawah */
        }

        .company-logo {
            max-width: 100px; /* Logo diperkecil */
            height: auto;
        }

        .invoice-title {
            font-size: 24px; /* Judul tidak perlu terlalu besar */
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }

        /* 3. INFO SECTION YANG RAPAT */
        .info-table { margin-bottom: 10px; }
        .info-table td { padding: 2px 0; vertical-align: top; }
        .info-label { font-weight: bold; font-size: 10px; color: #555; text-transform: uppercase; }

        /* 4. TABEL DATA (ITEM) YANG LEBIH PADAT */
        .data-table {
            width: 100%;
            margin-bottom: 10px;
            border: 1px solid #ccc;
        }

        .data-table th {
            background-color: #2c3e50;
            color: #fff;
            padding: 4px 6px; /* Padding header tipis */
            font-size: 11px;
            text-transform: uppercase;
            text-align: left;
        }

        .data-table td {
            padding: 4px 6px; /* Padding cell tipis agar muat banyak baris */
            border-bottom: 1px solid #eee;
            font-size: 11px;
        }

        .data-table tr:nth-child(even) { background-color: #f8f8f8; }

        /* Baris Total */
        .total-row td {
            border-top: 2px solid #333;
            font-weight: bold;
            background-color: #fff;
            font-size: 13px;
        }

        /* 5. FOOTER & SYARAT KETENTUAN (FONT KECIL) */
        .footer-section {
            margin-top: 15px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }

        .terms-box {
            font-size: 10px; /* Font syarat diperkecil */
            color: #666;
            text-align: justify;
        }

        .terms-box ul {
            padding-left: 15px;
            margin: 2px 0;
        }

        .terms-box li { margin-bottom: 2px; }

        .bank-box {
            background: #f0f0f0;
            padding: 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
        }

        .signature-box {
            text-align: center;
            width: 180px;
            float: right;
            font-size: 11px;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            margin-top: 40px; /* Ruang tanda tangan */
            margin-bottom: 5px;
        }
    </style>
</head>
<body>

    <table class="top-header">
        <tr>
            <td width="60%">
                <img src="{{ public_path('assets/img/logo-namiroh-hd.png') }}" class="company-logo"/>
                <div style="font-size: 14px; font-weight: bold; margin-top: 5px;">PT AN NAMIROH TRAVELINDO</div>
                <div style="font-size: 11px;">
                    Jl. Gajah Mada No.10/03, Menanggal,
                </div>
                <div style="font-size: 11px;">
                    Kec. Mojosari,
                    Kabupaten Mojokerto,
                </div>
                <div style="font-size: 11px;">
                    Jawa Timur, 61382
                </div>
            </td>
            <td width="40%" class="text-right" style="vertical-align: bottom;">
                <h1 class="invoice-title">INVOICE</h1>
                <div style="font-size: 11px;">
                    No: <strong>{{ $invoice->no_invoice_hotel ?? '-' }}</strong><br>
                    Tgl: {{ $date }}
                </div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td width="55%">
                <span class="info-label">Tagihan Kepada:</span><br>
                <strong>{{ $invoice->nama_pemesan }}</strong>
            </td>
            <td width="45%" class="text-right">
                <table style="width: auto; float: right;">
                    <tr>
                        <td class="info-label text-right" style="padding-right: 10px;">Check In:</td>
                        <td class="text-right"><strong>{{ $invoice->check_in }}</strong></td>
                    </tr>
                    <tr>
                        <td class="info-label text-right" style="padding-right: 10px;">Check Out:</td>
                        <td class="text-right"><strong>{{ $invoice->check_out }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table class="info-table">
    <tr>
        <td width="55%">
            <span class="info-label">Nama Hotel:</span><br>
            <strong>{{ $invoice->hotel_name }}</strong>
        </td>
    </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th>Keterangan / Tipe Kamar</th>
                <th width="8%" class="text-center">Qty</th>
                <th width="20%" class="text-right">Harga</th>
                <th width="20%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->details as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->type_room_desc }}</td>
                <td class="text-center">{{ $item->qty }}</td>
                <td class="text-right">{{ number_format($item->price_each_item) }}</td>
                <td class="text-right">{{ number_format($item->total_amount) }}</td>
            </tr>
            @endforeach

            <tr class="total-row">
                <td colspan="4" class="text-right">GRAND TOTAL</td>
                <td class="text-right">Rp {{ number_format($invoice->total_payment) }}</td>
            </tr>
        </tbody>
    </table>

    @if (count($invoice->payments) > 0)
        <div style="font-size: 11px; font-weight: bold; margin-bottom: 2px;">Riwayat Pembayaran:</div>
        <table class="data-table" style="margin-bottom: 5px;">
            <thead>
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th width="20%">Tanggal</th>
                    <th>Ref/Ket</th>
                    <th width="20%" class="text-right">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->payments as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->date_transfer }}</td>
                    <td>{{ $item->desc }}</td>
                    <td class="text-right">{{ number_format($item->payment_idr) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer-section">
        <table>
            <tr>
                <td width="65%" style="vertical-align: top; padding-right: 20px;">
                    <div class="bank-box">
                        <strong>TRANSFER KE:</strong> MANDIRI (142-00-0000747-5) A/N PT AN NAMIROH TRAVELINDO
                    </div>

                    <div class="terms-box">
                        <span style="font-weight: bold; text-decoration: underline;">Syarat & Ketentuan:</span>
                        <ul>
                            <li>Down Payment 25% wajib dibayar 1x24 jam setelah invoice terbit.</li>
                            <li>Jika melewati 1x24 jam tanpa pembayaran, invoice otomatis batal.</li>
                            <li>Penambahan DP 50% dari total invoice wajib H-14 sebelum check-in.</li>
                            <li>Pelunasan penuh maksimal H-10 sebelum check-in.</li>
                            <li>Pembatalan akan dikenakan biaya (charge) sesuai ketentuan waktu pembatalan.</li>
                        </ul>
                    </div>
                </td>

                <td width="35%" style="vertical-align: top;">
                    <div class="signature-box">
                        Issued by,
                        <div class="signature-line"></div>
                        <strong>{{ $invoice->name_created_user }}</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>

```
