@extends('layouts.app')

@section('content')
<style>
    #coa-table {
        table-layout: fixed;
        width: 100%;
    }

    #coa-table th,
    #coa-table td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .col-action   { width: 100px; }
    .col-no       { width: 60px;  }
    .col-kode     { width: 100px; }
    .col-nama     { width: 180px; }
    .col-deskripsi{ width: 220px; }
    .col-type     { width: 120px; }
    .col-tax-type { width: 120px; }
    .col-ytd      { width: 120px; }
</style>
<div class="card shadow mb-5">
    
    <!-- HEADER -->
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Master Chart of Account</h5>

        <div class="d-flex align-items-center" style="gap:10px;">
            <select id="dataMode" class="form-select form-select-sm" style="width:160px;">
                <option value="live">Live (Xero)</option>
                <option value="local">Local</option>
            </select>

            <button class="btn btn-primary btn-sm" id="btnTambah">
                <i class="ti ti-plus"></i> Tambah COA
            </button>
        </div>
    </div>

    <!-- BODY -->
    <div class="card-body">

        <!-- Loading -->
        <div id="loadingIndicator" class="text-center my-3 d-none">
            <div class="spinner-border text-primary"></div>
            <div>Memuat data...</div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="coa-table">
                <thead class="table-dark">
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-kode">Kode</th>
                        <th class="col-nama">Nama</th>
                        <th class="col-deskripsi">Deskripsi</th>
                        <th class="col-type">Type</th>
                        <th class="col-tax-type">Tax Type</th>
                        <th class="col-ytd">YTD</th>
                        <th class="col-action text-center">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>
</div>
{{-- MODAL TAMBAH / EDIT --}}
<div class="modal fade" id="modalCoa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formCoa">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Form COA</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body text-left">
                    <input type="hidden" id="xero_account_id" name="xero_account_id">

                    <div class="form-group">
                        <label>Kode</label>
                        <input type="text" class="form-control" id="code" required>
                    </div>

                    <div class="form-group">
                        <label>Nama</label>
                        <input type="text" class="form-control" id="name" required>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi</label>
                        <input type="text" class="form-control" id="description">
                    </div>

                    <div class="form-group">
                        <label>Type</label>
                        <select class="form-control" id="type" required>
                            <option value="">-- Pilih Type --</option>
                            <optgroup label="Asset">
                                <option value="BANK">Bank</option>
                                <option value="CURRENT">Current Asset</option>
                                <option value="FIXED">Fixed Asset</option>
                                <option value="INVENTORY">Inventory</option>
                                <option value="NONCURRENT">Non-current Asset</option>
                                <option value="PREPAYMENT">Prepayment</option>
                            </optgroup>
                            <optgroup label="Equity">
                                <option value="EQUITY">Equity</option>
                            </optgroup>
                            <optgroup label="Expense">
                                <option value="DEPRECIATION">Depreciation</option>
                                <option value="DIRECTCOSTS">Direct Cost</option>
                                <option value="EXPENSE">Expense</option>
                                <option value="OVERHEADS">Overheads</option>
                            </optgroup>
                            <optgroup label="Liabilities">
                                <option value="CURRENT">Current Liability</option>
                                <option value="LIABILITY">Liability</option>
                                <option value="NONCURRENT">Non-current Liability</option>
                            </optgroup>
                            <optgroup label="Revenue">
                                <option value="OTHERINCOME">Other Income</option>
                                <option value="REVENUE">Revenue</option>
                                <option value="SALES">Sales</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tax</label>
                        <select class="form-control" id="tax_type" required>
                            <option value="">-- Pilih Tax --</option>
                            <option value="NONE">No Tax</option>
                            <option value="OUTPUT">Output Tax</option>
                            <option value="INPUT">Input Tax</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnSave" class="btn btn-primary">
                        Simpan
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection


@push('scripts')
<script>
let table;
let currentMode = 'live';

// Mapping type lama ke Xero valid (taruh di luar biar tidak dibuat ulang)
const typeMapping = {
    'CURRENTASSET': 'CURRENT',
    'FIXEDASSET': 'FIXED',
    'NON-CURRENTASSET': 'NONCURRENT',
    'NON-CURRENTLIABILITY': 'NONCURRENT',
    'CURRENTLIABILITY':'CURRENT'
};

$(document).ready(function () {

    loadTable('live'); // default load

    $('#dataMode').change(function(){
        loadTable($(this).val());
    });

});

function loadTable(mode) {

    currentMode = mode;

    let url = mode === 'live'
        ? `{{ route('master-coa.live-xero') }}`
        : `{{ route('master-coa.getdata') }}`;

    // destroy datatable lama
    if ($.fn.DataTable.isDataTable('#coa-table')) {
        $('#coa-table').DataTable().destroy();
        $('#coa-table tbody').empty();
    }

    table = initGlobalDataTableToken(
        '#coa-table',
        url,
        [
            { 
                data: null, 
                className: "text-center",
                render: function (data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { data: 'code', name: 'code' },
            { data: 'name', name: 'name' },
            { data: 'description', name: 'description' },
            { data: 'type', name: 'type' },
            { data: 'tax_type', name: 'tax_type' },
            { 
                data: 'ytd', 
                name: 'ytd',
                render: function (d) {
                    return d ?? 0;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function () {
                    return `
                        <button class="btn btn-sm btn-info edit">
                            <i class="ti ti-pencil"></i>
                        </button>
                    `;
                }
            }
        ],
        { kolom_name: 'name' }
    );
}
// ===============================
// TAMBAH
// ===============================
$('#btnTambah').click(function () {

    $('#formCoa')[0].reset();

    // reset id (untuk live maupun local)
    $('#xero_account_id').val('');
    $('#xero_account_id').data('mode', currentMode);

    $('#code').prop('readonly', false);
    $('#code').data('original', '');

    $('#modalCoa').modal('show');
});


// ===============================
// EDIT
// ===============================
$('#coa-table').on('click', '.edit', function () {

    let data = table.row($(this).closest('tr')).data();

    if (!data) return;

    // tentukan id berdasarkan mode
    let recordId = currentMode === 'live'
        ? (data.xero_account_id || '')
        : (data.id || '');

    $('#xero_account_id').val(recordId);
    $('#xero_account_id').data('mode', currentMode);

    $('#code').val(data.code || '');
    $('#name').val(data.name || '');
    $('#description').val(data.description || '');

    // mapping type lama → type valid
    let typeVal = typeMapping[data.type] || data.type || '';
    $('#type').val(typeVal);

    $('#tax_type').val(data.tax_type || '');

    // kalau edit → kode readonly
    $('#code').prop('readonly', true);
    $('#code').data('original', data.code || '');

    $('#modalCoa').modal('show');
});
    // submit
$('#formCoa').submit(function(e){
    e.preventDefault();

    let btn = $('#btnSave');
    let originalText = btn.html();
    btn.html('<i class="fa fa-spin fa-spinner"></i> Menyimpan...')
       .prop('disabled', true);

    let xeroId   = $('#xero_account_id').val();
    let codeInput = $('#code').val().trim();

    // validasi kode saat edit (berlaku untuk semua mode)
    if (xeroId && codeInput !== $('#code').data('original')) {
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: 'Kode tidak bisa diubah saat edit!'
        });

        btn.html(originalText).prop('disabled', false);
        return;
    }

    let payload = {
        name: $('#name').val().trim(),
        description: $('#description').val().trim(),
        type: $('#type').val().trim(),
        tax_type: $('#tax_type').val()
    };

    let url = '';
    let method = '';

    // ===============================
    // MODE LIVE (XERO)
    // ===============================
    if (currentMode === 'live') {

        if (!xeroId) {
            url = `{{ route('master-coa.save') }}`;
            method = 'PUT';
            payload.code = codeInput;
        } else {
            url = `{{ route('master-coa.update', ['id' => ':id']) }}`
                    .replace(':id', xeroId);
            method = 'POST';
        }

    } 
    // ===============================
    // MODE LOCAL (DATABASE)
    // ===============================
    else {

        if (!xeroId) {
            url = `{{ route('master-coa.store-local') }}`;
            method = 'POST';
            payload.code = codeInput;
        } else {
            url = `{{ route('master-coa.update-local', ['id' => ':id']) }}`
                    .replace(':id', xeroId);
            method = 'PUT';
        }

    }

    // ===============================
    // AJAX REQUEST
    // ===============================
    ajaxRequest(url, method, payload, localStorage.getItem("token"))
    .then(res => {

        $('#modalCoa').modal('hide');

        Swal.fire({
            icon:'success',
            title:'Berhasil',
            text: xeroId 
                    ? 'Data berhasil diupdate'
                    : 'Data berhasil ditambahkan'
        });

        table.ajax.reload(null,false);
    })
.catch(err => {

    let message = "Terjadi kesalahan";
    const res = err?.error;

    // ===============================
    // HANDLE VALIDATION LOCAL (Laravel)
    // ===============================
if (res?.errors) {
    message = Object.keys(res.errors)
        .map(field => {
            if (field === 'code') {
                return "Kode sudah digunakan, gunakan kode lain.";
            }
            if (field === 'name') {
                return "Nama sudah digunakan, gunakan nama lain.";
            }
            return res.errors[field].join('\n');
        })
        .join('\n');
}
    // ===============================
    // HANDLE VALIDATION XERO
    // ===============================
    else if (res?.debug?.Elements?.[0]?.ValidationErrors?.length) {

        message = res.debug.Elements[0].ValidationErrors
            .map(e => {
                if (e.Message.includes("unique Code")) {
                    return "Kode sudah digunakan, gunakan kode lain.";
                }
                if (e.Message.includes("unique Name")) {
                    return "Nama sudah digunakan, gunakan nama lain.";
                }
                return e.Message;
            })
            .join('\n');
    }

    Swal.fire({
        icon:'error',
        title:'Gagal',
        text: message
    });

})    .finally(() => {
        btn.html(originalText).prop('disabled', false);
    });

});
</script>
@endpush