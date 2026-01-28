<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        .header { text-align: center; margin-bottom: 20px; }
        .total { font-weight: bold; text-align: right; }

        .text-right { text-align: right; }
        .bg-gray { background-color: #f2f2f2; }
    </style>
</head>
<body>

    <div class="header">
        <h2>{{ $title }}</h2>
        <p>Tanggal Cetak: {{ $date }}</p>
    </div>

    <table style="border: none;">
        <tr style="border: none;">
            <td style="border: none;">
                <strong>Dari:</strong><br>
                PT Travel Umroh<br>
                Jakarta, Indonesia
            </td>
            <td style="border: none; text-align: right;">
                <strong>Kepada:</strong><br>
                {{ $invoice->nama_pemesan }}<br>
                {{ $invoice->address ?? '-' }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr class="bg-gray">
                <th>No</th>
                <th>Jenis Kamar</th>
                <th>Qty</th>
                <th class="text-right">Harga Tiap Tipe</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->details as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->type_room_desc }}</td>
                <td>{{ $item->qty }}</td>
                <td>{{ $item->price_each_item }}</td>
                <td class="text-right">{{ number_format($item->total_amount) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="total">Total Tagihan</td>
                <td class="total">{{ number_format($invoice->total_payment) }}</td>
            </tr>
        </tfoot>
    </table>

</body>
</html>
