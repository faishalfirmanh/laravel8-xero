{{-- resources/views/report/coa_paket_payment.blade.php --}}
@extends('layouts.app')

@section('content')

{{-- CSS select2: hapus dua baris <link> ini kalau layout / @push('styles') Anda sudah memuatnya --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">

<style>
    .report-filter label { display: block; margin-bottom: .25rem; font-size: .85rem; font-weight: 600; }
    .report-filter .select2-container { width: 100% !important; }
    .report-filter .btn-quick { margin: 0 .25rem .25rem 0; }
    .summary-card { cursor: pointer; }
</style>

<div class="card shadow mb-5">
    <div class="card-header report-filter">
        <h5 class="mb-3">Laporan Pembayaran per Paket / Tracking</h5>

        <div class="row">
            <div class="col-12 col-md-3 mb-3">
                <label for="filterCoa">COA <span class="text-danger">jangan lupa input account masing-masing travel</span></label>
                <select id="filterCoa" class="form-control">
                    @foreach ($coaOptions as $opt)
                        <option value="{{ $opt->code }}">{{ $opt->code }} - {{ $opt->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-5 mb-3">
                <label for="nama_paket">Nama item paket</label>
                <select id="nama_paket" class="form-control"><option></option></select>
            </div>
            <div class="col-12 col-md-4 mb-3">
                <label for="tracking_paket_name">Tracking nama paket</label>
                <select id="tracking_paket_name" disabled class="form-control"><option></option></select>
            </div>
        </div>
        <div class="small text-muted mb-3">
            Isi salah satu (atau keduanya). Jika keduanya diisi, hasilnya digabung (item ATAU tracking).
        </div>

        <div class="row align-items-end">
            <div class="col-6 col-md-2 mb-3">
                <label for="filterDateFrom" id="lblDateFrom">Dari tanggal</label>
                <input type="date" id="filterDateFrom" class="form-control" value="{{ $today }}">
            </div>
            <div class="col-6 col-md-2 mb-3">
                <label for="filterDateTo" id="lblDateTo">Sampai tanggal</label>
                <input type="date" id="filterDateTo" class="form-control" value="{{ $today }}">
            </div>
            <div class="col-12 col-md-3 mb-3">
                <label for="filterStatus">Tampilkan</label>
                <select id="filterStatus" class="form-control">
                    <option value="closing">Closing (dp masuk)</option>
                    <option value="pelunasan">Baru pelunasan</option>
                    <option value="segera">Harus segera lunas</option>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-3">
                <label class="d-none d-md-block">&nbsp;</label>
                <button type="button" id="btnApplyFilter" class="btn btn-primary w-100">Tampilkan</button>
            </div>
        </div>

        <div class="form-check mb-2" id="wrapOverdue" style="display:none;">
            <input class="form-check-input" type="checkbox" id="filterOverdue">
            <label class="form-check-label" for="filterOverdue" style="display:inline; font-weight:400;">
                Termasuk yang sudah lewat jatuh tempo (semua jatuh tempo s/d tanggal "sampai")
            </label>
        </div>

        <div>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="today">Hari ini</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="yesterday">Kemarin</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="tomorrow">Besok</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="7days">7 hari ke belakang</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="next7days">7 hari ke depan</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-quick" data-range="month">Bulan ini</button>
        </div>

        <div id="filterHint" class="alert alert-warning py-2 mt-3 mb-0" style="display:none;"></div>
    </div>

    <div class="card-body pb-0">
        <div class="row" id="summaryCards">
            <div class="col-12 col-md-4 mb-2">
                <div class="border rounded p-3 summary-card" role="button" tabindex="0" data-status="closing">
                    <div class="text-muted small">Closing (dp masuk)</div>
                    <div class="h4 mb-0"><span id="sumClosingCount">-</span> <small class="text-muted">pembayaran</small></div>
                    <div class="small">Uang masuk: Rp <span id="sumClosingCash">-</span></div>
                </div>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <div class="border rounded p-3 summary-card" role="button" tabindex="0" data-status="pelunasan">
                    <div class="text-muted small">Baru pelunasan</div>
                    <div class="h4 mb-0"><span id="sumPelunasanCount">-</span> <small class="text-muted">pembayaran</small></div>
                    <div class="small">Uang masuk: Rp <span id="sumPelunasanCash">-</span></div>
                </div>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <div class="border rounded p-3 summary-card" role="button" tabindex="0" data-status="segera">
                    <div class="text-muted small">Harus segera lunas</div>
                    <div class="h4 mb-0"><span id="sumSegeraCount">-</span> <small class="text-muted">invoice</small></div>
                    <div class="small">Sisa tagihan: Rp <span id="sumSegeraSisa">-</span></div>
                </div>
            </div>
        </div>
        <div class="small text-muted">
            Uang masuk tidak menghitung pembayaran lewat overpay (apply credit). Pembayaran lama (belum punya item) dihitung penuh per pembayaran,
            jadi untuk invoice dengan beberapa item angkanya bisa lebih besar dari nilai paket.
        </div>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    {{-- Tabel pembayaran: closing (dp masuk) & baru pelunasan --}}
    <div class="table-responsive p-3" id="wrapTablePay" style="display:none;">
        <table class="table table-striped table-bordered mt-0" id="tablePaketPay">
            <thead class="table-dark">
                <tr>
                    <th width="4%">No</th>
                    <th>Tanggal bayar</th>
                    <th>Invoice</th>
                    <th>Kontak</th>
                    <th>Jenis</th>
                    <th>Untuk item</th>
                    <th class="text-right">Dibayar</th>
                    <th class="text-right">Dibayar (Rp)</th>
                    <th class="text-right">Total invoice</th>
                    <th class="text-right">Nilai paket</th>
                    <th class="text-right">Terbayar s/d ini</th>
                    <th class="text-right">Sisa</th>
                    <th>Catatan</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
        </table>
    </div>

    {{-- Tabel per invoice: harus segera lunas --}}
    <div class="table-responsive p-3" id="wrapTableDue" style="display:none;">
        <table class="table table-striped table-bordered mt-0" id="tablePaketDue">
            <thead class="table-dark">
                <tr>
                    <th width="4%">No</th>
                    <th>Invoice</th>
                    <th>Kontak</th>
                    <th>Tgl invoice</th>
                    <th>Jatuh tempo</th>
                    <th class="text-right">Total invoice</th>
                    <th class="text-right">Terbayar</th>
                    <th class="text-right">Sisa</th>
                    <th class="text-right">Nilai paket</th>
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

    const KIND = {
        dp:             ['DP', 'badge-info'],
        lunas_langsung: ['Closing-lunas', 'badge-success'],
        pelunasan:      ['Pelunasan', 'badge-success'],
        cicilan:        ['Cicilan', 'badge-secondary']
    };

    function esc(v) { return $('<div>').text(v == null ? '' : v).html(); }

    function fmtNum(n) {
        n = parseFloat(n) || 0;
        return n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
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

    // --- SELECT2 (semua single select) ---
    function initCoaSelect() {
        $('#filterCoa').select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: $('body'),
            minimumResultsForSearch: Infinity   // hanya 4 pilihan, kotak cari tidak perlu
        });
    }

    function initNamaPaketSelect2() {
        var $el = $('#nama_paket');

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        var limit = 10;

        $el.select2({
            theme: 'bootstrap4',
            placeholder: 'Pilih nama item paket',
            allowClear: true,
            width: '100%',
            dropdownParent: $('body'),
            language: {
                searching: function () { return 'Mencari...'; },
                noResults: function () { return 'Paket tidak ditemukan'; },
                loadingMore: function () { return 'Memuat lainnya...'; }
            },
            ajax: {
                url: '{{ route("list-paket-select2") }}',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        page: params.page || 1,
                        keyword: params.term || '',
                        limit: limit
                    };
                },
                processResults: function (response, params) {
                    params.page = params.page || 1;

                    if (response.status != 'success') {
                        return { results: [] };
                    }

                    var items = (response.data && response.data.results) || [];
                    return {
                        results: $.map(items, function (item) {
                            return { id: item.id, text: item.nama_paket };
                        }),
                        pagination: { more: items.length >= limit }
                    };
                },
                cache: true
            }
        });
    }

    function initTrackingSelect($el, parentCategory) {
        $el.select2({
            theme          : 'bootstrap4',
            placeholder    : 'Pilih tracking nama paket',
            allowClear     : true,
            width          : '100%',
            dropdownParent : $('body'),
            ajax: {
                url      : '{{ route("tracking-by-parent") }}',
                dataType : 'json',
                delay    : 300,
                data     : function (p) {
                    return { keyword: p.term || '', name_parent_category: parentCategory };
                },
                processResults: function (response) {
                    if (!response.status || !response.data) return { results: [] };
                    return {
                        results: $.map(response.data.lines_category || [], function (item) {
                            return {
                                id  : item.item_uuid_category || item.id,
                                text: item.item_name_category
                            };
                        })
                    };
                },
                cache: true
            }
        });
    }

    // --- FILTER ---
    function selectedItem()     { return $('#nama_paket').val() || ''; }
    function selectedTracking() { return $('#tracking_paket_name').val() || ''; }

    function hasPaketFilter() {
        return selectedItem() !== '' || selectedTracking() !== '';
    }

    function filters() {
        let f = {
            coa_code:       $('#filterCoa').val(),
            item_ids:       selectedItem(),        // satu id (backend menerima daftar dipisah koma)
            tracking_uuids: selectedTracking(),
            date_from:      $('#filterDateFrom').val(),
            date_to:        $('#filterDateTo').val(),
            status:         $('#filterStatus').val()
        };
        if (f.status === 'segera') {
            f.include_overdue = $('#filterOverdue').is(':checked') ? 1 : 0;
        }
        return f;
    }

    // --- 1. DATATABLE ---
    const colHubungi = {
        data: 'wa_number',
        orderable: false,
        searchable: false,
        className: "text-center",
        render: function(d) {
            if (!d) return '';
            return `<a href="https://wa.me/${encodeURIComponent(d)}" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="ti ti-brand-whatsapp"></i> Hubungi</a>`;
        }
    };

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
                let k = KIND[d] || [d, 'badge-secondary'];
                return `<span class="badge ${k[1]}">${esc(k[0])}</span>`;
            }
        },
        { data: 'payment_item_name', orderable: false, render: function(d) { return d ? esc(d) : '-'; } },
        { data: 'amount',        className: "text-right", render: function(d, t, row) { return curPrefix(row) + fmtNum(d); } },
        { data: 'amount_base',   className: "text-right", render: function(d) { return fmtNum(d); } },
        { data: 'invoice_total', className: "text-right", render: function(d) { return fmtNum(d); } },
        { data: 'item_amount',   className: "text-right", render: function(d) { return fmtNum(d); } },
        { data: 'paid_after',    className: "text-right", render: function(d) { return fmtNum(d); } },
        { data: 'sisa',          className: "text-right", render: function(d) { return parseFloat(d) > 0 ? fmtNum(d) : '-'; } },
        {
            data: 'reference_detail', orderable: false,
            render: function(d, t, row) {
                let out = '';
                if (row.via_overpay) out += '<span class="badge badge-warning mr-1">Via overpay</span>';
                if (row.mixed)       out += '<span class="badge badge-light border mr-1">Ada item lain</span>';
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
        { data: 'invoice_total',  className: "text-right", render: function(d, t, row) { return curPrefix(row) + fmtNum(d); } },
        { data: 'paid_total',     className: "text-right", render: function(d, t, row) { return curPrefix(row) + fmtNum(d); } },
        { data: 'sisa',           className: "text-right", render: function(d, t, row) { return curPrefix(row) + fmtNum(d); } },
        { data: 'item_amount',    className: "text-right", render: function(d) { return fmtNum(d); } },
        {
            data: 'overdue_days', orderable: false,
            render: function(d, t, row) {
                let out = '';
                if (parseInt(d) > 0) out += `<span class="badge badge-danger mr-1">Lewat ${parseInt(d)} hari</span>`;
                if (row.mixed)       out += '<span class="badge badge-light border mr-1">Ada item lain</span>';
                return out;
            }
        },
        colHubungi
    ];

    function loadTable() {
        let f = filters();
        let isDue = f.status === 'segera';

        $('#wrapTablePay').toggle(!isDue);
        $('#wrapTableDue').toggle(isDue);

        table = initGlobalDataTableTokenSelected(
            isDue ? '#tablePaketDue' : '#tablePaketPay',
            `{{ route('get-coa-paket-payment') }}`,
            isDue ? columnDue : columnPay,
            Object.assign({ 'kolom_name': 'invoice_number' }, f)
        );
    }

    // --- 2. RINGKASAN ---
    function resetSummary() {
        $('#summaryCards [id^=sum]').text('-');
    }

    function loadSummary() {
        $.ajax({
            url: `{{ route('get-coa-paket-payment-summary') }}`,
            method: 'GET',
            data: filters(),
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('token')
            },
            success: function(res) {
                let s = res.data || {};
                $('#sumClosingCount').text(s.closing_count ?? 0);
                $('#sumClosingCash').text(fmtNum(s.closing_cash));
                $('#sumPelunasanCount').text(s.pelunasan_count ?? 0);
                $('#sumPelunasanCash').text(fmtNum(s.pelunasan_cash));
                $('#sumSegeraCount').text(s.segera_count ?? 0);
                $('#sumSegeraSisa').text(fmtNum(s.segera_sisa));
            },
            error: function(xhr) {
                resetSummary();
                let msg = (xhr.responseJSON && xhr.responseJSON.message) || '';
                if (msg) $('#filterHint').text(msg).show();
            }
        });
    }

    function syncUi() {
        let isDue = $('#filterStatus').val() === 'segera';
        $('#wrapOverdue').toggle(isDue);
        $('#lblDateFrom').text(isDue ? 'Jatuh tempo dari' : 'Dari tanggal');
        $('#lblDateTo').text(isDue ? 'Jatuh tempo sampai' : 'Sampai tanggal');
        $('.summary-card').removeClass('border-primary bg-light');
        $('.summary-card[data-status="' + $('#filterStatus').val() + '"]').addClass('border-primary bg-light');
    }

    function applyFilter() {
        syncUi();

        // wajib isi salah satu: nama paket atau tracking (API juga memvalidasi)
        if (!hasPaketFilter()) {
            $('#filterHint').text('Pilih nama paket atau tracking paket (salah satu wajib diisi).').show();
            $('#wrapTablePay, #wrapTableDue').hide();
            resetSummary();
            return;
        }
        $('#filterHint').hide();

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

    initCoaSelect();
    initNamaPaketSelect2();
    initTrackingSelect($('#tracking_paket_name'), 'nama paket');
    syncUi();
});
</script>
@endpush