@extends('layouts.app')

@section('content')
<div class="card shadow mb-5">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:10px;">
        <h5 class="mb-0" id="title_trans">Daftar Transaksi</h5>

        <div class="d-flex align-items-end flex-wrap" style="gap:8px;">
            <div class="form-group mb-0">
                <label class="small font-weight-bold mb-1 d-block">Dari Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="filter_date_start">
            </div>
            <div class="form-group mb-0">
                <label class="small font-weight-bold mb-1 d-block">Sampai Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="filter_date_end">
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="btn_filter_date">
                <i class="ti ti-filter mr-1"></i> Filter
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="btn_reset_date">
                <i class="ti ti-x mr-1"></i> Reset
            </button>
        </div>
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
                    <th>Action</th>
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
        },
        {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data, type, row) {

                let base_url = window.location.origin
                let cek_inv = row.d_invoice ? `${base_url}/travel/admin/transaksi/sales-invoice/?open=${row.d_invoice.get_parent.id}` : ''
               let urlnya = row.d_bank
                ? `${base_url}/travel/admin/transaksi/bank-trans/${row.d_bank.get_parent.bank_id_xero}?open=${row.d_bank.get_parent.id}`
                : `${cek_inv}`;
                return `<a href="${urlnya}" data-id="${data}" class="text-primary edit-hotel mr-2">
                            <i class="ti ti-eye"></i>
                        </a>`;
            },
        }
    ];

    // --- 2. EXTRA PARAMS (di-mutate saat filter tanggal berubah) ---
    // NOTE: initGlobalDataTableTokenSelected diasumsikan meng-extend request
    // dengan object ini secara "live" (by reference) tiap kali ajax dijalankan
    // — pola umum di helper DataTable project ini (mutasi object ini lalu
    // panggil table.ajax.reload() akan otomatis kebawa ke request berikutnya).
    // Kalau helper globalnya ternyata cuma snapshot nilai sekali di awal init,
    // filter date range di bawah tidak akan ter-apply saat reload — sesuaikan
    // cara reload-nya dengan implementasi helper tersebut.
    let filterParams = {
        code_coa: lastSegment,
        kolom_name: "name",
        date_start: '',
        date_end: ''
    };

    // --- 3. INISIALISASI DATATABLE ---
    var table = initGlobalDataTableTokenSelected(
        '#tableDetailCoa',
        `{{ route('rep-detail-coa') }}`,
        columnCoa,
        filterParams
    );

    // --- 4. FILTER DATE RANGE ---
    $('#btn_filter_date').on('click', function() {
        const start = $('#filter_date_start').val();
        const end   = $('#filter_date_end').val();

        if (start && end && start > end) {
            Swal.fire('Peringatan', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.', 'warning');
            return;
        }

        filterParams.date_start = start;
        filterParams.date_end   = end;
        table.ajax.reload();
    });

    $('#btn_reset_date').on('click', function() {
        $('#filter_date_start').val('');
        $('#filter_date_end').val('');
        filterParams.date_start = '';
        filterParams.date_end   = '';
        table.ajax.reload();
    });
});
</script>
@endpush