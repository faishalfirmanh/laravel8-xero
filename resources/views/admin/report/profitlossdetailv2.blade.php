@extends('layouts.app')

@section('content')
<style>
.detail-back { font-size: 13px; color: #0070c4; text-decoration: none; }
.detail-back:hover { text-decoration: underline; }
.detail-header { margin: 14px 0 20px; }
.detail-header h2 { font-size: 20px; margin: 0 0 4px; }
.detail-header .period { font-size: 12px; color: #666; }
#filterTrack { font-size: 12px; color: #333; margin-top: 8px; }
#filterTrack:empty { display: none; }

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
.detail-table tbody tr:hover { background-color: #eef6fc; transition: background-color .2s ease; }
#detailLoading { padding: 30px; text-align: center; color: #888; font-size: 13px; }

/* Tabel bisa digeser horizontal di layar kecil */
.detail-table-wrap { width: 100%; overflow-x: auto; }
</style>

<a href="{{ url()->previous() }}" class="detail-back">&larr; Kembali ke Profit and Loss</a>

<div class="detail-header">
    <h2 id="detailCoaName">&nbsp;</h2>
    <div class="period" id="detailPeriod">&nbsp;</div>
    <div id="filterTrack"></div>
</div>

<div id="detailLoading">Memuat data...</div>

<div class="detail-table-wrap">
    <table class="detail-table" id="detailTable" style="display:none;">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Referensi</th>
                <th>Sumber</th>
                <th>Keterangan</th>
                <th style="text-align:right;">Nominal</th>
            </tr>
        </thead>
        <tbody id="detailBody"></tbody>
        <tfoot>
            <tr class="detail-total">
                <td colspan="4">Total</td>
                <td class="amt" id="detailTotal"></td>
            </tr>
        </tfoot>
    </table>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    var account   = @json($account);
    var dateStart = @json($dateStart);
    var dateEnd   = @json($dateEnd);

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
    function showError(err) {
        if (typeof cathError === 'function') cathError(err);
        else console.error('Error loading data:', err);
    }

    // URL API (route detail-combine)
    var apiUrl = "{{ route('detail-combine', ['account' => '__ACCOUNT__', 'date_start' => '__DATE_START__', 'date_end' => '__DATE_END__']) }}"
        .replace('__ACCOUNT__',    encodeURIComponent(account))
        .replace('__DATE_START__', encodeURIComponent(dateStart))
        .replace('__DATE_END__',   encodeURIComponent(dateEnd));

    // Filter diambil dari query string halaman ini (dikirim oleh link di laporan),
    // lalu diteruskan ke API sebagai parameter berindeks.
    function getFilterParams() {
        var qs     = new URLSearchParams(window.location.search);
        var params = {};

        $.each(['nama_paket', 'tracking_divisi', 'tracking_paket_name'], function (_, key) {
            $.each(qs.getAll(key + '[]'), function (i, val) {
                params[key + '[' + i + ']'] = val;
            });
        });

        return params;
    }

    // Badge filter aktif di bawah judul
    function badgeGroup(label, names, cls) {
        if (!names || !names.length) return '';
        var h = '<span class="mr-3"><strong>' + label + ':</strong> ';
        $.each(names, function (i, n) {
            h += '<span class="badge ' + cls + ' mr-1 mb-1">' + escHtml(n) + '</span>';
        });
        return h + '</span>';
    }

    function renderFilterBadges(f) {
        f = f || {};
        $('#filterTrack').html(
            badgeGroup('Nama Paket (item)',
                $.map(f.nama_paket || [], function (o) { return o.nama_paket; }), 'badge-info') +
            badgeGroup('Divisi',
                $.map(f.tracking_divisi || [], function (o) { return o.item_name_category; }), 'badge-success') +
            badgeGroup('Nama Paket (tracking)',
                $.map(f.tracking_paket_name || [], function (o) { return o.item_name_category; }), 'badge-warning')
        );
    }

    function renderDetail(data) {
        $('#detailCoaName').text(data.coa.name);
        $('#detailPeriod').text(
            moment(data.period.date_start).format('D MMM YYYY') + ' – ' +
            moment(data.period.date_end).format('D MMM YYYY')
        );

        renderFilterBadges(data.filters);

        var $body = $('#detailBody');
        $body.empty();

        if (!data.transactions.length) {
            $body.append('<tr><td colspan="5" style="text-align:center; padding:15px;">Tidak ada transaksi pada periode ini.</td></tr>');
        }

        $.each(data.transactions, function (i, t) {
            $body.append(
                '<tr>' +
                '<td>' + moment(t.date).format('D MMM YYYY') + '</td>' +
                '<td>' + escHtml(t.reference) + '</td>' +
                '<td>' + escHtml(t.source) + '</td>' +
                '<td>' + escHtml(t.description) + '</td>' +
                '<td class="' + amtClass(t.nominal) + '">' + formatAmt(t.nominal) + '</td>' +
                '</tr>'
            );
        });

        $('#detailTotal').attr('class', 'amt').text(formatAmt(data.total));
        $('#detailTable').show();
    }

    ajaxRequest(apiUrl, 'GET', getFilterParams(), localStorage.getItem('token'))
        .then(function (response) {
            if (!response.status || !response.data) {
                throw new Error(response.message || 'Gagal memuat data');
            }
            renderDetail(response.data.data);
        })
        .catch(showError)
        .finally(function () {
            $('#detailLoading').hide();
        });
});
</script>
@endpush