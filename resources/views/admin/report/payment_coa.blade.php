{{-- resources/views/report/coa_trans.blade.php --}}
@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header">
        <h5 class="mb-3">Laporan Pembayaran per COA</h5>

        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1" for="filterCoa">COA</label>
                <select id="filterCoa" class="form-select form-select-sm">
                    @foreach ($coaOptions as $opt)
                        <option value="{{ $opt->code }}">{{ $opt->code }} - {{ $opt->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1" for="filterDateFrom" id="lblDateFrom">Dari tanggal</label>
                <input type="date" id="filterDateFrom" class="form-control form-control-sm" value="{{ $today }}">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1" for="filterDateTo" id="lblDateTo">Sampai tanggal</label>
                <input type="date" id="filterDateTo" class="form-control form-control-sm" value="{{ $today }}">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1" for="filterStatus">Tampilkan</label>
                <select id="filterStatus" class="form-select form-select-sm">
                    <option value="all" selected>Semua status</option>
                    <option value="closing">Closing (dp masuk)</option>
                    <option value="pelunasan">Baru pelunasan</option>
                    <option value="segera">Harus segera lunas</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" id="btnApplyFilter" class="btn btn-primary btn-sm w-100">Tampilkan</button>
            </div>
        </div>

        <div class="form-text text-muted small mt-1" id="hintAll" style="display:none;">
            Closing &amp; pelunasan memakai tanggal bayar; "Harus segera lunas" memakai tanggal jatuh tempo.
        </div>

        <div class="form-check mt-2" id="wrapOverdue" style="display:none;">
            <input class="form-check-input" type="checkbox" id="filterOverdue">
            <label class="form-check-label small" for="filterOverdue">
                Termasuk yang sudah lewat jatuh tempo (semua jatuh tempo s/d tanggal "sampai")
            </label>
        </div>

        <div class="mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="today">Hari ini</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="yesterday">Kemarin</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="tomorrow">Besok</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="7days">7 hari ke belakang</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="next7days">7 hari ke depan</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="month">Bulan ini</button>
        </div>
    </div>

    <div class="card-body pb-0">
        {{-- Pill: tampilkan semua status --}}
        <div class="mb-2">
            <button type="button" class="btn btn-sm btn-outline-primary summary-pill" data-status="all">
                Semua status <span class="badge bg-primary ms-1" id="sumAllCount">-</span>
            </button>
        </div>

        <div class="row" id="summaryCards">
            <div class="col-12 col-md-6 col-xl-3 mb-2">
                <div class="border rounded p-3 h-100 summary-card" role="button" tabindex="0" data-status="closing">
                    <div class="text-muted small">Closing (dp masuk)</div>
                    <div class="h4 mb-0"><span id="sumClosingCount">-</span> <small class="text-muted fs-6">pembayaran</small></div>
                    <div class="small">Uang masuk: Rp <span id="sumClosingCash">-</span></div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3 mb-2">
                <div class="border rounded p-3 h-100 summary-card" role="button" tabindex="0" data-status="pelunasan">
                    <div class="text-muted small">Baru pelunasan</div>
                    <div class="h4 mb-0"><span id="sumPelunasanCount">-</span> <small class="text-muted fs-6">pembayaran</small></div>
                    <div class="small">Uang masuk: Rp <span id="sumPelunasanCash">-</span></div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3 mb-2">
                <div class="border rounded p-3 h-100 summary-card" role="button" tabindex="0" data-status="segera">
                    <div class="text-muted small">Harus segera lunas</div>
                    <div class="h4 mb-0"><span id="sumSegeraCount">-</span> <small class="text-muted fs-6">invoice</small></div>
                    <div class="small">Sisa tagihan: Rp <span id="sumSegeraSisa">-</span></div>
                </div>
            </div>
            {{-- Laba bersih per COA --}}
            <div class="col-12 col-md-6 col-xl-3 mb-2">
                <div class="border rounded p-3 h-100" id="cardLaba">
                    <div class="text-muted small">
                        Laba bersih
                        <span class="d-block text-truncate" id="lblLabaCoa" style="max-width:100%;"></span>
                    </div>
                    <div class="h4 mb-0">Rp <span id="sumLaba">-</span> <small class="text-muted fs-6" id="sumLabaUnit"></small></div>
                    <div class="small text-muted">
                        Pendapatan Rp <span id="sumLabaPend">-</span> &minus; HPP Rp <span id="sumLabaHpp">-</span>
                    </div>
                    <div class="small text-muted mt-1" id="labaScope"></div>
                    <div class="btn-group btn-group-sm mt-2" role="group" id="labaMode" aria-label="Mode laba bersih">
                        <button type="button" class="btn btn-outline-secondary" data-mode="orang">Per Orang</button>
                        <button type="button" class="btn btn-outline-secondary active" data-mode="minggu">Per Minggu</button>
                        <button type="button" class="btn btn-outline-secondary" data-mode="bulan">Per Bulan</button>
                    </div>
                    <div class="small mt-2" id="labaBreakdown"></div>
                </div>
            </div>
        </div>
        <div class="small text-muted">
            Uang masuk tidak menghitung pembayaran lewat overpay (apply credit), karena uangnya sudah tercatat saat diterima.
        </div>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    {{-- Tabel semua status (closing + pelunasan + segera lunas) --}}
    <div class="table-responsive p-3" id="wrapTableAll">
        <table class="table table-striped table-bordered mt-0" id="tableCoaAll">
            <thead class="table-dark">
                <tr>
                    <th width="4%">No</th>
                    <th>Tanggal</th>
                    <th>Invoice</th>
                    <th>Kontak</th>
                    <th>Status</th>
                    <th class="text-end">Total invoice</th>
                    <th class="text-end">Dibayar / terbayar</th>
                    <th class="text-end">Nilai COA</th>
                    <th class="text-end">Sisa</th>
                    <th>Catatan</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
        </table>
    </div>

    {{-- Tabel pembayaran: closing (dp masuk) & baru pelunasan --}}
    <div class="table-responsive p-3" id="wrapTablePay" style="display:none;">
        <table class="table table-striped table-bordered mt-0" id="tableCoaTrans">
            <thead class="table-dark">
                <tr>
                    <th width="4%">No</th>
                    <th>Tanggal bayar</th>
                    <th>Invoice</th>
                    <th>Kontak</th>
                    <th>Jenis</th>
                    <th class="text-end">Dibayar</th>
                    <th class="text-end">Dibayar (Rp)</th>
                    <th class="text-end">Total invoice</th>
                    <th class="text-end">Nilai COA</th>
                    <th class="text-end">Terbayar s/d ini</th>
                    <th class="text-end">Sisa</th>
                    <th>Catatan</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
        </table>
    </div>

    {{-- Tabel per invoice: harus segera lunas --}}
    <div class="table-responsive p-3" id="wrapTableDue" style="display:none;">
        <table class="table table-striped table-bordered mt-0" id="tableCoaDue">
            <thead class="table-dark">
                <tr>
                    <th width="4%">No</th>
                    <th>Invoice</th>
                    <th>Kontak</th>
                    <th>Tgl invoice</th>
                    <th>Jatuh tempo</th>
                    <th class="text-end">Total invoice</th>
                    <th class="text-end">Terbayar</th>
                    <th class="text-end">Sisa</th>
                    <th class="text-end">Nilai COA</th>
                    <th>Catatan</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var table;
    var labaData = null;      // laba_bersih dari summary (null = belum tersedia)
    var labaMode = 'minggu';  // orang | minggu | bulan

    const KIND = {
        dp:             ['DP', 'bg-info'],
        lunas_langsung: ['Closing-lunas', 'bg-success'],
        pelunasan:      ['Pelunasan', 'bg-success'],
        cicilan:        ['Cicilan', 'bg-secondary']
    };

    // tx_status dari getCoaTrans (dipakai pada tabel "Semua status")
    const TX = {
        closing:   ['Closing',     'bg-info'],
        pelunasan: ['Pelunasan',   'bg-success'],
        segera:    ['Harus lunas', 'bg-danger']
    };

    function esc(v) { return $('<div>').text(v == null ? '' : v).html(); }

    function fmtNum(n) {
        n = parseFloat(n) || 0;
        return n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    // angka dengan tanda minus di depan (untuk laba yang bisa negatif)
    function fmtSigned(n) {
        n = parseFloat(n) || 0;
        return (n < 0 ? '-' : '') + fmtNum(Math.abs(n));
    }

    function fmtDate(v) {
        if (!v) return '-';
        let p = String(v).substring(0, 10).split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : v;
    }

    function ymd(d) {
        const z = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate());
    }

    function curPrefix(row) {
        return row.code_curr && row.code_curr !== 'IDR' ? esc(row.code_curr) + ' ' : '';
    }

    function filters() {
        let f = {
            coa_code:  $('#filterCoa').val(),
            date_from: $('#filterDateFrom').val(),
            date_to:   $('#filterDateTo').val(),
            status:    $('#filterStatus').val()
        };
        if (f.status === 'segera' || f.status === 'all') {
            f.include_overdue = $('#filterOverdue').is(':checked') ? 1 : 0;
        }
        return f;
    }

    const colHubungi = {
        data: 'wa_number',
        orderable: false,
        searchable: false,
        defaultContent: '',
        className: "text-center",
        render: function(d) {
            if (!d) return '';
            return `<a href="https://wa.me/${encodeURIComponent(d)}" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="ti ti-brand-whatsapp"></i> Hubungi</a>`;
        }
    };

    // --- 1. DATATABLE ---
    let columnPay = [
        {
            data: null, className: "text-center", orderable: false,
            render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; },
        },
        { data: 'date_transaction', name: 'date_transaction', render: function(d) { return fmtDate(d); } },
        { data: 'invoice_number',   name: 'invoice_number',   render: function(d) { return esc(d); } },
        { data: 'contact_name',     name: 'contact_name',     render: function(d) { return esc(d); } },
        {
            data: 'kind', name: 'kind',
            render: function(d) {
                let k = KIND[d] || [d, 'bg-secondary'];
                return `<span class="badge ${k[1]}">${esc(k[0])}</span>`;
            }
        },
        { data: 'amount',        className: "text-end", render: function(d, t, row) { return curPrefix(row) + fmtNum(d); } },
        { data: 'amount_base',   className: "text-end", render: function(d) { return fmtNum(d); } },
        { data: 'invoice_total', className: "text-end", render: function(d) { return fmtNum(d); } },
        { data: 'coa_amount',    className: "text-end", render: function(d) { return fmtNum(d); } },
        { data: 'paid_after',    className: "text-end", render: function(d) { return fmtNum(d); } },
        { data: 'sisa',          className: "text-end", render: function(d) { return parseFloat(d) > 0 ? fmtNum(d) : '-'; } },
        {
            data: 'reference_detail', orderable: false,
            render: function(d, t, row) {
                 console.log('pay',row)
                let out = '';
                if (row.via_overpay) out += '<span class="badge bg-warning text-dark me-1">Via overpay</span>';
                if (row.mixed)       out += '<span class="badge bg-light text-dark border me-1">Ada COA lain</span>';
                return out + esc(d);
            }
        },
        colHubungi
    ];

    let columnDue = [
        {
            data: null, className: "text-center", orderable: false,
            render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; },
        },
        { data: 'invoice_number', name: 'invoice_number', render: function(d) { return esc(d); } },
        { data: 'contact_name',   name: 'contact_name',   render: function(d) { return esc(d); } },
        { data: 'issue_date',     name: 'issue_date',     render: function(d) { return fmtDate(d); } },
        { data: 'due_date',       name: 'due_date',       render: function(d) { return fmtDate(d); } },
        { data: 'invoice_total',  className: "text-end", render: function(d, t, row) { return curPrefix(row) + fmtNum(d); } },
        { data: 'paid_total',     className: "text-end", render: function(d, t, row) { return curPrefix(row) + fmtNum(d); } },
        { data: 'sisa',           className: "text-end", render: function(d, t, row) { return curPrefix(row) + fmtNum(d); } },
        { data: 'coa_amount',     className: "text-end", render: function(d) { return fmtNum(d); } },
        {
            data: 'overdue_days', orderable: false,
            render: function(d, t, row) {
                console.log('due',row)
                let out = '';
                if (parseInt(d) > 0) out += `<span class="badge bg-danger me-1" style="color:white !important;">Lewat ${parseInt(d)} hari</span>`;
                if (row.mixed)       out += '<span class="badge bg-light text-dark border me-1">Ada COA lain</span>';
                return out;
            }
        },
        colHubungi
    ];

    // Gabungan closing + pelunasan + segera lunas (baris dibedakan oleh tx_status)
    let columnAll = [
        {
            data: null, className: "text-center", orderable: false,
            render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; },
        },
        {
            data: null, orderable: false,
            render: function(d, t, row) {
                if (row.tx_status === 'segera') {
                    return fmtDate(row.due_date) + '<div class="small text-muted">Jatuh tempo</div>';
                }
                return fmtDate(row.date_transaction) + '<div class="small text-muted">Tgl bayar</div>';
            }
        },
        { data: 'invoice_number', name: 'invoice_number', render: function(d) { return esc(d); } },
        { data: 'contact_name',   name: 'contact_name',   render: function(d) { return esc(d); } },
        {
            data: 'tx_status', orderable: false,
            render: function(d, t, row) {
                let s = TX[d] || [d, 'bg-secondary'];
                let out = `<span class="badge ${s[1]}" style="color:white !important;">${esc(s[0])}</span>`;
                if (row.kind && KIND[row.kind]) {
                    out += ` <span class="badge ${KIND[row.kind][1]}">${esc(KIND[row.kind][0])}</span>`;
                }
                return out;
            }
        },
        {
            data: 'invoice_total', className: "text-end", orderable: false,
            render: function(d, t, row) {
                return (row.tx_status === 'segera' ? curPrefix(row) : '') + fmtNum(d);
            }
        },
        {
            data: null, className: "text-end", orderable: false,
            render: function(d, t, row) {
                return row.tx_status === 'segera'
                    ? curPrefix(row) + fmtNum(row.paid_total)
                    : fmtNum(row.amount_base);
            }
        },
        { data: 'coa_amount', className: "text-end", orderable: false, render: function(d) { return fmtNum(d); } },
        {
            data: 'sisa', className: "text-end", orderable: false,
            render: function(d, t, row) {
                if (!(parseFloat(d) > 0)) return '-';
                return (row.tx_status === 'segera' ? curPrefix(row) : '') + fmtNum(d);
            }
        },
        {
            data: null, orderable: false,
            render: function(d, t, row) {
                let out = '';
                if (row.tx_status === 'segera' && parseInt(row.overdue_days) > 0) {
                    out += `<span class="badge bg-danger me-1" style="color:white !important;">Lewat ${parseInt(row.overdue_days)} hari</span>`;
                }
                if (row.via_overpay) out += '<span class="badge bg-warning text-dark me-1">Via overpay</span>';
                if (row.mixed)       out += '<span class="badge bg-light text-dark border me-1">Ada COA lain</span>';
                return out + esc(row.reference_detail);
            }
        },
        colHubungi
    ];

    function loadTable() {
        let f = filters();
        let mode = f.status === 'segera' ? 'due' : (f.status === 'all' ? 'all' : 'pay');

        $('#wrapTableAll').toggle(mode === 'all');
        $('#wrapTablePay').toggle(mode === 'pay');
        $('#wrapTableDue').toggle(mode === 'due');

        const T = {
            all: ['#tableCoaAll',   columnAll],
            pay: ['#tableCoaTrans', columnPay],
            due: ['#tableCoaDue',   columnDue]
        }[mode];

        table = initGlobalDataTableTokenSelected(
            T[0],
            `{{ route('get-coa-trans') }}`,
            T[1],
            Object.assign({ 'kolom_name': 'invoice_number' }, f)
        );
    }

    // --- 2. RINGKASAN ---
    function fmtShort(ymd) {
        return new Date(ymd + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    }

    function fmtPeriode(mode, r) {
        if (mode === 'bulan') {
            let p = String(r.periode).split('-');
            return new Date(p[0], p[1] - 1, 1).toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
        }
        let m = String(r.periode).match(/^(\d{4})-W(\d{2})$/);
        return (m ? 'Minggu ' + parseInt(m[2], 10) + ' · ' : '') + fmtShort(r.awal) + ' - ' + fmtShort(r.akhir);
    }

    function renderLaba() {
        $('#lblLabaCoa').text($('#filterCoa option:selected').text());
        $('#labaMode button').removeClass('active').filter('[data-mode="' + labaMode + '"]').addClass('active');

        let $val = $('#sumLaba'), $bd = $('#labaBreakdown');
        $bd.empty();
        $('#labaScope').text('');
        $('#sumLabaUnit').text('');

        if (!labaData) {
            $val.text('-').removeClass('text-success text-danger');
            $('#sumLabaPend, #sumLabaHpp').text('-');
            $bd.html('<span class="text-muted">Data laba belum tersedia.</span>');
            return;
        }

        if (labaData.mapping_ok === false) {
            $val.text('-').removeClass('text-success text-danger');
            $('#sumLabaPend, #sumLabaHpp').text('-');
            $bd.html('<span class="text-warning">' + esc(labaData.pesan) + '</span>');
            return;
        }

        let main = parseFloat(labaMode === 'orang' ? labaData.per_orang : labaData.laba) || 0;
        $val.text(fmtSigned(main)).toggleClass('text-success', main >= 0).toggleClass('text-danger', main < 0);
        $('#sumLabaUnit').text(labaMode === 'orang' ? '/ orang (' + (parseInt(labaData.pax) || 0) + ' orang)' : '');
        $('#sumLabaPend').text(fmtNum(labaData.pendapatan));
        $('#sumLabaHpp').text(fmtNum(labaData.hpp));
        $('#labaScope').text(labaData.scope_label || '');

        let html = '';

        if (labaMode === 'minggu' || labaMode === 'bulan') {
            let list = (labaMode === 'minggu' ? labaData.per_minggu : labaData.per_bulan) || [];
            if (!list.length) {
                $bd.html('<span class="text-muted">Tidak ada transaksi pada periode ini.</span>');
                return;
            }
            html = '<div style="max-height:170px;overflow:auto;"><table class="table table-sm mb-0"><tbody>';
            list.forEach(function(r) {
                let l = parseFloat(r.laba) || 0;
                html += `<tr><td>${esc(fmtPeriode(labaMode, r))}` +
                        `<div class="text-muted" style="font-size:.75em;">${parseInt(r.pax) || 0} orang</div></td>` +
                        `<td class="text-end ${l < 0 ? 'text-danger' : 'text-success'}">${fmtSigned(l)}</td></tr>`;
            });
        } else {
            // per orang: rincian per COA (pendapatan +, HPP -)
            let list = labaData.per_coa || [];
            if (!list.length) {
                $bd.html('<span class="text-muted">Tidak ada transaksi pada periode ini.</span>');
                return;
            }
            html = '<div style="max-height:170px;overflow:auto;"><table class="table table-sm mb-0"><tbody>';
            list.forEach(function(r) {
                let a = parseFloat(r.amount) || 0;
                html += `<tr class="${r.selected ? 'fw-bold' : ''}">` +
                        `<td>${esc(r.name)}<div class="text-muted" style="font-size:.75em;">${esc(r.type)}</div></td>` +
                        `<td class="text-end ${a < 0 ? 'text-danger' : 'text-success'}">${fmtSigned(a)}</td></tr>`;
            });
        }

        $bd.html(html + '</tbody></table></div>');
    }

    function loadSummary(withLaba) {
        if (withLaba === undefined) withLaba = true;

        let params = filters();
        if (withLaba) params.laba_bersih = 1;

        $.ajax({
            url: `{{ route('get-coa-trans-summary') }}`,
            method: 'GET',
            data: params,
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('token')
            },
            success: function(res) {
                let s = res.data || {};
                $('#sumAllCount').text(s.all_count ?? 0);
                $('#sumClosingCount').text(s.closing_count ?? 0);
                $('#sumClosingCash').text(fmtNum(s.closing_cash));
                $('#sumPelunasanCount').text(s.pelunasan_count ?? 0);
                $('#sumPelunasanCash').text(fmtNum(s.pelunasan_cash));
                $('#sumSegeraCount').text(s.segera_count ?? 0);
                $('#sumSegeraSisa').text(fmtNum(s.segera_sisa));

                labaData = s.laba_bersih || null;
                renderLaba();
            },
            error: function() {
                if (withLaba) {
                    // endpoint laba belum siap / gagal -> tampilkan ringkasan lain tanpa laba
                    console.warn('Ringkasan dengan laba gagal, mencoba ulang tanpa laba_bersih');
                    labaData = null;
                    renderLaba();
                    loadSummary(false);
                    return;
                }
                $('#summaryCards [id^=sum]').text('-');
                $('#sumAllCount').text('-');
            }
        });
    }

    function syncUi() {
        let st = $('#filterStatus').val();
        let isDue = st === 'segera';
        $('#wrapOverdue').toggle(isDue || st === 'all');
        $('#hintAll').toggle(st === 'all');
        $('#lblDateFrom').text(isDue ? 'Jatuh tempo dari' : 'Dari tanggal');
        $('#lblDateTo').text(isDue ? 'Jatuh tempo sampai' : 'Sampai tanggal');
        $('.summary-card').removeClass('border-primary bg-light');
        $('.summary-card[data-status="' + st + '"]').addClass('border-primary bg-light');
        $('.summary-pill').toggleClass('active', st === 'all');
    }

    function applyFilter() {
        syncUi();
        loadSummary();
        loadTable();
    }

    // --- 3. EVENT ---
    $('#btnApplyFilter').on('click', applyFilter);
    $('#filterCoa, #filterStatus, #filterOverdue').on('change', applyFilter);

    $('.summary-card').on('click keypress', function(e) {
        if (e.type === 'keypress' && e.which !== 13) return;
        $('#filterStatus').val($(this).data('status'));
        applyFilter();
    });

    $('.summary-pill').on('click', function() {
        $('#filterStatus').val($(this).data('status'));
        applyFilter();
    });

    $('#labaMode').on('click', 'button', function() {
        labaMode = $(this).data('mode');
        renderLaba();
    });

    $('.btn-quick').on('click', function() {
        let now = new Date(), from = new Date(), to = new Date();
        switch ($(this).data('range')) {
            case 'yesterday': from.setDate(now.getDate() - 1); to = new Date(from); break;
            case 'tomorrow':  from.setDate(now.getDate() + 1); to = new Date(from); break;
            case '7days':     from.setDate(now.getDate() - 6); break;
            case 'next7days': to.setDate(now.getDate() + 7); break;
            case 'month':     from = new Date(now.getFullYear(), now.getMonth(), 1); break;
        }
        $('#filterDateFrom').val(ymd(from));
        $('#filterDateTo').val(ymd(to));
        applyFilter();
    });

    applyFilter();
});
</script>
@endpush