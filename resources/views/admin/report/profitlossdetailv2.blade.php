@extends('layouts.app')

@section('content')
<style>
.detail-back { font-size: 13px; color: #0070c4; text-decoration: none; }
.detail-back:hover { text-decoration: underline; }
.detail-header { margin: 14px 0 20px; }
.detail-header h2 { font-size: 20px; margin: 0 0 4px; }
.detail-header .period { font-size: 12px; color: #666; }
.detail-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.detail-table th {
    text-align: left; background: #f5f5f5; padding: 8px 10px;
    border-bottom: 1px solid #d8d8d8; font-weight: 600;
}
.detail-table td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
.detail-table td.amt { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.detail-table td.amt.negative { color: #c0392b; }
.detail-table td.amt.positive { color: #1a6abf; }
.detail-total td { font-weight: 600; border-top: 2px solid #333; background: #fafafa; }
#detailLoading { padding: 30px; text-align: center; color: #888; font-size: 13px; }
</style>

<a href="{{ url()->previous() }}" class="detail-back">&larr; Kembali ke Profit and Loss</a>

<div class="detail-header">
    <h2 id="detailCoaName">&nbsp;</h2>
    <div class="period" id="detailPeriod">&nbsp;</div>
</div>

<div id="detailLoading">Memuat data...</div>

<table class="detail-table" id="detailTable" style="display:none;">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>Referensi</th>
            <th>Keterangan</th>
            <th style="text-align:right;">Nominal</th>
        </tr>
    </thead>
    <tbody id="detailBody"></tbody>
    <tfoot>
        <tr class="detail-total">
            <td colspan="3">Total</td>
            <td class="amt" id="detailTotal"></td>
        </tr>
    </tfoot>
</table>
@endsection

@push('scripts')
<script>
$(function () {
    var account   = "{{ $account }}";
    var dateStart = "{{ $dateStart }}";
    var dateEnd   = "{{ $dateEnd }}";

    var qsParams    = new URLSearchParams(window.location.search);
    var trackPaket  = qsParams.get('track_paket')  || '';
    var trackDivisi = qsParams.get('track_divisi') || '';

    var fmt = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function formatAmt(val) {
        val = parseFloat(val) || 0;
        if (val < 0) return '(' + fmt.format(Math.abs(val)) + ')';
        return fmt.format(val);
    }
    function amtClass(val) {
        return (parseFloat(val) || 0) < 0 ? 'amt negative' : 'amt positive';
    }
    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }

    var apiUrl = "{{ route('api-profit-loss-detail', ['account' => '__ACCOUNT__', 'date_start' => '__DATE_START__', 'date_end' => '__DATE_END__']) }}"
        .replace('__ACCOUNT__', account)
        .replace('__DATE_START__', dateStart)
        .replace('__DATE_END__', dateEnd);

    var qs = [];
    if (trackPaket)  qs.push('track_paket=' + encodeURIComponent(trackPaket));
    if (trackDivisi) qs.push('track_divisi=' + encodeURIComponent(trackDivisi));
    if (qs.length) apiUrl += '?' + qs.join('&');

    ajaxRequest(apiUrl, 'GET', {}, localStorage.getItem('token'))
        .then(function (response) {
            if (!response.status || !response.data) {
                throw new Error(response.message || 'Gagal memuat data');
            }
            renderDetail(response.data.data);
        })
        .catch(function (err) {
            cathError(err);
        })
        .finally(function () {
            $('#detailLoading').hide();
        });

    function renderDetail(data) {
        $('#detailCoaName').text(data.coa.name);
        $('#detailPeriod').text(
            moment(data.period.date_start).format('D MMM YYYY') + ' – ' +
            moment(data.period.date_end).format('D MMM YYYY')
        );

        var $body = $('#detailBody');
        $body.empty();

        if (!data.transactions.length) {
            $body.append('<tr><td colspan="4">Tidak ada transaksi pada periode ini.</td></tr>');
        }

        $.each(data.transactions, function (i, t) {
            $body.append(
                '<tr>' +
                '<td>' + moment(t.date).format('D MMM YYYY') + '</td>' +
                '<td>' + escHtml(t.reference) + '</td>' +
                '<td>' + escHtml(t.description) + '</td>' +
                '<td class="' + amtClass(t.nominal) + '">' + formatAmt(t.nominal) + '</td>' +
                '</tr>'
            );
        });

        $('#detailTotal').attr('class', 'amt').text(formatAmt(data.total));
        $('#detailTable').show();
    }
});
</script>
@endpush