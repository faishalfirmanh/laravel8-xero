{{--
    =====================================================================
    ASUMSI ROUTE (silakan sesuaikan nama route jika berbeda di project):
    - 'get-all-coa-select2'        -> sudah ada di project (dipakai utk Accounts)
    - 'tracking-by-parent'         -> sudah ada di project (dipakai utk Divisi & Nama Paket)
    - 'report-account-transactions'-> BARU, perlu dibuat route + controller-nya.
      Controller harus support DataTables server-side (draw/start/length/search)
      DAN menerima extra filter: account_id, date_start, date_end,
      divisi_ids[], paket_ids[].
      Response tiap baris idealnya:
      { date, source, description, reference, currency,
        debit_source, credit_source, debit_idr, credit_idr, running_balance_idr }
    =====================================================================
--}}
@extends('layouts.app')

@section('content')

<style>
    /* ── FILTER BAR ── */
    .rpt-filter-card {
        background: #fff;
        border: 1px solid #e6e6e6;
        border-radius: 8px;
        padding: 16px 18px;
        margin-bottom: 18px;
    }
    .rpt-filter-bar {
        display: flex;
        align-items: flex-end;
        gap: 18px;
        flex-wrap: wrap;
    }
    .rpt-filter-field { display: flex; flex-direction: column; gap: 5px; min-width: 210px; }
    .rpt-filter-field.rpt-field-btn { min-width: auto; margin-left: auto; }
    .rpt-filter-field label {
        font-size: 11px; font-weight: 700; color: #555;
        text-transform: none; margin-bottom: 0;
    }
    .rpt-filter-field .form-control {
        border: 1px solid #cfd4d8; border-radius: 4px;
        height: 36px; font-size: 12.5px; color: #222;
    }
    .rpt-filter-field .form-control:focus { border-color: #1a73c8; box-shadow: none; }

    .rpt-date-range { display: flex; align-items: center; gap: 8px; }
    .rpt-date-range input { flex: 1; min-width: 0; }
    .rpt-date-range .rpt-date-sep { color: #999; font-size: 13px; }

    .rpt-tracking-btn {
        display: flex; align-items: center; gap: 8px;
        height: 36px; padding: 0 12px;
        border: 1px solid #cfd4d8; border-radius: 4px;
        background: #fff; color: #333; font-size: 12.5px;
        cursor: pointer; white-space: nowrap;
    }
    .rpt-tracking-btn:hover { border-color: #1a73c8; }
    .rpt-tracking-btn i { font-size: 14px; color: #666; }
    .rpt-tracking-btn .rpt-tf-badge {
        background: #1a73c8; color: #fff; border-radius: 10px;
        font-size: 10px; font-weight: 700; padding: 1px 7px; margin-left: 2px;
    }

    .btn-rpt-run {
        background: #1a73c8; border: none; color: #fff;
        height: 36px; padding: 0 22px; border-radius: 4px;
        font-size: 12.5px; font-weight: 600;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-rpt-run:hover { background: #155fa3; color:#fff; }

    /* ── TRACKING FILTER SLIDE PANEL ── */
    .tf-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.25); z-index: 1050;
    }
    .tf-panel {
        display: none; position: fixed; top: 0; right: 0; bottom: 0;
        width: 340px; max-width: 92vw;
        background: #fff; z-index: 1060;
        box-shadow: -6px 0 18px rgba(0,0,0,0.15);
        flex-direction: column;
    }
    .tf-panel-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 18px; border-bottom: 1px solid #eee;
        font-weight: 700; font-size: 15px; color: #222;
    }
    .tf-close-btn {
        background: transparent; border: none; font-size: 22px;
        line-height: 1; color: #999; cursor: pointer;
    }
    .tf-close-btn:hover { color: #333; }
    .tf-panel-body { padding: 14px 18px; overflow-y: auto; flex: 1 1 auto; }
    .tf-search-input {
        border: 1px solid #ddd; border-radius: 4px;
        height: 34px; font-size: 12.5px; margin-bottom: 16px;
    }
    .tf-section-title { font-size: 12px; font-weight: 700; color: #333; margin-bottom: 8px; }

    .tf-category-group { border-top: 1px solid #eee; padding: 10px 0; }
    .tf-category-head {
        display: flex; align-items: center; gap: 8px;
        cursor: pointer; font-size: 12.5px; font-weight: 600; color: #333;
    }
    .tf-chevron { font-size: 13px; color: #777; }
    .tf-category-body { padding: 10px 4px 2px 22px; }
    .tf-category-actions {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 8px;
    }
    .tf-select-all { font-size: 11.5px; color: #1a73c8; }
    .tf-archived-toggle { font-size: 11px; color: #888; margin: 0; display: flex; align-items: center; gap: 4px; }
    .tf-checklist { max-height: 220px; overflow-y: auto; }
    .tf-checklist .form-check { margin-bottom: 6px; }
    .tf-checklist label { font-size: 12px; color: #333; cursor: pointer; }
    .tf-loading, .tf-empty { font-size: 11.5px; color: #aaa; padding: 6px 0; }

    .tf-panel-footer {
        display: flex; justify-content: flex-end; gap: 8px;
        padding: 12px 18px; border-top: 1px solid #eee;
    }

    /* ── RESULT CARD ── */
    .rpt-result-card { background:#fff; border:1px solid #e6e6e6; border-radius:8px; padding: 20px 22px; }
    .rpt-result-title { font-size: 16px; font-weight: 700; color: #222; margin: 0; }
    .rpt-result-sub { font-size: 12px; color: #777; margin-top: 2px; }

    .rpt-table {
        font-size: 11.5px; width: 100%;
    }
    .rpt-table thead th {
        background: #f7f8f9; color: #555; font-weight: 700; font-size: 10.5px;
        border-bottom: 1px solid #e2e2e2; white-space: nowrap; padding: 8px 10px;
    }
    .rpt-table tbody td {
        padding: 8px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; color: #333;
    }
    .rpt-table td.text-right, .rpt-table th.text-right { text-align: right; font-variant-numeric: tabular-nums; }
    .rpt-table tbody tr:hover { background: #fafcff; }

    @media (max-width: 900px) {
        .rpt-filter-field { min-width: 100%; }
        .rpt-filter-field.rpt-field-btn { margin-left: 0; }
    }
</style>

<div class="rpt-filter-card">
    <div class="rpt-filter-bar">

        {{-- ── ACCOUNTS ── --}}
        <div class="rpt-filter-field">
            <label>Accounts</label>
            <select class="form-control select2-rpt-account" id="filter_account_id" style="width:100%;">
                <option value="">-- All accounts --</option>
            </select>
        </div>

        {{-- ── DATE RANGE ── --}}
        <div class="rpt-filter-field">
            <label>Date range</label>
            <div class="rpt-date-range">
                <input type="date" class="form-control" id="filter_date_start">
                <span class="rpt-date-sep">&rarr;</span>
                <input type="date" class="form-control" id="filter_date_end">
            </div>
        </div>

        {{-- ── TRACKING CATEGORY (opens slide panel) ── --}}
        <div class="rpt-filter-field">
            <label>Tracking category</label>
            <button type="button" class="rpt-tracking-btn" id="btn_open_tf">
                <i class="ti ti-filter"></i>
                <span id="tf_label_text">All categories</span>
                <span class="rpt-tf-badge" id="tf_label_badge" style="display:none;">0</span>
            </button>
        </div>

        {{-- ── SINGLE FILTER BUTTON ── --}}
        <div class="rpt-filter-field rpt-field-btn">
            <button type="button" class="btn-rpt-run" id="btn_run_report">
                <i class="ti ti-search"></i> Filter
            </button>
        </div>

    </div>
</div>

{{-- ── TRACKING CATEGORY SLIDE PANEL (mengikuti tampilan gambar ke-2) ── --}}
<div class="tf-overlay" id="tfOverlay"></div>
<div class="tf-panel" id="tfPanel">
    <div class="tf-panel-header">
        <span>Filter</span>
        <button type="button" class="tf-close-btn" id="btn_close_tf">&times;</button>
    </div>

    <div class="tf-panel-body">
        <input type="text" class="form-control tf-search-input" id="tf_search_input" placeholder="Search filters">

        <div class="tf-section-title">Tracking categories</div>

        {{-- Divisi --}}
        <div class="tf-category-group">
            <div class="tf-category-head" data-target="#tfBodyDivisi">
                <i class="ti ti-chevron-down tf-chevron"></i>
                <span>Divisi</span>
            </div>
            <div class="tf-category-body" id="tfBodyDivisi" style="display:none;">
                <div class="tf-category-actions">
                    <a href="javascript:;" class="tf-select-all" data-group="divisi">Select all</a>
                    <label class="tf-archived-toggle">
                        <input type="checkbox" class="tf-show-archived" data-group="divisi"> Show archived
                    </label>
                </div>
                <div class="tf-checklist" id="tfListDivisi">
                    <div class="tf-loading">Memuat...</div>
                </div>
            </div>
        </div>

        {{-- Nama Paket --}}
        <div class="tf-category-group">
            <div class="tf-category-head" data-target="#tfBodyPaket">
                <i class="ti ti-chevron-up tf-chevron"></i>
                <span>Nama Paket</span>
            </div>
            <div class="tf-category-body" id="tfBodyPaket">
                <div class="tf-category-actions">
                    <a href="javascript:;" class="tf-select-all" data-group="paket">Select all</a>
                    <label class="tf-archived-toggle">
                        <input type="checkbox" class="tf-show-archived" data-group="paket"> Show archived
                    </label>
                </div>
                <div class="tf-checklist" id="tfListPaket">
                    <div class="tf-loading">Memuat...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="tf-panel-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_tf_clear">Clear</button>
        <button type="button" class="btn btn-primary btn-sm" id="btn_tf_apply">Apply</button>
    </div>
</div>

{{-- ── RESULT ── --}}
<div class="rpt-result-card">
    <h5 class="rpt-result-title" id="report_title_text">All accounts &ndash; Transactions</h5>
    <div class="rpt-result-sub" id="report_period_text"></div>

    <div class="table-responsive mt-3">
        <table class="table table-bordered rpt-table w-100" id="tableAccountTransaction">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th>Currency</th>
                    <th class="text-right">Debit (Source)</th>
                    <th class="text-right">Credit (Source)</th>
                    <th class="text-right">Debit (IDR)</th>
                    <th class="text-right">Credit (IDR)</th>
                    <th class="text-right">Running Balance (IDR)</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {

    // =====================================================================
    // 1. ACCOUNTS — select2 AJAX (reuse route yang sudah ada)
    // =====================================================================
    $('.select2-rpt-account').select2({
        placeholder: "Cari account...",
        allowClear: true,
        width: '100%',
        ajax: {
            url: "{{ route('get-all-coa-select2') }}",
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return { page: params.page || 1, keyword: params.term || '', limit: 10 };
            },
            processResults: function (response, params) {
                params.page = params.page || 1;
                return {
                    results: $.map(response.data?.data || [], function (item) {
                        return { id: item.id, text: item.name };
                    }),
                    pagination: { more: response.data?.next_page_url !== null }
                };
            },
            cache: true
        }
    });

    // =====================================================================
    // 2. DATE RANGE — default: bulan berjalan ("This month")
    // =====================================================================
    (function setDefaultDateRange() {
        const now = new Date();
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        const last  = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        $('#filter_date_start').val(first.toISOString().split('T')[0]);
        $('#filter_date_end').val(last.toISOString().split('T')[0]);
    })();

   
});
</script>
@endpush