@extends('layouts.app')

@section('content')

<style>
/* ══════════════════════════════════════════════════════════
   FILTER BAR
   ══════════════════════════════════════════════════════════ */
.xero-filter-bar {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 18px 20px 14px;
    margin-bottom: 16px;
}
.xero-filter-bar .filter-label {
    font-size: 11px;
    font-weight: 600;
    color: #555;
    margin-bottom: 4px;
    display: block;
    white-space: nowrap;
}
.xero-filter-bar .form-control {
    height: 34px;
    font-size: 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 4px 10px;
    color: #222;
}
.xero-filter-bar .form-control:focus {
    border-color: #0070c4;
    box-shadow: none;
    outline: none;
}

/* Baris filter: wrap supaya tidak overlapping, label sejajar di atas */
.filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 12px 16px;
}
.filter-row > div { flex: 0 0 auto; }

/* ── Date range group ── */
.date-range-group {
    display: flex;
    align-items: center;
    gap: 0;
}
.date-range-group input {
    border-radius: 4px 0 0 4px !important;
    border-right: none !important;
    width: 130px;
    height: 34px;
    font-size: 12px;
}
.date-range-group input:last-of-type {
    border-radius: 0 !important;
    border-right: none !important;
}
.date-range-group .btn-dropdown {
    border: 1px solid #ccc;
    border-radius: 0 4px 4px 0;
    background: #f5f5f5;
    padding: 0 10px;
    height: 34px;
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 14px;
    color: #555;
    white-space: nowrap;
}
.date-range-group .btn-dropdown:hover { background: #ebebeb; }

/* ── Baris bawah filter (More + Update) ── */
.filter-row-2 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
}

.btn-xero-filter {
    background: #fff;
    border: 1px solid #0070c4;
    color: #0070c4;
    border-radius: 4px;
    padding: 5px 16px;
    font-size: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-xero-filter:hover { background: #e8f1fb; }

.btn-xero-more {
    background: #fff;
    border: 1px solid #d0d0d0;
    color: #333;
    border-radius: 4px;
    padding: 5px 14px;
    font-size: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-xero-more:hover { background: #f5f5f5; }

.btn-xero-update {
    background: #0070c4;
    border: none;
    color: #fff;
    border-radius: 4px;
    padding: 6px 20px;
    font-size: 12px;
    cursor: pointer;
    font-weight: 500;
}
.btn-xero-update:hover { background: #005fa3; }

/* ══════════════════════════════════════════════════════════
   SELECT2 MULTIPLE (Nama Paket item, Nama Paket tracking, Divisi)
   Di-scope ke .xero-filter-bar supaya tidak bergantung nama wrapper.
   ══════════════════════════════════════════════════════════ */

/* Lebar kolom filter (nama class lama ikut diakomodasi) */
.s2-field,
.tracking-select-wrap,
.paket-select-wrap {
    width: 260px;
    min-width: 200px;
    max-width: 100%;
}
.s2-field--lg { width: 320px; }

.xero-filter-bar .select2-container { width: 100% !important; }

/* Box utama: tinggi mengikuti isi */
.xero-filter-bar .select2-selection--multiple {
    position: relative;
    display: block !important;
    min-height: 34px;
    height: auto !important;
    max-height: none !important;
    padding: 3px 28px 3px 6px !important;
    border: 1px solid #ccc !important;
    border-radius: 4px !important;
    background: #fff !important;
    cursor: text;
    overflow: visible !important;
}
.xero-filter-bar .select2-container--focus .select2-selection--multiple,
.xero-filter-bar .select2-container--open  .select2-selection--multiple {
    border-color: #0070c4 !important;
    box-shadow: 0 0 0 2px rgba(0, 112, 196, .12) !important;
}

/* Daftar tag: flex wrap, scroll jika terlalu banyak */
.xero-filter-bar .select2-selection--multiple .select2-selection__rendered {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center;
    gap: 4px;
    width: 100%;
    max-height: 96px;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    margin: 0 !important;
    padding: 0 !important;
    list-style: none;
}

/* Satu tag */
.xero-filter-bar .select2-selection__choice {
    display: inline-flex !important;
    align-items: flex-start;
    float: none !important;
    position: static !important;
    height: auto !important;
    max-width: 100%;
    margin: 0 !important;
    padding: 3px 8px !important;
    background: #e8f0fe !important;
    border: 1px solid #c2d0f8 !important;
    border-radius: 4px !important;
    color: #1a56db !important;
    font-size: 12px;
    line-height: 1.35;
    white-space: normal !important;   /* nama panjang boleh turun baris */
    word-break: break-word;
    overflow: visible !important;
    text-overflow: clip !important;
}
.xero-filter-bar .select2-selection__choice__display { padding: 0 !important; }

/* Tombol × di dalam tag */
.xero-filter-bar .select2-selection__choice__remove {
    position: static !important;
    float: none !important;
    width: auto !important;
    height: auto !important;
    margin: 0 6px 0 0 !important;
    padding: 0 !important;
    background: none !important;
    border: 0 !important;
    color: #1a56db !important;
    font-size: 15px;
    line-height: 1.1;
    cursor: pointer;
}
.xero-filter-bar .select2-selection__choice__remove:hover { color: #c0392b !important; }

/* Input pencarian di sebelah tag */
.xero-filter-bar .select2-search--inline {
    flex: 1 1 90px;
    float: none !important;
    margin: 0 !important;
}
.xero-filter-bar .select2-search--inline .select2-search__field {
    width: 100% !important;
    min-width: 90px;
    height: 24px;
    margin: 0 !important;
    padding: 0 4px;
    border: 0 !important;
    background: transparent;
    font-size: 12px;
}

/* Tombol clear semua: pojok kanan atas */
.xero-filter-bar .select2-selection__clear {
    position: absolute !important;
    top: 6px;
    right: 8px;
    float: none !important;
    margin: 0 !important;
    padding: 0 !important;
    color: #999;
    font-size: 16px;
    line-height: 1;
    cursor: pointer;
    z-index: 1;
}
.xero-filter-bar .select2-selection__clear:hover { color: #c0392b; }

/* Dropdown (dirender di <body>, jadi style-nya global) */
.select2-container--open { z-index: 9999; }
.select2-dropdown { max-width: calc(100vw - 16px); }
.select2-results__option {
    padding: 8px 10px;
    font-size: 12px;
    white-space: normal;
    word-break: break-word;
}
.select2-container--bootstrap4 .select2-results__options { max-height: 260px; }

/* ══════════════════════════════════════════════════════════
   REPORT CARD
   ══════════════════════════════════════════════════════════ */
.xero-report-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 28px 32px;
}
.report-title {
    font-size: 22px;
    font-weight: 400;
    color: #222;
    margin: 0 0 6px;
}
.report-company {
    font-size: 13px;
    color: #333;
    margin: 0;
}
.report-period {
    font-size: 12px;
    color: #666;
    margin: 2px 0 0;
}
#filterTrack {
    font-size: 12px;
    color: #333;
    margin: 8px 0 0;
}

/* ── Header kolom periode ── */
.period-header-row {
    display: flex;
    justify-content: flex-end;
    padding: 14px 0 4px;
    border-bottom: 1px solid #d0d0d0;
    font-size: 12px;
    font-weight: 600;
    color: #333;
}
.period-header-row .col-amt {
    width: 160px;
    text-align: right;
}

/* ── Tabel laporan ── */
.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.report-table tr.section-header td {
    background: #f5f5f5;
    font-weight: 600;
    color: #222;
    padding: 8px 10px 8px 12px;
    border-top: 1px solid #d8d8d8;
    border-bottom: 1px solid #d8d8d8;
    font-size: 12px;
}
.report-table tr.section-total td {
    font-weight: 600;
    color: #222;
    padding: 8px 10px 8px 12px;
    border-top: 1px solid #d8d8d8;
    background: #fafafa;
    font-size: 12px;
}
.report-table tr.detail-row td {
    padding: 6px 10px 6px 28px;
    color: #222;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
}
.report-table tr.detail-row:hover td { background: #f0f7ff; }
.report-table tr.grand-total td {
    padding: 10px 10px 10px 12px;
    font-weight: 600;
    font-size: 13px;
    color: #222;
    border-top: 2px solid #333;
    border-bottom: 2px solid #333;
    background: #fafafa;
}
.report-table td.amt {
    text-align: right;
    width: 160px;
    font-variant-numeric: tabular-nums;
}
.report-table td.amt.positive { color: #1a6abf; }
.report-table td.amt.negative { color: #c0392b; }
.report-table td.amt.total    { color: #222; }

.report-table tr.net-profit td {
    padding: 10px 10px 10px 12px;
    font-weight: 600;
    font-size: 13px;
    background: #eaf3fb;
    color: #0070c4;
    border-top: 2px solid #0070c4;
    border-bottom: 2px solid #0070c4;
}
.report-table tr.net-profit td.amt { text-align: right; }

.compact-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 18px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
    font-size: 12px;
    color: #555;
}
.compact-toggle input[type="checkbox"] {
    accent-color: #0070c4;
    width: 14px;
    height: 14px;
}

#reportLoading {
    display: none;
    text-align: center;
    padding: 40px;
    color: #888;
    font-size: 13px;
}

/* ══════════════════════════════════════════════════════════
   MOBILE
   ══════════════════════════════════════════════════════════ */
@media (max-width: 767.98px) {
    .xero-filter-bar { padding: 14px 12px 10px; }

    /* Semua field turun ke bawah, lebar penuh */
    .filter-row { flex-direction: column; align-items: stretch; gap: 12px; }
    .filter-row > div { flex: 1 1 100%; width: 100%; }

    .date-range-group { width: 100%; }
    .date-range-group input { flex: 1 1 0; width: auto; min-width: 0; }

    .s2-field,
    .s2-field--lg,
    .tracking-select-wrap,
    .paket-select-wrap { width: 100%; min-width: 0; }

    /* Area sentuh lebih besar */
    .xero-filter-bar .select2-selection--multiple {
        min-height: 42px;
        padding: 5px 32px 5px 8px !important;
    }
    .xero-filter-bar .select2-selection__choice {
        padding: 4px 10px !important;
        font-size: 13px;
    }
    .xero-filter-bar .select2-selection__choice__remove {
        margin-right: 8px !important;
        padding: 0 4px !important;
        font-size: 18px;
    }
    .xero-filter-bar .select2-selection__clear { top: 9px; font-size: 20px; }

    /* font 16px mencegah iOS zoom otomatis saat input difokuskan */
    .xero-filter-bar .select2-search--inline .select2-search__field,
    .select2-search--dropdown .select2-search__field {
        height: 30px;
        font-size: 16px;
    }
    .select2-results__option { padding: 11px 12px; font-size: 14px; }

    /* Kartu laporan lebih lega di layar kecil */
    .xero-report-card { padding: 16px 12px; }
    .report-table td.amt,
    .period-header-row .col-amt { width: 120px; }
    .report-table tr.detail-row td { padding-left: 16px; }
}
</style>

{{-- ── FILTER BAR ── --}}
<div class="xero-filter-bar">
    <form id="formFilter" autocomplete="off">
        <div class="filter-row">

            {{-- Date range --}}
            <div>
                <span class="filter-label">
                    Date range:&nbsp;<span style="font-weight:400;color:#0070c4;" id="labelDateRange">This month</span>
                </span>
                <div class="date-range-group">
                    <input type="date" class="form-control" id="date_from" name="date_start"
                           value="{{ now()->startOfMonth()->format('Y-m-d') }}">
                    <input type="date" class="form-control" id="date_to" name="date_end"
                           value="{{ now()->endOfMonth()->format('Y-m-d') }}">
                    <button type="button" class="btn-dropdown" id="btnDatePreset"
                            title="Pilih preset tanggal">
                        <i class="ti ti-chevron-down" style="font-size:13px;" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            {{-- Nama Paket (item) --}}
            <div>
                <span class="filter-label">Nama Paket <span class="text-info font-weight-bold">(item)</span></span>
                <div class="paket-select-wrap">
                    <select id="nama_paket" name="nama_paket[]" class="form-control" multiple></select>
                </div>
            </div>

            {{-- Divisi (tracking) --}}
            <div>
                <span class="filter-label">Nama Paket <span class="text-success font-weight-bold">(tracking)</span></span>
                <div class="tracking-select-wrap">
                    <select class="select2-paket" name="tracking_paket_name[]"
                            id="tracking_paket_name" multiple="multiple" style="width:100%;"></select>
                </div>
            </div>

            <div>
                <span class="filter-label">Divisi <span class="text-success font-weight-bold">(tracking)</span></span>
                <div class="tracking-select-wrap">
                    <select class="select2-divisi" name="tracking_divisi[]"
                            id="tracking_divisi" multiple="multiple" style="width:100%;"></select>
                </div>
            </div>

            {{-- Nama Paket (tracking) --}}
           

        </div>{{-- /.filter-row --}}

        <div class="filter-row-2">
            <div class="d-flex align-items-center" style="gap:8px;">
                <div class="dropdown">
                    <button type="button" class="btn-xero-more" id="btnMore"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-dots" style="font-size:13px;" aria-hidden="true"></i> More
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" style="font-size:12px;">
                        <a class="dropdown-item" href="javascript:void(0)" id="btnExportExcel">
                            <i class="ti ti-file-spreadsheet mr-1" aria-hidden="true"></i> Export to Excel
                        </a>
                    </div>
                </div>
                <button type="button" class="btn-xero-update" id="btnUpdate">
                    Update
                </button>
            </div>
        </div>

    </form>
</div>

{{-- ── REPORT CARD ── --}}
<div class="xero-report-card">

    <div id="reportLoading">
        <div class="spinner-border text-primary" role="status" style="width:1.6rem;height:1.6rem;">
            <span class="sr-only">Loading...</span>
        </div>
        <div class="mt-2">Memuat laporan...</div>
    </div>

    <div id="reportContent">
        <p class="report-title">Profit and Loss</p>
        <p class="report-company">{{ config('app.company_name', 'PT An Namiroh Travelindo') }}</p>
        <p class="report-period" id="reportPeriodLabel">
            For the month ended {{ now()->endOfMonth()->isoFormat('D MMMM YYYY') }}
        </p>
        <p id="filterTrack">
    
        </p>

        <div class="period-header-row">
            <div class="col-amt" id="colPeriodLabel">
                {{ now()->format('M Y') }}
            </div>
        </div>

        <table class="report-table" id="reportTable">
            <tbody id="reportBody"></tbody>
        </table>

        <div class="compact-toggle">
            <input type="checkbox" id="compactView">
            <label for="compactView" style="cursor:pointer;margin:0;">Compact view</label>
        </div>
    </div>

</div>

{{-- ── DATE PRESET DROPDOWN ── --}}
<div class="dropdown-menu" id="datePresetMenu"
     style="font-size:12px;min-width:190px;position:fixed;z-index:9999;display:none;">
    <a class="dropdown-item preset-item" data-preset="this_month"  href="javascript:void(0)">This month</a>
    <a class="dropdown-item preset-item" data-preset="last_month"  href="javascript:void(0)">Last month</a>
    <a class="dropdown-item preset-item" data-preset="this_quarter"href="javascript:void(0)">This quarter</a>
    <a class="dropdown-item preset-item" data-preset="last_quarter"href="javascript:void(0)">Last quarter</a>
    <a class="dropdown-item preset-item" data-preset="this_year"   href="javascript:void(0)">This year (Jan – Dec)</a>
    <a class="dropdown-item preset-item" data-preset="last_year"   href="javascript:void(0)">Last year (Jan – Dec)</a>
    <div class="dropdown-divider"></div>
    <a class="dropdown-item preset-item" data-preset="custom"      href="javascript:void(0)">Custom range</a>
</div>

@endsection


@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
$(function () {

    // =========================================================
    // FORMAT HELPERS
    // =========================================================
    var fmt = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

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

    // =========================================================
    // FILTER HELPERS
    // =========================================================
    function getFilters() {
        return {
            nama_paket         : $('#nama_paket').val()           || [],
            tracking_divisi    : $('#tracking_divisi').val()      || [],
            tracking_paket_name: $('#tracking_paket_name').val()  || []
        };
    }

    // Kirim sebagai key berindeks supaya tidak digabung koma
    function appendArrayParams(params, key, values) {
        $.each(values || [], function (i, val) {
            params[key + '[' + i + ']'] = val;
        });
    }

    var plRouteTemplate = "{{ route('view-report-profit-loss-detail', ['account' => '__ACCOUNT__', 'date_start' => '__DATE_START__','date_end' => '__DATE_END__']) }}";

    function buildDetailUrl(accountId, dateStart, dateEnd, filters) {
        var url = plRouteTemplate
            .replace('__ACCOUNT__', accountId)
            .replace('__DATE_START__', dateStart)
            .replace('__DATE_END__', dateEnd);

        var qs = [];
        $.each(['nama_paket', 'tracking_divisi', 'tracking_paket_name'], function (i, key) {
            $.each((filters && filters[key]) || [], function (j, val) {
                qs.push(key + '[]=' + encodeURIComponent(val));
            });
        });
        if (!qs.length) return url;

        return url + (url.indexOf('?') === -1 ? '?' : '&') + qs.join('&');
    }

    // =========================================================
    // SELECT2 — Nama Paket (item), id = items_paket_all_from_xeros.id
    // =========================================================
    function initNamaPaketSelect2() {
        var $el = $('#nama_paket');

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        var limit = 10;

        $el.select2({
            theme: 'bootstrap4',
            placeholder: 'Semua paket',
            allowClear: true,
            multiple: true,
            closeOnSelect: false,
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

    // =========================================================
    // DATE PRESETS
    // =========================================================
    var presets = {
        this_month   : function () { return [moment().startOf('month'),                           moment().endOf('month')]; },
        last_month   : function () { return [moment().subtract(1,'month').startOf('month'),       moment().subtract(1,'month').endOf('month')]; },
        this_quarter : function () { return [moment().startOf('quarter'),                         moment().endOf('quarter')]; },
        last_quarter : function () { return [moment().subtract(1,'quarter').startOf('quarter'),   moment().subtract(1,'quarter').endOf('quarter')]; },
        this_year    : function () { return [moment().startOf('year'),                            moment().endOf('year')]; },
        last_year    : function () { return [moment().subtract(1,'year').startOf('year'),         moment().subtract(1,'year').endOf('year')]; }
    };

    function applyPreset(key, label) {
        if (!presets[key]) return;
        var range = presets[key]();
        $('#date_from').val(range[0].format('YYYY-MM-DD'));
        $('#date_to').val(range[1].format('YYYY-MM-DD'));
        $('#labelDateRange').text(label);
        updatePeriodLabel();
    }

    $('#btnDatePreset').on('click', function (e) {
        e.stopPropagation();
        var $menu = $('#datePresetMenu');
        if ($menu.is(':visible')) { $menu.hide(); return; }

        var r = this.getBoundingClientRect();           // koordinat viewport (cocok untuk fixed)
        $menu.css({ top: r.bottom + 4, left: 0 }).show();

        var w    = $menu.outerWidth();
        var left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8));
        $menu.css('left', left);
    });

    $(document).on('click', '.preset-item', function (e) {
        e.preventDefault();
        applyPreset($(this).data('preset'), $(this).text().trim());
        $('#datePresetMenu').hide();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#btnDatePreset, #datePresetMenu').length) {
            $('#datePresetMenu').hide();
        }
    });

    // =========================================================
    // PERIOD LABEL
    // =========================================================
    function updatePeriodLabel() {
        var from = moment($('#date_from').val());
        var to   = moment($('#date_to').val());
        if (!from.isValid() || !to.isValid()) return;

        if (from.isSame(from.clone().startOf('month'), 'day') &&
            to.isSame(to.clone().endOf('month'), 'day') &&
            from.isSame(to, 'month')) {
            $('#reportPeriodLabel').text('For the month ended ' + to.format('D MMMM YYYY'));
            $('#colPeriodLabel').text(to.format('MMM YYYY'));
        } else {
            $('#reportPeriodLabel').text(from.format('D MMM YYYY') + ' – ' + to.format('D MMM YYYY'));
            $('#colPeriodLabel').text(from.format('MMM') + ' – ' + to.format('MMM YYYY'));
        }
    }

    $('#date_from, #date_to').on('change', updatePeriodLabel);

    // =========================================================
    // SELECT2 MULTIPLE — tracking filter (Divisi & Nama Paket tracking)
    // =========================================================
    function initTrackingSelect($el, parentCategory) {
        $el.select2({
            theme          : 'bootstrap4',
            placeholder    : 'None',
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

        // Overflow handler: tampilkan "+N" badge jika tag > MAX_VISIBLE
        $el.on('change', function () {
            setTimeout(function () { trimOverflowTags($el); }, 0);
        });
    }

    /**
     * Batasi tampilan tag di select2 multiple (murni visual,
     * value tetap dikirim semua saat submit).
     */
    var MAX_VISIBLE = 2;

    function trimOverflowTags($el) {
        var $container = $el.next('.select2-container');
        if (!$container.length) return;

        $container.find('.s2-overflow-badge').remove();
        $container.find('.select2-selection__choice.s2-hidden').removeClass('s2-hidden');

        var $choices = $container.find('.select2-selection__choice');
        var total    = $choices.length;

        if (total <= MAX_VISIBLE) return;

        $choices.each(function (i) {
            if (i >= MAX_VISIBLE) $(this).addClass('s2-hidden');
        });

        var overflow = total - MAX_VISIBLE;
        var $badge   = $('<span class="s2-overflow-badge">+' + overflow + '</span>');
        $container.find('.select2-selection__rendered').append($badge);
    }

   initTrackingSelect($('#tracking_paket_name'), 'nama paket', 'Semua paket');
   initTrackingSelect($('#tracking_divisi'),     'divisi',     'Semua divisi');

    // =========================================================
    // PRELOAD nilai tersimpan (edit mode / old())
    // =========================================================
    function preloadTrackingOption($el, uuid, parentCategory) {
        if (!uuid) return;
        ajaxRequest(
            '{{ route("tracking-detail") }}',
            'get',
            { name_parent_category: parentCategory, id: uuid },
            localStorage.getItem('token')
        ).then(function (response) {
            if (response.status == 200) {
                var d    = response.data.data;
                var text = d.item_name_category || ('ID: ' + uuid);
                var opt  = new Option(text, uuid, true, true);
                $el.append(opt).trigger('change');
            }
        }).catch(function (err) { cathError(err); });
    }

    // =========================================================
    // ADAPTER — ubah response API → format sections
    // =========================================================
    function adaptApiResponse(data) {
        function toSection(name, sectionData) {
            if (!sectionData) return null;
            return {
                type : 'section',
                name : name,
                items: $.map(sectionData.items || [], function (i) {
                    return { coa_id: i.coa_id, name: i.name, amount: i.total };
                }),
                total: sectionData.total
            };
        }

        var blocks = [];

        var tradingIncome = toSection('Trading Income', data.trading_income);
        if (tradingIncome) blocks.push(tradingIncome);

        var costOfSales = toSection('Cost of Sales', data.cost_of_sales);
        if (costOfSales) blocks.push(costOfSales);

        if (data.gross_profit !== undefined) {
            blocks.push({
                type  : 'total',
                label : 'Gross Profit',
                amount: parseFloat(data.gross_profit) || 0
            });
        }

        var otherIncome = toSection('Other Income', data.other_income);
        if (otherIncome) blocks.push(otherIncome);

        var operatingExpenses = toSection('Operating Expenses', data.operating_expenses);
        if (operatingExpenses) blocks.push(operatingExpenses);

        return {
            blocks           : blocks,
            net_profit       : parseFloat(data.net_profit) || 0,
            net_profit_label : (parseFloat(data.net_profit) || 0) >= 0 ? 'Net Profit' : 'Net Loss'
        };
    }

    // =========================================================
    // LOAD REPORT — AJAX
    // =========================================================
    var _lastData   = null;
    var _lastParams = null;   // parameter + label dari laporan yang SEDANG tampil

    function badgeGroup(label, names, badgeClass) {
        if (!names || !names.length) return '';
        var html = '<span class="mr-3"><strong>' + label + ':</strong> ';
        $.each(names, function (i, n) {
            html += '<span class="badge ' + badgeClass + ' mr-1 mb-1">' + escHtml(n) + '</span>';
        });
        return html + '</span>';
    }

    function loadReport() {
        var filters = getFilters();

        var params = {
            date_start: $('#date_from').val(),
            date_end  : $('#date_to').val()
        };
        appendArrayParams(params, 'nama_paket',          filters.nama_paket);
        appendArrayParams(params, 'tracking_divisi',     filters.tracking_divisi);
        appendArrayParams(params, 'tracking_paket_name', filters.tracking_paket_name);

        // Disimpan ke _lastParams hanya jika request sukses,
        // supaya link detail & export selalu cocok dengan laporan yang tampil.
        var requestParams = {
            date_start: params.date_start,
            date_end  : params.date_end,
            filters   : filters,
            labels    : { item: [], divisi: [], paket: [] }
        };

        $('#reportLoading').show();
        $('#reportContent').hide();

        ajaxRequest(
            '{{ route("profit-and-loss-v3") }}',
            'GET',
            params,
            localStorage.getItem('token')
        )
        .then(function (response) {
            if (!response.status || !response.data) {
                throw new Error(response.message || 'Gagal memuat data');
            }

            var data = response.data.data;
            var af   = data.active_filters || {};

            requestParams.labels.item = $.map(af.item_paket || [], function (p) {
                return p.nama_paket;
            });
            requestParams.labels.divisi = $.map(af.divisi_name || [], function (o) {
                return o.item_name_category;
            });
            requestParams.labels.paket = $.map(af.paket_name || [], function (o) {
                return o.item_name_category;
            });

            var L = requestParams.labels;
            $('#filterTrack').html(
                badgeGroup('Nama Paket (item)',     L.item,   'badge-info') +
                badgeGroup('Divisi',                L.divisi, 'badge-success') +
                badgeGroup('Nama Paket (tracking)', L.paket,  'badge-warning')
            );

            _lastParams = requestParams;
            _lastData   = adaptApiResponse(data);
            renderReport(_lastData);
        })
        .catch(function (err) {
            cathError(err);
        })
        .finally(function () {
            $('#reportLoading').hide();
            $('#reportContent').show();
        });
    }

    // =========================================================
    // RENDER REPORT
    // =========================================================
    function renderReport(data) {
        var $body   = $('#reportBody');
        var compact = $('#compactView').is(':checked');
        $body.empty();

        // Pakai parameter laporan yang tampil (bukan isi form saat ini)
        var p = _lastParams || {
            date_start: $('#date_from').val(),
            date_end  : $('#date_to').val(),
            filters   : getFilters()
        };

        $.each(data.blocks || [], function (idx, block) {

            if (block.type === 'total') {
                $body.append(
                    '<tr class="grand-total">' +
                    '<td>' + escHtml(block.label) + '</td>' +
                    '<td class="amt total ' + amtClass(block.amount) + '">' + formatAmt(block.amount) + '</td>' +
                    '</tr>'
                );
                return;
            }

            $body.append(
                '<tr class="section-header">' +
                '<td>' + escHtml(block.name) + '</td>' +
                '<td class="amt total"></td>' +
                '</tr>'
            );

            if (!compact) {
                $.each(block.items || [], function (i, item) {
                    var detailUrl = buildDetailUrl(item.coa_id, p.date_start, p.date_end, p.filters);
                    $body.append(
                        '<tr class="detail-row">' +
                        '<td>' + escHtml(item.name) + '</td>' +
                        '<td class="' + amtClass(item.amount) + '">' +
                            '<a href="' + detailUrl + '" target="_blank">' + formatAmt(item.amount) + '</a>' +
                        '</td>' +
                        '</tr>'
                    );
                });
            }

            $body.append(
                '<tr class="section-total">' +
                '<td>Total ' + escHtml(block.name) + '</td>' +
                '<td class="amt total">' + formatAmt(block.total) + '</td>' +
                '</tr>'
            );
        });

        var npLabel = data.net_profit_label || 'Net Profit';
        $body.append(
            '<tr class="net-profit">' +
            '<td>' + escHtml(npLabel) + '</td>' +
            '<td class="amt">' + formatAmt(data.net_profit || 0) + '</td>' +
            '</tr>'
        );
    }

    // =========================================================
    // EVENT BINDINGS
    // =========================================================
    $('#btnUpdate').on('click', function () {
        updatePeriodLabel();
        loadReport();
    });

    $('#compactView').on('change', function () {
        if (_lastData) renderReport(_lastData);
    });

    $('#btnFilter').on('click', function () {
        Swal.fire({
            title: 'Filter', icon: 'info',
            text : 'Panel filter tambahan (implementasi sesuai kebutuhan).',
            confirmButtonText: 'OK'
        });
    });

    // =========================================================
    // EXPORT EXCEL (client-side, dari data laporan yang tampil)
    // =========================================================
    var COMPANY_NAME = @json(config('app.company_name', 'PT An Namiroh Travelindo'));

    // Cegah formula injection di Excel (sel diawali = + - @)
    function safeText(v) {
        v = (v === null || v === undefined) ? '' : String(v);
        return /^[=+\-@]/.test(v) ? "'" + v : v;
    }

    function exportToExcel() {
        if (!_lastData || !_lastParams) {
            Swal.fire('Peringatan', 'Belum ada laporan yang dimuat. Klik Update dulu.', 'warning');
            return;
        }

        var from = moment(_lastParams.date_start);
        var to   = moment(_lastParams.date_end);
        var periodText = (from.isSame(from.clone().startOf('month'), 'day') &&
                          to.isSame(to.clone().endOf('month'), 'day') &&
                          from.isSame(to, 'month'))
            ? 'For the month ended ' + to.format('D MMMM YYYY')
            : from.format('D MMM YYYY') + ' – ' + to.format('D MMM YYYY');

        var colHeader = from.isSame(to, 'month')
            ? to.format('MMM YYYY')
            : from.format('MMM') + ' – ' + to.format('MMM YYYY');

        var L = _lastParams.labels;
        var aoa = [];
        var numRows = [];   // index baris yang kolom B-nya angka

        aoa.push(['Profit and Loss']);
        aoa.push([safeText(COMPANY_NAME)]);
        aoa.push([periodText]);

        function pushFilterLine(label, names) {
            if (names && names.length) {
                aoa.push([label + ': ' + safeText(names.join(', '))]);
            }
        }
        pushFilterLine('Nama Paket (item)',     L.item);
        pushFilterLine('Divisi',                L.divisi);
        pushFilterLine('Nama Paket (tracking)', L.paket);

        aoa.push([]);
        aoa.push(['', colHeader]);

        function pushAmt(label, amount) {
            aoa.push([safeText(label), parseFloat(amount) || 0]);
            numRows.push(aoa.length - 1);
        }

        $.each(_lastData.blocks || [], function (idx, block) {
            if (block.type === 'total') {
                aoa.push([]);
                pushAmt(block.label, block.amount);
                aoa.push([]);
                return;
            }

            aoa.push([safeText(block.name)]);
            $.each(block.items || [], function (i, item) {
                pushAmt('    ' + item.name, item.amount);   // indent detail
            });
            pushAmt('Total ' + block.name, block.total);
            aoa.push([]);
        });

        pushAmt(_lastData.net_profit_label || 'Net Profit', _lastData.net_profit || 0);

        var ws = XLSX.utils.aoa_to_sheet(aoa);

        // Format angka: ribuan + negatif dalam kurung (sama seperti tampilan Xero)
        $.each(numRows, function (i, r) {
            var ref = XLSX.utils.encode_cell({ r: r, c: 1 });
            if (ws[ref]) { ws[ref].t = 'n'; ws[ref].z = '#,##0.00;(#,##0.00)'; }
        });

        ws['!cols'] = [{ wch: 48 }, { wch: 20 }];

        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Profit and Loss');

        var nameParts = [].concat(L.item, L.divisi, L.paket);
        var namaFile = nameParts.length
            ? '_' + nameParts.join('_').replace(/[^A-Za-z0-9\-_]+/g, '_').substring(0, 60)
            : '';
        var fileName = 'Profit_and_Loss' + namaFile + '_' + _lastParams.date_start + '_' + _lastParams.date_end + '.xlsx';
        XLSX.writeFile(wb, fileName);
    }

    $('#btnExportExcel').on('click', function () {
        try {
            exportToExcel();
        } catch (e) {
            console.error(e);
            Swal.fire('Gagal', 'Export Excel gagal: ' + e.message, 'error');
        }
    });

    // =========================================================
    // INIT
    // =========================================================
    updatePeriodLabel();
    initNamaPaketSelect2();
    loadReport();
});
</script>
@endpush