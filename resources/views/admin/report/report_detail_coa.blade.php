@extends('layouts.app')

@section('content')
<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0" id="title_trans">Daftar Transaksi</h5>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered mt-0 w-100" id="tableDetailCoa">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>Date</th>
                    <th>Name Contact</th>
                    <th>Item</th>
                    <th>Total</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let full_url = window.location.href;
    const segments = full_url.split('/').filter(Boolean);
    const lastSegment = segments.pop();
  
    // --- 1. DATATABLE COLUMNS ---
    let columnCoa = [
        {
            data: null,
            className: "text-center",
            orderable: false,
            searchable: false,
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        {
            data: 'date_transaction',
            name: 'date_transaction',
            render: function(data){
                // Pastikan fungsi convertStringDate sudah ada secara global
                return convertStringDate(data);
            }
        },
        {
            data: null,
            orderable: false,
            render: function(data, type, row){
                if(data.d_bill){
                    return `<b style="color:#4CB555">bills </b> | <b>${data.d_bill.desc} </b> | ${data.d_bill.get_parent.name_contact_bill}`;
                } else if(data.d_bank){
                   let cek_kondisi_bank = data.d_bank.get_parent.is_spend == 1 ? 'keluar' : 'terima';
                   let cek_warna = data.d_bank.get_parent.is_spend == 1 ? 'red' : '#78C0FF';
                   return `<b style="color:${cek_warna}">bank ${cek_kondisi_bank}</b> | <b>${data.d_bank.desc} </b> | ${data.d_bank.get_parent.name_contact_trans_bank}`;
                } else if(data.d_invoice){
                   let cek_kondisi_bank = 'terima';
                   return `<b style="color:#78C0FF">invoice ${cek_kondisi_bank}</b> | <b>${data.d_invoice.desc} </b> | ${data.d_invoice.get_parent.contact_name}`;
                } else {
                    return '-';
                }
            }
        },
        {
            data: null,
            orderable: false,
            render: function(data, type, row){
                // Mengubah judul header tabel sesuai nama COA
                if(data.name_coa) {
                    $("#title_trans").text(data.name_coa);
                }

                if(data.d_bill){
                    let datanya = data.d_bill.desc ?? '-';
                    return `<b style="color:#E53407">bills</b> &nbsp; ${datanya}`;
                } else if(data.d_invoice){
                    return `<b style="color:#2CBF56">Invoice</b> &nbsp; ${data.d_invoice.desc}`;
                } else if(data.d_bank){
                    return `<b style="color:#2155FF">Bank</b> &nbsp; ${data.d_bank.desc}`;
                } else {
                    return 'not registered';
                }
            }
        },
        {
            data: 'nominal',
            name: 'nominal',
            render: function (data) {
                // Pastikan fungsi formatCurrency sudah ada secara global
                return formatCurrency(data);
            }
        }
    ];

    // --- 2. INISIALISASI DATATABLE ---
    // Fungsi initGlobalDataTableTokenSelected akan otomatis menangkap event search
    // dan mengirimkan "keyword" beserta "limit" dan "page" ke backend.
    var table = initGlobalDataTableTokenSelected(
        '#tableDetailCoa',
        `{{ route('rep-detail-coa') }}`, // Pastikan nama route ini sesuai di web.php/api.php
        columnCoa,
        { 
            code_coa: lastSegment, 
            kolom_name: "name" 
        }
    );
});
</script>
@endpush