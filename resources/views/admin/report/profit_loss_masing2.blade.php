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

/* ── Seksi filter (judul kecil + grid rapi) ── */
.filter-section + .filter-section {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px dashed #e6e6e6;
}
.filter-section-title {
    margin: 0 0 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: #8a94a6;
}
.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
    align-items: start;
    gap: 14px 18px;
}
.filter-row > div { min-width: 0; }

.filter-note {
    margin: 12px 0 0;
    font-size: 12px;
}

.date-range-group {
    display: flex;
    align-items: center;
    gap: 0;
}
.date-range-group input {
    border-radius: 4px 0 0 4px !important;
    border-right: none !important;
    flex: 1 1 0;
    min-width: 0;
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

/* ── Tanggal keberangkatan (awal s/d akhir) ── */
.date-pair {
    display: flex;
    align-items: center;
    gap: 8px;
}
.date-pair .form-control {
    flex: 1 1 0;
    min-width: 0;
}
.date-pair .sep {
    flex: 0 0 auto;
    font-size: 12px;
    color: #888;
}

/* ── Radio tipe (segmented control) ── */
.type-switch {
    display: flex;
    width: 100%;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: #fff;
    overflow: hidden;
}
.type-option {
    position: relative;
    flex: 1 1 0;
    margin: 0;
    cursor: pointer;
}
.type-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.type-option span {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 32px;
    padding: 0 12px;
    font-size: 12px;
    color: #555;
    white-space: nowrap;
    transition: background .15s, color .15s;
}
.type-option + .type-option span { border-left: 1px solid #ccc; }
.type-option:hover span { background: #f5f5f5; }
.type-option input:focus-visible + span {
    outline: 2px solid #0070c4;
    outline-offset: -2px;
}
.type-option.is-item input:checked + span {
    background: #e8f1fb;
    color: #0070c4;
    font-weight: 600;
}
.type-option.is-tracking input:checked + span {
    background: #e6f6ec;
    color: #1e8e4e;
    font-weight: 600;
}

.autofill-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-top: 6px;
    min-height: 16px;
}
.autofill-status {
    font-size: 11px;
    line-height: 1.4;
    color: #777;
}
.autofill-status.is-loading { color: #0070c4; }
.autofill-status.is-ok      { color: #1e8e4e; }
.autofill-status.is-empty   { color: #b7791f; }
.autofill-status.is-error   { color: #c0392b; }
.autofill-status .spinner-border {
    width: .85rem;
    height: .85rem;
    border-width: .14em;
    vertical-align: -2px;
}
.btn-link-reset {
    flex: 0 0 auto;
    padding: 0;
    border: 0;
    background: none;
    font-size: 11px;
    color: #0070c4;
    text-decoration: underline;
    cursor: pointer;
}
.btn-link-reset:hover { color: #005fa3; }

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
.btn-xero-update:disabled {
    opacity: .6;
    cursor: not-allowed;
}

/* ══════════════════════════════════════════════════════════
   SELECT2 MULTIPLE
   ══════════════════════════════════════════════════════════ */
.s2-field,
.s2-field--lg,
.tracking-select-wrap,
.paket-select-wrap {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.xero-filter-bar .select2-container { width: 100% !important; }

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

.xero-filter-bar .select2-selection--multiple .select2-selection__rendered {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center;
    gap: 4px;
    width: 100%;
    max-height: 150px;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    margin: 0 !important;
    padding: 0 !important;
    list-style: none;
}

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
    white-space: normal !important;
    word-break: break-word;
    overflow: visible !important;
    text-overflow: clip !important;
}
.xero-filter-bar .select2-selection__choice__display { padding: 0 !important; }

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

.xero-filter-bar .select2-selection__choice.s2-hidden { display: none !important; }

.xero-filter-bar .s2-overflow-badge {
    display: inline-flex;
    align-items: center;
    height: 24px;
    margin: 0;
    padding: 0 9px;
    border-radius: 12px;
    background: #0070c4;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
    list-style: none;
}
.xero-filter-bar .select2-container--focus .select2-selection__choice.s2-hidden,
.xero-filter-bar .select2-container--open  .select2-selection__choice.s2-hidden {
    display: inline-flex !important;
}
.xero-filter-bar .select2-container--focus .s2-overflow-badge,
.xero-filter-bar .select2-container--open  .s2-overflow-badge {
    display: none;
}

.select2-container--open { z-index: 9999; }

.select2-dropdown {
    max-width: calc(100vw - 16px);
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(0, 0, 0, .12);
}

.select2-container--bootstrap4 .select2-results > .select2-results__options,
.select2-container--default   .select2-results > .select2-results__options {
    max-height: 260px !important;
    max-height: min(260px, 40vh) !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}
.select2-results__options .select2-results__options {
    max-height: none !important;
    overflow: visible !important;
}

.select2-results__option {
    padding: 8px 10px;
    font-size: 12px;
    line-height: 1.35;
    white-space: normal;
    word-break: break-word;
    -webkit-tap-highlight-color: transparent;
}

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

/* ── Tabel laporan (multi-kolom per paket) ── */
.report-table-wrap {
    width: 100%;
    overflow-x: auto;
    margin-top: 10px;
}
.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.report-table thead th {
    padding: 12px 10px 6px;
    font-size: 11px;
    font-weight: 600;
    color: #333;
    text-align: right;
    vertical-align: bottom;
    border-bottom: 1px solid #d0d0d0;
    min-width: 150px;
    white-space: normal;
    word-break: break-word;
    text-transform: uppercase;
}
.report-table thead th.lbl {
    text-align: left;
    min-width: 240px;
}
.report-table thead th.col-total,
.report-table td.col-total {
    border-left: 1px solid #e3e3e3;
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
}
.report-table tr.detail-row td.amt { padding-left: 10px; }
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
    min-width: 150px;
    font-variant-numeric: tabular-nums;
}
.report-table td.amt.positive { color: #1a6abf; }
.report-table td.amt.negative { color: #c0392b; }
.report-table td.amt.total    { color: #222; }
.report-table td.amt a        { color: inherit; }
.report-table td.amt.zero     { color: #999; }

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

    .filter-row { grid-template-columns: 1fr; gap: 14px; }

    /* 16px mencegah iOS zoom otomatis saat input difokus */
    .xero-filter-bar .form-control { height: 40px; font-size: 16px; }

    .date-range-group { width: 100%; }
    .date-range-group input { flex: 1 1 0; width: auto; min-width: 0; }
    .date-range-group .btn-dropdown { height: 40px; }

    .date-pair { gap: 6px; }

    .type-option span { height: 38px; font-size: 13px; }
    .autofill-status,
    .btn-link-reset { font-size: 12px; }

    .filter-row-2 > .d-flex { width: 100%; }
    .filter-row-2 .btn-xero-more { height: 40px; }
    .filter-row-2 .btn-xero-update { flex: 1 1 auto; height: 40px; }

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
    .xero-filter-bar .s2-overflow-badge { height: 28px; font-size: 12px; padding: 0 11px; border-radius: 14px; }

    .xero-filter-bar .select2-search--inline .select2-search__field,
    .select2-search--dropdown .select2-search__field {
        height: 30px;
        font-size: 16px;
    }

    .select2-container--bootstrap4 .select2-results > .select2-results__options,
    .select2-container--default   .select2-results > .select2-results__options {
        max-height: 240px !important;
        max-height: min(240px, 36vh) !important;
    }
    .select2-results__option { padding: 12px 12px; font-size: 14px; }

    .xero-report-card { padding: 16px 12px; }
    .report-table td.amt,
    .report-table thead th { min-width: 120px; }
    .report-table thead th.lbl { min-width: 160px; }
    .report-table tr.detail-row td { padding-left: 16px; }
    .report-table tr.detail-row td.amt { padding-left: 8px; }
}
</style>

{{-- ── FILTER BAR ── --}}
<div class="xero-filter-bar">
    <form id="formFilter" autocomplete="off">
        {{-- ═════ Grup 1: Keberangkatan ═════ --}}
        <div class="filter-section">
            <p class="filter-section-title">Keberangkatan</p>

            <div class="filter-row">

                {{-- Tanggal keberangkatan (awal s/d akhir) --}}
                <div>
                    <span class="filter-label">Tanggal Keberangkatan</span>
                    <div class="date-pair">
                        <input type="date" class="form-control" id="tanggal_keberangkatan_awal"
                               name="tanggal_keberangkatan_awal" aria-label="Tanggal keberangkatan awal">
                        <span class="sep">s/d</span>
                        <input type="date" class="form-control" id="tanggal_keberangkatan_akhir"
                               name="tanggal_keberangkatan_akhir" aria-label="Tanggal keberangkatan akhir">
                    </div>
                </div>

                {{-- Radio tipe: isi otomatis ke Select2 item / tracking paket --}}
                <div>
                    <span class="filter-label" id="lblTipeFilter">Isi otomatis ke filter</span>
                    <div class="type-switch" role="radiogroup" aria-labelledby="lblTipeFilter">
                        <label class="type-option is-item">
                            <input type="radio" name="tipe_filter" id="tipe_item" value="item">
                            <span>Item</span>
                        </label>
                        <label class="type-option is-tracking">
                            <input type="radio" name="tipe_filter" id="tipe_tracking_paket" value="tracking_paket">
                            <span>Tracking Paket</span>
                        </label>
                    </div>
                    <div class="autofill-row">
                        <div class="autofill-status" id="autofillStatus" role="status" aria-live="polite"></div>
                        <button type="button" class="btn-link-reset" id="btnResetAuto">Reset</button>
                    </div>
                </div>

            </div>{{-- /.filter-row --}}
        </div>

        {{-- ═════ Grup 2: Periode & filter paket ═════ --}}
        <div class="filter-section">
            <p class="filter-section-title">Periode &amp; filter paket</p>

            <div class="filter-row">

                {{-- Date range laporan --}}
                <div>
                    <span class="filter-label">
                        Date range:&nbsp;<span style="font-weight:400;color:#0070c4;" id="labelDateRange">This month</span>
                    </span>
                    <div class="date-range-group">
                        <input type="date" class="form-control" id="date_from" name="date_start"
                            value="{{ now()->subYear()->format('Y-m-d') }}">
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

                {{-- Nama Paket (tracking) --}}
                <div>
                    <span class="filter-label">Nama Paket <span class="text-success font-weight-bold">(tracking)</span></span>
                    <div class="tracking-select-wrap">
                        <select class="select2-paket" name="tracking_paket_name[]"
                                id="tracking_paket_name" multiple="multiple" style="width:100%;"></select>
                    </div>
                </div>

                {{-- Divisi (tracking) --}}
                <div>
                    <span class="filter-label">Divisi <span class="text-success font-weight-bold">(tracking)</span></span>
                    <div class="tracking-select-wrap">
                        <select class="select2-divisi" name="tracking_divisi[]"
                                id="tracking_divisi" multiple="multiple" style="width:100%;"></select>
                    </div>
                </div>

            </div>{{-- /.filter-row --}}

            <p class="filter-note text-danger">pilih salah 1 filter Nama Paket (item) atau Nama Paket(tracking) jangan gunakan 2 sekaligus</p>
        </div>

        {{-- ═════ Aksi ═════ --}}
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
        <p id="filterTrack"></p>

        <div class="report-table-wrap">
            <table class="report-table" id="reportTable">
                <thead id="reportHead"></thead>
                <tbody id="reportBody"></tbody>
            </table>
        </div>

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

    // Nol tampil "-" seperti Xero
    function formatCell(val) {
        val = parseFloat(val) || 0;
        return val === 0 ? '-' : formatAmt(val);
    }

    function amtClass(val) {
        val = parseFloat(val) || 0;
        if (val < 0) return 'amt negative';
        return 'amt positive';
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
    // SELECT2 HELPER — ringkas tag jadi "+N" saat tidak difokus
    // =========================================================
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

        var $badge  = $('<li class="s2-overflow-badge">+' + (total - MAX_VISIBLE) + '</li>');
        var $search = $container.find('.select2-search--inline');
        if ($search.length) {
            $search.before($badge);
        } else {
            $container.find('.select2-selection__rendered').append($badge);
        }
    }

    function bindOverflowTrim($el) {
        $el.off('change.s2trim').on('change.s2trim', function () {
            setTimeout(function () { trimOverflowTags($el); }, 0);
        });
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

        bindOverflowTrim($el);
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

        var r = this.getBoundingClientRect();
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
    function isFullMonth(from, to) {
        return from.isSame(from.clone().startOf('month'), 'day') &&
               to.isSame(to.clone().endOf('month'), 'day') &&
               from.isSame(to, 'month');
    }

    function periodText(from, to) {
        return isFullMonth(from, to)
            ? 'For the month ended ' + to.format('D MMMM YYYY')
            : from.format('D MMM YYYY') + ' – ' + to.format('D MMM YYYY');
    }

    // Judul kolom saat laporan TIDAK dipecah per paket
    function periodColLabel(from, to) {
        return isFullMonth(from, to)
            ? to.format('MMM YYYY')
            : from.format('MMM') + ' – ' + to.format('MMM YYYY');
    }

    function updatePeriodLabel() {
        var from = moment($('#date_from').val());
        var to   = moment($('#date_to').val());
        if (!from.isValid() || !to.isValid()) return;
        $('#reportPeriodLabel').text(periodText(from, to));
    }

    $('#date_from, #date_to').on('change', updatePeriodLabel);

    // =========================================================
    // SELECT2 MULTIPLE — tracking filter (Divisi & Nama Paket tracking)
    // =========================================================
    function initTrackingSelect($el, parentCategory, placeholderText) {
        $el.select2({
            theme          : 'bootstrap4',
            placeholder    : placeholderText || 'None',
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

        bindOverflowTrim($el);
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
    // AUTO-ISI FILTER dari TANGGAL KEBERANGKATAN
    // ---------------------------------------------------------
    // Radio "Item"           -> isi Select2 Nama Paket (item)
    // Radio "Tracking Paket" -> isi Select2 Nama Paket (tracking)
    // Data diambil dari route "item-list" (semua halaman).
    // =========================================================
    var AUTOFILL = {
        limit   : 100,   // jumlah data per request (turunkan jika API membatasi)
        maxPages: 50,    // batas pengaman jumlah halaman yang diambil
        // urutan kolom yang dicoba untuk teks tampilan di Select2
        textKeys: ['nama_paket', 'name', 'product_name', 'title', 'code'],
        types   : {
            item: {
                label    : 'Nama Paket (item)',
                target   : '#nama_paket',
                other    : '#tracking_paket_name',
                kolomName: 'id',                          // param kolom_name ke API
                valueKey : 'id'                           // kolom respons yang jadi value Select2
            },
            tracking_paket: {
                label    : 'Nama Paket (tracking)',
                target   : '#tracking_paket_name',
                other    : '#nama_paket',
                kolomName: 'uuid_proudct_and_service',
                valueKey : 'uuid_proudct_and_service'
            }
        }
    };

    var _autofillSeq   = 0;      // penanda request terbaru (abaikan respons usang)
    var _autofillTimer = null;

    function currentType() {
        return $('input[name="tipe_filter"]:checked').val() || '';
    }

    // Respons gaya axios ({data: body}) atau body langsung
    function unwrapBody(response) {
        if (response && response.data && typeof response.data === 'object' && 'status' in response.data) {
            return response.data;
        }
        return response;
    }

    function setAutofillStatus(kind, msg) {
        var $s = $('#autofillStatus');
        $s.removeClass('is-loading is-ok is-empty is-error').empty();
        if (kind) $s.addClass('is-' + kind);
        if (kind === 'loading') {
            $s.append('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>');
        }
        $s.append($('<span>').text(msg || ''));
    }

    // Tombol Update dimatikan selama paket sedang dimuat
    function setAutofillBusy(on) {
        $('#btnUpdate').prop('disabled', !!on);
    }

    function clearSelect($el) {
        $el.val(null).empty().trigger('change');
    }

    function pickText(row) {
        for (var i = 0; i < AUTOFILL.textKeys.length; i++) {
            var v = row[AUTOFILL.textKeys[i]];
            if (v !== undefined && v !== null && String(v).trim() !== '') return String(v);
        }
        return String(row.code || row.id);
    }

    // Isi Select2 dengan semua baris (tanpa duplikat). Return jumlah opsi terisi.
    function fillSelect2($el, rows, valueKey) {
        $el.empty();
        var seen = {};
        var n    = 0;
        $.each(rows, function (i, row) {
            var val = row[valueKey];
            if (val === undefined || val === null || val === '' || seen[val]) return;
            seen[val] = true;
            $el.append(new Option(pickText(row), val, true, true));
            n++;
        });
        $el.trigger('change');
        return n;
    }

    // Ambil semua halaman dari route item-list
    function fetchAllListItems(kolomName, dateStart, dateEnd, seq) {
        var all = [];

        function fetchPage(page) {
            return ajaxRequest(
                '{{ route("item-list") }}',
                'GET',
                {
                    limit     : AUTOFILL.limit,
                    page      : page,
                    keyword   : '',
                    kolom_name: kolomName,
                    date_start: dateStart,
                    date_end  : dateEnd
                },
                localStorage.getItem('token')
            ).then(function (response) {
                if (seq !== _autofillSeq) return null;     // digantikan request yang lebih baru

                var body = unwrapBody(response);
                if (!body || !body.status) {
                    throw new Error((body && body.message) || 'Gagal memuat daftar paket');
                }

                var pg = body.data || {};
                all = all.concat(pg.data || []);

                var last = parseInt(pg.last_page, 10) || 1;
                if (page < last) {
                    if (page >= AUTOFILL.maxPages) return { rows: all, truncated: true };
                    return fetchPage(page + 1);
                }
                return { rows: all, truncated: false };
            });
        }

        return fetchPage(1);
    }

    function autofillFromApi() {
        var seq = ++_autofillSeq;          // batalkan request sebelumnya
        setAutofillBusy(false);

        var ds   = $('#tanggal_keberangkatan_awal').val();
        var de   = $('#tanggal_keberangkatan_akhir').val();
        var type = currentType();

        if (!ds || !de) {
            setAutofillStatus(type ? 'empty' : '', type ? 'Isi tanggal keberangkatan awal dan akhir untuk memuat paket.' : '');
            return;
        }
        if (ds > de) {
            setAutofillStatus('error', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
            return;
        }
        if (!type) {
            setAutofillStatus('empty', 'Pilih Item atau Tracking Paket untuk mengisi filter otomatis.');
            return;
        }

        var cfg       = AUTOFILL.types[type];
        var rangeText = moment(ds).format('D MMM YYYY') + ' – ' + moment(de).format('D MMM YYYY');

        setAutofillStatus('loading', 'Memuat paket...');
        setAutofillBusy(true);

        fetchAllListItems(cfg.kolomName, ds, de, seq)
            .then(function (res) {
                if (!res || seq !== _autofillSeq) return;

                var $target = $(cfg.target);
                clearSelect($(cfg.other));     // hanya 1 filter paket yang boleh aktif

                if (!res.rows.length) {
                    clearSelect($target);
                    setAutofillStatus('empty', 'Tidak ada paket dengan keberangkatan ' + rangeText + '.');
                    return;
                }

                var n = fillSelect2($target, res.rows, cfg.valueKey);
                if (!n) {
                    clearSelect($target);
                    setAutofillStatus('error', 'Data ada, tetapi kolom "' + cfg.valueKey + '" tidak ditemukan di respons.');
                    return;
                }

                setAutofillStatus('ok',
                    n + ' paket dimuat ke ' + cfg.label +
                    (res.truncated ? ' (dibatasi ' + AUTOFILL.maxPages + ' halaman pertama)' : '') + '.');
            })
            .catch(function (err) {
                if (seq !== _autofillSeq) return;
                setAutofillStatus('error', 'Gagal memuat paket. Coba lagi.');
                cathError(err);
            })
            .finally(function () {
                if (seq === _autofillSeq) setAutofillBusy(false);
            });
    }

    $('input[name="tipe_filter"]').on('change', autofillFromApi);

    $('#tanggal_keberangkatan_awal, #tanggal_keberangkatan_akhir').on('change', function () {
        clearTimeout(_autofillTimer);
        _autofillTimer = setTimeout(autofillFromApi, 350);
    });

    $('#btnResetAuto').on('click', function () {
        clearTimeout(_autofillTimer);
        _autofillSeq++;                    // batalkan request yang sedang berjalan
        setAutofillBusy(false);

        $('input[name="tipe_filter"]').prop('checked', false);
        $('#tanggal_keberangkatan_awal, #tanggal_keberangkatan_akhir').val('');
        clearSelect($('#nama_paket'));
        clearSelect($('#tracking_paket_name'));
        setAutofillStatus('', '');
    });

    // =========================================================
    // ADAPTER — ubah response API → format sections (multi kolom)
    // =========================================================
    function adaptApiResponse(data) {
        function toSection(name, s) {
            if (!s) return null;
            return {
                type : 'section',
                name : name,
                items: $.map(s.items || [], function (i) {
                    return { coa_id: i.coa_id, name: i.name, amounts: i.amounts || {}, total: i.total };
                }),
                column_totals: s.column_totals || {},
                total        : s.total
            };
        }

        var blocks = [];

        var tradingIncome = toSection('Trading Income', data.trading_income);
        if (tradingIncome) blocks.push(tradingIncome);

        var costOfSales = toSection('Cost of Sales', data.cost_of_sales);
        if (costOfSales) blocks.push(costOfSales);

        if (data.gross_profit) {
            blocks.push({
                type   : 'total',
                label  : 'Gross Profit',
                amounts: data.gross_profit.column_totals || {},
                total  : parseFloat(data.gross_profit.total) || 0
            });
        }

        var otherIncome = toSection('Other Income', data.other_income);
        if (otherIncome) blocks.push(otherIncome);

        var operatingExpenses = toSection('Operating Expenses', data.operating_expenses);
        if (operatingExpenses) blocks.push(operatingExpenses);

        var np = data.net_profit || { column_totals: {}, total: 0 };
        var npTotal = parseFloat(np.total) || 0;

        return {
            columns          : data.columns || [],
            has_split        : !!data.has_split,
            blocks           : blocks,
            net_profit       : { amounts: np.column_totals || {}, total: npTotal },
            net_profit_label : npTotal >= 0 ? 'Net Profit' : 'Net Loss'
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

    // Peta id -> nama dari pilihan Select2 yang sedang aktif
    function selectMap($el) {
        var m = {};
        $.each($el.select2('data') || [], function (i, d) {
            if (d && d.id !== undefined) m[String(d.id)] = d.text;
        });
        return m;
    }

    // Judul kolom: nama paket (item) dan/atau nama paket (tracking), bukan kode
    function columnLabel(c) {
        var m = (_lastParams && _lastParams.maps) || { item: {}, track: {} };
        var f = c.filters || {};
        var parts = [];
        $.each(f.nama_paket || [], function (i, id) {
            parts.push(m.item[String(id)] || id);
        });
        $.each(f.tracking_paket_name || [], function (i, id) {
            parts.push(m.track[String(id)] || id);
        });
        return parts.length ? parts.join(' / ') : c.label;
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

        var from = moment(params.date_start);
        var to   = moment(params.date_end);

        var requestParams = {
            date_start: params.date_start,
            date_end  : params.date_end,
            filters   : filters,
            colLabel  : (from.isValid() && to.isValid()) ? periodColLabel(from, to) : 'Total',
            labels    : { item: [], divisi: [], paket: [] },
            maps      : { item: selectMap($('#nama_paket')), track: selectMap($('#tracking_paket_name')) }
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

            // Lengkapi peta nama item dari response (cadangan jika Select2 kosong)
            $.each(af.item_paket || [], function (i, p) {
                requestParams.maps.item[String(p.id)] = p.nama_paket;
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
    // RENDER REPORT (1 kolom per paket + kolom Total)
    // =========================================================
    function renderReport(data) {
        var $body   = $('#reportBody');
        var $head   = $('#reportHead');
        var compact = $('#compactView').is(':checked');
        $body.empty();
        $head.empty();

        var p = _lastParams || {
            date_start: $('#date_from').val(),
            date_end  : $('#date_to').val(),
            filters   : getFilters(),
            colLabel  : 'Total'
        };

        var cols  = data.columns || [];
        var split = data.has_split && cols.length > 0;
        var span  = 1 + cols.length + (split ? 1 : 0);

        // ── Header kolom ──
        var h = '<tr><th class="lbl"></th>';
        $.each(cols, function (i, c) {
            h += '<th>' + escHtml(split ? columnLabel(c) : p.colLabel) + '</th>';
        });
        if (split) h += '<th class="col-total">Total</th>';
        $head.append(h + '</tr>');

        // Sel angka tanpa link (total / grand total / net profit)
        function amtCells(amounts, total, cls) {
            var s = '';
            $.each(cols, function (i, c) {
                s += '<td class="' + cls + '">' + formatCell((amounts || {})[c.key]) + '</td>';
            });
            if (split) s += '<td class="' + cls + ' col-total">' + formatCell(total) + '</td>';
            return s;
        }

        // Sel angka baris detail: tiap kolom punya link dengan filter kolomnya sendiri
        function linkCells(item) {
            var s = '';
            $.each(cols, function (i, c) {
                var v = parseFloat((item.amounts || {})[c.key]) || 0;
                if (v === 0) {
                    s += '<td class="amt zero">-</td>';
                    return;
                }
                var url = buildDetailUrl(item.coa_id, p.date_start, p.date_end, c.filters);
                s += '<td class="' + amtClass(v) + '"><a href="' + url + '" target="_blank">' + formatAmt(v) + '</a></td>';
            });
            if (split) {
                var t = parseFloat(item.total) || 0;
                if (t === 0) {
                    s += '<td class="amt zero col-total">-</td>';
                } else {
                    var urlAll = buildDetailUrl(item.coa_id, p.date_start, p.date_end, p.filters);
                    s += '<td class="' + amtClass(t) + ' col-total"><a href="' + urlAll + '" target="_blank">' + formatAmt(t) + '</a></td>';
                }
            }
            return s;
        }

        $.each(data.blocks || [], function (idx, block) {

            if (block.type === 'total') {
                $body.append(
                    '<tr class="grand-total"><td>' + escHtml(block.label) + '</td>' +
                    amtCells(block.amounts, block.total, 'amt total') + '</tr>'
                );
                return;
            }

            $body.append(
                '<tr class="section-header"><td colspan="' + span + '">' + escHtml(block.name) + '</td></tr>'
            );

            if (!compact) {
                $.each(block.items || [], function (i, item) {
                    $body.append(
                        '<tr class="detail-row"><td>' + escHtml(item.name) + '</td>' + linkCells(item) + '</tr>'
                    );
                });
            }

            $body.append(
                '<tr class="section-total"><td>Total ' + escHtml(block.name) + '</td>' +
                amtCells(block.column_totals, block.total, 'amt total') + '</tr>'
            );
        });

        var np = data.net_profit || { amounts: {}, total: 0 };
        $body.append(
            '<tr class="net-profit"><td>' + escHtml(data.net_profit_label || 'Net Profit') + '</td>' +
            amtCells(np.amounts, np.total, 'amt') + '</tr>'
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

        var cols  = _lastData.columns || [];
        var split = _lastData.has_split && cols.length > 0;
        var L     = _lastParams.labels;

        var NUMFMT = '#,##0.00;(#,##0.00)';
        var nCols  = cols.length;
        var firstL = XLSX.utils.encode_col(1);       // kolom paket pertama (B)
        var lastL  = XLSX.utils.encode_col(nCols);   // kolom paket terakhir

        var aoa   = [];
        var rowOf = {};   // nama seksi / label -> nomor baris Excel (1-based)

        aoa.push(['Profit and Loss']);
        aoa.push([safeText(COMPANY_NAME)]);
        aoa.push([periodText(from, to)]);

        function pushFilterLine(label, names) {
            if (names && names.length) {
                aoa.push([label + ': ' + safeText(names.join(', '))]);
            }
        }
        pushFilterLine('Nama Paket (item)',     L.item);
        pushFilterLine('Divisi',                L.divisi);
        pushFilterLine('Nama Paket (tracking)', L.paket);

        aoa.push([]);

        var header = [''];
        $.each(cols, function (i, c) {
            header.push(safeText(split ? columnLabel(c) : _lastParams.colLabel));
        });
        if (split) header.push('Total');
        aoa.push(header);

        // Sel angka; jika ada formula, nilai terhitung tetap disimpan sebagai cache
        function num(v, f) {
            var c = { t: 'n', v: parseFloat(v) || 0, z: NUMFMT };
            if (f) c.f = f;
            return c;
        }

        // colFormula(colLetter) -> rumus per kolom paket (null = nilai biasa).
        // Kolom Total (jika ada) selalu = SUM seluruh kolom paket di baris yang sama.
        function pushRow(label, amounts, total, colFormula) {
            var r   = aoa.length + 1;
            var row = [safeText(label)];
            $.each(cols, function (i, c) {
                var colL = XLSX.utils.encode_col(i + 1);
                row.push(num((amounts || {})[c.key], colFormula ? colFormula(colL) : null));
            });
            if (split) {
                row.push(num(total, 'SUM(' + firstL + r + ':' + lastL + r + ')'));
            }
            aoa.push(row);
            return r;
        }

        function ref(name, colL) {
            return rowOf[name] ? colL + rowOf[name] : '0';
        }

        $.each(_lastData.blocks || [], function (idx, block) {
            if (block.type === 'total') {
                // Gross Profit = Total Trading Income - Total Cost of Sales
                aoa.push([]);
                rowOf[block.label] = pushRow(block.label, block.amounts, block.total, function (colL) {
                    return ref('Trading Income', colL) + '-' + ref('Cost of Sales', colL);
                });
                aoa.push([]);
                return;
            }

            aoa.push([safeText(block.name)]);
            var first    = aoa.length + 1;
            $.each(block.items || [], function (i, item) {
                pushRow('    ' + item.name, item.amounts, item.total, null);   // indent detail
            });
            var last     = aoa.length;
            var hasItems = (block.items || []).length > 0;

            // Total seksi = SUM baris detail di atasnya
            rowOf[block.name] = pushRow('Total ' + block.name, block.column_totals, block.total,
                hasItems ? function (colL) { return 'SUM(' + colL + first + ':' + colL + last + ')'; } : null);
            aoa.push([]);
        });

        // Net Profit = Gross Profit + Total Other Income - Total Operating Expenses
        var np = _lastData.net_profit || { amounts: {}, total: 0 };
        pushRow(_lastData.net_profit_label || 'Net Profit', np.amounts, np.total, function (colL) {
            return ref('Gross Profit', colL) + '+' + ref('Other Income', colL) + '-' + ref('Operating Expenses', colL);
        });

        var ws = XLSX.utils.aoa_to_sheet(aoa);

        var widths = [{ wch: 48 }];
        for (var k = 0; k < nCols + (split ? 1 : 0); k++) widths.push({ wch: 22 });
        ws['!cols'] = widths;

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