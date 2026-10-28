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


#formCreateHotel {
    display: contents;
}

/* jaga-jaga: pastikan footer tidak ikut menyusut saat body scroll */
#modalCreateHotel .modal-footer {
    flex-shrink: 0;
}

/* ── APPLY CREDIT (OVERPAYMENT) ── */
#paymentMethodWrap .btn-group-toggle .btn {
    font-size: 11px;
}

/*-- drag item inv --*/
/* ── SORTABLE DRAG ROW ── */
.drag-handle {
    color: #bbb;
    font-size: 16px;
    cursor: grab;
    user-select: none;
    display: block;
    text-align: center;
    padding: 2px 0;
    transition: color .15s;
}
.drag-handle:hover  { color: #1ab394; }
.drag-handle:active { cursor: grabbing; }

/* Ghost = baris yang "ditinggalkan" saat drag */
.row-sortable-ghost {
    opacity: 0.35;
    background: #e8f4fd !important;
}
/* Chosen = baris yang sedang dipegang */
.row-sortable-chosen {
    background: #f0faf8 !important;
    box-shadow: 0 2px 8px rgba(26,179,148,.25);
}
/*fix select2 pilih bank mmodal multi paymnet*/
/* ══════════════════════════════════════════════
   PAYMENT MODAL — WIDTH & LAYOUT
   ══════════════════════════════════════════════ */
#paymentModal .modal-dialog {
    max-width: 860px !important;
    width: calc(100vw - 40px) !important;
    margin: 20px auto !important;
}

/* ══════════════════════════════════════════════
   SELECT2 FIX — di dalam #newPaymentRowsTable
   ══════════════════════════════════════════════ */

/* Container harus 100% lebar cell */
#newPaymentRowsTable td .select2-container {
    width: 100% !important;
    min-width: 0 !important;
}

/* Selection box */
#newPaymentRowsTable .select2-container--bootstrap4
    .select2-selection--single {
    height: 31px !important;
    position: relative !important;          /* wajib: agar clear btn bisa absolute */
    display: flex !important;
    align-items: center !important;
    border: 1px solid #ced4da !important;
    border-radius: 4px !important;
}

/* Teks yang terpilih — beri ruang kanan agar tidak ketimpa × dan ▾ */
#newPaymentRowsTable .select2-container--bootstrap4
    .select2-selection--single
    .select2-selection__rendered {
    display: block !important;
    padding-left  : 8px  !important;
    padding-right : 42px !important;    /* ← ruang untuk × + ▾ */
    line-height   : 29px !important;
    font-size     : 12px !important;
    overflow      : hidden !important;
    text-overflow : ellipsis !important;
    white-space   : nowrap !important;
    max-width     : 100% !important;
    color         : #333 !important;
}

/* Tombol × (clear) — pin ke kanan, jangan ikut flow teks */
#newPaymentRowsTable .select2-container--bootstrap4
    .select2-selection--single
    .select2-selection__clear {
    position    : absolute !important;
    right       : 22px !important;     /* taruh sebelum panah ▾ */
    top         : 50% !important;
    transform   : translateY(-50%) !important;
    font-size   : 14px !important;
    line-height : 1 !important;
    color       : #888 !important;
    z-index     : 1 !important;
}
#newPaymentRowsTable .select2-container--bootstrap4
    .select2-selection--single
    .select2-selection__clear:hover { color: #e74c3c !important; }

/* Panah ▾ */
#newPaymentRowsTable .select2-container--bootstrap4
    .select2-selection--single
    .select2-selection__arrow {
    height   : 29px !important;
    width    : 20px !important;
    right    : 2px !important;
    position : absolute !important;
    top      : 0 !important;
}

/* Placeholder sedikit redup */
#newPaymentRowsTable .select2-container--bootstrap4
    .select2-selection--single
    .select2-selection__placeholder {
    color   : #aaa !important;
    font-size: 12px !important;
}
/*label setelah select*/
/* ── Select2 di form Apply Credit (paymentModal) ── */
#wrapCreditForm .select2-container {
    display: block !important;
    width: 100% !important;
}
#wrapCreditForm .select2-container--bootstrap4 .select2-selection--single {
    height: calc(1.5em + 0.75rem + 2px) !important;
    display: flex !important;
    align-items: center !important;
    position: relative !important;
    border: 1px solid #ced4da !important;
    border-radius: 0.25rem !important;
}
#wrapCreditForm .select2-selection__rendered {
    display: block !important;
    flex: 1 1 auto;
    min-width: 0;
    line-height: 1.5 !important;
    padding-left: 0.6rem !important;
    padding-right: 2.2rem !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    font-size: 0.875rem;
    color: #333 !important;
}
#wrapCreditForm .select2-selection__clear {
    position: absolute !important;
    right: 1.5rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    margin: 0 !important;
    line-height: 1 !important;
    color: #888 !important;
}
#wrapCreditForm .select2-selection__arrow {
    position: absolute !important;
    right: 0.4rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    height: auto !important;
}
#wrapCreditForm .select2-selection__placeholder {
    color: #aaa !important;
    font-size: 0.875rem;
}


/*style select2 bank fee overpy*/
/* ══════════════════════════════════════════════
   FEE OVERPAY — Select2 bank asal di dalam tabel
   ══════════════════════════════════════════════ */
#feeOverpayRowsTable td {
    vertical-align: middle;
}
#feeOverpayRowsTable td .select2-container {
    display: block !important;
    width: 100% !important;
    min-width: 0 !important;
}
#feeOverpayRowsTable .select2-container--bootstrap4 .select2-selection--single {
    height: 31px !important;
    position: relative !important;
    display: flex !important;
    align-items: center !important;
    border: 1px solid #ced4da !important;
    border-radius: 4px !important;
    background-color: #fff !important;
}
#feeOverpayRowsTable .select2-selection__rendered {
    display: block !important;
    flex: 1 1 auto;
    min-width: 0;
    line-height: 29px !important;
    padding-left: 8px !important;
    padding-right: 42px !important;
    font-size: 12px !important;
    color: #333 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}
#feeOverpayRowsTable .select2-selection__clear {
    position: absolute !important;
    right: 22px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    margin: 0 !important;
    font-size: 14px !important;
    line-height: 1 !important;
    color: #888 !important;
    z-index: 1;
}
#feeOverpayRowsTable .select2-selection__clear:hover {
    color: #e74c3c !important;
}
#feeOverpayRowsTable .select2-selection__arrow {
    position: absolute !important;
    right: 2px !important;
    top: 0 !important;
    height: 29px !important;
    width: 20px !important;
}
#feeOverpayRowsTable .select2-selection__placeholder {
    color: #aaa !important;
    font-size: 12px !important;
}


/* ══════════════════════════════════════════════
   MODAL INPUT CONTACT DUPLICATE — Select2 rapi
   ══════════════════════════════════════════════ */
#modalInputContactDuplicate .select2-container {
    display: block !important;
    width: 100% !important;
}
#modalInputContactDuplicate .select2-container--bootstrap4 .select2-selection--single {
    height: calc(1.5em + 0.75rem + 2px) !important;
    position: relative !important;
    display: flex !important;
    align-items: center !important;
    border: 1px solid #ced4da !important;
    border-radius: 0.25rem !important;
    background-color: #fff !important;
}
#modalInputContactDuplicate .select2-selection__rendered {
    display: block !important;
    flex: 1 1 auto;
    min-width: 0;
    line-height: 1.5 !important;
    padding-left: 0.6rem !important;
    padding-right: 2.2rem !important;
    font-size: 0.875rem !important;
    color: #333 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}
#modalInputContactDuplicate .select2-selection__clear {
    position: absolute !important;
    right: 1.5rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    margin: 0 !important;
    font-size: 14px !important;
    line-height: 1 !important;
    color: #888 !important;
    z-index: 1;
}
#modalInputContactDuplicate .select2-selection__clear:hover {
    color: #e74c3c !important;
}
#modalInputContactDuplicate .select2-selection__arrow {
    position: absolute !important;
    right: 0.4rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    height: auto !important;
    width: 20px !important;
}
#modalInputContactDuplicate .select2-selection__placeholder {
    color: #aaa !important;
    font-size: 0.875rem !important;
}

/**/
/* ── MODAL ADD ITEM MASTER ── */
#modalCreateItemMaster .xero-modal-section { padding: 14px 20px; border-bottom: 1px solid #eee; }
#modalCreateItemMaster .xero-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; }
#modalCreateItemMaster .xero-field { margin-bottom: 8px; }
#modalCreateItemMaster .xero-field label { font-size: 11px; font-weight: 600; color: #555; margin-bottom: 3px; }
#modalCreateItemMaster .req { color: #e74c3c; font-weight: 400; }
#modalCreateItemMaster .xero-check-row { display: flex; gap: 10px; align-items: flex-start; padding: 8px 0; }
#modalCreateItemMaster .xero-check-row > input[type=checkbox] { margin-top: 4px; }
#modalCreateItemMaster .xero-check-label h6 { margin: 0; font-size: 13px; font-weight: 600; }
#modalCreateItemMaster .xero-check-label p  { margin: 0 0 6px; font-size: 11px; color: #888; }
#modalCreateItemMaster .xero-expanded { display: none; padding: 8px 0 4px; }
#modalCreateItemMaster .xero-expanded.show { display: block; }
#modalCreateItemMaster .btn-save-main {
    background: #1ab394; border: none; color: #fff;
    border-radius: 4px; padding: 5px 18px; font-size: 12px; font-weight: 600; cursor: pointer;
}
#modalCreateItemMaster .btn-save-main:hover { background: #17a085; }
#modalCreateItemMaster .btn-save-main:disabled { opacity: .6; }
#modalCreateItemMaster .select2-container { width: 100% !important; }
</style>


<div class="card shadow mb-5">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 font-weight-bold text-primary">List Invoices From Xero </h5>

        <button type="button" id="button_add_hotel" class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#modalCreateHotel">
            <i class="ti ti-plus me-1"></i> Add Invoice
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
                      <th>Reference</th>
                    <th>Nama Contact</th>
                    <th>Issue Date</th>
                    <th>Saved By</th>
                    <th>Amount</th>
                    <th>Total Payment</th>
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
     aria-labelledby="modalCreateHotelLabel" aria-hidden="true"
     data-backdrop="static" data-keyboard="false">

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
                        <span class="badge-draft" id="invoiceStatusBadge"></span>
                    </div>

                    <input type="hidden" name="idHotelInput" id="idHotelInput">

                    {{-- ── BARIS 1: Contact | Issue date | Due date | Invoice no ── --}}
                   <div class="xero-fields-grid" style="display:grid; grid-template-columns:35% 20% 22% 23%; gap:10px; align-items:start;">
                        {{-- Contact --}}
                        <div class="xero-field" style="min-width:0;">
                            <label>Contact <span class="text-danger">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-user"></i>
                                <select
                                    class="form-control select2-contact"
                                    name="contact_id"
                                    id="contact_id"
                                    style="width:100%;"
                                    required>
                                </select>
                            </div>
                        </div>

                        {{-- VA Number --}}
                        <div class="xero-field" style="min-width:0;">
                            <label>Va Number </label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-credit-card"></i>
                                <input
                                    type="number"
                                    class="form-control"
                                    name="va_number"
                                    id="va_number">
                            </div>
                        </div>

                        {{-- Issue Date --}}
                        <div class="xero-field" style="min-width:0;">
                            <label>Issue date <span class="text-danger">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-calendar"></i>
                                <input
                                    type="date"
                                    class="form-control"
                                    name="issue_date"
                                    id="issue_date"
                                    required>
                            </div>
                        </div>

                        {{-- Due Date --}}
                        <div class="xero-field" style="min-width:0;">
                            <label>Due date</label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-calendar"></i>
                                <input
                                    type="date"
                                    class="form-control"
                                    name="due_date"
                                    id="due_date">
                            </div>
                        </div>

                        {{-- Invoice Number --}}
                        <div class="xero-field" style="min-width:0; grid-column:1;">
                            <label>Invoice number</label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-hash"></i>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="invoice_number_display"
                                    name="invoice_number"
                                    placeholder="auto"
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
                            {{-- <select class="form-control" name="code_curr"
                                    id="currency_selected" required> --}}
                                {{-- <option value="IDR">IDR &ndash; Rupiah</option>
                                <option value="SAR">SAR &ndash; Saudi Riyal</option> --}}
                                {{-- @foreach ($cur as $item)
                                        <option value="{{ $item->code_curr }}">{{ $item->ket ." | ".(int)$item->nominal_currency }}</option>
                                @endforeach
                            </select> --}}
                            <select class="form-control" name="code_curr" id="currency_selected" required>
                                <option value="IDR" selected>IDR – Rupiah</option>
                                <option value="SAR">SAR – Saudi Riyal</option>
                            </select>
                        </div>

                        <div class="xero-field d-none">
                            {{-- <label>Amounts are</label>
                            <select class="form-control" name="amount_are" id="amount_are">
                                <option value="0" selected>No Tax</option>
                                <option value="1">Tax Exclusive</option>
                                <option value="2">Tax Inclusive</option>
                            </select> --}}
                            <label>Nama Trevel  <span class="text-danger">*</span></label>
                            <select class="form-control" name="travel_id" id="travel_id">
                                    <option value="" selected>Pilih Travel</option>
                               @foreach ($travel as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                               @endforeach
                            </select>
                        </div>
                        <div class="xero-field">
                            <label>Branding Theme <span class="text-danger">*</span></label>
                            <select class="form-control" name="branding_theme_id" id="branding_theme_id">
                                <option value="">Default</option>
                                @foreach ($branding as $theme)
                                    <option value="{{ $theme->id }}">{{ $theme->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- ── KURS ALERT ── --}}
                    <div class="kurs-alert" id="wrap_nominal_currency" style="display:none;">
                        <i class="ti ti-info-circle" style="font-size:15px;color:#f0a500;"></i>
                        <strong>Kurs:</strong>
                        <input type="number" class="form-control" name="nominal_currency" id="nominal_currency"
                            min="1" step="any" placeholder="mis. 4350" style="width:160px; height:32px;">
                        <span class="text-danger font-weight-bold" id="text_currency">1 SAR = Rp 0</span>
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
                        <button type="button" class="btn-dashed" id="btn-add-item-master" style="border-color:#6f42c1; color:#6f42c1; margin-left:10px;">
                            <i class="ti ti-package" style="font-size:12px;"></i> Add Item Master
                        </button>
                        <button type="button" class="btn-dashed" id="btn-add-item-contact" style="border-color:#fd7e14; color:#fd7e14; margin-left:10px;">
                            <i class="ti ti-user-plus" style="font-size:12px;"></i> Add Contact
                        </button>
                    </div>

                    <div class="dropzone" id="buktiDropzone">
                        <div class="dz-message" data-dz-message>
                            <span>Klik atau Drop <b>gambar / PDF</b> bukti di sini (bisa banyak file)<br>
                            <small class="text-muted">Format: JPG, PNG, WEBP, PDF (maks 10 MB / file)</small></span>
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
                                <td>Subtotal </td>
                                <td id="summarySubtotalIDR">0</td>
                            </tr>
                            <tr>
                                <td>Total tax</td>
                                <td id="summaryTax">0.00</td>
                            </tr>
                            <tr class="row-total">
                                <td>Total Tagihan</td>
                                <td id="summaryTotal">Rp 0</td>
                            </tr>
                            <tr class="">
                                <td>Total Dibayarkan</td>
                                <td id="summaryTotalPayment">Rp 0</td>
                            </tr>
                             <tr class="row-total">
                                <td>Sisa Kekurangan</td>
                                <td id="summaryRemainder">Rp 0</td>
                            </tr>
                        </table>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <h6 class="font-weight-bold mb-2">
                            <i class="ti ti-history mr-1"></i> Log History
                        </h6>
                        <ul class="list-group list-group-flush small" id="history_log_list" style="max-height: 200px; overflow-y: auto;">
                            <!-- di-render via JS -> renderHistoryLog() -->
                        </ul>
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

                            {{-- ── APPLY CREDIT: pilihan metode pembayaran, hanya tampil kalau ada overpayment ── --}}
                            <div class="form-group" id="paymentMethodWrapAdd" style="display:none; padding:0 4px;">
                                <label class="small font-weight-bold d-block">Metode Pembayaran</label>
                                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                    <label class="btn btn-outline-primary btn-sm active">
                                        <input type="radio" name="payment_method_add" id="method_bank_add" value="bank" checked> Bank Transfer
                                    </label>
                                    <label class="btn btn-outline-success btn-sm">
                                        <input type="radio" name="payment_method_add" id="method_credit_add" value="credit"> Apply Credit (Overpayment)
                                    </label>
                                </div>
                            </div>

                            {{-- Fields sejajar --}}
                            <div class="payment-fields-grid" id="form_input_multi_payment">

                                {{-- Date Paid --}}
                                <div class="xero-field">
                                    <label>Date Paid <span class="text-danger">*</span></label>
                                    <div class="input-icon-wrap">
                                        <i class="ti ti-calendar"></i>
                                        <input type="date" class="form-control"
                                            name="date_transaction" id="date_paid">
                                    </div>
                                </div>

                                {{-- Account Bank — dipakai untuk metode "bank" --}}
                                <div class="xero-field" id="wrap_bank_select_add">
                                    <label>Account Bank <span class="text-danger">*</span></label>
                                    <select class="form-control select2-account-bank"
                                            name="uuid_bank" id="account_bank"
                                            style="width:100%;">
                                        <option value="">-- Pilih Akun --</option>
                                        {{-- populate via JS / AJAX --}}
                                    </select>
                                </div>

                                {{-- Pilih Overpayment — dipakai untuk metode "credit", default hidden --}}
                                <div class="xero-field" id="wrap_overpay_select_add" style="display:none;">
                                    <label>Pilih Overpayment <span class="text-danger">*</span></label>
                                    <select class="form-control select2-overpay-credit-add"
                                            name="overpay_id" id="overpay_id_add"
                                            style="width:100%;">
                                        <option value="">-- Pilih Overpayment --</option>
                                        {{-- populate via JS dari get_jamaah.list_all_overpay --}}
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
                                    <label>Reference <span class="text-danger">*</span></label>
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
                                    Tutup
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
                    <button type="button" class="btn btn-secondary btn-sm" id="btl-save"
                            data-dismiss="modal">
                        <i class="ti ti-x mr-1"></i> Tutup
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
                            <button type="submit" id="btn_duplicate" class="dropdown-item align-items-center text-primary font-weight-bold action-submit" value="2">
                                <i class="ti ti-bookmark mr-2" style="font-size: 1.2rem;"></i>
                                <span>Duplicate</span>
                            </button>
                            {{-- <button type="submit" style="display: none" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="0">
                                <i class="ti ti-bookmark mr-2" style="font-size: 1.2rem;"></i>
                                <span>Save draft</span>
                            </button> --}}
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

{{-- ── MODAL ADD ITEM MASTER (dari halaman invoice) ── --}}
<div class="modal fade" id="modalCreateItemMaster" tabindex="-1" role="dialog" aria-hidden="true"
     data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">New item</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="formCreateItemMaster">
                <div class="modal-body p-0">

                    {{-- Section 1 --}}
                    <div class="xero-modal-section">
                        <div class="xero-field-row">
                            <div class="xero-field">
                                <label>Code <span class="req">*</span></label>
                                <input type="text" class="form-control" id="mi_code" placeholder="e.g. UMR-001" required>
                            </div>
                            <div class="xero-field">
                                <label>Name</label>
                                <input type="text" class="form-control" id="mi_name" placeholder="e.g. Umroh Group 16 hari">
                            </div>
                        </div>
                    </div>

                    {{-- Section 2 --}}
                    <div class="xero-modal-section">
                        <div class="xero-field">
                            <label>Item Type <span class="req">*</span></label>
                            <div class="d-flex align-items-center" style="gap:18px; min-height:34px;">
                                <label class="mb-0 font-weight-normal" style="font-size:12px; cursor:pointer;">
                                    <input type="radio" name="mi_is_item_product" value="1" checked style="margin-right:6px;"> Product
                                </label>
                                <label class="mb-0 font-weight-normal" style="font-size:12px; cursor:pointer;">
                                    <input type="radio" name="mi_is_item_product" value="0" style="margin-right:6px;"> Service
                                </label>
                            </div>
                        </div>
                        <div class="xero-field">
                            <label for="mi_departure_date">Tanggal Keberangkatan
                                <span class="req">(wajib diisi jika item paket umroh)</span>
                            </label>
                            <input type="date" id="mi_departure_date" class="form-control">
                        </div>
                    </div>

                    {{-- Section 3 --}}
                    <div class="xero-modal-section">

                        {{-- Purchase --}}
                        <div class="xero-check-row">
                            <input type="checkbox" id="mi_chkPurchase" value="1">
                            <div class="xero-check-label" style="flex:1;">
                                <h6>Purchase</h6>
                                <p>Add item to bills, purchase orders, and other purchase transactions</p>
                                <div class="xero-expanded" id="mi_purchaseFields">
                                    <div class="xero-field">
                                        <label>Purchase price</label>
                                        <input type="number" class="form-control" id="mi_price_purchase" placeholder="0" step="any" min="0">
                                    </div>
                                    <div class="xero-field">
                                        <label>Account</label>
                                        <select class="form-control" id="mi_account_purchase" style="width:100%;">
                                            <option value="">-- Pilih Akun --</option>
                                        </select>
                                    </div>
                                    <div class="xero-field">
                                        <label>Description</label>
                                        <input type="text" class="form-control" id="mi_desc">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Sell --}}
                        <div class="xero-check-row">
                            <input type="checkbox" id="mi_chkSell" value="1">
                            <div class="xero-check-label" style="flex:1;">
                                <h6>Sell</h6>
                                <p>Add item to invoices, quotes, and other sales transactions</p>
                                <div class="xero-expanded" id="mi_sellFields">
                                    <div class="xero-field">
                                        <label>Sale price</label>
                                        <input type="number" class="form-control" id="mi_price_sales" placeholder="0" step="any" min="0">
                                    </div>
                                    <div class="xero-field">
                                        <label>Account</label>
                                        <select class="form-control" id="mi_account_sales" style="width:100%;">
                                            <option value="">-- Pilih Akun --</option>
                                        </select>
                                    </div>
                                    <div class="xero-field">
                                        <label>Va Number</label>
                                        <input type="number" class="form-control" id="mi_va_number">
                                    </div>
                                    <div class="xero-field">
                                        <label>Description</label>
                                        <input type="text" class="form-control" id="mi_desc_salles">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-save-main" id="mi_btnSave">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── MODAL INPUT CONTACT DUPLICATE ── --}}
<div class="modal fade" id="modalInputContactDuplicate" tabindex="-1" role="dialog"
     aria-labelledby="modalInputContactDuplicateLabel" aria-hidden="true"
     data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalInputContactDuplicateLabel">
                    <i class="ti ti-copy mr-1"></i> Duplicate Invoice
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="formInputContactDuplicate">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="id_contact_duplicate" class="font-weight-bold small">
                            Pilih Contact <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" name="id_contact_duplicate"
                                id="id_contact_duplicate" style="width:100%;" required>
                        </select>
                        <small class="text-muted">Ketik minimal 4 huruf untuk mencari contact.</small>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="ti ti-x mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="btnSubmitContactDuplicate">
                        <i class="ti ti-check mr-1"></i> Lanjutkan
                    </button>
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
        {{-- <button type="button" class="btn btn-primary" onclick="printInvoice()">
          <i class="fa fa-print"></i> Download PDF
        </button> --}}
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
                        <h5 class="font-weight-bold text-success mb-0" id="summary_paid"></h5>
                        <h5 class="font-weight-bold text-success mb-0" id="sum_paid_rp"></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-danger mb-2">
                    <div class="card-body py-2">
                        <small class="text-muted font-weight-bold text-uppercase">Sisa Kekurangan</small>
                        <h5 class="font-weight-bold text-danger mb-0" id="summary_remaining"></h5>
                        <input type="hidden" name="over_pay_sisa" id="over_pay_sisa">
                        <h5 class="font-weight-bold text-danger mb-0" id="sum_pem_rp"></h5>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        {{-- ── BUKTI PEMBAYARAN (submit form = upload ke server) ── --}}
       {{-- ── BUKTI PEMBAYARAN ── --}}
        <form id="formPaymentBukti" class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="font-weight-bold mb-0">
                    <i class="fa fa-paperclip mr-1"></i> Bukti Pembayaran
                </h6>
                <small class="text-muted">JPG, PNG, WEBP, PDF (maks 10 MB / file)</small>
            </div>

            <div class="dropzone" id="buktiDropzonePay">
                <div class="dz-message" data-dz-message>
                    <span>Klik atau Drop <b>gambar / PDF</b> bukti di sini (bisa banyak file)<br>
                    <small class="text-muted">Format: JPG, PNG, WEBP, PDF (maks 10 MB / file)</small></span>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-2">
                <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="btnSaveBuktiPay">
                    <i class="fa fa-upload mr-1"></i> Simpan Bukti
                </button>
            </div>
        </form>

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
                        <th width="12%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="payment_list_body">
                    <tr><td colspan="5" class="text-muted">Belum ada data pembayaran</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card bg-light d-print-none">
            <div class="card-body">

                {{-- Title bar + Cancel edit --}}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="font-weight-bold text-primary mb-0" id="payFormTitle">
                        <i class="fa fa-plus-circle mr-1"></i> Tambah Pembayaran Baru
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            id="btnCancelEditInv" style="display:none;">
                        <i class="fa fa-times mr-1"></i> Batal Edit
                    </button>
                </div>

                {{-- Hidden state fields --}}
                <input type="hidden" id="row_payment_id" value="">
                <input type="hidden" id="invoices_id_parent">
                <input type="hidden" id="pay_mode" value="new">  {{-- new | edit --}}

                {{-- Metode (tampil hanya jika ada overpayment) --}}
                <div class="form-group" id="paymentMethodWrap" style="display:none;">
                    <label class="small font-weight-bold d-block">Metode Pembayaran</label>
                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                        <label class="btn btn-outline-primary btn-sm active">
                            <input type="radio" name="payment_method" id="method_bank" value="bank" checked>
                            Bank Transfer
                        </label>
                        <label class="btn btn-outline-success btn-sm" id="opsi_apply_credit">
                            <input type="radio" name="payment_method" id="method_credit" value="credit">
                            Apply Credit (Overpayment)
                        </label>
                    </div>
                </div>

                {{-- === Bank Transfer: multi-row === --}}
                <div id="wrapBankRows">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-1"
                            id="newPaymentRowsTable" style="font-size:12px;">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="min-width:130px; width:150px;">Tanggal Bayar</th>
                                    <th style="min-width:240px;">Bank</th>
                                    <th style="min-width:130px; width:150px;">Nominal (IDR)</th>
                                    <th style="min-width:120px;">Catatan / Ref</th>
                                    <th style="width:36px;"></th>
                                </tr>
                            </thead>
                            <tbody id="newPaymentRowsBody"></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                id="btnAddPayRow">
                            <i class="fa fa-plus mr-1"></i> Add Row
                        </button>
                        <button type="button" class="btn btn-success btn-sm font-weight-bold"
                                id="btnSaveAllPayments">
                            <i class="fa fa-save mr-1"></i>
                            <span id="payBtnLabel">Simpan Pembayaran</span>
                        </button>
                    </div>
                </div>

                {{-- === Apply Credit (Overpayment): single action === --}}
                <div id="wrapCreditForm" style="display:none;">

                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body py-3">
                            {{-- Baris 1 --}}
                            <div class="form-row">
                                <div class="form-group col-md-6 mb-2">
                                    <label for="overpay_id_modal2" class="small font-weight-bold mb-1">
                                        Pilih Overpayment <span class="text-danger">*</span>
                                    </label>

                                    <select
                                        class="form-control select2-overpay-credit"
                                        id="overpay_id_modal2"
                                        style="width:100%;">
                                        <option value="">-- Pilih Overpayment --</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-3 mb-2">
                                    <label for="credit_date" class="small font-weight-bold mb-1">
                                        Tanggal
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control form-control-sm"
                                        id="credit_date">
                                </div>

                                <div class="form-group col-md-3 mb-2">
                                    <label for="credit_nominal" class="small font-weight-bold mb-1">
                                        Nominal <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="number"
                                        class="form-control form-control-sm"
                                        id="credit_nominal"
                                        min="1"
                                        placeholder="0">
                                </div>

                            </div>
                            {{-- Baris 2 --}}
                            <div class="form-row">

                                <div class="form-group col-md-6 mb-2">
                                    <label for="credit_ref" class="small font-weight-bold mb-1">
                                        Ref <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        id="credit_ref"
                                        placeholder="Optional">
                                </div>

                                <div class="form-group col-md-6 mb-2">
                                    <label for="bank_overpay_id" class="small font-weight-bold mb-1">
                                        Pilih Bank Overpay
                                        <span class="text-danger">*</span>
                                    </label>
                                <select
                                        class="form-control select2-bank-overpay"
                                        name="bank_overpay_id"
                                        id="bank_overpay_id"
                                        style="width:100%;">
                                        <option value="">-- Pilih Bank --</option>

                                        @foreach ($bank as $item)
                                            <option value="{{ $item->id }}">
                                                {{ $item->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>
                            {{-- Button --}}
                            <div class="d-flex justify-content-end pt-2 border-top mt-2">
                                <button
                                    type="button"
                                    class="btn btn-success btn-sm px-3"
                                    id="btnSaveCreditPayment">
                                    <i class="fa fa-save mr-1"></i>
                                    Simpan Apply Credit
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
        {{-- ── FEE OVERPAY (hanya tampil jika ada get_over_pay) ── --}}
           {{-- ── FEE OVERPAY ── --}}
            <div class="card border-warning mt-3 d-print-none" id="feeOverpaySection" style="display:none;">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                    <h6 class="mb-0 font-weight-bold text-warning" id="feeFormTitle">
                        <i class="fa fa-percent mr-1"></i> Fee Overpay
                    </h6>
                    <small class="text-muted">Sisa overpayment:
                        <b id="fee_overpay_sisa" class="text-dark">-</b>
                    </small>
                </div>

                <div class="card-body py-2">
                    <input type="hidden" id="fee_overpay_id" value="">
                    <input type="hidden" id="fee_jamaah_id" value="">
                    <input type="hidden" id="fee_edit_id" value="">

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-1" id="feeOverpayRowsTable" style="font-size:12px;">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="width:150px;">Tanggal</th>
                                    <th style="min-width:220px;">Bank Asal</th>
                                    <th style="width:150px;">Nominal</th>
                                    <th style="min-width:180px;">Rekening Tujuan Jamaah</th>
                                    <th style="width:36px;"></th>
                                </tr>
                            </thead>
                            <tbody id="feeOverpayRowsBody"></tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddFeeRow">
                            <i class="fa fa-plus mr-1"></i> Add Row
                        </button>
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm mr-1" id="btnCancelFeeEdit" style="display:none;">
                                <i class="fa fa-times mr-1"></i> Batal Edit
                            </button>
                            <button type="button" class="btn btn-warning btn-sm font-weight-bold" id="btnSaveFeeOverpay">
                                <i class="fa fa-save mr-1"></i> Simpan Fee Overpay
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── RIWAYAT FEE OVERPAY ── --}}
            <div class="mt-3" id="feeOverpayHistoryWrap" style="display:none;">
                <h6 class="font-weight-bold mb-2">
                    <i class="fa fa-history mr-1"></i> Riwayat Fee Overpay
                </h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm text-center mb-0">
                        <thead class="thead-dark">
                            <tr>
                                <th width="5%">No</th>
                                <th>Tanggal</th>
                                <th>Nama Bank</th>
                                <th>Nominal</th>
                                <th>Rekening Tujuan Jamaah</th>
                                <th width="12%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="feeOverpayHistoryBody">
                            <tr><td colspan="5" class="text-muted">Belum ada data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
      </div>

      <div class="modal-footer d-print-none bg-white">
        <input type="hidden" id="invoice_uuid_payment" value="">
        <button type="button" id="btn_check_invoice" class="btn btn-info">
            <i class="ti ti-eye mr-1"></i> Check Invoice
        </button>

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

@include('admin.transaksi.sales.preview_invoice_modal')

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

{{-- ── PREVIEW PDF MODAL ── --}}
<div class="modal fade" id="previewPdfModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content" style="height: 90vh;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">
                    <i class="ti ti-file-type-pdf mr-1"></i> Preview PDF
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0" style="height: calc(90vh - 56px);">
                <iframe id="previewPdfFrame"
                        src=""
                        style="width:100%; height:100%; border:none; display:block;">
                </iframe>
            </div>
        </div>
    </div>
</div>


{{-- ── MODAL ADD CONTACT (dari halaman invoice) ── --}}
<div class="modal fade" id="modalCreateJamaah" tabindex="-1" role="dialog" aria-hidden="true"
     data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalJamaahTitle">Tambah Contact</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="id_jamaah" value="">

                <div class="form-group mb-3">
                    <label for="full_name">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" placeholder="Nama lengkap jamaah">
                </div>

                <div class="form-group mb-3">
                    <label for="phone_number">No. Telepon <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="phone_number" placeholder="Contoh: 081234567890">
                </div>

                <div class="form-group mb-3">
                    <label for="nik">NIK <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nik" maxlength="16" placeholder="16 digit NIK">
                </div>

                <div class="form-group mb-3">
                    <label for="detail_address">Alamat</label>
                    <textarea class="form-control" id="detail_address" rows="3" placeholder="Alamat lengkap (opsional)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" id="btn_save_jamaah" class="btn btn-primary" onclick="simpanJamaah()">Simpan</button>
            </div>
        </div>
    </div>
</div>

@endsection

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
@push('scripts')
<script>
    //  function printInvoice() {
    //   let id_nya = $("#id_invoice_view_detail").val();
    //     if(!id_nya) {
    //         alert("ID Invoice tidak ditemukan");
    //         return;
    //     }
    //     let url = "{{ route('invoice_hotel_print', ':id') }}";
    //     url = url.replace(':id', id_nya);
    //     window.open(url, '_blank');
    // }

    let lineSortable = null;
    // ========================================================
// BUKTI PEMBAYARAN (Dropzone di paymentModal, pakai endpoint yang sama)
// ========================================================
// ========================================================
// BUKTI PEMBAYARAN (Dropzone di paymentModal)
// Endpoint sama dengan bukti invoice: get-image-sales-inv, remove-image-sales-inv
// ========================================================
let myDropzonePay = null;
let isClearingDropzonePay = false;

// Hapus file dari server (sama seperti dropzone invoice)
function deleteBuktiPayFromServer(file) {
    $.ajax({
        url: "{{ route('remove-image-sales-inv') }}",
        type: "POST",
        data: { file_name: file.name },
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem("token"),
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            if (response.success) {
                console.log("Berhasil:", response.message);
            }
        },
        error: function (err) {
            console.error("Gagal menghapus file:", err);
            Swal.fire('Gagal!', 'File gagal dihapus dari server.', 'error');
        }
    });
}

// Download PDF: file dibuat server (dompdf)
$('#btnDownloadPdf').off('click').on('click', function () {
    const url = "{{ route('salles_invoice_pdf', ['id' => '__ID__']) }}".replace('__ID__', uuid);
    window.location.href = url;
});

// Download gambar: screenshot isi iframe jadi PNG
$('#btnDownloadImage').off('click').on('click', async function () {
    const $btn = $(this).prop('disabled', true);
    try {
        const frame = document.getElementById('previewInvoiceFrame');
        const doc   = frame.contentDocument;
        if (!doc || !doc.body) {
            throw new Error('Preview belum selesai dimuat.');
        }

        // Sembunyikan tombol print supaya tidak ikut tergambar
        const toolbar = doc.querySelector('.print-toolbar');
        if (toolbar) toolbar.style.display = 'none';

        const canvas = await html2canvas(doc.body, {
            scale: 2,
            backgroundColor: '#ffffff',
            useCORS: true,
            windowWidth: doc.body.scrollWidth,
        });

        if (toolbar) toolbar.style.display = '';

        // Nama file dari <title> preview: "Invoice #INV-0001"
        const name = (doc.title || 'invoice').replace(/[^A-Za-z0-9\-_#]+/g, '_');

        const link = document.createElement('a');
        link.download = name + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (err) {
        cathError(err);
    } finally {
        $btn.prop('disabled', false);
    }
});

// Tampilkan bukti dari server ke Dropzone pembayaran
function loadPaymentBuktiImages(invoiceId) {
    if (!myDropzonePay || !invoiceId) return;

    // Kosongkan tanpa memicu hapus ke server
    isClearingDropzonePay = true;
    myDropzonePay.removeAllFiles(true);
    isClearingDropzonePay = false;

    ajaxRequest("{{ route('get-image-sales-inv') }}", 'GET',
                { invoice_id: invoiceId },
                localStorage.getItem("token"))
        .then(response => {
            if (response.data.data.success && response.data.data.data.length > 0) {
                $.each(response.data.data.data, function (key, value) {
                    const isPdf = (value.type === 'pdf') ||
                                  (value.name && value.name.toLowerCase().endsWith('.pdf'));

                    const mockFile = {
                        name          : value.name,
                        size          : value.size,
                        accepted      : true,
                        status        : Dropzone.ADDED,
                        url           : value.url,
                        isFromServer  : true,
                        type          : isPdf ? 'application/pdf' : 'image/webp',
                    };

                    myDropzonePay.emit("addedfile", mockFile);
                    if (!isPdf) {
                        myDropzonePay.emit("thumbnail", mockFile, value.url);
                    }
                    myDropzonePay.emit("complete", mockFile);
                    myDropzonePay.files.push(mockFile);
                });
            }
        })
        .catch((err) => { cathError(err); });
}

$(function () {
    myDropzonePay = new Dropzone("#buktiDropzonePay", {
        url: "{{ route('uploadImage-sales-inv') }}",
        autoProcessQueue: false,
        uploadMultiple: true,
        parallelUploads: 25,
        maxFiles: 25,
        acceptedFiles: "image/jpeg,image/png,image/jpg,image/webp,application/pdf",
        addRemoveLinks: false,   // tombol hapus bawaan dimatikan, diganti tombol dengan konfirmasi
        dictInvalidFileType: "Hanya JPG, PNG, WEBP, dan PDF yang diizinkan.",
        dictFileTooBig: "File terlalu besar. Maksimal 10 MB.",
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem("token"),
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        init: function () {

            this.on("sending", function (file, xhr, formData) {
                formData.append("invoice_id", $('#invoices_id_parent').val());
            });

            this.on("addedfile", function (file) {
                if (file.type === "application/pdf") {
                    $(file.previewElement).find(".dz-image").html(
                        '<div style="display:flex;align-items:center;justify-content:center;' +
                        'width:100%;height:100%;background:#f8d7da;color:#842029;' +
                        'font-weight:700;font-size:16px;border-radius:6px;">PDF</div>'
                    );
                }

                // ── Tombol hapus dengan konfirmasi ──
                const $removeBtn = $(
                    '<button type="button" class="dz-confirm-remove" ' +
                    'style="display:block;margin:6px auto 0;background:none;border:none;' +
                    'color:#e74c3c;font-size:12px;cursor:pointer;">Hapus</button>'
                );
                $removeBtn.on('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    Swal.fire({
                        title: 'Hapus bukti ini?',
                        text: `"${file.name}" akan dihapus${file.isFromServer ? ' dari server' : ''}.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, hapus',
                        cancelButtonText: 'Batal',
                        reverseButtons: true
                    }).then(result => {
                        if (!result.isConfirmed) return;
                        // removeFile memicu event removedfile, yang menghapus di server jika isFromServer
                        myDropzonePay.removeFile(file);
                    });
                });
                file.previewElement.appendChild($removeBtn[0]);

                // Klik preview → buka modal (abaikan klik tombol hapus)
                file.previewElement.addEventListener("click", function (e) {
                    if (e.target.closest('.dz-confirm-remove')) return;
                    e.stopPropagation();
                    e.preventDefault();

                    let fileUrl = file.url || file.dataURL;
                    if (!fileUrl && file.status === Dropzone.ADDED) {
                        fileUrl = URL.createObjectURL(file);
                    }
                    if (!fileUrl) return;

                    const isPdf = (file.type === "application/pdf")
                        || (file.name && file.name.toLowerCase().endsWith('.pdf'));

                    if (isPdf) {
                        $('#previewPdfFrame').attr('src', fileUrl);
                        $('#previewPdfModal').modal('show');
                    } else {
                        $('#previewImageModalSrc').attr('src', fileUrl);
                        $('#previewImageModal').modal('show');
                    }
                });
            });

            this.on("successmultiple", function (files, response) {
                const duplicates = response.duplicates || [];

                isClearingDropzonePay = true;
                myDropzonePay.removeAllFiles(true);
                isClearingDropzonePay = false;

                loadPaymentBuktiImages($('#invoices_id_parent').val());

                if (duplicates.length > 0) {
                    const namesDup = duplicates.map(d => d.original_name).join(', ');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sebagian file dilewati',
                        html: `File berikut sudah pernah diupload sebelumnya:<br><b>${namesDup}</b>`
                    });
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sukses!',
                        text: 'Bukti pembayaran berhasil disimpan.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });

            this.on("errormultiple", function () {
                Swal.fire('Peringatan', 'Gagal mengupload bukti pembayaran. Silakan coba lagi.', 'warning');
            });

            // Hanya dipanggil setelah user konfirmasi (atau pembersihan internal diabaikan)
            this.on("removedfile", function (file) {
                if (isClearingDropzonePay) return;
                if (file.isFromServer) {
                    deleteBuktiPayFromServer(file);
                }
            });
        }
    });
});

// Submit form = proses antrean upload
$('#formPaymentBukti').on('submit', function (e) {
    e.preventDefault();

    const invoiceId = $('#invoices_id_parent').val();
    if (!invoiceId) {
        Swal.fire('Peringatan', 'Invoice belum dipilih.', 'warning');
        return;
    }

    if (myDropzonePay.getQueuedFiles().length === 0) {
        Swal.fire('Peringatan', 'Belum ada file bukti yang dipilih.', 'warning');
        return;
    }

    myDropzonePay.processQueue();
});

//agar modal payment tidak close
$('#paymentModal').on('hide.bs.modal', function (e) {
    if ($('.select2-container--open').length > 0) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
});

function simpanJamaah(){
    let payload = {
        id: $('#id_jamaah').val() || null,
        full_name: $('#full_name').val().trim(),
        phone_number: $('#phone_number').val().trim(),
        nik: $('#nik').val().trim(),
        detail_address: $('#detail_address').val().trim(),
    };

    if(!payload.full_name || !payload.phone_number){
        Swal.fire('Peringatan', 'Nama lengkap dan No. Telepon wajib diisi', 'warning');
        return;
    }

    $('#btn_save_jamaah').prop('disabled', true).text('Menyimpan...');

    ajaxRequest(`{{ route('save-jamaah') }}`, 'POST', payload, localStorage.getItem('token'))
        .then(response => {
            if(response.status == 200){
                // ⚠️ sesuaikan dengan struktur response backend-mu
                const saved = response.data?.data || response.data || {};
                console.log('savedj',response.data)
                $('#modalCreateJamaah').modal('hide');
                Swal.fire({ title: 'Berhasil', text: 'Data contact tersimpan', icon: 'success', timer: 1500, showConfirmButton: false });

                if (saved.id) {
                    setSelect2Value('#contact_id', saved.id, saved.full_name || payload.full_name);
                } else {
                    console.warn('save-jamaah tidak mengembalikan id, contact tidak bisa auto-select', response);
                }
            } else {
                Swal.fire('Gagal', response?.data?.data?.message || 'Gagal menyimpan data contact', 'error');
            }
        })
        .catch((err) => {
           cathError(err)
        })
        .finally(() => {
            $('#btn_save_jamaah').prop('disabled', false).text('Simpan');
        });
}

$('#paymentModal').on('shown.bs.modal', function () {
    $(document).off('focusin.bs.modal');
 
  
    $('#newPaymentRowsBody tr').each(function () {
        var $row = $(this);
        var $sel = $row.find('.pay-bank-select');
        if ($sel.length && !$sel.hasClass('select2-hidden-accessible')) {
            initBankSelectOnRow($row);
        }
    });
});

$('#paymentModal').on('hidden.bs.modal', function () {
    $('#newPaymentRowsBody .pay-bank-select').each(function () {
        if ($(this).hasClass('select2-hidden-accessible')) {
            $(this).select2('destroy');
        }
    });
    // Select2 lainnya di dalam modal
    ['#overpay_id_modal2', '#bank_overpay_id'].forEach(function (sel) {
        var $el = $(sel);
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
    });
});
//agar modal payment tidak close

let payFormMode = 'new'; // 'new' | 'edit'

    function buildPaymentRow(data = {}) {
        const today = new Date().toISOString().split('T')[0];
        return `
            <tr>
                <td>
                    <input type="date" class="form-control form-control-sm pay-date"
                        value="${data.date || today}">
                </td>
                <td>
                    <select class="form-control form-control-sm pay-bank-select"
                            style="width:100%;">
                        ${data.bankId
                            ? `<option value="${data.bankId}" selected>${data.bankName || ''}</option>`
                            : ''}
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm pay-nominal"
                        min="1" placeholder="0" value="${data.nominal || ''}">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm pay-ref"
                        placeholder="Optional" value="${data.reference || ''}">
                </td>
                <td class="text-center">
                    <button type="button" class="btn-del-pay-row"
                            style="background:none;border:1px solid #e74c3c;color:#e74c3c;
                                border-radius:3px;padding:2px 6px;cursor:pointer;font-size:11px;"
                            title="Hapus baris">
                       <i class="ti ti-trash"></i>
                    </button>
                </td>
            </tr>`;
    }



        function initLineSortable() {
            const el = document.getElementById('lineItemsBody');
            if (!el) return;

            // Destroy dulu kalau sudah ada (misal setelah reload)
            if (lineSortable) { lineSortable.destroy(); lineSortable = null; }

            lineSortable = new Sortable(el, {
                handle      : '.drag-handle',   // hanya drag dari handle
                animation   : 150,
                ghostClass  : 'row-sortable-ghost',
                chosenClass : 'row-sortable-chosen',
                // Setelah drop selesai, update nilai sort_order di semua baris
                onEnd: function () {
                    updateSortOrders();
                }
            });
        }

        function updateSortOrders() {
            $('#lineItemsBody tr').each(function (i) {
                $(this).find('.sort-order-input').val(i + 1);
            });
        }

  // ================================================================
// TEMUKAN fungsi initBankSelectOnRow yang sudah ada di blade,
// dan PASTIKAN bagian berikut sudah ada (biasanya sudah benar):
//
//     dropdownParent: $('#paymentModal'),
//
// Jika belum ada atau berbeda, pastikan seluruh fungsi seperti ini:
// ================================================================

    function initBankSelectOnRow($row, bankId = null, bankName = null) {
        const $sel = $row.find('.pay-bank-select');

        // Destroy dulu jika sudah pernah diinit (cegah double-init)
        if ($sel.hasClass('select2-hidden-accessible')) {
            $sel.select2('destroy');
        }

        $sel.select2({
            theme           : 'bootstrap4',
            width           : '100%',
            // ← WAJIB: agar dropdown masuk ke dalam modal,
            //   bukan ke <body>. Tanpa ini → klik dropdown = klik backdrop.
            dropdownParent  : $('#paymentModal'),
            placeholder     : 'Ketik nama bank...',
            allowClear      : true,
            minimumInputLength: 1,
            language: {
                inputTooShort: () => 'Ketik minimal 1 huruf untuk mencari bank...',
                searching    : () => 'Mencari...',
                noResults    : () => 'Bank tidak ditemukan',
            },
            ajax: {
                url     : "{{ route('getbankselect2') }}",
                type    : 'GET',
                dataType: 'json',
                delay   : 350,
                data    : (p) => ({
                    page      : p.page || 1,
                    keyword   : p.term || '',
                    limit     : 10,
                    kolom_name: 'name'
                }),
                processResults: (response) => ({
                    results: $.map(response.data.data || [], (item) => ({
                        id  : item.id,
                        text: item.name + ' (' + (item.currency_code || '-') + ')'
                    })),
                    // cast ke boolean — jaga-jaga jika next_page_url null
                    pagination: { more: !!response.data.next_page_url }
                }),
                // Matikan cache — shared cache antar row menyebabkan
                // satu row bisa trigger load halaman milik row lain
                cache: false
            },
            templateSelection: function (data) {
                if (!data.id) return data.text;
                return data.text;
            }
        });

        // Set nilai pre-selected (saat mode edit)
        if (bankId && bankName) {
            $sel.append(new Option(bankName, bankId, true, true)).trigger('change');
        }
    }

    function addPaymentRow(data = {}) {
        const $row = $(buildPaymentRow(data));
        $('#newPaymentRowsBody').append($row);
        initBankSelectOnRow($row, data.bankId || null, data.bankName || null);
        updateRemovePayRowBtns();
    }

    function updateRemovePayRowBtns() {
        const count = $('#newPaymentRowsBody tr').length;
        // Minimal 1 baris di mode new, di mode edit boleh 0 (tidak relevan)
        $('#newPaymentRowsBody .btn-del-pay-row').prop('disabled', count <= 1 && payFormMode === 'new');
    }

    function resetPaymentForm() {
        payFormMode = 'new';
        $('#row_payment_id').val('');
        $('#pay_mode').val('new');

        // Destroy select2 semua row lama
        $('#newPaymentRowsBody .pay-bank-select').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
        });
        $('#newPaymentRowsBody').empty();
        addPaymentRow(); // 1 baris kosong

        $('#payFormTitle').html('<i class="fa fa-plus-circle mr-1"></i> Tambah Pembayaran Baru');
        $('#payBtnLabel').text('Simpan Pembayaran');
        $('#btnAddPayRow').show();
        $('#btnCancelEditInv').hide();
        $('#wrapBankRows').show();

        // Reset credit form
        $('#credit_date').val(new Date().toISOString().split('T')[0]);
        $('#credit_nominal').val('');
        $('#credit_ref').val('');
    }

    function switchToEditPayMode(data) {
        payFormMode = 'edit';
        $('#row_payment_id').val(data.id);
        $('#pay_mode').val('edit');

        // Clear semua row, isi 1 row pre-filled
        $('#newPaymentRowsBody .pay-bank-select').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
        });
        $('#newPaymentRowsBody').empty();
        addPaymentRow(data);

        $('#payFormTitle').html('<i class="fa fa-pencil mr-1"></i> Edit Pembayaran');
        $('#payBtnLabel').text('Update Pembayaran');
        $('#btnAddPayRow').hide();
        $('#btnCancelEditInv').show();

        // Scroll ke form
        document.getElementById('newPaymentRowsTable')
            .scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } 

    var table;

    const now = new Date();
    const year  = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    let nom_over_pay = 0;

    // ── APPLY CREDIT (OVERPAYMENT) ── daftar overpayment invoice yang sedang dibuka di paymentModal
    let listAllOverpayCurrent = [];

    function populateOverpaySelect() {
        if ($('.select2-overpay-credit').hasClass('select2-hidden-accessible')) {
            $('.select2-overpay-credit').select2('destroy');
        }
        $('#overpay_id_modal2').empty().append('<option value="">-- Pilih Overpayment --</option>');

        $('.select2-overpay-credit').select2({
            theme: 'bootstrap4',
            width:'100%',
            dropdownParent: $('#paymentModal'),
            placeholder: '-- Pilih Overpayment --',
            allowClear: true,
            data: $.map(listAllOverpayCurrent, function (item) {
                if(item.nominal_overpayment > 0)
                {
                    return {
                        id: item.id, // dikirim ke backend sebagai overpay_id
                        text: (item.inv_number || '-') + ' - ' + formatCurrency(item.nominal_overpayment)
                    };
                }
            })
        });
    }

    function initBankOverpaySelect() {
        const $el = $('#bank_overpay_id');
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        $el.select2({
            theme          : 'bootstrap4',
            width          : '100%',
            dropdownParent : $('#paymentModal'),
            placeholder    : 'Ketik nama bank...',
            allowClear     : true,
            minimumInputLength: 1,
            language: {
                inputTooShort: () => 'Ketik minimal 1 huruf untuk mencari bank...',
                searching    : () => 'Mencari...',
                noResults    : () => 'Bank tidak ditemukan',
            },
            ajax: {
                url     : "{{ route('getbankselect2') }}",
                type    : 'GET',
                dataType: 'json',
                delay   : 350,
                data    : (p) => ({ page: p.page || 1, keyword: p.term || '', limit: 10, kolom_name: 'name' }),
                processResults: (response) => ({
                    results: $.map(response.data.data || [], (item) => ({
                        id  : item.id,
                        text: item.name + ' (' + (item.currency_code || '-') + ')'
                    })),
                    pagination: { more: !!response.data.next_page_url }
                }),
                cache: false
            }
        });
    }

    // toggle field "Pilih Bank" <-> "Pilih Overpayment" sesuai radio yang dipilih
    $(document).on('change', 'input[name="payment_method"]', function () {
        const method = $(this).val();
         if (method === 'credit') {
            $('#wrapBankRows').hide();
            $('#wrapCreditForm').show();
            $('#btnAddPayRow').hide();
            initBankOverpaySelect()
        } else {
            $('#wrapCreditForm').hide();
            $('#wrapBankRows').show();
            if (payFormMode === 'new') $('#btnAddPayRow').show();
        }
    });

    $('#btnAddPayRow').on('click', function () {
        if (payFormMode === 'new') addPaymentRow();
    });

    // ── Remove row ───────────────────────────────────────────
    $(document).on('click', '.btn-del-pay-row', function () {
        const count = $('#newPaymentRowsBody tr').length;
        if (count <= 1 && payFormMode === 'new') return;
        const $sel = $(this).closest('tr').find('.pay-bank-select');
        if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
        $(this).closest('tr').remove();
        updateRemovePayRowBtns();
    });

    // ── Batal edit ───────────────────────────────────────────
    $('#btnCancelEditInv').on('click', function () {
        resetPaymentForm();
    });


    $('#btnSaveAllPayments').on('click', async function () {
        const mode        = $('#pay_mode').val();
        const parentInvId = $('#invoices_id_parent').val();
        const $btn        = $(this).prop('disabled', true);

        if (mode === 'edit') {
            // ── Edit single payment ──────────────────────────
            const $row = $('#newPaymentRowsBody tr:first');
            const bank    = $row.find('.pay-bank-select').val();
            const nominal = $row.find('.pay-nominal').val();
            const date    = $row.find('.pay-date').val();
            const ref     = $row.find('.pay-ref').val();

            if (!bank || !nominal || !date) {
                Swal.fire('Peringatan', 'Lengkapi Bank, Nominal, dan Tanggal.', 'warning');
                $btn.prop('disabled', false);
                return;
            }

            try {
                const res = await ajaxRequest(`{{ route('update-pay-sales-inv') }}`, 'POST', {
                    id               : $('#row_payment_id').val(),
                    uuid_bank        : bank,
                    nominal_receive  : nominal,
                    reference_detail : ref,
                    date_transaction : date,
                    parent_inv_id    : parentInvId,
                }, localStorage.getItem('token'));

                if (res.status == 200) {
                    Swal.fire({ icon: 'success', title: 'Berhasil diupdate!', timer: 1500, showConfirmButton: false });
                    resetPaymentForm();
                    loadPaymentHistoryModal(parentInvId);
                    table.ajax.reload(null, false);
                }
            } catch (err) { cathError(err); }
            finally { $btn.prop('disabled', false); }

        } else {
            // ── New multi-row payments ───────────────────────
            const rows     = $('#newPaymentRowsBody tr');
            let payloads   = [];
            let hasError   = false;

            rows.each(function () {
                const $row    = $(this);
                const bank    = $row.find('.pay-bank-select').val();
                const nominal = $row.find('.pay-nominal').val();
                const date    = $row.find('.pay-date').val();

                if (!bank || !nominal || !date) { hasError = true; return false; }

                payloads.push({
                    date_transaction : date,
                    uuid_bank        : bank,
                    nominal_receive  : nominal,
                    reference_detail : $row.find('.pay-ref').val(),
                    parent_inv_id    : parentInvId,
                });
            });

            if (hasError) {
                Swal.fire('Peringatan', 'Lengkapi Tanggal, Bank, dan Nominal pada setiap baris.', 'warning');
                $btn.prop('disabled', false);
                return;
            }

            try {
                for (const payload of payloads) {
                    await ajaxRequest(`{{ route('save-pay-sales-inv') }}`, 'POST', payload, localStorage.getItem('token'));
                }
                Swal.fire({
                    icon: 'success', title: 'Berhasil!',
                    text: `${payloads.length} pembayaran berhasil disimpan.`,
                    timer: 1500, showConfirmButton: false
                });
                resetPaymentForm();
                loadPaymentHistoryModal(parentInvId);
                table.ajax.reload(null, false);
            } catch (err) { cathError(err); }
            finally { $btn.prop('disabled', false); }
        }
    });
    // ── APPLY CREDIT (OVERPAYMENT) ── versi form "Add Payment" inline di modalCreateHotel

    $('#btnSaveCreditPayment').on('click', function () {
        const overpayId = $('#overpay_id_modal2').val();
        if (!overpayId) { Swal.fire('Peringatan', 'Pilih overpayment terlebih dahulu.', 'warning'); return; }

        const payload = {
            overpay_id       : overpayId,
            nominal_spend    : $('#credit_nominal').val(),
            reference_detail : $('#credit_ref').val(),
            date_transaction : $('#credit_date').val(),
            parent_inv_id    : $('#invoices_id_parent').val(),
            uuid_bank : $("#bank_overpay_id").val()
        };

        ajaxRequest(`{{ route('save-payover-sales-inv') }}`, 'POST', payload, localStorage.getItem('token'))
        .then(res => {
            if (res.status == 200) {
                Swal.fire({ icon: 'success', title: 'Berhasil!', timer: 1500, showConfirmButton: false });
                loadPaymentHistoryModal(payload.parent_inv_id);
                table.ajax.reload(null, false);
            }
        })
        .catch(err => cathError(err));
    });

    $(document).on('click', '.btn-edit-inv-pay', function () {
        switchToEditPayMode({
            id        : $(this).data('id'),
            date      : $(this).data('date'),
            bankId    : $(this).data('bank-id'),
            bankName  : $(this).data('bank-name'),
            nominal   : parseFloat($(this).data('nominal')),
            reference : $(this).data('reference'),
        });
        // pastikan mode bank aktif
        console.log($(this).data('nominal'));
        $('#method_bank').prop('checked', true).trigger('change');
    });


    $(document).on('click', '.btn-delete-inv-pay', function () {
        const payId      = $(this).data('id');
        const nominalStr = $(this).data('nominal');
        const parentId   = $('#invoices_id_parent').val();

       
        Swal.fire({
            title           : 'Hapus Pembayaran?',
            html            : `Nominal <b>${nominalStr}</b> akan dihapus.<br>Sisa tagihan akan dikembalikan.`,
            icon            : 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor : '#6c757d',
            confirmButtonText : 'Ya, Hapus!',
            cancelButtonText  : 'Batal',
            reverseButtons  : true
        }).then(result => {
            if (!result.isConfirmed) return;

            ajaxRequest(`{{ route('delete-pay-sales-inv') }}`, 'POST',
                { id: payId, parent_inv_id: parentId },
                localStorage.getItem('token')
            )
            .then(res => {
                if (res.status == 200) {
                    Swal.fire({ icon: 'success', title: 'Dihapus!', timer: 1200, showConfirmButton: false });
                    loadPaymentHistoryModal(parentId);
                    table.ajax.reload(null, false);
                }
            })
            .catch(err => cathError(err));
        });
    });

    let listAllOverpayCurrentAdd = [];

    function populateOverpaySelectAdd() {
        if ($('.select2-overpay-credit-add').hasClass('select2-hidden-accessible')) {
            $('.select2-overpay-credit-add').select2('destroy');
        }
        $('#overpay_id_add').empty().append('<option value="">-- Pilih Overpayment --</option>');

        $('.select2-overpay-credit-add').select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalCreateHotel'),
            placeholder: '-- Pilih Overpayment --',
            allowClear: true,
            data: $.map(listAllOverpayCurrentAdd, function (item) {
                if(item.nominal_overpayment > 0){
                    return {
                       id: item.id, // dikirim ke backend sebagai overpay_id
                       text: (item.inv_number || '-') + ' - ' + formatCurrency(item.nominal_overpayment)
                    };
                }
              
            })
        });
    }

    $(document).on('change', 'input[name="payment_method_add"]', function () {
        const method = $(this).val();
        if (method === 'credit') {
            $('#wrap_bank_select_add').hide();
            $('#wrap_overpay_select_add').show();
            $('#account_bank').val(null).trigger('change');
        } else {
            $('#wrap_overpay_select_add').hide();
            $('#wrap_bank_select_add').show();
            $('#overpay_id_add').val(null).trigger('change');
        }
    });




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
        myDropzone = new Dropzone("#buktiDropzone", {
            url: "{{ route('uploadImage-sales-inv') }}",
            autoProcessQueue: false,
            uploadMultiple: true,
            parallelUploads: 10,
            maxFiles: 10,
            // ── TERIMA GAMBAR + PDF ──
            acceptedFiles: "image/jpeg,image/png,image/jpg,image/webp,application/pdf",
            addRemoveLinks: true,
            dictInvalidFileType: "Hanya JPG, PNG, WEBP, dan PDF yang diizinkan.",
            dictFileTooBig:      "File terlalu besar. Maksimal 10 MB.",
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem("token"),
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            init: function() {

                // ── Kirim invoice_id untuk setiap file ──
                this.on("sending", function(file, xhr, formData) {
                    formData.append("invoice_id", $('#idHotelInput').val());
                });

                // ── Thumbnail custom: PDF → ikon, gambar → default ──
                // ── Thumbnail custom: PDF → ikon, gambar → default ──
                this.on("addedfile", function(file) {

                    // PDF: ganti thumbnail jadi ikon
                    if (file.type === "application/pdf") {
                        const $thumb = $(file.previewElement).find(".dz-image");
                        $thumb.html(
                            '<div style="display:flex;align-items:center;justify-content:center;' +
                            'width:100%;height:100%;background:#f8d7da;color:#842029;' +
                            'font-weight:700;font-size:16px;border-radius:6px;">PDF</div>'
                        );
                    }

                    // Klik preview → conditional
                    file.previewElement.addEventListener("click", function(e) {
                        // Jangan trigger kalau klik tombol remove
                        if (e.target.closest('.dz-remove')) return;

                        e.stopPropagation();
                        e.preventDefault();

                        let fileUrl = file.url || file.dataURL;
                        if (!fileUrl && file.status === Dropzone.ADDED) {
                            fileUrl = URL.createObjectURL(file);
                        }
                        if (!fileUrl) return;

                        // ▼▼▼ DISINI TARUH if (isPdf) ▼▼▼
                        const isPdf = (file.type === "application/pdf")
                            || (file.name && file.name.toLowerCase().endsWith('.pdf'));

                        if (isPdf) {
                            $('#previewPdfFrame').attr('src', fileUrl);
                            $('#previewPdfModal').modal('show');
                        } else {
                            $('#previewImageModalSrc').attr('src', fileUrl);
                            $('#previewImageModal').modal('show');
                        }
                        // ▲▲▲ SAMPAI SINI ▲▲▲
                    });
                });

                // ── Sukses ──
                this.on("successmultiple", function(files, response) {
                    const uploaded   = response.data || [];
                    const duplicates = response.duplicates || [];

                    if (duplicates.length > 0) {
                        const namesDup = duplicates.map(d => d.original_name).join(', ');
                        Swal.fire({
                            icon: 'warning',
                            title: 'Sebagian file dilewati',
                            html: `File berikut sudah pernah diupload sebelumnya:<br><b>${namesDup}</b>`
                        });
                    } else {
                        Swal.fire({
                            title: 'Sukses!',
                            text: 'Data invoice dan bukti berhasil disimpan.',
                            imageUrl: "{{ asset('assets/img/foto.png') }}",
                            imageWidth: 90,
                            imageHeight: 90,
                            imageAlt: 'Berhasil'
                        });
                    }
                });

                this.on("errormultiple", function(files, response) {
                    Swal.fire('Peringatan', 'Invoice tersimpan, namun gagal mengupload file.', 'warning');
                });

                // ── Hapus file dari server ──
                this.on("removedfile", function(file) {
                    if (isClearingDropzone) return;
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
                                if (response.success) {
                                    console.log("Berhasil:", response.message);
                                }
                            },
                            error: function(err) {
                                console.error("Gagal menghapus file:", err);
                                Swal.fire('Gagal!', 'File gagal dihapus dari server.', 'error');
                            }
                        });
                    }
                });
            }
        });

        // Toggle dropzone container
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
        { data: 'reference', name: 'reference' },
        { data: 'contact_name', name: 'contact_name' },
        { data: 'issue_date', name: 'issue_date' },
        { 
          data: 'created_by_name', 
          render: function(data,type,row){
            let cek_nam = data != null ? data : '-';
            return cek_nam;
          }
        },
        {
            data: 'invoice_total', 
            render: function(data,type,row){
            const formatter = new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
                return `<b>${row.code_curr} </b>|${parseFloat(data).toLocaleString()}`;
            } 
        }, // Sesuaikan jika nama hotel ada relasi
        {
            data: 'invoice_amount', 
            render: function(data,type,row){
                return `<b>${row.code_curr} </b>|${parseFloat(data).toLocaleString()}`;
            } 
        },
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
                let uuid_nya = row.invoice_uuid;
                let print_Inv = `<a style="margin-left:5px;" href="javascript:;" 
                                    data-id="${uuid_nya}" 
                                    onclick="printInvoice('${uuid_nya}')"
                                    class="text-success show-invoice-modal" 
                                    title="invoice">
                                    <i class="ti ti-clipboard"></i>
                                </a>`
                let delete_inv = `<a style="margin-left:5px;" href="javascript:;" 
                                    data-id="${uuid_nya}" 
                                    onclick="hapusInvoice('${data}')"
                                    class="text-danger show-invoice-modal" 
                                    title="hapus invoice">
                                    <i class="ti ti-trash"></i>
                                </a>`
                return `
                    <a href="javascript:;" data-id="${data}" class="text-primary edit-hotel mr-2" title="Edit Invoice"><i class="ti ti-pencil"></i></a>
                    <a href="javascript:;" data-id="${data}" class="text-success show-payment-modal mr-2" title="Payment History"><i class="ti ti-credit-card"></i></a>
                    <a href="javascript:;" 
                        data-id="${uuid_nya}" 
                        onclick="privewiInvoice('${uuid_nya}')"
                        class="text-danger show-invoice-privew" 
                        title="preview invoice">
                        <i class="ti ti-eye"></i>
                    </a>
                   ${delete_inv}
                    `
                ;
            },
        }
       ];

    table = initGlobalDataTableToken('#tableHotel', `{{ route('list-inv-xero-local') }}`, columnHotel, { "kolom_name": "contact_name" });

    function formatDate(date) {
        const [y, m, d] = date.split('-');
        return `${d}/${m}/${y}`;
    }

    
function hapusInvoice(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: 'Data invoice ini akan dihapus. Tindakan ini mungkin tidak dapat dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            ajaxRequest(
                `{{ route('delete-p-inv-trans') }}`,
                'POST',
                { id: id },
                localStorage.getItem("token")
            )
            .then(response => {
                if (response.status == 200) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Invoice berhasil dihapus.',
                        timer: 1500,
                        showConfirmButton: false
                    });

                    table.ajax.reload();
                }
            })
            .catch((err) => {
                cathError(err);
            });
        }
    });
}

    // ── Preview Invoice → buka di modal (iframe) ────────────────────────────
    function privewiInvoice(uuid) {
        const $frame   = $('#previewInvoiceFrame');
        const $loading = $('#previewInvoiceLoading');

        // Reset setiap kali modal dibuka
        $frame.hide().attr('src', '');
        $loading.show();
        $('#labelModalPreviewInvoice').html(
            '<i class="ti ti-file-invoice mr-1"></i> Preview Invoice'
        );

        // Kaitkan tombol download ke uuid invoice ini
        $('#btnDownloadPdf').off('click').on('click', function () {
            printInvoice(uuid);
        });

        // Buka modal dulu → user langsung lihat loading spinner
        $('#modalPreviewInvoice').modal('show');

        // Set src setelah modal tampil → trigger iframe load
        const previewUrl = "{{ route('salles_invoice_preview', ['id' => '__ID__']) }}"
                                .replace('__ID__', uuid);
        $frame.attr('src', previewUrl);
    }

    // Dipanggil oleh iframe onload
   

    //--open modal dari report
    //untuk auto open dari report
    const urlParams = new URLSearchParams(window.location.search);
    const openId = urlParams.get('open');
    const openPaymentFromCoa =  urlParams.get('openPayId');

    if (openId) {
        openDetailModal(openId);
    }else if(openPaymentFromCoa){
         openDetailModal(openPaymentFromCoa);
        //openModalPyamentAuto(openPaymentFromCoa)
    }

    function openModalPyamentAuto(id){
        // const rowData = table.row($(this).parents('tr')).data();
        $('#invoices_id_parent').val(id); // Set hidden ID untuk form tambah bayar
        $("#idHotelInput").val(id)
       // $('#invoice_uuid_payment').val(rowData.invoice_uuid || ''); 
        resetPaymentForm();
        resetFeeOverpayRows();


        $('#formSubmitPayment').find('.select2-account-bank-modal2').each(function() {
            $(this).val(null).trigger('change');
        });
        $("#input_payment_date").val(null)
        $("#input_payment_nominal").val(0)
        $("#input_payment_ref").val('')

        // ── APPLY CREDIT: reset ke mode default "bank" tiap kali modal dibuka ──
        $('#method_bank').prop('checked', true);
        $('#wrap_overpay_select').hide();
        $('#wrap_bank_select').show();

        // Munculkan Modal
        $('#paymentModal').modal('show');
        
        // Panggil AJAX Load History
        loadPaymentHistoryModal(id);
    }

    function openDetailModal(id_inv) {
        $('#idHotelInput').val(id_inv);
        $('.modal-title').text(`Edit Invoice `);
        loadInvoice(id_inv);
        loadDropzoneImages(id_inv);
        $('#modalCreateHotel').modal('show');
    }
    //untuk auto open dari report
    //--open modal dari report

     $('#tableHotel').on('click', '.edit-hotel', function() {
        const id      = $(this).data('id');
        const rowData = table.row($(this).parents('tr')).data();
        $("#btn_duplicate").show()
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


    function addRowWithData(item,index) {
    // 1. Buat baris kosong dan append
        const $row = $(buildRow());
        $('#lineItemsBody').append($row);

        // 2. Isi hidden id_detail (untuk update, bukan insert baru)
        $row.find('input[name="id_detail[]"]').val(item.id || '');

        $row.find('.sort-order-input').val(item.sort_order ?? index ?? 0);
        // 3. Isi field teks sederhana
       $row.find('.desc-input').val(item.desc || '').trigger('change');
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
          
            console.log('edit invoice',d)
            renderPaymentHistory(d.get_payment,d.less_nominal)
            renderHistoryLog(d.get_history_invoice)

            // ── APPLY CREDIT: cek apakah invoice ini punya overpayment yang bisa dipakai ──
            listAllOverpayCurrentAdd = (d.get_jamaah && d.get_jamaah.list_all_overpay) ? d.get_jamaah.list_all_overpay : [];
            if (listAllOverpayCurrentAdd.length > 0 && parseFloat(d.less_payment) > 0) {
                $('#paymentMethodWrapAdd').show();
                populateOverpaySelectAdd();
            } else {
                $('#paymentMethodWrapAdd').hide();
            }
            $('#method_bank_add').prop('checked', true);
            $('#wrap_overpay_select_add').hide();
            $('#wrap_bank_select_add').show();
            // ── 1. Header fields ─────────────────────────────
            $('#invoice_number_display').val(d.invoice_number || '');
            $('#issue_date').val(d.issue_date || '');         // name="issue_date" id="issue_date"
            $('#due_date').val(d.due_date || '');
            $('#reference').val(d.reference || '');
            $('#invoiceStatusBadge').text(d.status);
            $("#va_number").val(d.va_number || '')
            // $("#currency_selected").val(d.code_curr).trigger('change')

            if ($('#currency_selected option[value="' + d.code_curr + '"]').length === 0) {
                $('#currency_selected').append(new Option(d.code_curr, d.code_curr));
            }
            $('#currency_selected').val(d.code_curr).trigger('change');
            if (d.code_curr !== 'IDR') {
                $('#nominal_currency').val(parseFloat(d.nominal_currency));
            }
            syncKurs();
            lockCurrency(true);

            $("#branding_theme_id").val(d.branding_theme_id ).trigger('change')
            if( d.travel_id)
              $("#travel_id").val(d.travel_id).trigger('change')

            // ── 2. Contact — inject option ke Select2 ────────
            // Field: name="contact_id" id="contact_id"
            if (d.contact_id) {
                const contactText = d.contact_name || ('Contact #' + d.contact_id);
                setSelect2Value('#contact_id', d.contact_id, contactText);
            }else if(d.id_jamaah_alhid){
                 const contactText = d.contact_name || ('Contact #' + d.id_jamaah_alhid);
                setSelect2Value('#id_jamaah_alhid', d.id_jamaah_alhid, contactText);
            }

            $("#summaryTotalPayment").text(d.code_curr == 'IDR' ?  formatNumber(d.invoice_amount) : '–');
            $("#summaryRemainder").text(d.code_curr == 'IDR' ?  formatNumber(d.less_nominal) : '–');
            // ── 3. Detail rows ───────────────────────────────
            // Bersihkan dulu (destroy select2 dalam baris lama)
            destroyRowSelect2();
            $('#lineItemsBody').empty();

            const details = d.get_detail_by_id || [];

            if (details.length > 0) {
                details.forEach(function (item, index) {
                    addRowWithData(item,index);
                });
            } else {
                addFirstRow(); // minimal 1 baris kosong
            }

            updateDeleteButtons();
            syncCurrencyLabels();
            recalcSummary();
        })
        .catch(function (err) {
         
            if(err.status == 423){
                 $('#modalCreateHotel').modal('hide');
            }
            cathError(err)
            // console.error('[loadInvoice] error:', err);
            // Swal.fire('Gagal!', err.message || 'Terjadi kesalahan saat memuat data.', 'error');
        })
        .finally(function () {
            showModalLoading(false);
        });
    }


    function printInvoice(idTes) {
        if(!idTes) {
            alert("ID Invoice tidak ditemukan");
            return;
        }
        let url = "{{ route('salles_invoice_print', ':id') }}";
        url = url.replace(':id', idTes);
        window.open(url, '_blank');
    }


    function formatNumber(num) {
        return parseFloat(num).toLocaleString('id-ID');
    }


    $("#close_modal_payment").on('click',function(){
        
        // window.location.reload();
            ajaxRequest(`{{ route('unlock-sales-inv') }}`, 'POST', {'id':$('#idHotelInput').val()}, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200) {
                    table.ajax.reload();
                }
            })
            .catch((err) => {
                cathError(err)
            });
    })

    

    function resetHotelModal() {
    // ── Reset native form fields (text, date, number, hidden inputs) ──
        $('#formCreateHotel')[0].reset();
         const today = new Date();

        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');

        const issueDate = `${year}-${month}-${day}`;

        // Set issue date
        $('#issue_date').val(issueDate);

        // Issue date + 30 hari
        const dueDate = new Date(today);
        dueDate.setDate(dueDate.getDate() + 30);

        const dueYear = dueDate.getFullYear();
        const dueMonth = String(dueDate.getMonth() + 1).padStart(2, '0');
        const dueDay = String(dueDate.getDate()).padStart(2, '0');

        $('#due_date').val(`${dueYear}-${dueMonth}-${dueDay}`);
        // ── Hidden fields — reset() kadang tidak konsisten di semua browser, jadi di-set eksplisit ──
        $('#idHotelInput').val('');
        $('#actionTypeValue').val('');

        // ── Select2 (native reset() TIDAK me-refresh tampilan Select2, harus trigger manual) ──
        $('#contact_id').val(null).trigger('change');
        $('#account_bank').val(null).trigger('change');

        // ── APPLY CREDIT: reset radio & select overpay inline ──
        $('#method_bank_add').prop('checked', true);
        $('#wrap_overpay_select_add').hide();
        $('#wrap_bank_select_add').show();
        $('#paymentMethodWrapAdd').hide();
        if ($('.select2-overpay-credit-add').hasClass('select2-hidden-accessible')) {
            $('#overpay_id_add').val(null).trigger('change');
        }

        // ── Plain <select> dikembalikan ke opsi default ──
       // $('#currency_selected').val('IDR');
        lockCurrency(false);
        $('#currency_selected').val('IDR').trigger('change');
        $('#nominal_currency').val('');
        //$('#amount_are').val('0');

        // ── Text/date fields (extra safety net, walau harusnya sudah ke-cover reset() di atas) ──
     
          ajaxRequest(`{{ route('get_new_inv_number') }}`, 'GET', {}, localStorage.getItem("token"))
            .then(response => {
                if(response.status == 200){
                    $('#invoice_number_display').val(response.data.inv);
                }else{
                     $('#invoice_number_display').val('auto');
                }
               
                //console.log('res',)
            })
            .catch((err) => {
                cathError(err)
            });
        $('#reference').val('');

        // ── Info kurs ──
        $('#text_currency').text('–');

        // ── Kosongkan tabel line items (baris-baris ini di-generate via JS, bukan static HTML) ──
        $('#lineItemsBody').empty();
        // kalau ada fungsi default 1 baris kosong saat modal dibuka, panggil di sini, contoh:
        // addNewLineItemRow();

        // ── Reset ringkasan/summary ──
        $('#summarySubtotalSAR').text('–');
        $('#summarySubtotalIDR').text('Rp 0');
        $('#summaryTax').text('0.00');
        $('#summaryTotal').text('Rp 0');

        // ── Kosongkan log history ──
        $('#history_log_list').empty();

        // ── Reset form payment & sembunyikan section-nya lagi ──
        $('#date_paid').val('');
        $('#amount_paid').val('');
        $('#payment_reference').val('');
        $('#paymentFormSection').hide();

        // ── Reset Dropzone (hapus semua file yang sempat di-upload) ──
        try {
            const dz = Dropzone.forElement('#buktiDropzone');
            if (dz) {
                dz.removeAllFiles(true);
            }
        } catch (e) {
            // instance belum ter-init, aman diabaikan
        }
        $('#dropzone-container').hide();

        $('#modalInvoiceTitle').text('New invoice');
        $('#invoiceStatusBadge').text('Draft');
    }



    // Trigger Tambah Baru
    $("#button_add_hotel").on("click", function() {
        // $('.modal-title').text('Add New Invoices Hotel');
        // $('#issue_date').val('m-d-Y');
        // $('#due_date').val('m-d-Y');
        // $('#invoice_number_display').val('auto');
        // $("#reference").val('')
        // Reset handled by modal hidden event
        $('#btn_duplicate').hide();
        resetHotelModal()
    });


    $("#btn_add_payment_inv").on("click",function(){
        $('#account_bank').val(null).trigger('change')
        $("#amount_paid").val(0)

        // ── APPLY CREDIT: reset ke mode default "bank" tiap kali form dibuka ──
        $('#method_bank_add').prop('checked', true);
        $('#wrap_overpay_select_add').hide();
        $('#wrap_bank_select_add').show();
        $('#overpay_id_add').val(null).trigger('change');

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
                    return { page: params.page || 1, keyword: params.term || '', limit: 5, kolom_name: 'name' };
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
                    return { page: params.page || 1, keyword: params.term || '', limit: 5, kolom_name: 'name' };
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

       const method = $('input[name="payment_method_add"]:checked').val() || 'bank';
       const $btn = $(this);

       if (method === 'credit') {
            // ── APPLY CREDIT (OVERPAYMENT) ──
            const overpayId = $('#overpay_id_add').val();
            if (!overpayId) {
                Swal.fire('Peringatan', 'Silakan pilih overpayment terlebih dahulu.', 'warning');
                return;
            }

            const payload = {
                nominal_spend    : $('#amount_paid').val(),
                reference_detail : $('#payment_reference').val(),
                date_transaction : $('#date_paid').val(),
                parent_inv_id    : $('#idHotelInput').val(),
                overpay_id       : overpayId,
            };

            ajaxRequest(`{{ route('save-payover-sales-inv') }}`, 'POST', payload, localStorage.getItem("token"))
                .then(response => {
                    if (response.status == 200) {
                        Swal.fire('Sukses!', 'Data (apply credit) berhasil disimpan.', 'success');
                        $('#modalCreateHotel').modal('hide');
                        table.ajax.reload(null, false);
                    }
                })
                .catch((err) => {
                    cathError(err)
                });

       } else {
            // ── Payment normal (bank transfer) ──
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
       }
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
        <td style="text-align:center; vertical-align:middle; width:28px;">
            <span class="drag-handle" title="Geser untuk urutkan">&#x2630;</span>
        </td>

        <td style="display:none;">
            <input type="hidden" name="id_detail[]"    class="id-detail-input">
            <input type="hidden" name="sort_order[]"   class="sort-order-input" value="0">
        </td>

        <td>
            <select class="form-control select2-item" name="item_id[]" style="width:100%;"></select>
        </td>
        <td>
            <label for="desc">Pesan:</label>
            <textarea id="desc" class="form-control desc-input" name="desc[]" rows="4" cols="50"></textarea>

              {{--  <input type="text" class="form-control desc-input"
                    name="desc[]" placeholder="(otomatis dari item)" required> --}}
        </td>
        <td>
            <input type="number" class="form-control qty-input"
                   name="qty[]" min="0" value="0" style="text-align:right;">
        </td>
        <td>
            <div class="price-col-wrap">
                <span class="currency-label price-currency-label">SAR</span>
                <input type="number" class="form-control price-input"
                       name="unit_price[]" placeholder="0">
            </div>
        </td>
        <td>
            <input type="text" class="form-control" name="disc[]" placeholder="0%">
        </td>
        <td>
            <select class="form-control select2-account" name="coa_id[]" style="width:100%;"></select>
        </td>
        <td>
            <select class="form-control" name="tax_rate[]">
                <option value="0">No tax</option>
                <option value="11">11% PPN</option>
            </select>
        </td>
        <td>
            <input type="text" class="form-control tax-amount"
                   name="tax_amount[]" placeholder="0.00"
                   readonly style="text-align:right;background:#fafafa;">
        </td>
        <td>
            <input type="text" class="form-control amount-idr"
                   name="amount_idr[]" placeholder="0" required
                   readonly style="text-align:right;background:#fafafa;">
        </td>
        <td>
            <select class="form-control select2-paket" name="paket_tracking_uuid[]" style="width:100%;"></select>
        </td>
        <td>
            <select class="form-control select2-divisi" name="divisi_travel_tracking_uuid[]" style="width:100%;"></select>
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
                if (response.status != 'success') return { results: [], pagination: { more: false } };
                return {
                    results: $.map(response.data.results, function(item) {
                        return { 
                            id        : item.id,
                            text      : item.nama_paket,
                            harga     : item.price_sales,
                            id_coa : item.get_coa_salles_by_code,
                            track_id : item.uuid_tracking_category,
                            name_paket_track :item.tracking_category_paket,
                            va_number :item.va_number
                        };
                    }),
                }
            },
            cache: true
        }
    }).on('select2:select', function (e) {
        // Auto-fill desc dari field nama_paket response
        const d = e.params.data;
        const $row = $(this).closest('tr');
        // Auto-fill desc & price
        $row.find('.desc-input').val(d.nama_paket || d.text || '');
        $row.find('.price-input').val(Number(d.harga) || '');
        $row.find('.qty-input').val(1);

        // ✅ Auto-fill .select2-account dari id_coa
        if (d.id_coa && d.id_coa.id) {
            const coa = d.id_coa;
            const $accountSelect = $row.find('.select2-account');

            // Untuk select2 AJAX: buat Option baru lalu set sebagai selected
            const newOption = new Option(coa.name, coa.id, true, true);
            $accountSelect
                .empty()              // hapus option lama
                .append(newOption)    // inject option dari id_coa
                .trigger('change');   // trigger select2 supaya UI update
        }

        if(d.track_id){
            const $trackingPaketSelect = $row.find('.select2-paket');
            const option = new Option(
                d.name_paket_track,
                d.track_id,
                true,
                true
            );

            $trackingPaketSelect
                .empty()
                .append(option)
                .trigger('change'); 
        }

        if(d.va_number){
           $("#va_number").val(d.va_number)
        }
        console.log('vaa',d)
        setRowRequiredState($row, true);
        recalcSummary();
    }).on('select2:unselect select2:clear', function () {
        const $row = $(this).closest('tr');
        // ✅ item dikosongkan -> tidak wajib lagi
        setRowRequiredState($row, false);
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

function initContactAlhidSelect2() {
    $('#id_jamaah_alhid').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#modalCreateHotel'),
        placeholder: 'Pilih Agent / Contact',
        allowClear: true,
        minimumInputLength: 1,
        language: {
            inputTooShort: function () {
                return 'Ketik minimal 1 huruf untuk mencari contact...';
            },
            searching: function () {
                return 'Mencari...';
            },
            noResults: function () {
                return 'Contact jamaah tidak ditemukan';
            }
        },
        ajax: {
            url: '{{ route("list-select2-jamaahalhid") }}',
            dataType: 'json',
            delay: 1200,
            data: function (params) {
                return {
                    keyword: params.term || '',
                    limit: 5
                };
            },
            processResults: function (response) {
                return {
                    results: $.map(
                        response.data || [],
                        function (item) {
                            return {
                                id: item.id_jamaah,
                                text: item.nama_jamaah,
                                phone: item.hp_jamaah || '-'
                            };

                        }
                    ),
                    pagination: {
                        more: false
                    }
                };
            },
            cache: true
        }

    });

}

function initContactSelect2() { //limti min search
     if ($('#contact_id').hasClass('select2-hidden-accessible')) {
        $('#contact_id').select2('destroy');
    }

    $('#contact_id').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#modalCreateHotel'),
        placeholder: 'Pilih Agent / Contact',
        allowClear: true,
        cache          : false, 
        minimumInputLength: 4,
        language: {
            inputTooShort : () => 'Ketik minimal 4 huruf untuk mencari contact...',
            searching     : () => 'Mencari...',
            noResults     : () => 'Contact tidak ditemukan',
            loadingMore   : () => 'Memuat lebih banyak...',
        },

        ajax: {
            url: '{{ route("list-contact-select2") }}',
            dataType: 'json',
            delay: 450,
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
            cache: false
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


function toggleKursInput() {
    const cur = $('#currency_selected').val() || 'IDR';
    if (cur === 'SAR') {
        $('#wrap_nominal_currency').show();
    } else {
        $('#wrap_nominal_currency').hide();
        $('#nominal_currency').val('');
    }
    syncKurs();
}


$(document).on('input', '#nominal_currency', function () {
    syncKurs();
    recalcSummary();
});


function syncKurs() {
    const cur = $('#currency_selected').val() || 'IDR';
    if (cur === 'SAR') {
        kursRate = parseFloat($('#nominal_currency').val()) || 0;
        $('#text_currency').text('1 SAR = Rp ' + kursRate.toLocaleString('id-ID'));
    } else {
        kursRate = 1;
        $('#text_currency').text('');
    }
}

function lockCurrency(lock) {
    $('#currency_selected, #nominal_currency').prop('disabled', lock);
}

// ── Currency parent — ubah label SAR/IDR di semua baris ───
$('#currency_selected').on('change', function () {
    toggleKursInput();
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
                // amtIDR > 0 ? Math.round(amtIDR).toLocaleString('id-ID') : ''
               Math.round(total).toLocaleString('id-ID') //support minus
            );
            totalSAR += subtotal;
            totalIDR += amtIDR;
        } else {
            $(this).find('.amount-idr').val(
               // total > 0 ? Math.round(total).toLocaleString('id-ID') : ''
              Math.round(total).toLocaleString('id-ID')
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
    $('#previewPdfModal').on('hidden.bs.modal', function () {
    $('#previewPdfFrame').attr('src', '');   // stop loading
});
});

// ── Inisialisasi awal saat DOM ready ─────────────────────
function addFirstRow() {
    const $row = $(buildRow());
    $('#lineItemsBody').append($row);
    initRowSelect2($row);
    updateDeleteButtons();
}


// Fungsi untuk menarik gambar dari server dan menampilkannya di Dropzone
function loadDropzoneImages(invoiceId,container_img = '') {
    if (myDropzone) {
        isClearingDropzone = true;
        myDropzone.removeAllFiles(true);
        isClearingDropzone = false;
    }

    ajaxRequest("{{ route('get-image-sales-inv') }}", 'GET',
                { invoice_id: invoiceId },
                localStorage.getItem("token"))
        .then(response => {
            if (response.data.data.success && response.data.data.data.length > 0) {
                $('#dropzone-container').show();

                $.each(response.data.data.data, function (key, value) {
                    const isPdf = (value.type === 'pdf') ||
                                  (value.name && value.name.toLowerCase().endsWith('.pdf'));

                    let mockFile = {
                        name          : value.name,
                        size          : value.size,
                        accepted      : true,
                        status        : Dropzone.ADDED,
                        url           : value.url,
                        isFromServer  : true,
                        type          : isPdf ? 'application/pdf' : 'image/webp',
                    };

                    myDropzone.emit("addedfile", mockFile);

                    if (isPdf) {
                        // PDF: tidak ada thumbnail natural — biarkan handler
                        // addedfile yang memasang ikon PDF
                    } else {
                        myDropzone.emit("thumbnail", mockFile, value.url);
                    }

                    myDropzone.emit("complete", mockFile);
                    myDropzone.files.push(mockFile);
                });
            }
        })
        .catch((err) => { cathError(err); });
}

$(function () {
   initContactSelect2();
   // initContactAlhidSelect2(); //ini untuk jamaah alhidayah
    addFirstRow();
    syncCurrencyLabels();
     initLineSortable();

    // ✅ Binding load iframe preview — menggantikan onload="invoiceFrameLoaded()" inline
    $('#previewInvoiceFrame').on('load', function () {
        const src = $(this).attr('src');
        if (!src || src === '') return;
        $('#previewInvoiceLoading').fadeOut(200, function () {
            $('#previewInvoiceFrame').fadeIn(200);
        });
    });
});

// ── Toggle required pada qty, price, account berdasarkan item terpilih ──
function setRowRequiredState($row, isRequired) {
    const $qty     = $row.find('.qty-input');
    const $price   = $row.find('.price-input');
    const $account = $row.find('.select2-account');

    if (isRequired) {
        $qty.prop('required', true).attr('required', 'required');
        $price.prop('required', true).attr('required', 'required');
        $account.prop('required', true).attr('required', 'required');
        $row.addClass('row-required-active');
    } else {
        $qty.prop('required', false).removeAttr('required');
        $price.prop('required', false).removeAttr('required');
        $account.prop('required', false).removeAttr('required');
        $row.removeClass('row-required-active');
    }
}

//submit
    $('.action-submit').on('click', function (e) {
        const actionValue = $(this).val();
        $('#actionTypeValue').val(actionValue);

        // Duplicate: jangan submit form invoice, buka modal pilih contact
        if (String(actionValue) === '2') {
            e.preventDefault();
            e.stopImmediatePropagation();
            $('#modalInputContactDuplicate').modal('show');
        }
    });


    $('#formInputContactDuplicate').on('submit', async function (e) {
        e.preventDefault();

        const sourceId  = $('#idHotelInput').val();
        const contactId = $('#id_contact_duplicate').val();

        if (!sourceId) {
            Swal.fire('Peringatan', 'Invoice sumber belum disimpan.', 'warning');
            return;
        }
        if (!contactId) {
            Swal.fire('Peringatan', 'Pilih contact terlebih dahulu.', 'warning');
            return;
        }

        const $btn = $('#btnSubmitContactDuplicate').prop('disabled', true);

       
        try {
            const res = await ajaxRequest("{{ route('duplicate-save-sales-inv') }}", 'POST', {
                id                  : sourceId,
                id_contact_duplicate: contactId,
                issue_date          : $('#issue_date').val(),
                due_date            : $('#due_date').val(),
                travel_id           : $('#travel_id').val(),
                va_number           : $('#va_number').val(),
                branding_theme_id : $("#branding_theme_id").val(),
                action_save         : 1,
            }, localStorage.getItem('token'));

            if (res.status == 200) {
                $('#modalInputContactDuplicate').modal('hide');
                $('#modalCreateHotel').modal('hide');
                Swal.fire({ icon: 'success', title: 'Invoice diduplikat', timer: 1500, showConfirmButton: false });
                table.ajax.reload(null, false);
                openDetailModal(res.data.data.id);
            }
        } catch (err) {
            cathError(err);
        } finally {
            $btn.prop('disabled', false);
        }
    });

    $("#btl-save").on('click',function(){
        console.log('no inv',$('#idHotelInput').val())

        if($('#idHotelInput').val() != ''){
            ajaxRequest(`{{ route('unlock-sales-inv') }}`, 'POST', {'id':$('#idHotelInput').val()}, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200) {
                    table.ajax.reload(null, false);
                }
            })
            .catch((err) => {
                cathError(err)
            });
        }
         // table.ajax.reload(null, false);
    })

    // ========================================================
// MODAL INPUT CONTACT DUPLICATE
// ========================================================
    function initContactSelect2Duplicate() {
        const $el = $('#id_contact_duplicate');

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        $el.select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalInputContactDuplicate'),
            placeholder: 'Pilih Agent / Contact',
            allowClear: true,
            cache: false,
            minimumInputLength: 4,
            language: {
                inputTooShort: () => 'Ketik minimal 4 huruf untuk mencari contact...',
                searching    : () => 'Mencari...',
                noResults    : () => 'Contact tidak ditemukan',
                loadingMore  : () => 'Memuat lebih banyak...',
            },
            ajax: {
                url: '{{ route("list-contact-select2") }}',
                dataType: 'json',
                delay: 450,
                data: function (params) {
                    return { page: params.page || 1, keyword: params.term || '', limit: 5 };
                },
                processResults: function (response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data.data, function (item) {
                            return { id: item.id, text: item.full_name, phone: item.phone_number || '-' };
                        }),
                        pagination: { more: response.data.next_page_url !== null }
                    };
                },
                cache: false
            }
        });
    }

    // Buka modal: pasang select2 setelah modal tampil (penting agar dropdown tidak tertutup)
    $('#modalInputContactDuplicate').on('shown.bs.modal', function () {
        initContactSelect2Duplicate();
    });

    // Bersihkan saat modal ditutup
    $('#modalInputContactDuplicate').on('hidden.bs.modal', function () {
        const $el = $('#id_contact_duplicate');
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
        $el.empty();
    });

    $('#modalInputContactDuplicate').on('hidden.bs.modal', function () {
        $('#actionTypeValue').val('1');
    });

    // Submit: ambil id contact yang dipilih
    $('#formInputContactDuplicate').on('submit', function (e) {
        e.preventDefault();

        const contactId   = $('#id_contact_duplicate').val();
        const contactText = $('#id_contact_duplicate').select2('data')[0]?.text || '';

        if (!contactId) {
            Swal.fire('Peringatan', 'Pilih contact terlebih dahulu.', 'warning');
            return;
        }

        // Lanjutkan ke proses duplicate (isi sesuai kebutuhan)
        console.log('duplicate untuk contact', contactId, contactText);

        $('#modalInputContactDuplicate').modal('hide');
    });

    
    $('#formCreateHotel').on('submit', function(e) {
            e.preventDefault();
          
            
            $('.select2-account, .select2-paket, .select2-item, .select2-divisi').each(function() {
                if ($(this).data('select2')) { $(this).trigger('change'); }
            });

            updateSortOrders();

            let formData = $(this).serialize();
            let params = new URLSearchParams(formData);
            let idInput = params.get('idHotelInput');
            let id_inv = (idInput && idInput > 0) ? idInput : null;
            let action_selected = params.get('action_type');

            // ✅ Helper: pastikan tidak pernah null, agar tidak dibuang oleh $.map()
            const getVals = (selector) =>
                $(selector).map(function () {
                    const v = $(this).val();
                    return v === null || v === undefined ? '' : v;
                }).get();
           
                let contactId = params.get('contact_id');
                let idJamaahAlhid = params.get('id_jamaah_alhid');

            let selectedData = {
                id: id_inv,
                // contact_id: params.get('contact_id'),
                issue_date: params.get('issue_date'),
                due_date: params.get('due_date'),
                reference: params.get('reference'),
                invoice_number: params.get('invoice_number'),
                code_curr: $("#currency_selected").val(),
                nominal_currency: $('#nominal_currency').val(),
                action_save: action_selected,
                travel_id :$("#travel_id").val(),
                va_number: $("#va_number").val(),
                item_id: getVals('select[name="item_id[]"]'),
                coa_id: getVals('select[name="coa_id[]"]'),
                desc: getVals('.desc-input'),
                qty: getVals('input[name="qty[]"]'),
                unit_price: getVals('input[name="unit_price[]"]'),
                paket_tracking_uuid: getVals('select[name="paket_tracking_uuid[]"]'),
                divisi_travel_tracking_uuid: getVals('select[name="divisi_travel_tracking_uuid[]"]'),
                id_detail: getVals('input[name="id_detail[]"]'),
                sort_order: getVals('input[name="sort_order[]"]'),
                branding_theme_id : $("#branding_theme_id").val(),
            };

            if (contactId) {
                selectedData.contact_id = contactId;
            } 
            else if (idJamaahAlhid) {
                selectedData.id_jamaah_alhid = idJamaahAlhid;
            }


            // ✅ Validasi manual per baris (item terisi -> qty/price/account wajib)
            let hasError = false;
            let errorMessages = [];

            selectedData.item_id.forEach((itemVal, i) => {
                if (itemVal) {
                    const qty = selectedData.qty[i];
                    const price = selectedData.unit_price[i];
                    const account = selectedData.coa_id[i];

                    if (!qty || Number(qty) <= 0) {
                        hasError = true;
                        errorMessages.push(`Baris ${i + 1}: Qty wajib diisi.`);
                    }

                    //disable boleh minus
                    // if (!price || Number(price) <= 0) {
                    //     hasError = true;
                    //     errorMessages.push(`Baris ${i + 1}: Harga wajib diisi.`);
                    // }
                    if (!account) {
                        hasError = true;
                        errorMessages.push(`Baris ${i + 1}: Account (COA) wajib dipilih.`);
                    }
                }
            });

            if (hasError) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Data belum lengkap',
                    html: errorMessages.join('<br>')
                });
                return;
            }

            let cek_val_action = $("#actionTypeValue").val();
            if (!id_inv && $('#currency_selected').val() === 'SAR'
                && (parseFloat($('#nominal_currency').val()) || 0) <= 0) {
                Swal.fire('Peringatan', 'Isi kurs 1 SAR = berapa Rupiah.', 'warning');
                return;
            }
            
            ajaxRequest(`{{ route('save-sales-inv') }}`, 'POST', selectedData, localStorage.getItem("token"))
                .then(response => {
                    if (response.status == 200) {
                        $("#invoice_number_display").val(response.data.data.invoice_number)

                        let savedInvoiceId = id_inv ? id_inv : response.data.data.id;
                        if (savedInvoiceId) {
                            $('#idHotelInput').val(savedInvoiceId);
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Sukses!',
                            text: 'Data berhasil disimpan.',
                            timer: 2500,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });

                        if (action_selected == "1" && myDropzone.getQueuedFiles().length > 0) {
                            myDropzone.processQueue();
                        }
                        $('#invoices_id_parent').val(response.data.data.id)
                    }
                })
                .catch((err) => {
                    cathError(err)
                })
                .finally(() => {
                    $('.action-submit').prop('disabled', false);
                });
        });

        function renderHistoryLog(historyArr) {
            const $list = $('#history_log_list');
            $list.empty();

            if (!historyArr || historyArr.length === 0) {
                $list.append('<li class="list-group-item text-muted">Belum ada riwayat.</li>');
                return;
            }

            historyArr.forEach(function (h) {
                const action = $('<div>').text(`${convertStringDate(h.created_at)} | ${h.action}` || '-').html(); // escape biar aman dari HTML/script
                $list.append('<li class="list-group-item">' + action + '</li>');
            });
        }

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
                const nominal = p.nominal_receive > 0 ? parseFloat(p.nominal_receive) : parseFloat(p.nominal_spend)
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

    // $('#tableHotel').on('click', '.show-invoice-modal', function() {
    //     const id = $(this).data('id');
    //     $('#invoices_id_parent').val(id);
    // })


    $('#tableHotel').on('click', '.show-payment-modal', function() {
        const id = $(this).data('id');
           const rowData = table.row($(this).parents('tr')).data();
        $('#invoices_id_parent').val(id); // Set hidden ID untuk form tambah bayar
        $("#idHotelInput").val(id)
        $('#invoice_uuid_payment').val(rowData.invoice_uuid || ''); 
        resetPaymentForm();
        resetFeeOverpayRows();
        loadPaymentBuktiImages(id);


        $('#formSubmitPayment').find('.select2-account-bank-modal2').each(function() {
            $(this).val(null).trigger('change');
        });
        $("#input_payment_date").val(null)
        $("#input_payment_nominal").val(0)
        $("#input_payment_ref").val('')

        // ── APPLY CREDIT: reset ke mode default "bank" tiap kali modal dibuka ──
        $('#method_bank').prop('checked', true);
        $('#wrap_overpay_select').hide();
        $('#wrap_bank_select').show();

        // Munculkan Modal
        $('#paymentModal').modal('show');
        
        // Panggil AJAX Load History
        loadPaymentHistoryModal(id);
    });

    // 2. Fungsi Load History ke Table Modal
   function loadPaymentHistoryModal(invoiceId) {
        $('#payment_list_body').html('<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary spinner-border-sm"></div> Memuat Data...</td></tr>');

        ajaxRequest(`{{ route('detail-sales-inv') }}`, 'GET', { id: invoiceId }, localStorage.getItem("token"))
        .then(function (response) {
            const d        = response.data.data;
            const payments = d.get_payment || [];

            // get_over_pay bisa berupa objek atau array, ambil yang pertama
            const overpay = Array.isArray(d.get_over_pay)
                ? (d.get_over_pay[0] || null)
                : (d.get_over_pay || null);

         

            // ── APPLY CREDIT ──
            listAllOverpayCurrent = (d.get_jamaah && d.get_jamaah.list_all_overpay) ? d.get_jamaah.list_all_overpay : [];
            if (d.status != 'PAID') {
                if (listAllOverpayCurrent.length > 0) {
                    $('#paymentMethodWrap').show();
                    populateOverpaySelect();
                }
            } else {
                $('#paymentMethodWrap').hide();
            }

            // ── Header info ──
            $('#hotel_name_display').text(d.contact_name || '-');
            $('#no_invoice_display').text(d.invoice_number || '-');
            $('#summary_total').text(formatCurrency(d.invoice_total, d.code_curr));

            // ── Riwayat pembayaran ──
            let totalPaid = 0;
            let tbody = '';

            if (payments.length === 0) {
                tbody = '<tr><td colspan="5" class="text-muted text-center">Belum ada data pembayaran</td></tr>';
            } else {
                payments
                    .sort((a, b) => new Date(b.date_transaction) - new Date(a.date_transaction))
                    .forEach((p, index) => {
                        const nominal = p.nominal_receive > 0
                            ? parseFloat(p.nominal_receive)
                            : parseFloat(p.nominal_spend);

                        totalPaid += nominal;
                        const cek_style = p.nominal_receive > 0
                            ? ''
                            : 'style="background-color:#FFC6BA;font-color:white;"';

                        tbody += `
                            <tr ${cek_style}>
                                <td>${index + 1}</td>
                                <td>${p.date_transaction || '-'}</td>
                                <td>${p.name_bank}</td>
                                <td>${p.reference_detail || '-'}</td>
                                <td class="text-right">${formatCurrency(nominal, d.code_curr)}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-xs btn-warning mr-1 btn-edit-inv-pay" style="padding:10px;"
                                        title="Edit"
                                        data-id="${p.id}"
                                        data-nominal="${p.nominal_receive > 0 ? p.nominal_receive : p.nominal_spend}"
                                        data-date="${p.date_transaction}"
                                        data-bank-id="${p.uuid_bank}"
                                        data-bank-name="${p.name_bank}"
                                        data-reference="${p.reference_detail || ''}">
                                        <i class="ti ti-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-danger btn-delete-inv-pay"
                                        title="Hapus"
                                        data-id="${p.id}"
                                        data-nominal="${formatCurrency(nominal, d.code_curr)}">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </td>
                            </tr>`;
                    });
            }

            $('#payment_list_body').html(tbody);

            // ── Summary cards ──
            $('#summary_paid').text(formatCurrency(totalPaid, d.code_curr));
            const remaining   = parseFloat(d.invoice_total) - totalPaid;
            //`overpayment ${formatCurrency(overpay.nominal_overpayment /parseInt(d.nominal_currency) , d.code_curr)}`
            const cek_overpay = overpay
                ? `overpayment ${formatCurrency(overpay.nominal_overpayment , d.code_curr)}`
                : '';
            $('#summary_remaining').text(
                `${formatCurrency(remaining < 0 ? 0 : remaining, d.code_curr)}  ${cek_overpay}`
            );

          //  if(overpay.nominal_overpayment > 0){
            //     $("#over_pay_sisa").val(parseInt(overpay.nominal_overpayment))
            // }else{
            //     $("#over_pay_sisa").val(0)
            // }

            //console.log('overpay',overpay)

            // ── FEE OVERPAY: INI YANG SEBELUMNYA BELUM ADA ──
            renderFeeOverpaySection(d.nominal_currency, overpay, invoiceId, d.id_jamaah_alhid, d.code_curr);
        })
        .catch(function (err) {
            console.log('err', err);
            $('#payment_list_body').html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat data riwayat pembayaran</td></tr>');
            if (err.status == 423) {
                cathError(err);
                $('#paymentModal').modal('hide');
            }
        });
    }

    // 3. Submit Pembayaran Baru dari Modal History
    $('#formSubmitPayment').on('submit', function(e) {
        e.preventDefault();

        const method = $('input[name="payment_method"]:checked').val() || 'bank';
        const $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Menyimpan...');

        if (method === 'credit') {
            // ── APPLY CREDIT (OVERPAYMENT) ──
            const overpayId = $('#overpay_id_modal2').val();
            if (!overpayId) {
                Swal.fire('Peringatan', 'Silakan pilih overpayment terlebih dahulu.', 'warning');
                $btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i> Simpan Pembayaran');
                return;
            }

            const payload = {
                nominal_spend    : $('#input_payment_nominal').val(),
                reference_detail : $('#input_payment_ref').val(),
                date_transaction : $('#input_payment_date').val(),
                parent_inv_id    : $('#invoices_id_parent').val(),
                overpay_id       : overpayId,
            };

            console.log('payload (apply credit)', payload)

            ajaxRequest(`{{ route('save-payover-sales-inv') }}`, 'POST', payload, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200) {
                    Swal.fire('Sukses!', 'Pembayaran (apply credit) berhasil ditambahkan.', 'success');
                    $('#formSubmitPayment')[0].reset();
                    $('#overpay_id_modal2').val(null).trigger('change');

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

        } else {
            // ── Payment normal (bank transfer) ──
            const payload = {
                date_transaction : $('#input_payment_date').val(),
                nominal_receive  : $('#input_payment_nominal').val(),
                reference_detail : $('#input_payment_ref').val(),
                parent_inv_id    : $('#invoices_id_parent').val(),
                uuid_bank : $('#account_bank_modal_2').val(),
            };

            console.log('payload',payload)

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
        }
    });


    $('#btn_check_invoice').on('click', function () {
        const uuid     = $('#invoice_uuid_payment').val();
        const parentId = $('#idHotelInput').val();

        if (!uuid) {
            Swal.fire('Peringatan', 'UUID invoice tidak ditemukan.', 'warning');
            return;
        }

        // Unlock invoice (sama seperti tombol Tutup)
        if (parentId) {
            ajaxRequest(`{{ route('unlock-sales-inv') }}`, 'POST', { id: parentId }, localStorage.getItem('token'))
                .then(res => { if (res.status == 200) table.ajax.reload(null, false); })
                .catch(err => cathError(err));
        }

        // Buka preview SETELAH paymentModal benar-benar tertutup,
        // supaya backdrop & scroll body Bootstrap 4 tidak bentrok
        $('#paymentModal')
            .off('hidden.bs.modal.checkInv')
            .one('hidden.bs.modal.checkInv', function () {
                privewiInvoice(uuid);
            });

        $('#paymentModal').modal('hide');
    });

    // 4. Aksi Delete Payment History

    // Matikan auto discover Dropzone agar kita bisa inisiasi manual
    
/// ========================================================
// FEE OVERPAY (create, edit, delete)
// ========================================================
const feeOverpayRoutes = {
    list  : "{{ route('get-fee-overpay') }}",
    store : "{{ route('save-fee-overpay') }}",
    delete: "{{ route('delete-fee-overpay') }}",
};

let feeOverpayCache        = {};  // id => data fee, dipakai tombol edit
let feeEditOriginalNominal = 0;   // nominal fee yang sedang diedit

const feeEditingId = () => $('#fee_edit_id').val() || '';

function getFeeMaxNominal() {
    const sisa = parseFloat($('#feeOverpaySection').data('sisa')) || 0;
    return sisa + (feeEditingId() ? feeEditOriginalNominal : 0);
}

function setFeeEditUI(isEdit) {
    $('#feeFormTitle').html(isEdit
        ? '<i class="fa fa-pencil mr-1"></i> Edit Fee Overpay'
        : '<i class="fa fa-percent mr-1"></i> Fee Overpay');
    $('#btnSaveFeeOverpay').html(
        `<i class="fa fa-save mr-1"></i> ${isEdit ? 'Update Fee Overpay' : 'Simpan Fee Overpay'}`
    );
    $('#btnAddFeeRow').toggle(!isEdit);
    $('#btnCancelFeeEdit').toggle(isEdit);
}


function initFeeBankSelect($row, bankId = null, bankName = null) {
    const $sel = $row.find('.fee-bank-select');
    if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');

    // Isi opsi awal SEBELUM select2 dipasang (mode edit)
    $sel.empty();
    if (bankId && bankName) {
        $sel.append(new Option(String(bankName), String(bankId), true, true));
    }

    $sel.select2({
        theme              : 'bootstrap4',
        width              : '100%',
        dropdownParent     : $('#paymentModal'),
        placeholder        : 'Ketik nama bank...',
        allowClear         : true,
        minimumInputLength : 1,
        language: {
            inputTooShort: () => 'Ketik minimal 1 huruf untuk mencari bank...',
            searching    : () => 'Mencari...',
            noResults    : () => 'Bank tidak ditemukan',
        },
        ajax: {
            url     : "{{ route('getbankselect2') }}",
            type    : 'GET',
            dataType: 'json',
            delay   : 350,
            data    : (p) => ({ page: p.page || 1, keyword: p.term || '', limit: 10, kolom_name: 'name' }),
            processResults: (response) => ({
                results: $.map(response.data.data || [], (item) => ({
                    id  : String(item.id),
                    text: item.name + ' (' + (item.currency_code || '-') + ')'
                })),
                pagination: { more: !!response.data.next_page_url }
            }),
            cache: false
        }
    });
}

function buildFeeRow() {
    const today = new Date().toISOString().split('T')[0];
    const max   = getFeeMaxNominal();
    return `
        <tr>
            <td>
                <input type="date" class="form-control form-control-sm fee-date" value="${today}">
            </td>
            <td>
                <select class="form-control form-control-sm fee-bank-select" style="width:100%;"></select>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm fee-nominal"
                       min="1" step="0.01" max="${max}" placeholder="0">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm fee-rek"
                       maxlength="255" placeholder="No rekening jamaah">
            </td>
            <td class="text-center">
                <button type="button" class="btn-del-fee-row"
                        style="background:none;border:1px solid #e74c3c;color:#e74c3c;
                               border-radius:3px;padding:2px 6px;cursor:pointer;font-size:11px;"
                        title="Hapus baris">
                    <i class="ti ti-trash"></i>
                </button>
            </td>
        </tr>`;
}

function addFeeRow(data = {}) {
    const $row = $(buildFeeRow());
    $('#feeOverpayRowsBody').append($row);

    if (data.date)    $row.find('.fee-date').val(data.date);
    if (data.nominal) $row.find('.fee-nominal').val(data.nominal);
    if (data.rek)     $row.find('.fee-rek').val(data.rek);
    //if (data.rek)     $row.find('.fee-B').val(data.rek);


    initFeeBankSelect($row, data.bankId || null, data.bankName || null);
    updateFeeRowButtons();
}

function ensureFeeRow() {
    if ($('#feeOverpayRowsBody tr').length === 0) addFeeRow();
}

function updateFeeRowButtons() {
    const count = $('#feeOverpayRowsBody tr').length;
    $('#feeOverpayRowsBody .btn-del-fee-row').prop('disabled', count <= 1);
}

// Kosongkan form dan keluar dari mode edit
function resetFeeOverpayRows() {
    $('#feeOverpayRowsBody .fee-bank-select').each(function () {
        if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
    });
    $('#feeOverpayRowsBody').empty();
    $('#fee_edit_id').val('');
    feeEditOriginalNominal = 0;
    setFeeEditUI(false);
}

function loadFeeOverpayHistory(invoiceId) {
    const $body = $('#feeOverpayHistoryBody');
    $body.html('<tr><td colspan="5" class="text-center text-muted">Memuat...</td></tr>');

    ajaxRequest(feeOverpayRoutes.list, 'GET', { invoice_id: invoiceId }, localStorage.getItem('token'))
        .then(res => {
            const list = Array.isArray(res.data) ? res.data : (res.data?.data || []);

            feeOverpayCache = {};
            if (list.length === 0) {
                $body.html('<tr><td colspan="5" class="text-muted">Belum ada data</td></tr>');
                return;
            }

            let html = '';
            list.forEach((f, i) => {
                feeOverpayCache[f.id] = f;
                html += `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${f.date_transaction || '-'}</td>
                        <td>${f.name_bank || '-'}</td>
                        <td class="text-right">${formatCurrency(f.nominal)}</td>
                        <td>${f.bank_tujuan_jamaah_rek || '-'}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-warning mr-1 btn-edit-fee"
                                    data-id="${f.id}" title="Edit">
                                <i class="ti ti-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-xs btn-danger btn-delete-fee"
                                    data-id="${f.id}" title="Hapus">
                                <i class="ti ti-trash"></i>
                            </button>
                        </td>
                    </tr>`;
            });
            $body.html(html);
        })
        .catch(err => {
            $body.html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat riwayat fee overpay</td></tr>');
            console.log('fee overpay list err', err);
        });
}

// Dipanggil dari loadPaymentHistoryModal()
function renderFeeOverpaySection(_nom_curr=1,overpay, invoiceId, jamaahId, currency) {
    if (!overpay) {
        $('#feeOverpaySection, #feeOverpayHistoryWrap').hide();
        return;
    }

    const sisa = parseFloat(overpay.nominal_overpayment / parseInt(_nom_curr)) || 0;

    $('#fee_overpay_id').val(overpay.id);
    $('#fee_jamaah_id').val(jamaahId || '');
    $('#fee_overpay_sisa').text(formatCurrency(sisa, currency));
    $('#feeOverpaySection').data('sisa', sisa).show();
    $('#feeOverpayHistoryWrap').show();

    ensureFeeRow();
    loadFeeOverpayHistory(invoiceId);
}

// ── Mode edit: isi form dengan data fee yang dipilih ──
function startEditFee(f) {
    resetFeeOverpayRows();

    $('#fee_edit_id').val(f.id);
    feeEditOriginalNominal = parseFloat(f.nominal) || 0;

    addFeeRow({
        date     : f.date_transaction,
        bankId   : f.bank_id_origin,
        bankName : f.name_bank,
        nominal  : Math.ceil(f.nominal),
        rek      : f.bank_tujuan_jamaah_rek,
    });

    setFeeEditUI(true);
    document.getElementById('feeOverpaySection')
        .scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Event: tambah & hapus baris ──
$('#btnAddFeeRow').on('click', function () {
    addFeeRow();
});

$(document).on('click', '.btn-del-fee-row', function () {
    if ($('#feeOverpayRowsBody tr').length <= 1) return;
    const $tr  = $(this).closest('tr');
    const $sel = $tr.find('.fee-bank-select');
    if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
    $tr.remove();
    updateFeeRowButtons();
});

// ── Event: batal edit ──
$('#btnCancelFeeEdit').on('click', function () {
    resetFeeOverpayRows();
    ensureFeeRow();
});

// ── Event: tombol edit di riwayat ──
$(document).on('click', '.btn-edit-fee', function () {
    const f = feeOverpayCache[$(this).data('id')];
    if (!f) return;
    console.log('edit fee', f);
    console.log(f.bank_id_origin)
    console.log($('#feeOverpayRowsBody .fee-bank-select').val())
    console.log('d',$('#feeOverpayRowsBody .fee-bank-select option').length )
    startEditFee(f);
});

// ── Event: tombol hapus di riwayat ──
$(document).on('click', '.btn-delete-fee', function () {
    const id = $(this).data('id');
    const f  = feeOverpayCache[id] || {};

    Swal.fire({
        title            : 'Hapus Fee Overpay?',
        html             : `Nominal <b>${formatCurrency(f.nominal || 0)}</b> akan dihapus.<br>
                            Saldo overpayment dan saldo bank akan dikembalikan.`,
        icon             : 'warning',
        showCancelButton : true,
        confirmButtonColor: '#d33',
        cancelButtonColor : '#6c757d',
        confirmButtonText : 'Ya, Hapus!',
        cancelButtonText  : 'Batal',
        reverseButtons    : true
    }).then(result => {
        if (!result.isConfirmed) return;

        ajaxRequest(feeOverpayRoutes.delete, 'POST', { id: id }, localStorage.getItem('token'))
            .then(res => {
                if (res.status == 200) {
                    Swal.fire({ icon: 'success', title: 'Dihapus!', timer: 1200, showConfirmButton: false });

                    if (feeEditingId() == String(id)) {
                        resetFeeOverpayRows();
                    }
                    ensureFeeRow();

                    loadPaymentHistoryModal($('#invoices_id_parent').val());
                    table.ajax.reload(null, false);
                }
            })
            .catch(err => cathError(err));
    });
});

// ── Simpan (create atau update) ──
$('#btnSaveFeeOverpay').on('click', async function () {
    const $btn      = $(this).prop('disabled', true);
    const invoiceId = $('#invoices_id_parent').val();
    const overpayId = $('#fee_overpay_id').val();
    const jamaahId  = $('#fee_jamaah_id').val() || null;
    const editId    = feeEditingId() || null;
    const max       = getFeeMaxNominal();

    const payloads = [];
    let total = 0;
    let error = null;

    $('#feeOverpayRowsBody tr').each(function (i) {
        const $r      = $(this);
        const date    = $r.find('.fee-date').val();
        const bank    = $r.find('.fee-bank-select').val();
        const nominal = parseFloat($r.find('.fee-nominal').val());
        const rek     = ($r.find('.fee-rek').val() || '').trim();

        if (!date || !bank || !nominal || nominal <= 0 || !rek) {
            error = `Baris ${i + 1}: lengkapi tanggal, bank, nominal, dan rekening tujuan.`;
            return false;
        }

        total += nominal;
        payloads.push({
            id                     : editId,
            date_transaction       : date,
            invoice_id             : invoiceId,
            bank_id_origin         : bank,
            nominal                : nominal,
            bank_tujuan_jamaah_rek : rek,
            overpay_id             : overpayId,
            jamaah_id              : jamaahId,
        });
    });

    if (error) {
        Swal.fire('Peringatan', error, 'warning');
        $btn.prop('disabled', false);
        return;
    }

    console.log(`${total} || ${max} ||` )
    if (total > max) {//perlu fix
        Swal.fire('Peringatan', 'Total fee melebihi sisa overpayment.', 'warning');
        $btn.prop('disabled', false);
        return;
    }

    try {
        for (const payload of payloads) {
            await ajaxRequest(feeOverpayRoutes.store, 'POST', payload, localStorage.getItem('token'));
        }

        Swal.fire({
            icon : 'success',
            title: 'Berhasil!',
            text : editId
                ? 'Fee overpay berhasil diupdate.'
                : `${payloads.length} fee overpay berhasil disimpan.`,
            timer: 1500,
            showConfirmButton: false
        });

        resetFeeOverpayRows();
        ensureFeeRow();
        loadPaymentHistoryModal(invoiceId);   // refresh sisa overpay & riwayat
        table.ajax.reload(null, false);
    } catch (err) {
        cathError(err);
    } finally {
        $btn.prop('disabled', false);
    }
});

// Bersihkan Select2 fee saat modal ditutup
$('#paymentModal').on('hidden.bs.modal', function () {
    $('#feeOverpayRowsBody .fee-bank-select').each(function () {
        if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
    });
});

// ========================================================
// ADD ITEM MASTER dari modal invoice
// ========================================================

// ── AUTO GENERATE CODE (item master) ──
let miCodeManuallyEdited = false;   // true jika user mengetik code sendiri
let miLastItemId = null;    

// ── Buka modal ──
$('#btn-add-item-master').on('click', function () {
       miCodeManuallyEdited = false; 
    miLoadLastItemId();  
    $('#modalCreateItemMaster').modal('show');
});

        // diisi dari server

function miFirstWord(name) {
    const w = (name || '').trim().split(/\s+/)[0] || 'ITM';
    return (w.replace(/[^A-Za-z0-9]/g, '').toUpperCase().substring(0, 3)) || 'ITM';
}

function miYmd() {
    const val = $('#mi_departure_date').val();          // format YYYY-MM-DD
    if (val) return val.replace(/-/g, '');
    const d = new Date();                                // kosong -> hari ini (waktu lokal)
    return d.getFullYear()
        + String(d.getMonth() + 1).padStart(2, '0')
        + String(d.getDate()).padStart(2, '0');
}

function miGenerateCode() {
    if (miCodeManuallyEdited) return;                    // jangan timpa ketikan manual

    // lastId + 1; fallback jika server belum siap: 4 digit terakhir timestamp
    const seq = (miLastItemId !== null)
        ? miLastItemId + 1
        : parseInt(Date.now().toString().slice(-4));

    $('#mi_code').val(`${miFirstWord($('#mi_name').val())}-${miYmd()}-${String(seq).padStart(4, '0')}`);
}

function miLoadLastItemId() {
    ajaxRequest(`{{ route('item-last-id') }}`, 'GET', {}, localStorage.getItem("token"))
        .then(res => {
            miLastItemId = parseInt(res.last_id ?? res.data?.last_id ?? res.data?.data?.last_id) || 0;
        })
        .catch(() => { miLastItemId = null; })
        .finally(() => miGenerateCode());
}

// regenerate saat nama / tanggal berubah
$('#mi_name').on('input', miGenerateCode);
$('#mi_departure_date').on('change', miGenerateCode);

// kalau user mengetik code sendiri, jangan ditimpa lagi
$('#mi_code').on('input', function () { miCodeManuallyEdited = true; });

// ── Nested modal fix (Bootstrap 4) ──
$('#modalCreateItemMaster').on('show.bs.modal', function () {
    const $m = $(this);
    // modal kedua harus di atas modal invoice (z-index 1050)
    const z = 1070;
    $m.css('z-index', z);
    setTimeout(function () {
        $('.modal-backdrop').last().css('z-index', z - 1);
    }, 0);
});

$('#modalCreateItemMaster').on('shown.bs.modal', function () {
    // matikan enforceFocus modal induk, kalau tidak input di modal ini tidak bisa diketik
    $(document).off('focusin.bs.modal');
    initItemMasterSelect2();
    $('#mi_code').trigger('focus');
});

$('#modalCreateItemMaster').on('hidden.bs.modal', function () {
    resetItemMasterForm();
    // kembalikan scroll modal invoice yang hilang setelah modal kedua ditutup
    if ($('#modalCreateHotel').hasClass('show')) {
        $('body').addClass('modal-open');
    }
});

// ── Toggle field Purchase / Sell ──
$('#mi_chkPurchase').on('change', function () {
    $('#mi_purchaseFields').toggleClass('show', this.checked);
});
$('#mi_chkSell').on('change', function () {
    $('#mi_sellFields').toggleClass('show', this.checked);
});

// ── Select2 account (pakai route COA yang sama dengan line item) ──
function initItemMasterSelect2() {
    ['#mi_account_purchase', '#mi_account_sales'].forEach(function (sel) {
        const $el = $(sel);
        if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');

        $el.select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: $('#modalCreateItemMaster'),
            placeholder: '-- Pilih Akun --',
            allowClear: true,
            ajax: {
                url: '{{ route("get-all-coa-select2") }}',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return { page: params.page || 1, keyword: params.term || '', limit: 5 };
                },
                processResults: function (response) {
                    return {
                        results: $.map(response.data?.data || [], function (item) {
                            return { id: item.id, text: item.name };
                        })
                    };
                },
                cache: false
            }
        });
    });
}

function resetItemMasterForm() {
    $('#formCreateItemMaster')[0].reset();
     miCodeManuallyEdited = false;  
    $('#mi_purchaseFields, #mi_sellFields').removeClass('show');
    ['#mi_account_purchase', '#mi_account_sales'].forEach(function (sel) {
        const $el = $(sel);
        if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
        $el.empty().append('<option value="">-- Pilih Akun --</option>');
    });
    $('#mi_btnSave').prop('disabled', false).text('Save');
}

// ── Pilih item yang baru dibuat ke baris line item ──
function selectNewItemOnRow(itemId, payload, salesAccountText) {
    // cari baris dengan item masih kosong, kalau tidak ada tambah baris baru
    let $row = $('#lineItemsBody tr').filter(function () {
        return !$(this).find('.select2-item').val();
    }).first();

    if (!$row.length) {
        $row = $(buildRow());
        $('#lineItemsBody').append($row);
        initRowSelect2($row);
        syncCurrencyLabels();
        updateDeleteButtons();
    }

    const $sel = $row.find('.select2-item');

    // 1. set option terpilih
    $sel.empty().append(new Option(payload.nama_paket, itemId, true, true)).trigger('change');

    // 2. picu handler 'select2:select' milik initRowSelect2()
    //    (auto-fill desc, price, qty, account COA, va_number, dan hitung summary)
    $sel.trigger({
        type: 'select2:select',
        params: {
            data: {
                id        : itemId,
                text      : payload.nama_paket,
                nama_paket: payload.nama_paket,
                harga     : payload.price_sales,
                id_coa    : payload.account_id_salles
                                ? { id: payload.account_id_salles, name: salesAccountText }
                                : null,
                track_id  : null,
                va_number : payload.va_number
            }
        }
    });
}

// ── Submit ──
$('#formCreateItemMaster').on('submit', function (e) {
    e.preventDefault();
    if (!$('#mi_code').val()) miGenerateCode();
    const isPurchase = $('#mi_chkPurchase').is(':checked');
    const isSell     = $('#mi_chkSell').is(':checked');

    const selectedData = {
        id               : null,
        nama_paket       : $('#mi_name').val(),
        code             : $('#mi_code').val(),
        desc             : $('#mi_desc').val(),
        desc_salles      : $('#mi_desc_salles').val(),
        is_item_product  : $('input[name="mi_is_item_product"]:checked').val(),
        departure_date   : $('#mi_departure_date').val(),

        is_purchase         : isPurchase ? 1 : 0,
        price_purchase      : isPurchase ? ($('#mi_price_purchase').val() || null) : null,
        account_id_purchase : isPurchase ? ($('#mi_account_purchase').val() || null) : null,

        is_sell             : isSell ? 1 : 0,
        price_sales         : isSell ? ($('#mi_price_sales').val() || null) : null,
        account_id_salles   : isSell ? ($('#mi_account_sales').val() || null) : null,
        va_number           : $('#mi_va_number').val() ?? ''
    };

    // simpan teks akun sales sebelum form di-reset
    const salesAccountText = $('#mi_account_sales option:selected').text();

    $('#mi_btnSave').prop('disabled', true).text('Saving...');

    ajaxRequest(`{{ route('save-item-product') }}`, 'POST', selectedData, localStorage.getItem("token"))
        .then(response => {
            if (response.status == 200 || response.status === true) {

                // ⚠️ sesuaikan dengan struktur response backend-mu
                const saved  = response.data?.data || response.data || {};
                const itemId = saved.id;

                $('#modalCreateItemMaster').modal('hide');

                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Item berhasil disimpan.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => { /* noop */ });

                if (itemId) {
                    selectNewItemOnRow(itemId, selectedData, salesAccountText);
                } else {
                    console.warn('save-item-product tidak mengembalikan id, item tidak bisa auto-select', response);
                }
            } else {
                cathError(response);
            }
        })
        .catch(err => cathError(err))
        .finally(() => $('#mi_btnSave').prop('disabled', false).text('Save'));
});


// ========================================================
// ADD CONTACT dari modal invoice
// ========================================================
$('#btn-add-item-contact').on('click', function () {
    $('#modalCreateJamaah').modal('show');
});

// ── Nested modal fix (Bootstrap 4) ──
$('#modalCreateJamaah').on('show.bs.modal', function () {
    const z = 1070;                      // di atas modal invoice (1050)
    $(this).css('z-index', z);
    setTimeout(function () {
        $('.modal-backdrop').last().css('z-index', z - 1);
    }, 0);
});

$('#modalCreateJamaah').on('shown.bs.modal', function () {
    $(document).off('focusin.bs.modal'); // kalau tidak, input tidak bisa diketik
    $('#full_name').trigger('focus');
});

$('#modalCreateJamaah').on('hidden.bs.modal', function () {
    $('#id_jamaah, #full_name, #phone_number, #nik, #detail_address').val('');
    $('#btn_save_jamaah').prop('disabled', false).text('Simpan');
    // kembalikan scroll modal invoice yang hilang setelah modal kedua ditutup
    if ($('#modalCreateHotel').hasClass('show')) {
        $('body').addClass('modal-open');
    }
});

</script>
@endpush