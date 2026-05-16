@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0" id="title_trans">Daftar Transaksi </h5>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered mt-0" id="tableDetailCoa">
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
    //console.log("token",localStorage.getItem("token"))
    var table;
    let full_url = window.location.href;
    const segments = full_url.split('/').filter(Boolean);
    const lastSegment = segments.pop();
  
    // --- 1. DATATABLE ---
    let columnCoa = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        {
            data: 'date_transaction',
            name: 'date_transaction',
            render: function(data){
                return convertStringDate(data)
            }
        },
        {
            data: 'name_trans',
            name: 'name_trans' ,
        },
        {
            data: null,
            name: null,
            render: function(data){
                $("#title_trans").text(data.name_coa)
                console.log('daa',data.name_coa)
                let datanya = data.d_bill ? data.d_bill.desc : '-';
                return datanya
            }
        },
        {
            data: 'nominal',
            name: 'nominal',
            render: function (data) {
                    return formatCurrency(data);
            }
        }
    ];

   

     let payload = {
        code_coa: lastSegment,
        limit:10,
        page:1
    };

     table = initGlobalDataTableTokenSelected(
        '#tableDetailCoa',
        `{{ route('rep-detail-coa') }}`,
        columnCoa,
        { code_coa: lastSegment}
    );

});
</script>
@endpush
