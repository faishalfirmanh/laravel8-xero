@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Coa</h5>
        <div class="form-group mb-0">
            <select id="filterAccountType" class="form-select form-select-sm">
                <option value="ALL">ALL</option>
                <option value="ASSET">ASSET</option>
                <option value="LIABILITIES">LIABILITIES</option>
                <option value="EQUITY">EQUITY</option>
                <option value="EXPENSE">EXPENSE</option>
                <option value="REVENUE">REVENUE</option>
            </select>
        </div>
    </div>

    

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered mt-0" id="tableCoa">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                     <th>Name</th>
                    <th>Account Type</th>
                    <th>Diskripsi</th>
                    <th>Nominal</th>
                    <th width="15%">Action</th>
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
            data: 'name',
            name: 'name'
        },
        {
            data: 'account_type',
            name: 'account_type' ,
            render: function(data,type, row) {
                let final_view = data.replaceAll('_', ' ');
                return final_view;
            }
        },
        {
            data: 'desc',
            name: 'desc'
        },
        {
            data: 'sum_nominal',
            name: 'sum_nominal',
             render: function(data,type, row) {
              
                return formatCurrency(data);
            }
        },
        {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data, type, row) {
               
                let url = "{{ route('view-report-detail-coa', ':id') }}";
                url = url.replace(':id', data);
                let btnEdit = `<a href="${url}" data-id="${data}" class="text-primary edit-hotel mr-2" title="View Detail"><i class="ti ti-eye"></i></a>`;
                
                return btnEdit;
            },
        }
    ];

   

    function loadTable(accountType) {
    console.log('loadd',accountType)
        table = initGlobalDataTableTokenSelected(
            '#tableCoa',
            `{{ route('get-all-coa') }}`,
            columnCoa,
            { 'kolom_name': 'name', 'type' : accountType } 
        );
    }

    let initialType = $('#filterAccountType').val(); 
    loadTable(initialType);

    $('#filterAccountType').on('change', function() {
       let selectedType = $(this).val();
       loadTable(selectedType);
    });

});
</script>
@endpush
