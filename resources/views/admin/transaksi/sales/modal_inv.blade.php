{{-- ===================================================================== --}}
{{-- MODAL CREATE INVOICE — XERO STYLE — LARAVEL 8 — FIXED                --}}
{{-- ===================================================================== --}}

<style>
/* ── Reset & base ── */
#modalCreateHotel .modal-content {
    border: none;
    border-radius: 8px;
    overflow: hidden;
}

/* ── PAKSA modal selebar viewport, margin kecil ── */
#modalCreateHotel .modal-dialog {
    max-width: calc(100vw - 40px) !important;
    width: calc(100vw - 40px) !important;
    margin: 20px auto !important;
}

/* ── Topbar Xero ── */
.xero-modal-header {
    background: #1c3a5e;
    padding: 8px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.xero-modal-header .breadcrumb-nav {
    font-size: 11px;
    color: #9bb4cc;
    display: flex;
    align-items: center;
    gap: 5px;
}
.xero-modal-header .breadcrumb-nav b { color: #fff; }
.xero-modal-header .header-actions   { display: flex; align-items: center; gap: 7px; }
.btn-xero-ghost {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.35);
    color: #fff; border-radius: 4px;
    padding: 4px 13px; font-size: 11px; cursor: pointer;
}
.btn-xero-approve {
    background: #1ab394; border: none; color: #fff;
    border-radius: 4px 0 0 4px;
    padding: 5px 13px; font-size: 11px; cursor: pointer;
}
.btn-xero-approve-caret {
    background: #17a085; border: none; color: #fff;
    border-radius: 0 4px 4px 0;
    padding: 5px 8px; font-size: 11px; cursor: pointer;
    border-left: 1px solid rgba(255,255,255,0.3);
}

/* ── Banner ── */
.xero-banner {
    background: #1c3a6e; color: #fff;
    padding: 8px 18px; font-size: 11px;
    display: flex; align-items: center;
    justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    flex-shrink: 0;
}
.card-badge {
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 3px; font-size: 8px; font-weight: 700;
    height: 14px; padding: 0 5px;
}
.btn-add-pay {
    background: #1ab394; border: none; color: #fff;
    border-radius: 4px; padding: 4px 12px; font-size: 11px; cursor: pointer;
}

/* ── Invoice title ── */
.invoice-title-row {
    display: flex; align-items: center; gap: 8px; margin-bottom: 16px;
}
.invoice-title-row h5 { font-size: 18px; font-weight: 400; margin: 0; color: #222; }
.badge-draft {
    background: #e8e8e8; color: #555;
    border-radius: 4px; padding: 2px 9px;
    font-size: 11px; font-weight: 400;
}

/* ── Header fields grid ── */
.xero-fields-grid {
    display: grid;
    grid-template-columns: 3fr 1.2fr 1.2fr 1fr;
    gap: 10px 16px;
    margin-bottom: 12px;
    align-items: end;
}
.xero-row-2 {
    display: grid;
    grid-template-columns: 3fr 1.5fr 1.5fr;
    gap: 10px 16px;
    margin-bottom: 14px;
    align-items: end;
}
@media (max-width: 900px) {
    .xero-fields-grid { grid-template-columns: 1fr 1fr; }
    .xero-row-2       { grid-template-columns: 1fr 1fr; }
}
.xero-field { display: flex; flex-direction: column; gap: 3px; }
.xero-field label {
    font-size: 10px; font-weight: 600; color: #555; margin-bottom: 0;
}
.xero-field .form-control {
    border: 1px solid #ccc; border-radius: 4px;
    height: 32px; padding: 4px 8px;
    font-size: 12px; color: #222;
}
.xero-field .form-control:focus { border-color: #1ab394; box-shadow: none; }
.xero-field .input-icon-wrap { position: relative; }
.xero-field .input-icon-wrap > i {
    position: absolute; left: 7px; top: 50%;
    transform: translateY(-50%); color: #bbb;
    font-size: 13px; pointer-events: none; z-index: 1;
}
.xero-field .input-icon-wrap .form-control { padding-left: 26px; }

/* ── Kurs alert ── */
.kurs-alert {
    background: #fff8e1;
    border: 1px solid #ffe082;
    border-radius: 4px;
    padding: 7px 12px;
    margin-bottom: 14px;
    font-size: 11px; color: #555;
    display: flex; align-items: center; gap: 8px;
}

/* ──────────────────────────────────────────────
   LINE ITEMS TABLE — area paling kritis
   ────────────────────────────────────────────── */
.line-items-wrap {
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    overflow-x: auto;          /* scroll horizontal jika perlu */
    overflow-y: visible;       /* JANGAN clip dropdown ke bawah */
    margin-bottom: 10px;
    /* Beri ruang bawah agar dropdown Select2 tidak terpotong */
    padding-bottom: 4px;
}
.line-items-table {
    width: 100%;
    border-collapse: separate;  /* perlu untuk overflow visible */
    border-spacing: 0;
    font-size: 11px;
    /* min-width agar kolom tidak crush — sesuaikan dgn lebar modal */
    min-width: 1350px;
    table-layout: fixed;        /* kolom FIXED — tidak melar/menyempit */
}
.line-items-table thead th {
    background: #f6f6f6;
    padding: 7px 8px;
    font-weight: 600; color: #555;
    border-bottom: 1px solid #e0e0e0;
    white-space: nowrap; font-size: 10px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.line-items-table thead th.col-r { text-align: right; }
.line-items-table tbody td {
    padding: 5px 6px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
    overflow: visible;          /* dropdown tidak terpotong */
}
.line-items-table tbody tr:last-child td { border-bottom: none; }

/* Input & native select di dalam tabel */
.line-items-table td .form-control {
    border: 1px solid #ddd; border-radius: 3px;
    padding: 3px 6px; font-size: 11px;
    height: 30px; width: 100%;
    min-width: 0;
}
.line-items-table td .form-control:focus {
    border-color: #1ab394; box-shadow: none;
}

/* ── PRICE kolom dengan prefix label ── */
.price-col-wrap { display: flex; }
.price-col-wrap .currency-label {
    background: #f0f0f0;
    border: 1px solid #ddd; border-right: none;
    border-radius: 3px 0 0 3px;
    padding: 0 7px; height: 30px;
    display: flex; align-items: center;
    font-size: 10px; font-weight: 600; color: #555;
    white-space: nowrap; flex-shrink: 0;
}
.price-col-wrap .form-control {
    border-radius: 0 3px 3px 0 !important;
}

/* Drag & delete */
.drag-handle { color: #ccc; font-size: 14px; cursor: grab; user-select: none; }
.btn-del-line {
    background: transparent; border: none; color: #ccc;
    padding: 2px 4px; cursor: pointer; font-size: 14px; line-height: 1;
}
.btn-del-line:hover { color: #e74c3c; }

/* ── Table actions ── */
.table-actions { display: flex; gap: 8px; margin-bottom: 18px; }
.btn-dashed {
    background: transparent; border: 1px dashed #1ab394; color: #1ab394;
    border-radius: 4px; padding: 4px 14px; font-size: 11px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 4px;
}
.btn-dashed:hover { background: #e8faf6; }

/* ── Summary ── */
.invoice-summary { display: flex; justify-content: flex-end; margin-bottom: 4px; }
.summary-table { min-width: 280px; font-size: 12px; }
.summary-table td { padding: 4px 0; color: #555; }
.summary-table td:last-child { text-align: right; padding-left: 40px; font-variant-numeric: tabular-nums; }
.summary-table .row-total td {
    font-size: 16px; font-weight: 500; color: #222;
    border-top: 1.5px solid #333; padding-top: 8px;
}

/* ═══════════════════════════════════════════════════════
   SELECT2 FIXES — INI BAGIAN TERPENTING
   ═══════════════════════════════════════════════════════ */

/* 1. Header form — tinggi 32px */
.xero-field .select2-container--bootstrap4 .select2-selection--single {
    height: 32px !important;
    border: 1px solid #ccc !important;
    border-radius: 4px !important;
    display: flex !important;
    align-items: center !important;
}
.xero-field .select2-container--bootstrap4
    .select2-selection--single .select2-selection__rendered {
    line-height: 30px !important;
    font-size: 12px !important;
    padding-left: 8px !important;
    /* KUNCI: teks tidak terpotong */
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    max-width: calc(100% - 24px) !important;
}
.xero-field .select2-container--bootstrap4
    .select2-selection--single .select2-selection__arrow {
    height: 30px !important;
}

/* 2. Tabel detail — tinggi 30px */
.line-items-table .select2-container--bootstrap4 .select2-selection--single {
    height: 30px !important;
    border: 1px solid #ddd !important;
    border-radius: 3px !important;
    display: flex !important;
    align-items: center !important;
}
.line-items-table .select2-container--bootstrap4
    .select2-selection--single .select2-selection__rendered {
    line-height: 28px !important;
    font-size: 11px !important;
    padding-left: 6px !important;
    padding-right: 20px !important;
    /* KUNCI: teks selected tidak terpotong/setengah */
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    max-width: 100% !important;
    display: block !important;
}
.line-items-table .select2-container--bootstrap4
    .select2-selection--single .select2-selection__arrow {
    height: 28px !important;
    width: 20px !important;
    right: 2px !important;
}

/* 3. Select2 container width = 100% dari td */
.line-items-table td .select2-container {
    width: 100% !important;
    min-width: 0 !important;
}

/* 4. Dropdown hasil pencarian — font & ukuran wajar */
.select2-dropdown {
    font-size: 12px !important;
    z-index: 9999 !important;     /* muncul di atas modal */
}
.select2-results__option {
    padding: 6px 10px !important;
    font-size: 12px !important;
    line-height: 1.4 !important;
    white-space: normal !important;   /* bisa wrap — tidak terpotong */
    word-break: break-word !important;
}
.select2-results__option--highlighted {
    background-color: #1ab394 !important;
    color: #fff !important;
}

/* 5. Search box di dalam dropdown */
.select2-search--dropdown .select2-search__field {
    border: 1px solid #ddd !important;
    border-radius: 3px !important;
    padding: 4px 8px !important;
    font-size: 12px !important;
    height: 30px !important;
}

/* 6. Pastikan select2 dengan icon tidak overlap */
.xero-field .input-icon-wrap .select2-container {
    padding-left: 0 !important;
}
.xero-field .input-icon-wrap .select2-container .select2-selection__rendered {
    padding-left: 26px !important;
}
</style>

<div class="modal fade" id="modalCreateHotel" tabindex="-1" role="dialog"
     aria-labelledby="modalCreateHotelLabel" aria-hidden="true">

    {{-- modal-dialog dikendalikan CSS di atas --}}
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">

            {{-- ── TOPBAR ── --}}
            <div class="xero-modal-header">
                <div class="breadcrumb-nav">
                    <i class="ti ti-chevron-left" style="font-size:12px;"></i>
                    <span>Sales overview &rsaquo; </span><b>Invoices</b>
                </div>
                <div class="header-actions">
                    {{-- <button type="button" class="btn-xero-ghost" data-dismiss="modal">
                        Save &amp; close
                    </button> --}}
                    <div class="d-flex">
                        {{-- <button type="button" class="btn-xero-approve" id="btnApproveTop">
                            Approve
                        </button>
                        <button type="button" class="btn-xero-approve-caret">
                            <i class="ti ti-chevron-down" style="font-size:11px;"></i>
                        </button> --}}
                    </div>
                    <span style="color:#9bb4cc;font-size:18px;cursor:pointer;">&#8942;</span>
                </div>
            </div>

            {{-- ── BANNER ── --}}
            <div class="xero-banner">
               
                <button class="btn-add-pay">
                    <i class="ti ti-credit-card" style="font-size:11px;"></i> Add payments
                </button>
            </div>

            {{-- ── FORM ── --}}
            <form id="formCreateHotel">
                @csrf
                <div class="modal-body" style="padding: 20px 26px; overflow: visible;">

                    <div class="invoice-title-row">
                        <h5 id="modalInvoiceTitle">New invoice</h5>
                        <span class="badge-draft" id="invoiceStatusBadge">Draft</span>
                    </div>

                    <input type="hidden" name="idHotelInput" id="idHotelInput">

                    {{-- ── BARIS 1: Contact | Issue date | Due date | Invoice no ── --}}
                    <div class="xero-fields-grid">
                        <div class="xero-field">
                            <label>Contact <span class="text-danger">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-user"></i>
                                <select class="form-control select2-contact"
                                        name="contact_id" id="contact_id"
                                        style="width:100%;" required>
                                </select>
                            </div>
                        </div>

                        <div class="xero-field">
                            <label>Issue date <span class="text-danger">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-calendar"></i>
                                <input type="date" class="form-control"
                                       name="issue_date" id="issue_date" required>
                            </div>
                        </div>

                        <div class="xero-field">
                            <label>Due date</label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-calendar"></i>
                                <input type="date" class="form-control"
                                       name="due_date" id="due_date">
                            </div>
                        </div>

                        <div class="xero-field">
                            <label>Invoice number</label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-hash"></i>
                                <input type="text" class="form-control"
                                       id="invoice_number_display"
                                       placeholder="Auto" readonly
                                       style="background:#f9f9f9;">
                            </div>
                        </div>
                    </div>

                    {{-- ── BARIS 2: Reference | Currency | Amounts are ── --}}
                    <div class="xero-row-2">
                        <div class="xero-field">
                            <label>Reference</label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-bookmark"></i>
                                <input type="text" class="form-control"
                                       name="reference" id="reference">
                            </div>
                        </div>

                        <div class="xero-field">
                            <label>Currency <span class="text-danger">*</span></label>
                            <select class="form-control" name="currency_selected"
                                    id="currency_selected" required>
                                <option value="IDR" selected>IDR &ndash; Rupiah</option>
                                <option value="SAR">SAR &ndash; Saudi Riyal</option>
                            </select>
                        </div>

                        <div class="xero-field">
                            <label>Amounts are</label>
                            <select class="form-control" name="amount_are" id="amount_are">
                                <option value="0" selected>No Tax</option>
                                <option value="1">Tax Exclusive</option>
                                <option value="2">Tax Inclusive</option>
                            </select>
                        </div>
                    </div>

                    {{-- ── KURS ALERT ── --}}
                    <div class="kurs-alert">
                        <i class="ti ti-info-circle" style="font-size:15px;color:#f0a500;"></i>
                        <span><strong>Info Kurs:</strong> Harga 1 SAR saat ini diestimasi
                            <b id="text_currency">–</b>
                        </span>
                    </div>

                    {{-- ── LINE ITEMS TABLE ── --}}
                    <div class="line-items-wrap">
                        <table class="line-items-table" id="lineItemsTable">
                            <colgroup>
                                <col style="width:28px;">      {{-- drag --}}
                                <col style="width:200px;">     {{-- Item --}}
                                <col style="width:180px;">     {{-- Desc --}}
                                <col style="width:62px;">      {{-- Qty --}}
                                <col style="width:140px;">     {{-- Price --}}
                                <col style="width:66px;">      {{-- Disc --}}
                                <col style="width:160px;">     {{-- Account --}}
                                <col style="width:100px;">     {{-- Tax rate --}}
                                <col style="width:88px;">      {{-- Tax amt --}}
                                <col style="width:110px;">     {{-- Amount IDR --}}
                                <col style="width:150px;">     {{-- Nama Paket --}}
                                <col style="width:130px;">     {{-- Divisi --}}
                                <col style="width:28px;">      {{-- delete --}}
                            </colgroup>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th class="text-center">Qty</th>
                                    <th>Price</th>
                                    <th>Disc.</th>
                                    <th>Account</th>
                                    <th>Tax rate</th>
                                    <th class="col-r">Tax amt</th>
                                    <th class="col-r">Amount IDR</th>
                                    <th>Nama Paket</th>
                                    <th>Divisi</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="lineItemsBody">
                                {{-- diisi JS --}}
                            </tbody>
                        </table>
                    </div>

                    <div class="table-actions">
                        <button type="button" class="btn-dashed" id="btn-add-row">
                            <i class="ti ti-plus" style="font-size:12px;"></i> Add row
                        </button>
                    </div>

                    {{-- ── SUMMARY ── --}}
                    <div class="invoice-summary">
                        <table class="summary-table">
                            <tr>
                                <td>Subtotal (SAR)</td>
                                <td id="summarySubtotalSAR">–</td>
                            </tr>
                            <tr>
                                <td>Subtotal (IDR)</td>
                                <td id="summarySubtotalIDR">Rp 0</td>
                            </tr>
                            <tr>
                                <td>Total tax</td>
                                <td id="summaryTax">0.00</td>
                            </tr>
                            <tr class="row-total">
                                <td>Total</td>
                                <td id="summaryTotal">Rp 0</td>
                            </tr>
                        </table>
                    </div>

                </div>{{-- /.modal-body --}}

                <div class="modal-footer bg-white"
                     style="border-top:1px solid #eee; padding:10px 22px;">
                    <input type="hidden" name="action_type" id="actionTypeValue" value="">
                    <button type="button" class="btn btn-secondary btn-sm"
                            data-dismiss="modal">
                        <i class="ti ti-x mr-1"></i> Batal
                    </button>
                    <div class="btn-group dropup">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Save
                        </button>
                        <div class="dropdown-menu dropdown-menu-right shadow">
                            <button type="submit" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="1">
                                <i class="ti ti-calendar mr-2" style="font-size: 1.2rem;"></i>
                                <span>Approve</span>
                            </button>
                            <button type="submit" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="0">
                                <i class="ti ti-bookmark mr-2" style="font-size: 1.2rem;"></i>
                                <span>Save draft</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>