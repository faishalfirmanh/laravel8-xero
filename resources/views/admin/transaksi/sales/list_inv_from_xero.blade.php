@extends('layouts.app')

@section('content')

<style>
    /* Styling khusus untuk tombol hapus agar sejajar vertikal */
    .btn-remove-row {
        margin-top: 32px; /* Menyesuaikan tinggi label */
    }

    .select2-container .select2-selection--single {
        border: 2px solid #888 !important; /* Ubah 2px sesuai ketebalan yg diinginkan */
        height: calc(1.5em + 0.75rem + 4px) !important; /* Sesuaikan tinggi agar isi tidak gepeng */
    }

    .select2-container--default.select2-container--open .select2-selection--single,
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--bootstrap4.select2-container--focus .select2-selection {
        border: 2px solid #007bff !important; /* Warna biru primary saat aktif */
        box-shadow: none !important; /* Opsional: Hilangkan glow jika ingin flat */
    }

    .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: calc(1.5em + 0.75rem) !important;
        margin-top: 1px; /* Geser sedikit text biar tengah */
    }
    .select2-container .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + 0.75rem) !important;
        top: 2px !important; /* Geser panah biar tengah */
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #printArea, #printArea * {
            visibility: visible;
        }

        #printArea {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
    }

    #modalCreateHotel .modal-body {
        overflow-y: auto  !important;   /* OVERRIDE inline style overflow:visible */
        overflow-x: hidden;
        flex: 1 1 auto;                 /* ambil sisa tinggi yang tersedia */
    }

    #paymentFormSection {
        margin: 0 -26px -20px;         /* kompensasi padding modal-body */
    }

    /**/
    /* ── PAYMENT HISTORY SECTION ── */
        #paymentHistorySection {
            display: none;
            margin-top: 16px;
            margin-bottom: 12px;
        }
        .pay-history-wrap {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            font-size: 11px;
        }
        .pay-history-head {
            background: #f6f6f6;
            padding: 7px 12px;
            border-bottom: 1px solid #e0e0e0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .pay-history-head span { font-weight: 600; color: #444; font-size: 11px; }
        .pay-history-head small { font-size: 10px; color: #999; }
        .pay-history-table { width: 100%; border-collapse: collapse; }
        .pay-history-table thead th {
            background: #fafafa;
            padding: 6px 10px;
            font-size: 10px; font-weight: 600; color: #555;
            border-bottom: 1px solid #eee;
            white-space: nowrap;
        }
        .pay-history-table tbody td {
            padding: 5px 10px;
            border-bottom: 1px solid #f5f5f5;
            font-size: 11px; color: #333;
            vertical-align: middle;
        }
        .pay-history-table tbody tr:last-child td { border-bottom: none; }
        .pay-history-table tfoot td {
            padding: 6px 10px;
            background: #f0fdf8;
            font-weight: 600; font-size: 11px;
        }
        .pay-history-empty {
            padding: 14px; text-align: center;
            color: #aaa; font-size: 11px;
        }
        .btn-del-pay {
            background: transparent; border: 1px solid #e74c3c;
            color: #e74c3c; border-radius: 3px;
            padding: 2px 6px; font-size: 11px; cursor: pointer; line-height: 1;
        }
        .btn-del-pay:hover { background: #e74c3c; color: #fff; }

        /*modal ---*/
        /* ── Reset & base ── */
#modalCreateHotel .modal-content {
    border: none;
    border-radius: 8px;
    overflow: hidden;               /* TAMBAH: wajib untuk scrollable */
    max-height: calc(100vh - 40px); /* TAMBAH: batas tinggi = viewport - margin */
    display: flex;                  /* TAMBAH: flex column agar body bisa grow */
    flex-direction: column;

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
    z-index: 9999 !important;      /* muncul di atas modal */
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

/* untuk form payment */
/* ── PAYMENT FORM SECTION ── */
.payment-form-section {
    display: none;
    border-top: 2px solid #1ab394;
    background: #f4fdf9;
}
.payment-form-section .payment-form-inner {
    padding: 14px 26px 18px;
}
.payment-form-section .payment-form-title {
    display: flex; align-items: center; gap: 7px;
    margin-bottom: 12px;
}
.payment-form-section .payment-form-title span {
    font-size: 12px; font-weight: 600; color: #1c3a5e;
}
.payment-form-section .payment-form-title .btn-close-payment {
    margin-left: auto; background: transparent; border: none;
    color: #aaa; cursor: pointer; font-size: 18px; line-height: 1;
    padding: 0 2px;
}
.payment-form-section .payment-form-title .btn-close-payment:hover { color: #e74c3c; }
.payment-fields-grid {
    display: grid;
    grid-template-columns: 1fr 1.6fr 1fr 1fr;
    gap: 10px 16px;
    align-items: end;
}
@media (max-width: 900px) {
    .payment-fields-grid { grid-template-columns: 1fr 1fr; }
}
.payment-form-footer {
    display: flex; justify-content: flex-end; gap: 8px;
    margin-top: 14px; padding-top: 12px;
    border-top: 1px solid #d8f0e8;
}
.btn-payment-cancel {
    background: transparent; border: 1px solid #ccc; color: #555;
    border-radius: 4px; padding: 5px 16px; font-size: 11px; cursor: pointer;
}
.btn-payment-cancel:hover { background: #f5f5f5; }
.btn-payment-save {
    background: #1ab394; border: none; color: #fff;
    border-radius: 4px; padding: 5px 18px; font-size: 11px;
    font-weight: 600; cursor: pointer;
    display: inline-flex; align-items: center; gap: 5px;
}
.btn-payment-save:hover { background: #17a085; }

/* --- CSS TAMBAHAN UNTUK SCROLLABLE PAYMENT MODAL --- */
#paymentModal .modal-content {
    max-height: calc(100vh - 40px);
    display: flex;
    flex-direction: column;
}
#paymentModal .modal-body {
    overflow-y: auto !important;
    overflow-x: hidden;
    flex: 1 1 auto;
}

/* Styling agar thumbnail Dropzone bisa di-klik */        
#buktiDropzone .dz-preview .dz-image {
    cursor: pointer;
    transition: transform 0.2s;
}
#buktiDropzone .dz-preview .dz-image:hover {
    transform: scale(1.05); /* Sedikit membesar saat di-hover */
}
</style>


<div class="card shadow mb-5">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 font-weight-bold text-primary">List Invoices From Xero </h5>

        <button type="button" id="button_add_hotel" class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#modalCreateHotel">
            <i class="ti ti-plus me-1"></i> Add Invoices
        </button>
    </div>

    <div id="loadingIndicator" class="text-center my-5" style="display:none;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="sr-only">Loading...</span>
        </div>
        <div class="mt-3 text-muted font-weight-bold">Memuat data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-hover table-striped table-bordered w-100" id="tableHotel">
            <thead class="thead-dark">
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th>No Invoice</th>
                    <th>Nama Contact</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>



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
               
                <button class="btn-add-pay" id="btn_add_payment_inv">
                    <i class="ti ti-credit-card" style="font-size:11px;"></i> Add payments
                </button>
            </div>

            {{-- ── FORM ── --}}
            <form id="formCreateHotel">
                @csrf
                <div class="modal-body" style="padding: 20px 26px;">

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
                        <button type="button" class="btn-dashed" id="btn-show-dropzone" style="border-color: #007bff; color: #007bff; margin-left: 10px;">
                            <i class="ti ti-upload" style="font-size:12px;"></i> Upload Bukti
                        </button>
                    </div>

                    <div id="dropzone-container" style="display: none; margin-bottom: 20px;">
                        <div class="dropzone" id="buktiDropzone">
                            <div class="dz-message" data-dz-message><span>Klik atau Drop gambar bukti di sini (Bisa pilih banyak file)</span></div>
                        </div>
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

                   

                    <div class="payment-form-section" id="paymentFormSection">
                        <div class="payment-form-inner">

                            {{-- Title bar --}}
                            <div class="payment-form-title">
                                <i class="ti ti-credit-card" style="font-size:15px; color:#1ab394;"></i>
                                <span>Add Payment</span>
                                <button type="button" class="btn-close-payment" id="btnClosePayment"
                                        title="Tutup">&times;</button>
                            </div>

                            {{-- Fields sejajar --}}
                            <div class="payment-fields-grid">

                                {{-- Date Paid --}}
                                <div class="xero-field">
                                    <label>Date Paid <span class="text-danger">*</span></label>
                                    <div class="input-icon-wrap">
                                        <i class="ti ti-calendar"></i>
                                        <input type="date" class="form-control"
                                            name="date_transaction" id="date_paid">
                                    </div>
                                </div>

                                {{-- Account Bank --}}
                                <div class="xero-field">
                                    <label>Account Bank <span class="text-danger">*</span></label>
                                    <select class="form-control select2-account-bank"
                                            name="uuid_bank" id="account_bank"
                                            style="width:100%;">
                                        <option value="">-- Pilih Akun --</option>
                                        {{-- populate via JS / AJAX --}}
                                    </select>
                                </div>

                                {{-- Amount Paid --}}
                                <div class="xero-field">
                                    <label>Amount Paid <span class="text-danger">*</span></label>
                                    <div class="input-icon-wrap">
                                        <i class="ti ti-cash"></i>
                                        <input type="number" class="form-control"
                                            name="nominal_receive" id="amount_paid"
                                            min="0" step="0.01" placeholder="0.00">
                                    </div>
                                </div>

                                {{-- Reference --}}
                                <div class="xero-field">
                                    <label>Reference</label>
                                    <div class="input-icon-wrap">
                                        <i class="ti ti-bookmark"></i>
                                        <input type="text" class="form-control"
                                            name="reference_detail" id="payment_reference"
                                            placeholder="Optional">
                                    </div>
                                </div>

                            </div>{{-- /.payment-fields-grid --}}

                            {{-- Actions --}}
                            <div class="payment-form-footer">
                                <button type="button" class="btn-payment-cancel" id="btnCancelPayment">
                                    Batal
                                </button>
                                <button type="button" class="btn-payment-save" id="btnSavePayment">
                                    <i class="ti ti-check" style="font-size:12px;"></i>
                                    Save Payment
                                </button>
                            </div>

                        </div>{{-- /.payment-form-inner --}}
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



<div class="modal fade" id="invoiceModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content" id="printArea">

      <div class="modal-header">
        <h5 class="modal-title">Invoice Hotel</h5>
        <button type="button" class="close d-print-none" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body">

        <div class="text-center mb-3">
          <h5 class="font-weight-bold" id="hotel_name"></h5>
          <small>No Invoice: <span id="no_invoice"></span></small>
        </div>

        <div class="row">
          <div class="col-12 col-md-6">
            <input type="hidden" id="id_invoice_view_detail" name="id_invoice_view_detail"/>
            <p><strong>Nama Pemesan:</strong><br><span id="nama_pemesan"></span></p>
          </div>
          <div class="col-6 col-md-3">
            <p><strong>Check In:</strong><br><span id="check_in_inv"></span></p>
          </div>
          <div class="col-6 col-md-3">
            <p><strong>Check Out:</strong><br><span id="check_out_inv"></span></p>
          </div>
        </div>
        <div class="row">
            <div class="col-12 col-md-6">
                <p><strong>Tanggal transaksi :</strong><br><span id="date_trans_inv"></span></p>
            </div>
        </div>

        <hr>

        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead class="thead-light">
              <tr>
                <th>Type Room</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Harga</th>
                <th class="text-right">Total Malam</th>
                <th class="text-right">Total</th>
              </tr>
            </thead>
            <tbody id="detail_rows"></tbody>
          </table>
        </div>

        <div class="text-right">
          <h6>Total Payment:</h6>
          <h5 class="font-weight-bold">SAR <span id="total_payment"></span></h5>
          <h5 class="font-weight-bold">Rp <span id="total_payment_rp"></span></h5>
        </div>

      </div>

      <div class="modal-footer d-print-none">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" onclick="printInvoice()">
          <i class="fa fa-print"></i> Download PDF
        </button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content" id="printArea">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="paymentModalLabel">
            <i class="fa fa-money-bill-wave mr-2"></i> Form Pembayaran
        </h5>
        <button type="button" class="close text-white d-print-none" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">

        <div class="text-center mb-4">
          <h4 class="font-weight-bold mb-1" id="hotel_name_display"></h4>
          <span class="badge badge-light border px-3 py-2">
              No Invoice: <span id="no_invoice_display" class="font-weight-bold text-primary">...</span>
          </span>
        </div>

        <div class="row mb-4 text-center">
            <div class="col-md-4">
                <div class="card border-primary mb-2">
                    <div class="card-body py-2">
                        <small class="text-muted font-weight-bold text-uppercase">Total Tagihan</small>
                        <h5 class="font-weight-bold text-primary mb-0" id="summary_total">0</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success mb-2">
                    <div class="card-body py-2">
                        <small class="text-muted font-weight-bold text-uppercase">Sudah Dibayar</small>
                        <h5 class="font-weight-bold text-success mb-0" id="summary_paid">0</h5>
                        <h5 class="font-weight-bold text-success mb-0" id="sum_paid_rp">0</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-danger mb-2">
                    <div class="card-body py-2">
                        <small class="text-muted font-weight-bold text-uppercase">Sisa Kekurangan</small>
                        <h5 class="font-weight-bold text-danger mb-0" id="summary_remaining">0</h5>
                        <h5 class="font-weight-bold text-danger mb-0" id="sum_pem_rp">0</h5>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <h6 class="font-weight-bold mb-3"><i class="fa fa-history mr-1"></i> Riwayat Pembayaran</h6>
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-striped table-sm text-center" id="tablePaymentList">
                <thead class="thead-dark">
                    <tr>
                        <th width="5%">No</th>
                        <th>Tanggal</th>
                        <th>Nama Bank</th>
                        <th>Keterangan / Ref</th>
                        <th>Nominal</th>
                    </tr>
                </thead>
                <tbody id="payment_list_body">
                    <tr><td colspan="5" class="text-muted">Belum ada data pembayaran</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card bg-light d-print-none">
            <div class="card-body">
                <h6 class="font-weight-bold mb-3 text-primary"><i class="fa fa-plus-circle mr-1"></i> Tambah Pembayaran Baru</h6>
                <a href="javascript:;" style="margin-left:5px;" class="text-info clear-edit-pay"><i class="ti ti-plus"></i></a>
                <form id="formSubmitPayment">
                    <input type="hidden" id="row_payment_id" name="row_payment_id">
                    <input type="hidden" id="invoices_id_parent" name="invoices_id_parent">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Tanggal Bayar</label>
                            <input type="date" class="form-control form-control-sm" id="input_payment_date" name="date_transfer" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Pilih Bank</label>
                            <select class="form-control select2-account-bank-modal2"
                                    name="uuid_bank" id="account_bank_modal_2"
                                    style="width:100%;">
                                <option value="">-- Pilih Akun --</option>
                                {{-- populate via JS / AJAX --}}
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small font-weight-bold">Nominal (Rupiah)</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text font-weight-bold">Rupiah</span>
                                </div>
                                <input type="number" class="form-control font-weight-bold" id="input_payment_nominal" name="payment_idr" placeholder="0" min="1"  required>
                            </div>
                        </div>
                        <div class="form-group col-md-5">
                            <label class="small font-weight-bold">Catatan / Ref</label>
                            <input type="text" class="form-control form-control-sm" id="input_payment_ref" name="desc" placeholder="Contoh: Transfer Bank / Cash">
                        </div>
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-success shadow-sm" id="btnSavePayment">
                            <i class="fa fa-save mr-1"></i> Simpan Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>

      </div>

      <div class="modal-footer d-print-none bg-white">
        <button type="button" id="close_modal_payment" class="btn btn-secondary" data-dismiss="modal">
            <i class="fa fa-times mr-1"></i> Tutup
        </button>
        {{-- <button type="button" class="btn btn-info" onclick="window.print()">
          <i class="fa fa-print mr-1"></i> Print Laporan
        </button> --}}
      </div>

    </div>
  </div>
</div>


<div class="modal fade" id="previewImageModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">
                    <span aria-hidden="true" style="font-size: 2.5rem;">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="previewImageModalSrc" src="" alt="Preview Gambar" style="max-width: 100%; max-height: 80vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
     function printInvoice() {
      let id_nya = $("#id_invoice_view_detail").val();
        if(!id_nya) {
            alert("ID Invoice tidak ditemukan");
            return;
        }
        let url = "{{ route('invoice_hotel_print', ':id') }}";
        url = url.replace(':id', id_nya);
        window.open(url, '_blank');
    }


    var table;

    const now = new Date();
    const year  = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');






    function initRoomSelect2(element) {
        $(element).select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalCreateHotel'),
            placeholder: "Pilih Tipe",
            allowClear: true
        });
    }

    // FUNGSI UTAMA: Membuat HTML Baris Kamar (Bisa dipakai Edit & Tambah Baru)
   

    Dropzone.autoDiscover = false;
    let myDropzone;
    let isClearingDropzone = false;

    $(function() {
        // 1. Inisialisasi Dropzone
        myDropzone = new Dropzone("#buktiDropzone", {
            url: "{{ route('uploadImage-sales-inv') }}",
            autoProcessQueue: false, // PENTING: Jangan langsung upload saat gambar dipilih
            uploadMultiple: true,
            parallelUploads: 10,
            maxFiles: 10,
            acceptedFiles: "image/*",
            addRemoveLinks: true,
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem("token"),
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            init: function() {
                // Saat proses upload berjalan, kirimkan ID Invoice
                this.on("sending", function(file, xhr, formData) {
                    // Ambil ID dari hidden input (Bisa dari edit, atau ID baru setelah save form)
                    formData.append("invoice_id", $('#idHotelInput').val()); 
                });

                // Jika semua file berhasil diupload
                this.on("successmultiple", function(files, response) {
                    Swal.fire('Sukses!', 'Data invoice dan bukti berhasil disimpan.', 'success');
                    $('#modalCreateHotel').modal('hide');
                    table.ajax.reload(null, false);
                });

                // Jika terjadi error saat upload
                this.on("errormultiple", function(files, response) {
                    Swal.fire('Peringatan', 'Invoice tersimpan, namun gagal mengupload gambar.', 'warning');
                    table.ajax.reload(null, false);
                });

                //hapus
                this.on("removedfile", function(file) {
                    // Hanya eksekusi AJAX hapus jika file tersebut berasal dari server

                    if (isClearingDropzone) {
                        return; 
                    }

                    if (file.isFromServer) {
                        $.ajax({
                            url: "{{ route('remove-image-sales-inv') }}",
                            type: "POST",
                            data: { file_name: file.name },
                            headers: {
                                'Authorization': 'Bearer ' + localStorage.getItem("token"),
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                if(response.success) {
                                    console.log("Berhasil:", response.message);
                                    // Optional: Tampilkan toast / notifikasi kecil bahwa gambar dihapus
                                }
                            },
                            error: function(err) {
                                console.error("Gagal menghapus gambar:", err);
                                Swal.fire('Gagal!', 'Gambar gagal dihapus dari server.', 'error');
                            }
                        });
                    }
                    // Jika file.isFromServer false/undefined, Dropzone hanya akan menghapus antrean di browser secara diam-diam.
                });


                this.on("addedfile", function(file) {
                file.previewElement.addEventListener("click", function(e) {
                    // Cegah klik agar tidak memicu dialog "Pilih File" Dropzone lagi jika diklik pas di gambar
                    e.stopPropagation(); 
                    e.preventDefault();
                    let imageUrl = file.url || file.dataURL;
                    if (!imageUrl && file.status === Dropzone.ADDED) {
                        imageUrl = URL.createObjectURL(file);
                    }
                    if (imageUrl) {
                        $('#previewImageModalSrc').attr('src', imageUrl);
                        $('#previewImageModal').modal('show');
                    }
                });
            });
            }
        });

        // 2. Toggle Tampilkan/Sembunyikan Area Dropzone
        $('#btn-show-dropzone').on('click', function() {
            $('#dropzone-container').slideToggle(200);
        });
    });
   

    let columnHotel = [
        {
            data: null, className: "text-center",
            render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
        },
        { data: 'invoice_number', name: 'invoice_number' },
        { data: 'contact_name', name: 'contact_name' },
        { data: 'issue_date', name: 'issue_date' },
        { data: 'due_date', name: 'due_date' },
        {
            data: 'invoice_total', 
            render: function(data,type,row){
                return formatCurrency(data)
            } 
        }, // Sesuaikan jika nama hotel ada relasi
        {
            data: 'status', name: 'status',
            render: function(data){
                return data;
                // if(data != 'PAID'){
                //     return '<span class="badge badge-danger">Belum dibayar</span>'
                // }else{
                //     return '<span class="badge badge-primary">Lunas</span>'
                // }
            }
        },
        {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data, type, row) {
                return `
                    <a href="javascript:;" data-id="${data}" class="text-primary edit-hotel mr-2" title="Edit Invoice"><i class="ti ti-pencil"></i></a>
                    <a href="javascript:;" data-id="${data}" class="text-success show-payment-modal" title="Payment History"><i class="ti ti-credit-card"></i></a>
                `;
            },
        }
       ];

    table = initGlobalDataTableToken('#tableHotel', `{{ route('list-inv-xero-local') }}`, columnHotel, { "kolom_name": "contact_name" });

    function formatDate(date) {
        const [y, m, d] = date.split('-');
        return `${d}/${m}/${y}`;
    }

     $('#tableHotel').on('click', '.edit-hotel', function() {
        const id      = $(this).data('id');
        const rowData = table.row($(this).parents('tr')).data();

        // Reset dulu ke state kosong
        //resetModal();

        // Set judul & ID
        $('#modalInvoiceTitle').text('Edit Invoice ' + (rowData.invoice_number || ''));
        $('#idHotelInput').val(id);//id paretn

        // Buka modal
        $('#modalCreateHotel').modal('show');

        // Load data SETELAH modal benar-benar tampil (penting untuk Select2)
        $('#modalCreateHotel').one('shown.bs.modal', function () {
            loadInvoice(id);
            loadDropzoneImages(id);
        });
        
    });


    function destroyRowSelect2() {
        $('#lineItemsBody').find(
            '.select2-item, .select2-account, .select2-paket, .select2-divisi'
        ).each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
        });
    }


    function showModalLoading(show) {
        const $body = $('#modalCreateHotel .modal-body');
        if (show) {
            if (!$('#modalBodyLoader').length) {
                $body.prepend(`
                    <div id="modalBodyLoader"
                        style="position:absolute;inset:0;background:rgba(255,255,255,0.85);
                                z-index:999;display:flex;align-items:center;
                                justify-content:center;border-radius:0 0 8px 8px;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status" style="width:2.5rem;height:2.5rem;">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <div class="mt-2 text-muted small">Memuat data...</div>
                        </div>
                    </div>`);
                $body.css('position', 'relative');
            }
            $('#modalBodyLoader').show();
        } else {
            $('#modalBodyLoader').fadeOut(200);
        }
    }


    function setSelect2Value(selector, id, text) {
        const $el = $(selector);
        // Hapus semua opsi dulu kecuali placeholder
        $el.find('option[value!=""]').remove();
        // Buat option baru dan set sebagai selected
        const newOpt = new Option(text, id, true, true);
        $el.append(newOpt).trigger('change');
    }


    function addRowWithData(item) {
    // 1. Buat baris kosong dan append
        const $row = $(buildRow());
        $('#lineItemsBody').append($row);

        // 2. Isi hidden id_detail (untuk update, bukan insert baru)
        $row.find('input[name="id_detail[]"]').val(item.id || '');

        // 3. Isi field teks sederhana
        $row.find('.desc-input').val(item.desc || '');
        $row.find('.qty-input').val(item.qty || 1);
        $row.find('.price-input').val(parseFloat(item.unit_price) || 0);

        // 4. Init Select2 pada baris ini dulu
        initRowSelect2($row);

        
        if(item.item_id) {
            const itemText = item.get_item.nama_paket;
            setRowSelect2Value($row, '.select2-item', item.item_id, itemText);
        }


        if (item.coa_id) {
            const coaText = item.get_coa.name || ('Account #' + item.coa_id);
            setRowSelect2Value($row, '.select2-account', item.coa_id, coaText);
        }


        if (item.coa_id) {
            const coaText = item.get_coa.name || ('Account #' + item.coa_id);
            setRowSelect2Value($row, '.select2-account', item.coa_id, coaText);
        }

        // 6. Set Nama Paket
        if (item.paket_tracking_uuid) {
            const paketText = item.tracking_category_paket;
            setRowSelect2Value($row, '.select2-paket', item.paket_tracking_uuid, paketText);
        }


        // 7. Set Divisi
        if (item.divisi_travel_tracking_uuid) {
            const divisiText = item.tracking_category_divisi;
            setRowSelect2Value($row, '.select2-divisi', item.divisi_travel_tracking_uuid, divisiText);
        }

        // 8. Item (item_id) — null di response ini, skip
        // Jika suatu saat ada, tambahkan:
        // if (item.item_id) setRowSelect2Value($row, '.select2-item', item.item_id, item.item_name || '');
    }

// ── Inject option ke select2 dalam row ($row) ────────────
function setRowSelect2Value($row, selector, id, text) {
    if (!id) return;
    const $el = $row.find(selector);
    $el.find('option[value!=""]').remove();
    $el.append(new Option(text, id, true, true)).trigger('change');
}

   function loadInvoice(id) {
    showModalLoading(true);

    ajaxRequest(
        `{{ route('detail-sales-inv') }}`,
        'GET',
        { id: id },
        localStorage.getItem("token")
    )
    .then(function (response) {
        if (!response.status) {
            Swal.fire('Gagal!', response.message || 'Data tidak ditemukan.', 'error');
            return;
        }

        const d = response.data.data;
        console.log(',d_invoice',d)

        renderPaymentHistory(d.get_payment,d.less_nominal)
        // ── 1. Header fields ─────────────────────────────
        $('#invoice_number_display').val(d.invoice_number || '');
        $('#issue_date').val(d.issue_date || '');         // name="issue_date" id="issue_date"
        $('#due_date').val(d.due_date || '');
        $('#reference').val(d.reference || '');
        $('#invoiceStatusBadge').text(d.status == '1' ? 'Approved' : 'Draft');

        // ── 2. Contact — inject option ke Select2 ────────
        // Field: name="contact_id" id="contact_id"
        if (d.contact_id) {
            const contactText = d.contact_name || ('Contact #' + d.contact_id);
            setSelect2Value('#contact_id', d.contact_id, contactText);
        }

        // ── 3. Detail rows ───────────────────────────────
        // Bersihkan dulu (destroy select2 dalam baris lama)
        destroyRowSelect2();
        $('#lineItemsBody').empty();

        const details = d.get_detail_by_id || [];

        if (details.length > 0) {
            details.forEach(function (item) {
                addRowWithData(item);
            });
        } else {
            addFirstRow(); // minimal 1 baris kosong
        }

        updateDeleteButtons();
        syncCurrencyLabels();
        recalcSummary();
    })
    .catch(function (err) {
        cathError(err)
        // console.error('[loadInvoice] error:', err);
        // Swal.fire('Gagal!', err.message || 'Terjadi kesalahan saat memuat data.', 'error');
    })
    .finally(function () {
        showModalLoading(false);
    });
}


    function formatNumber(num) {
        return parseFloat(num).toLocaleString('id-ID');
    }


    $("#close_modal_payment").on('click',function(){
         table.ajax.reload();
       
    })

    // Trigger Tambah Baru
    $("#button_add_hotel").on("click", function() {
        $('.modal-title').text('Add New Invoices Hotel');
        $('#issue_date').val('m-d-Y');
        $('#due_date').val('m-d-Y');
        $('#invoice_number_display').val('auto');
        $("#reference").val('')
        // Reset handled by modal hidden event
    });


    $("#btn_add_payment_inv").on("click",function(){
       
        $('#paymentFormSection').slideDown(200);
        // Set default date hari ini
        if (!$('#date_paid').val()) {
            $('#date_paid').val(new Date().toISOString().split('T')[0]);
        }
    })


    $(document).on('click', '#btnClosePayment, #btnCancelPayment', function () {
        $('#paymentFormSection').slideUp(200);
    });


     function initAllSelect2Bank() {
       $('.select2-account-bank-modal2').select2({
            placeholder: "Cari nama bank...",
            allowClear: true,
            dropdownParent: $('#paymentModal'),
            ajax: {
                url: "{{ route('getbankselect2') }}",  
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { page: params.page || 1, keyword: params.term || '', limit: 10, kolom_name: 'name' };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data.data, function(item) {
                            return { id: item.id, text: item.name, currency_code: item.currency_code || '-' };
                        }),
                        pagination: { more: response.data.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${item.currency_code})</small></span>`);
            }
        });
    }
    initAllSelect2Bank()

    //modal1
    function initAllSelectBank() {
       $('.select2-account-bank').select2({
            placeholder: "Cari nama bank...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('getbankselect2') }}",  
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { page: params.page || 1, keyword: params.term || '', limit: 10, kolom_name: 'name' };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data.data, function(item) {
                            return { id: item.id, text: item.name, currency_code: item.currency_code || '-' };
                        }),
                        pagination: { more: response.data.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${item.currency_code})</small></span>`);
            }
        });
    }
    initAllSelectBank();


    $("#btnSavePayment").on('click', function (e) {
       
       e.preventDefault();
         const payload = {
            date_transaction         : $('#date_paid').val(),
            uuid_bank      : $('#account_bank').val(),
            nominal_receive       : $('#amount_paid').val(),
            reference_detail : $('#payment_reference').val(),
            parent_inv_id : $('#idHotelInput').val()
          
        };

         ajaxRequest(`{{ route('save-pay-sales-inv') }}`, 'POST', payload, localStorage.getItem("token"))
            .then(response => {
                if(response.status == 200){
                    Swal.fire('Sukses!', 'Data berhasil disimpan.', 'success');
                    $('#modalCreateHotel').modal('hide');
                    table.ajax.reload(null, false);
                }
            })
            .catch((err) => {
                cathError(err)
                //Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
            });
    });


   

// =========================================================
// INVOICE MODAL — JAVASCRIPT
// =========================================================

// ── Kurs rate (diisi dari response server jika ada) ───────
let kursRate = 0;  // set dari luar: kursRate = 4350;

// ── Template HTML satu baris item ────────────────────────
function buildRow() {
    return `
    <tr class="line-item-row">
        <td><span class="drag-handle">&#x28FF;</span></td>

          <input type="hidden" name="id_detail[]"/>
        {{-- Item — Select2 AJAX --}}
        <td>
            <select class="form-control select2-item" name="item_id[]"
                    style="width:100%;" required>
            </select>
        </td>

        {{-- Desc — auto-fill dari item --}}
        <td>
            <input type="text" class="form-control desc-input"
                   name="desc[]" placeholder="(otomatis dari item)" required>
        </td>

        {{-- Qty --}}
        <td>
            <input type="number" class="form-control qty-input"
                   name="qty[]" min="1" value="1"
                   style="text-align:right;" required>
        </td>

        {{-- Price dengan label currency --}}
        <td>
            <div class="price-col-wrap">
                <span class="currency-label price-currency-label">SAR</span>
                <input type="number" class="form-control price-input"
                       name="unit_price[]" placeholder="0" required>
            </div>
        </td>

        {{-- Disc --}}
        <td>
            <input type="text" class="form-control"
                   name="disc[]" placeholder="0%">
        </td>

        {{-- Account — Select2 AJAX --}}
        <td>
            <select class="form-control select2-account" name="coa_id[]" required
                    style="width:100%;">
            </select>
        </td>

        {{-- Tax rate --}}
        <td>
            <select class="form-control" name="tax_rate[]">
                <option value="0">No tax</option>
                <option value="11">11% PPN</option>
            </select>
        </td>

        {{-- Tax amount (readonly) --}}
        <td>
            <input type="text" class="form-control tax-amount"
                   name="tax_amount[]" placeholder="0.00"
                   readonly style="text-align:right;background:#fafafa;">
        </td>

        {{-- Amount IDR (readonly) --}}
        <td>
            <input type="text" class="form-control amount-idr"
                   name="amount_idr[]" placeholder="0" required
                   readonly style="text-align:right;background:#fafafa;">
        </td>

        {{-- Nama Paket — Select2 AJAX --}}
        <td>
            <select class="form-control select2-paket" name="paket_tracking_uuid[]"
                    style="width:100%;">
            </select>
        </td>

        {{-- Divisi — Select2 AJAX --}}
        <td>
            <select class="form-control select2-divisi" name="divisi_travel_tracking_uuid[]"
                    style="width:100%;">
            </select>
        </td>

        <td>
            <button type="button" class="btn-del-line" title="Hapus baris">
                <i class="ti ti-trash"></i>
            </button>
        </td>
    </tr>`;
}

// ── Init semua Select2 pada satu baris ($row) ─────────────
function initRowSelect2($row) {
    const modalEl = $('#modalCreateHotel');

    // Item — AJAX list-paket-select2
    $row.find('.select2-item').select2({
        theme: 'bootstrap4',
        dropdownParent: modalEl,
        placeholder: 'Pilih Item',
        allowClear: true,
        ajax: {
            url: '{{ route("list-paket-select2") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return { page: params.page || 1, keyword: params.term || '', limit: 5 };
            },
            processResults: function(response) {
                if (response.status != 'success') return { results: [], pagination: { more: false } };;
                return {
                    results: $.map(response.data.results, function(item) {
                        return { 
                            id        : item.id,
                            text      : item.nama_paket,
                            harga     : item.price_sales
                        };
                    }),
                }
            },
            cache: true
        }
    }).on('select2:select', function (e) {
        // Auto-fill desc dari field nama_paket response
        const d = e.params.data;
        $(this).closest('tr').find('.desc-input')
               .val(d.nama_paket || d.text || '');
        $(this).closest('tr').find('.price-input').val(Number(d.harga) || ''); 
        recalcSummary();
    });

    // Account — AJAX get-all-coa-select2
    $row.find('.select2-account').select2({
        theme: 'bootstrap4',
        dropdownParent: modalEl,
        placeholder: 'Account',
        allowClear: true,
        ajax: {
            url: '{{ route("get-all-coa-select2") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return { page: params.page || 1, keyword: params.term || '', limit: 5 };
            },
            processResults: function(response, params) {
                params.page = params.page || 1;
                return {
                    results: $.map(response.data?.data || [], function(item) {
                        return { id: item.id, text: item.name, account_type: item.account_type || '-' };
                    })
                };
            },
            cache: true
        }
    });

    // Nama Paket — AJAX tracking-by-parent
    $row.find('.select2-paket').select2({
        theme: 'bootstrap4',
        dropdownParent: modalEl,
        placeholder: 'Nama Paket',
        allowClear: true,
        ajax: {
            url: '{{ route("tracking-by-parent") }}',
            dataType: 'json',
            delay: 300,
            data: (params) => ({
                keyword: params.term,
                name_parent_category: 'nama paket'
            }),
            processResults: function(response) {
                if (!response.status || !response.data?.lines_category) return { results: [] };
                return {
                    results: $.map(response.data.lines_category, function(item) {
                        return { id: item.item_uuid_category || item.id, text: item.item_name_category };
                    })
                };
            },
            cache: false
        }
    });

    // Divisi — AJAX tracking-by-parent
    $row.find('.select2-divisi').select2({
        theme: 'bootstrap4',
        dropdownParent: modalEl,
        placeholder: 'Divisi',
        allowClear: true,
        ajax: {
            url: '{{ route("tracking-by-parent") }}',
            dataType: 'json',
            delay: 300,
            data: (params) => ({
                keyword: params.term,
                name_parent_category: 'divisi'
            }),
           processResults: function(response) {
                if (!response.status || !response.data?.lines_category) return { results: [] };
                return {
                    results: $.map(response.data.lines_category, function(item) {
                        return { id: item.item_uuid_category || item.id, text: item.item_name_category };
                    })
                };
            },
            cache: false
        }
    });
}

// ── Init Contact Select2 di form atas ─────────────────────
function initContactSelect2() {
    $('#contact_id').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#modalCreateHotel'),
        placeholder: 'Pilih Agent / Contact',
        allowClear: true,
        ajax: {
            url: '{{ route("list-contact-select2") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                    return { page: params.page || 1, keyword: params.term || '', limit: 5 };
            },
            processResults: function(response, params) {
                params.page = params.page || 1;
                return {
                    results: $.map(response.data.data, function(item) {
                        return { id: item.id, text: item.full_name, phone: item.phone_number || '-' };
                    }),
                    pagination: { more: response.data.next_page_url !== null }
                };
            },
            cache: true
        }
    });
}

// ── Tambah baris ──────────────────────────────────────────
$('#btn-add-row').on('click', function () {
    const $row = $(buildRow());
    $('#lineItemsBody').append($row);
    initRowSelect2($row);
    syncCurrencyLabels();
    updateDeleteButtons();
});

// ── Hapus baris ───────────────────────────────────────────
$(document).on('click', '.btn-del-line', function () {
    $(this).closest('tr').remove();
    updateDeleteButtons();
    recalcSummary();
});

function updateDeleteButtons() {
    const $rows = $('#lineItemsBody tr');
    $rows.find('.btn-del-line').prop('disabled', $rows.length <= 1);
}

// ── Currency parent — ubah label SAR/IDR di semua baris ───
$('#currency_selected').on('change', function () {
    syncCurrencyLabels();
    recalcSummary();
});

function syncCurrencyLabels() {
    const cur = $('#currency_selected').val() || 'SAR';
    $('.price-currency-label').text(cur);
}

// ── Recalc saat input berubah ─────────────────────────────
$(document).on('input change', '.qty-input, .price-input, [name="tax_rate[]"]',
    recalcSummary);

function recalcSummary() {
    const cur     = $('#currency_selected').val() || 'SAR';
    const isSAR   = (cur === 'SAR');
    let totalSAR  = 0;
    let totalIDR  = 0;
    let totalTax  = 0;

    $('#lineItemsBody tr').each(function () {
        const qty      = parseFloat($(this).find('.qty-input').val())  || 0;
        const price    = parseFloat($(this).find('.price-input').val()) || 0;
        const taxRate  = parseFloat($(this).find('[name="tax_rate[]"]').val()) || 0;
        const subtotal = qty * price;
        const taxAmt   = subtotal * (taxRate / 100);
        const total    = subtotal + taxAmt;

        $(this).find('.tax-amount').val(taxAmt > 0 ? taxAmt.toFixed(2) : '');

        if (isSAR) {
            const amtIDR = total * kursRate;
            $(this).find('.amount-idr').val(
                amtIDR > 0 ? Math.round(amtIDR).toLocaleString('id-ID') : ''
            );
            totalSAR += subtotal;
            totalIDR += amtIDR;
        } else {
            $(this).find('.amount-idr').val(
                total > 0 ? Math.round(total).toLocaleString('id-ID') : ''
            );
            totalIDR += total;
        }
        totalTax += taxAmt;
    });

    $('#summarySubtotalSAR').text(isSAR ? 'SAR ' + totalSAR.toLocaleString('id-ID') : '–');
    $('#summarySubtotalIDR').text('Rp ' + Math.round(totalIDR).toLocaleString('id-ID'));
    $('#summaryTax').text(totalTax > 0 ? totalTax.toFixed(2) : '0.00');
    $('#summaryTotal').text('Rp ' + Math.round(totalIDR).toLocaleString('id-ID'));
}

// ── Reset saat modal ditutup ──────────────────────────────
$('#modalCreateHotel').on('hidden.bs.modal', function () {
    if(myDropzone) {
       isClearingDropzone = true;       
       myDropzone.removeAllFiles(true);
       isClearingDropzone = false;
    }

    $('#formCreateHotel')[0].reset();
    $('#lineItemsBody').empty();
    addFirstRow();                     // selalu ada 1 baris kosong
    syncCurrencyLabels();
    $('#summarySubtotalSAR, #summarySubtotalIDR, #summaryTax, #summaryTotal')
        .text('–');
    $('#dropzone-container').hide();
});

// ── Inisialisasi awal saat DOM ready ─────────────────────
function addFirstRow() {
    const $row = $(buildRow());
    $('#lineItemsBody').append($row);
    initRowSelect2($row);
    updateDeleteButtons();
}


// Fungsi untuk menarik gambar dari server dan menampilkannya di Dropzone
function loadDropzoneImages(invoiceId) {
    // Kosongkan Dropzone terlebih dahulu jika ada gambar dari sesi sebelumnya
    if(myDropzone) {
        isClearingDropzone = true;       
        myDropzone.removeAllFiles(true); 
        isClearingDropzone = false;
    }

      ajaxRequest("{{ route('get-image-sales-inv') }}", 'GET', { invoice_id: invoiceId }, localStorage.getItem("token"))
        .then(response => {
           if (response.data.success && response.data.data.length > 0) {
                $('#dropzone-container').show();
                // Looping data gambar dari server
                $.each(response.data.data, function(key, value) {
                    let mockFile = { 
                        name: value.name, 
                        size: value.size, 
                        accepted: true,
                        status: Dropzone.ADDED,
                        url: value.url,
                        isFromServer: true
                    };

                    // Emit event agar Dropzone membuatkan thumbnail di UI
                    myDropzone.emit("addedfile", mockFile);
                    myDropzone.emit("thumbnail", mockFile, value.url);
                    myDropzone.emit("complete", mockFile);

                    // Tambahkan file ke array internal Dropzone agar tidak bentrok
                    myDropzone.files.push(mockFile);
                   
                });
            }
        })
        .catch((err) => {
            cathError(err)
            //Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
        })
}

$(function () {
    initContactSelect2();
    addFirstRow();
    syncCurrencyLabels();
});

//submit
    $('.action-submit').on('click', function() {
        let actionValue = $(this).val();
        $('#actionTypeValue').val(actionValue);
    });


        $('#formCreateHotel').on('submit', function(e) {
            e.preventDefault();
            
            $('.select2-account, .select2-paket, select2-item, .select2-divisi').each(function() {
                if ($(this).data('select2')) { $(this).trigger('change'); }
            });

            let formData = $(this).serialize();
            let params = new URLSearchParams(formData);
            let idInput = params.get('idHotelInput');
            let id_inv = (idInput && idInput > 0) ? idInput : null;
            let action_selected = params.get('action_type');

            let selectedData = {
                id: id_inv,
                contact_id: params.get('contact_id'),
                issue_date: params.get('issue_date'),
                due_date: params.get('due_date'),
                reference: params.get('reference'),
                // currency: params.get('currency'),
                action_save : action_selected,

                item_id : $('select[name="item_id[]"]').map(function(){ return $(this).val(); }).get(),
                coa_id: $('select[name="coa_id[]"]').map(function(){ return $(this).val(); }).get(),
                desc: $('input[name="desc[]"]').map(function(){ return $(this).val(); }).get(),
                qty: $('input[name="qty[]"]').map(function(){ return $(this).val(); }).get(),
                unit_price: $('input[name="unit_price[]"]').map(function(){ return $(this).val(); }).get(),
                //tax_rate: $('input[name="tax_rate[]"]').map(function(){ return $(this).val(); }).get(),
                paket_tracking_uuid: $('select[name="paket_tracking_uuid[]"]').map(function(){ return $(this).val(); }).get(),
                divisi_travel_tracking_uuid: $('select[name="divisi_travel_tracking_uuid[]"]').map(function(){ return $(this).val(); }).get(),
                id_detail:$('input[name="id_detail[]"]').map(function(){ return $(this).val(); }).get(),
            };

            ajaxRequest(`{{ route('save-sales-inv') }}`, 'POST', selectedData, localStorage.getItem("token"))
                .then(response => {
                    if(response.status == 200){
                        // Swal.fire('Sukses!', 'Data berhasil disimpan.', 'success');
                        // $('#modalCreateHotel').modal('hide');
                        // table.ajax.reload(null, false);
                        if (action_selected == "1" && myDropzone.getQueuedFiles().length > 0) {
                            let savedInvoiceId = id_inv ? id_inv : response.data.id; 
                            $('#idHotelInput').val(savedInvoiceId); 
                            $('.action-submit').prop('disabled', true);
                            myDropzone.processQueue(); 
                        } else {
                            // Jika Save Draft (0) ATAU tidak ada gambar yang dipilih, langsung tutup dan sukses
                            Swal.fire('Sukses!', 'Data berhasil disimpan.', 'success');
                            $('#modalCreateHotel').modal('hide');
                            table.ajax.reload(null, false);
                        }
                    }
                })
                .catch((err) => {
                    cathError(err)
                    //Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                })
                .finally(() => {
                    $('.action-submit').prop('disabled', false);
                });
        });

        function renderPaymentHistory(payments, totalDue) {
            const $section    = $('#paymentHistorySection');
            const $tbody      = $('#paymentHistoryBody');
            const $tfoot      = $('#paymentHistoryFoot');
            const $count      = $('#payHistoryCount');
            const $total      = $('#payHistoryTotal');
            const $remaining  = $('#payHistoryRemaining');

            // Reset
            $tbody.empty();
            $tfoot.hide();
            
            // TAMBAHKAN BARIS INI UNTUK MEMUNCULKAN TABEL:
            $('.pay-history-wrap').removeClass('d-none'); 

            if (!payments || payments.length === 0) {
                $tbody.html(`
                    <tr>
                        <td colspan="6" class="pay-history-empty">
                            <i class="ti ti-inbox" style="font-size:18px; color:#ccc;"></i>
                            <br>Belum ada riwayat pembayaran
                        </td>
                    </tr>
                `);
                $section.show();
                return;
            }

            let totalPaid = 0;

            payments.forEach((p, i) => {
                // console.log('aa',p.get_bank.name)
                const nominal = parseFloat(p.nominal_receive || 0);
                totalPaid += nominal;

                $tbody.append(`
                    <tr>
                        <td class="text-center">${i + 1}</td>
                        <td>${p.date_transaction ?? '-'}</td>
                        <td>${p.name_bank ?? p.uuid_bank ?? '-'}</td>
                        <td>${p.reference_detail ?? '-'}</td>
                        <td class="text-right">${formatCurrency(nominal)}</td>
                    </tr>
                `);
            });

            const remaining = (parseFloat(totalDue) || 0) - totalPaid;

            $count.text(`${payments.length} transaksi`);
            $total.text(formatCurrency(totalPaid));
            $remaining.text(formatCurrency(remaining < 0 ? 0 : remaining));
            $tfoot.show();
            $section.show();
        }

    // =========================================================
    // AJAX HISTORY PAYMENT MODAL
    // =========================================================

    // 1. Buka Modal dan Load Data
    $('#tableHotel').on('click', '.show-payment-modal', function() {
        const id = $(this).data('id');
        $('#invoices_id_parent').val(id); // Set hidden ID untuk form tambah bayar
        
        



        $('#formSubmitPayment').find('.select2-account-bank-modal2').each(function() {
            $(this).val(null).trigger('change');
        });
        $("#input_payment_date").val(null)
        $("#input_payment_nominal").val(0)
        $("#input_payment_ref").val('')
        // Munculkan Modal
        $('#paymentModal').modal('show');
        
        // Panggil AJAX Load History
        loadPaymentHistoryModal(id);
    });

    // 2. Fungsi Load History ke Table Modal
    function loadPaymentHistoryModal(invoiceId) {
        $('#payment_list_body').html('<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary spinner-border-sm"></div> Memuat Data...</td></tr>');
        
        ajaxRequest(`{{ route('detail-sales-inv') }}`, 'GET', { id: invoiceId }, localStorage.getItem("token"))
        .then(function(response) {
            const d = response.data.data;
            const payments = d.get_payment || [];
            
            // Set View Info Modal
            $('#hotel_name_display').text(d.contact_name || '-');
            $('#no_invoice_display').text(d.invoice_number || '-');
            $('#summary_total').text(formatCurrency(d.invoice_total));
            
            let totalPaid = 0;
            let tbody = '';
            
            if (payments.length === 0) {
                tbody = '<tr><td colspan="5" class="text-muted text-center">Belum ada data pembayaran</td></tr>';
            } else {
                payments.forEach((p, index) => {
                  
                    const nominal = parseFloat(p.nominal_receive || 0);
                    totalPaid += nominal;
                    tbody += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${p.date_transaction || '-'}</td>
                            <td>${p.name_bank}</td>
                            <td>${p.reference_detail || p.name_bank || '-'}</td>
                            <td class="text-right">${formatCurrency(nominal)}</td>
                        </tr>
                    `;
                });
            }
            
            $('#payment_list_body').html(tbody);
            
            // Set Summary Cards
            $('#summary_paid').text(formatCurrency(totalPaid));
            const remaining = parseFloat(d.invoice_total) - totalPaid;
            $('#summary_remaining').text(formatCurrency(remaining < 0 ? 0 : remaining));
        })
        .catch(function(err) {
            $('#payment_list_body').html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat data riwayat pembayaran</td></tr>');
        });
    }

    // 3. Submit Pembayaran Baru dari Modal History
    $('#formSubmitPayment').on('submit', function(e) {
        e.preventDefault();
        const payload = {
            date_transaction : $('#input_payment_date').val(),
            nominal_receive  : $('#input_payment_nominal').val(),
            reference_detail : $('#input_payment_ref').val(),
            parent_inv_id    : $('#invoices_id_parent').val(),
            uuid_bank : $('#account_bank_modal_2').val(),
            parent_inv_id : $('#invoices_id_parent').val(),
        };

        // Ganti UI Button
        const $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Menyimpan...');

        ajaxRequest(`{{ route('save-pay-sales-inv') }}`, 'POST', payload, localStorage.getItem("token"))
        .then(response => {
            if(response.status == 200){
                Swal.fire('Sukses!', 'Pembayaran berhasil ditambahkan.', 'success');
                $('#formSubmitPayment')[0].reset(); 
                
                // Reload table History & DataTable Induk
                loadPaymentHistoryModal(payload.parent_inv_id);
                table.ajax.reload(null, false); 
            }
        })
        .catch((err) => {
            cathError(err);
        })
        .finally(() => {
            $btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i> Simpan Pembayaran');
        });
    });

    // 4. Aksi Delete Payment History

    // Matikan auto discover Dropzone agar kita bisa inisiasi manual
    

</script>
@endpush

```