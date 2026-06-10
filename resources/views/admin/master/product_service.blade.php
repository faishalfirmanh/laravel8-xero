@extends('layouts.app')

@section('content')

<style>
/* ═══════════════════════════════════════════
   PAGE LAYOUT
═══════════════════════════════════════════ */
.xero-page-header {
    background: #fff;
    border-bottom: 1px solid #e8e8e8;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: -24px -24px 20px;   /* keluar dari padding card parent */
}
.xero-page-header h1 {
    font-size: 20px;
    font-weight: 400;
    color: #222;
    margin: 0;
}
.xero-page-header .header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── Tombol header ── */
.btn-xero-outline {
    background: #fff;
    border: 1px solid #d0d0d0;
    color: #333;
    border-radius: 4px;
    padding: 6px 14px;
    font-size: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    line-height: 1;
}
.btn-xero-outline:hover { background: #f5f5f5; }

.btn-xero-blue {
    background: #0070c4;
    border: none;
    color: #fff;
    border-radius: 4px 0 0 4px;
    padding: 7px 16px;
    font-size: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    line-height: 1;
}
.btn-xero-blue:hover { background: #005fa3; }
.btn-xero-blue-caret {
    background: #0070c4;
    border: none;
    color: #fff;
    border-radius: 0 4px 4px 0;
    padding: 7px 9px;
    cursor: pointer;
    border-left: 1px solid rgba(255,255,255,0.3);
    line-height: 1;
}
.btn-xero-blue-caret:hover { background: #005fa3; }

.btn-xero-green {
    background: #1ab394;
    border: none;
    color: #fff;
    border-radius: 4px;
    padding: 7px 14px;
    font-size: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-xero-green:hover { background: #17a085; }

/* ═══════════════════════════════════════════
   TABLE CARD
═══════════════════════════════════════════ */
.xero-table-card {
    background: #fff;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
    overflow: hidden;
}
.xero-table-toolbar {
    padding: 12px 16px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.xero-table-toolbar .toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
}
.filter-tab {
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: #555;
    font-size: 12px;
    padding: 4px 8px;
    cursor: pointer;
}
.filter-tab.active {
    color: #0070c4;
    border-bottom-color: #0070c4;
    font-weight: 500;
}
.xero-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.xero-search-wrap i {
    position: absolute;
    left: 8px;
    color: #999;
    font-size: 14px;
    pointer-events: none;
}
.xero-search {
    border: 1px solid #d0d0d0;
    border-radius: 4px;
    padding: 5px 10px 5px 28px;
    font-size: 12px;
    width: 200px;
    color: #333;
    background: #fafafa;
}
.xero-search:focus { outline: none; border-color: #0070c4; background: #fff; }

/* ── Tabel ── */
.xero-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.xero-table thead th {
    background: #f6f6f6;
    padding: 9px 14px;
    font-weight: 600;
    color: #555;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
    font-size: 11px;
    white-space: nowrap;
}
.xero-table thead th.th-r { text-align: right; }
.xero-table tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid #f0f0f0;
    color: #222;
    vertical-align: middle;
}
.xero-table tbody tr:last-child td { border-bottom: none; }
.xero-table tbody tr:hover { background: #fafafa; }

.td-item-name {
    color: #0070c4;
    cursor: pointer;
    font-weight: 500;
}
.td-item-name:hover { text-decoration: underline; }
.td-r { text-align: right; }
.td-c { text-align: center; }

/* ── Badges purchased/sold ── */
.xbadge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 3px;
    padding: 2px 8px;
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
}
.xbadge-sell  { background: #e1f5ee; color: #0a6640; }
.xbadge-buy   { background: #e6f1fb; color: #0c447c; }
.xbadge-both  { background: #fff8e1; color: #854f0b; }

.action-edit {
    color: #0070c4;
    font-size: 12px;
    cursor: pointer;
    text-decoration: none;
}
.action-edit:hover { text-decoration: underline; }

/* ═══════════════════════════════════════════
   MODAL — XERO NEW ITEM STYLE
═══════════════════════════════════════════ */
#modalCreateHotel .modal-content {
    border: none;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0,0,0,0.16);
}
#modalCreateHotel .modal-dialog {
    max-width: 600px;
}
#modalCreateHotel .modal-header {
    background: #fff;
    border-bottom: 1px solid #e8e8e8;
    padding: 18px 24px;
}
#modalCreateHotel .modal-header h5 {
    font-size: 18px;
    font-weight: 400;
    color: #222;
    margin: 0;
}
#modalCreateHotel .close {
    font-size: 22px;
    color: #999;
    opacity: 1;
}
#modalCreateHotel .close:hover { color: #222; }

/* ── Section dividers ── */
.xero-modal-section {
    padding: 18px 24px;
    border-bottom: 1px solid #e8e8e8;
}
.xero-modal-section:last-child { border-bottom: none; }

/* ── Field grid ── */
.xero-field-row {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 14px;
    margin-bottom: 12px;
}
.xero-field-row:last-child { margin-bottom: 0; }
.xero-field-full { margin-bottom: 12px; }
.xero-field-full:last-child { margin-bottom: 0; }

.xero-field { display: flex; flex-direction: column; gap: 4px; }
.xero-field label {
    font-size: 11px;
    font-weight: 600;
    color: #555;
    margin: 0;
}
.xero-field label .req { color: #e24b4a; }
.xero-field .form-control {
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 12px;
    color: #222;
    height: 34px;
}
.xero-field .form-control:focus {
    border-color: #0070c4;
    box-shadow: 0 0 0 2px rgba(0,112,196,0.15);
    outline: none;
}

/* ── Purchase / Sell checkbox rows ── */
.xero-check-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 0;
    border-bottom: 1px solid #e8e8e8;
}
.xero-check-row:last-child { border-bottom: none; padding-bottom: 0; }
.xero-check-row input[type="checkbox"] {
    width: 16px;
    height: 16px;
    margin-top: 2px;
    flex-shrink: 0;
    cursor: pointer;
    accent-color: #0070c4;
}
.xero-check-label h6 {
    font-size: 13px;
    font-weight: 500;
    color: #222;
    margin: 0 0 3px;
}
.xero-check-label p {
    font-size: 11px;
    color: #777;
    margin: 0;
    line-height: 1.5;
}

/* ── Expanded fields saat checkbox dicentang ── */
.xero-expanded {
    margin-top: 14px;
    display: none;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.xero-expanded.show { display: grid; }
.xero-expanded .xero-field .form-control { height: 32px; font-size: 11px; }

/* ── Info alert box ── */
.xero-info-box {
    background: #e8f4fd;
    border: 1px solid #b5d4f4;
    border-radius: 4px;
    padding: 10px 14px;
    display: flex;
    gap: 8px;
    align-items: flex-start;
    margin-top: 12px;
    font-size: 11px;
    color: #0c447c;
    line-height: 1.5;
}
.xero-info-box i { font-size: 15px; margin-top: 1px; flex-shrink: 0; }

/* ── Modal footer ── */
#modalCreateHotel .modal-footer {
    background: #fafafa;
    border-top: 1px solid #e8e8e8;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}
.btn-save-dup {
    background: #fff;
    border: 1px solid #d0d0d0;
    color: #333;
    border-radius: 4px;
    padding: 7px 16px;
    font-size: 12px;
    cursor: pointer;
}
.btn-save-dup:hover { background: #f5f5f5; }
.btn-save-main {
    background: #0070c4;
    border: none;
    color: #fff;
    border-radius: 4px 0 0 4px;
    padding: 7px 18px;
    font-size: 12px;
    cursor: pointer;
}
.btn-save-main:hover { background: #005fa3; }
.btn-save-caret {
    background: #0070c4;
    border: none;
    color: #fff;
    border-radius: 0 4px 4px 0;
    padding: 7px 9px;
    cursor: pointer;
    border-left: 1px solid rgba(255,255,255,0.3);
}
.btn-save-caret:hover { background: #005fa3; }
</style>

{{-- ── PAGE HEADER ── --}}
<div class="xero-page-header">
    <h1>Products and services</h1>
    <div class="header-actions">
        <button class="btn-xero-outline">
            <i class="ti ti-download" style="font-size:13px;" aria-hidden="true"></i> Import
        </button>
        <button class="btn-xero-outline">
            <i class="ti ti-upload" style="font-size:13px;" aria-hidden="true"></i> Export
        </button>
        <button onclick="syncProductFromXero()" class="btn-xero-green">
            <i class="ti ti-refresh" style="font-size:13px;" aria-hidden="true"></i> Sync Xero
        </button>
        <div class="d-flex">
            <button class="btn-xero-blue"
                    id="button_add_bank"
                    data-toggle="modal"
                    data-target="#modalCreateHotel">
                <i class="ti ti-plus" style="font-size:13px;" aria-hidden="true"></i> New item
            </button>
            <button class="btn-xero-blue-caret">
                <i class="ti ti-chevron-down" style="font-size:11px;" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>

{{-- ── TABLE CARD ── --}}
<div class="xero-table-card">
    {{-- <div class="xero-table-toolbar">
        <div class="toolbar-left">
            <button class="filter-tab active">All items</button>
            <button class="filter-tab">Purchased</button>
            <button class="filter-tab">Sold</button>
        </div>
        <div class="xero-search-wrap">
            <i class="ti ti-search" aria-hidden="true"></i>
            <input type="text" class="xero-search" id="searchTable" placeholder="Search items...">
        </div>
    </div> --}}

    <div id="loadingIndicator" class="text-center py-4" style="display:none;">
        <div class="spinner-border text-primary" role="status" style="width:1.8rem;height:1.8rem;">
            <span class="sr-only">Loading...</span>
        </div>
        <div class="mt-2 text-muted small">Loading data...</div>
    </div>

    <div class="table-responsive">
        <table class="xero-table" id="tableHotel">
            <thead>
                <tr>
                    <th width="4%">No</th>
                    <th width="8%">Code</th>
                    <th>Name</th>
                    <th width="14%">Code</th>
                    <th width="12%" class="th-r">Price Sales</th>
                    <th width="12%" class="th-r">Price Purchase</th>
                    <th width="8%">Action</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     MODAL — NEW / EDIT ITEM (XERO STYLE)
═══════════════════════════════════════════ --}}
<div class="modal fade" id="modalCreateHotel" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            {{-- Header --}}
            <div class="modal-header">
                <h5 class="modal-title" id="modalItemTitle">New item</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="formCreateHotel">
                @csrf
                <input type="hidden" name="id" id="idHotelInput">

                {{-- ── Section 1: Code + Name + Currency ── --}}
                <div class="xero-modal-section">
                    <div class="xero-field-row">
                        <div class="xero-field">
                            <label>Code <span class="req">*</span></label>
                            <input type="text" class="form-control"
                                   id="code" name="code"
                                   placeholder="e.g. UMR-001" required>
                        </div>
                        <div class="xero-field">
                            <label>Name</label>
                            <input type="text" class="form-control"
                                   id="nameHotel" name="nama_paket"
                                   placeholder="e.g. Umroh Group 16 hari">
                        </div>
                    </div>
                </div>

                {{-- ── Section 2: Purchase & Sell ── --}}
                <div class="xero-modal-section">

                    {{-- Purchase --}}
                    <div class="xero-check-row">
                        <input type="checkbox" id="chkPurchase" name="is_purchase" value="1">
                        <div class="xero-check-label" style="flex:1;">
                            <h6>Purchase</h6>
                            <p>Add item to bills, purchase orders, and other purchase transactions</p>

                            <div class="xero-expanded" id="purchaseFields">
                                <div class="xero-field">
                                    <label>Purchase price</label>
                                    <input type="number" class="form-control"
                                           id="price_purchase" name="price_purchase"
                                           placeholder="0" step="any" min="0">
                                </div>
                                <div class="xero-field">
                                    <label>Account</label>
                                    <select class="form-control select2-account-purchase"
                                            name="account_id_purchase" id="account_id_purchase"
                                            style="width:100%;">
                                        <option value="">-- Pilih Akun --</option>
                                    </select>
                                </div>
                                <div class="xero-field">
                                    <label>Tax rate</label>
                                    <select class="form-control" name="purchase_tax">
                                        <option value="0">No tax</option>
                                        <option value="11">11% PPN</option>
                                    </select>
                                </div>
                                <div class="xero-field">
                                    <label>Description</label>
                                    <input type="text" class="form-control"
                                          id="desc" name="desc" placeholder="">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Sell --}}
                    <div class="xero-check-row">
                        <input type="checkbox" id="chkSell" name="is_sell" value="1">
                        <div class="xero-check-label" style="flex:1;">
                            <h6>Sell</h6>
                            <p>Add item to invoices, quotes, and other sales transactions</p>

                            <div class="xero-expanded" id="sellFields">
                                <div class="xero-field">
                                    <label>Sale price</label>
                                    <input type="number" class="form-control"
                                           id="price_sales" name="price_sales"
                                           placeholder="0" step="any" min="0">
                                </div>
                                <div class="xero-field">
                                    <label>Account</label>
                                    <select class="form-control select2-account-salles"
                                            name="account_id_salles" id="account_id_salles"
                                            style="width:100%;">
                                        <option value="">-- Pilih Akun --</option>
                                    </select>
                                </div>
                                <div class="xero-field">
                                    <label>Tax rate</label>
                                    <select class="form-control" name="sell_tax">
                                        <option value="0">No tax</option>
                                        <option value="11">11% PPN</option>
                                    </select>
                                </div>
                                <div class="xero-field">
                                    <label>Description</label>
                                    <input type="text" class="form-control"
                                          id="desc_salles" name="desc_salles" placeholder="">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- ── Footer ── --}}
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        Batal
                    </button>
                    <button type="button" class="btn-save-dup" id="btnSaveDuplicate">
                        Save &amp; duplicate
                    </button>
                    <div class="d-flex">
                        <button type="submit" class="btn-save-main" id="btnSave">
                            Save
                        </button>
                        <button type="button" class="btn-save-caret">
                            <i class="ti ti-chevron-down" style="font-size:11px;" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function () {

    // =========================================================
    // DATATABLE
    // =========================================================
    var table;

    let columnHotel = [
        {
            data: null, className: "td-c",
            render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
        },
        {
            data: 'code', name: 'code',
            render: data => `<span style="font-size:11px;color:#777;">${data || '–'}</span>`
        },
        {
            data: 'nama_paket', name: 'nama_paket',
            render: (data, type, row) =>
                `<span class="td-item-name edit-hotel-name" data-id="${row.id}">${data}</span>`
        },
        {
            data: 'code',
            render: data => data
                ? `<span class="xbadge ${data === 'SAR' ? 'xbadge-buy' : 'xbadge-sell'}">${data}</span>`
                : '–'
        },
        {
            data: 'price_sales',
            className: 'td-r',
            render: data => data
                ? parseFloat(data).toLocaleString('id-ID')
                : '<span style="color:#bbb;">–</span>'
        },
        {
            data: 'price_purchase',   
            className: 'td-r',
            render: data => data
                ? parseFloat(data).toLocaleString('id-ID')
                : '<span style="color:#bbb;">–</span>'
        },
        {
            data: "id", orderable: false, searchable: false, className: "td-c",
            render: (data, type, row) =>
                `<a href="javascript:;" data-id="${data}" class="action-edit edit-hotel">
                    <i class="ti ti-pencil" aria-hidden="true"></i> Edit
                 </a>`
        }
    ];

    table = initGlobalDataTableToken(
        '#tableHotel',
        `{{ route('item-list') }}`,
        columnHotel,
        { "kolom_name": "id" }
    );

    // =========================================================
    // FILTER TABS
    // =========================================================
    $('.filter-tab').on('click', function () {
        $('.filter-tab').removeClass('active');
        $(this).addClass('active');
        // Jika API support filter: table.ajax.url(...).load();
    });

    // Search live
    $('#searchTable').on('keyup', function () {
        table.search($(this).val()).draw();
    });

    // =========================================================
    // KLIK EDIT (dari icon pensil atau nama item)
    // =========================================================
    
    function loadItem(idDetail) {
       
        ajaxRequest(`{{ route('item-detail-xero') }}`, 'get', {id:idDetail}, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200 || response.status === true) {
                    let rowData = response.data.data
                    // ── Basic fields ──────────────────────────────────────
                    $('#idHotelInput').val(idDetail);
                    $('#code').val(rowData.code || '');
                    $('#nameHotel').val(rowData.nama_paket || '');
                    $('#desc').val(rowData.desc || '');
                    $('#desc_salles').val(rowData.desc_salles || '');
                    console.log('aa',rowData)

                    // ── Purchase ──────────────────────────────────────────
                    const hasPurchase = rowData.price_purchase || rowData.account_id_purchase;
                    if (hasPurchase) {
                        $('#chkPurchase').prop('checked', true).trigger('change');
                        $('#price_purchase').val(Number(rowData.price_purchase) || '');

                        if (rowData.account_id_purchase) {
                            // Ambil nama akun: coba beberapa kemungkinan nama field dari server
                            const accountName = rowData.account_purchase_name
                                            ?? rowData.purchase_account_name
                                            ?? rowData.coa_purchase_name
                                            ?? ('Account #' + rowData.account_id_purchase);

                            setModalSelect2('#account_id_purchase', rowData.account_id_purchase, rowData.get_coa_purchase.name);
                        }
                    }

                    // ── Sell ──────────────────────────────────────────────
                    const hasSell = rowData.price_sales || rowData.account_id_salles;
                    if (hasSell) {
                        $('#chkSell').prop('checked', true).trigger('change');
                        $('#price_sales').val(Number(rowData.price_sales) || '');

                        if (rowData.account_id_salles) {
                            const accountName = rowData.account_salles_name
                                            ?? rowData.sales_account_name
                                            ?? rowData.coa_salles_name
                                            ?? ('Account #' + rowData.account_id_salles);

                            setModalSelect2('#account_id_salles', rowData.account_id_salles,  rowData.get_coa_salles.name);
                        }
                    }
                } else {
                    cathError(response);
                }
            })
            .catch(err => cathError(err))
            .finally(() => $('#btnSave').prop('disabled', false).text('Save'));
    }

    $('#tableHotel').on('click', '.edit-hotel, .edit-hotel-name', function () {
        const id      = $(this).data('id');
        const rowData = table.row($(this).closest('tr')).data();

        // Reset dulu sebelum isi (hindari sisa data edit sebelumnya)
        resetModal();
        console.log('edit',rowData)
        loadItem(id);

        $('#modalItemTitle').text('Edit item — ' + (rowData.nama_paket || ''));
        $('#modalCreateHotel').modal('show');
    });

    // =========================================================
    // CHECKBOX TOGGLE (Purchase / Sell)
    // =========================================================
    $('#chkPurchase').on('change', function () {
        $('#purchaseFields').toggleClass('show', this.checked);
        if (!this.checked) $('#purchase_price').val('');
    });

    $('#chkSell').on('change', function () {
        $('#sellFields').toggleClass('show', this.checked);
        if (!this.checked) $('#account_number').val('');
    });

    // =========================================================
    // RESET MODAL (Tambah Baru)
    // =========================================================
    $('#button_add_bank').on('click', function () {
        resetModal();
    });

    $('#modalCreateHotel').on('hidden.bs.modal', function () {
        resetModal();
    });

    function resetModal() {
        $('#formCreateHotel')[0].reset();
        $('#idHotelInput').val(0);
        $('#modalItemTitle').text('New item');
        $('#chkPurchase, #chkSell').prop('checked', false);
        $('#purchaseFields, #sellFields').removeClass('show');
    }

    // =========================================================
    // SAVE & DUPLICATE
    // =========================================================
    $('#btnSaveDuplicate').on('click', function () {
        submitForm(true); // true = duplicate setelah simpan
    });

    // =========================================================
    // SUBMIT FORM
    // =========================================================
    $('#formCreateHotel').on('submit', function (e) {
        e.preventDefault();
        submitForm(false);
    });

    function initAllSelect2AccountPurchase() {
        $('.select2-account-purchase').select2({
            placeholder: "Cari nama account purchase...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
             ajax: {
                url: "{{ route('get-all-coa-select2') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { keyword: params.term || '', page: params.page || 1, type :'EXPENSE' };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data?.data || [], function(item) {
                            return { id: item.id, text: item.name, account_type: item.account_type || '-' };
                        }),
                        pagination: { more: response.data?.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${item.account_type})</small></span>`);
            }
        });
    }
    initAllSelect2AccountPurchase()


    function initAllSelect2AccountSalles() {
        $('.select2-account-salles').select2({
            placeholder: "Cari nama account salles...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('get-all-coa-select2') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { keyword: params.term || '', page: params.page || 1, type :'REVENUE' };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data?.data || [], function(item) {
                            return { id: item.id, text: item.name, account_type: item.account_type || '-' };
                        }),
                        pagination: { more: response.data?.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${item.account_type})</small></span>`);
            }
        });
    }
    initAllSelect2AccountSalles()

    
    function submitForm(duplicate) {
        const idInput    = $('#idHotelInput').val();
        const id         = (idInput && parseInt(idInput) > 0) ? idInput : null;
        const isPurchase = $('#chkPurchase').is(':checked');
        const isSell     = $('#chkSell').is(':checked');

        const selectedData = {
            id,
            nama_paket:   $('#nameHotel').val(),
            code:         $('#code').val(),
            desc:         $('#desc').val(),
            desc_salles:  $('#desc_salles').val(),

            // Purchase — kirim flag + data, backend akan skip jika is_purchase = 0
            is_purchase:         isPurchase ? 1 : 0,
            price_purchase:      isPurchase ? $('#price_purchase').val() || null : null,
            account_id_purchase: isPurchase ? $('#account_id_purchase').val() || null : null,

            // Sell — sama
            is_sell:             isSell ? 1 : 0,
            price_sales:         isSell ? $('#price_sales').val() || null : null,
            account_id_salles:   isSell ? $('#account_id_salles').val() || null : null,
        };

        $('#btnSave').prop('disabled', true).text('Saving...');

        ajaxRequest(`{{ route('save-item-product') }}`, 'POST', selectedData, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200 || response.status === true) {
                    if (duplicate) {
                        $('#idHotelInput').val(0);
                        $('#code').val('');
                        $('#nameHotel').val('');
                        Swal.fire({ icon: 'success', title: 'Tersimpan!', text: 'Silakan isi data item duplikat.', timer: 1500, showConfirmButton: false });
                    } else {
                        $('#modalCreateHotel').modal('hide');
                        Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Item berhasil disimpan.', timer: 1800, showConfirmButton: false });
                    }
                    table.ajax.reload();
                } else {
                    cathError(response);
                }
            })
            .catch(err => cathError(err))
            .finally(() => $('#btnSave').prop('disabled', false).text('Save'));
    }

    // =========================================================
    // DELETE (jika perlu)
    // =========================================================
    $('#tableHotel').on('click', '.deleted-hotel', function () {
        const id      = $(this).data('id');
        const rowData = table.row($(this).closest('tr')).data();
        Swal.fire({
            title: 'Hapus item?',
            text: `"${rowData ? rowData.name : ''}" akan dihapus permanen.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                ajaxRequest(
                    `{{ route('deleteMasterHotel') }}`,
                    'POST',
                    { id: id },
                    localStorage.getItem("token")
                )
                .then(res => {
                    if (res.status == 200) {
                        Swal.fire({ icon: 'success', title: 'Dihapus!', timer: 1500, showConfirmButton: false });
                        table.ajax.reload();
                    }
                })
                .catch(err => Swal.fire('Gagal!', err.message, 'error'));
            }
        });
    });

    // =========================================================
    // PAYMENT MODAL close
    // =========================================================
    $("#close_modal_payment").on('click', function () {
        table.ajax.reload();
    });

}); // end ready


// =========================================================
// SYNC XERO
// =========================================================
function syncProductFromXero() {
    Swal.fire({
        title: 'Sinkronisasi dari Xero?',
        html: 'Akan mengambil semua <strong>Products &amp; Services</strong> dari Xero dan memperbarui data lokal.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1ab394',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'Ya, Sync Sekarang',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then(result => {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: 'Sedang Sinkronisasi...',
            text: 'Mohon tunggu sebentar',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        ajaxRequest(
            `{{ route('sync-item-paket') }}`,
            'GET',
            { is_sync: 1 },
            localStorage.getItem("token")
        )
        .then(response => {
            Swal.close();
            if (response.status === 200 || response.status === true) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: `${response.total_saved || 0} item berhasil disinkronisasi.`,
                    timer: 2500,
                    showConfirmButton: false
                });
                $('#tableHotel').DataTable().ajax.reload();
            } else {
                Swal.fire('Gagal', response.message || 'Gagal sinkronisasi', 'error');
            }
        })
        .catch(err => {
            Swal.close();
            Swal.fire('Error', 'Terjadi kesalahan saat sinkronisasi', 'error');
            console.error(err);
        });
    });
}
</script>
@endpush