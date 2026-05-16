@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Coa</h5>
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
                    <th width="15%">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalCreateHotel" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Coa Baru</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formCreateHotel">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="idHotelInput" id="idHotelInput">
                    <div class="form-group"> <label for="nameHotel">Nama Coa</label>
                        <input type="text" class="form-control" id="nameHotel" name="name" placeholder="Contoh: Hotel Hilton" required>
                    </div>
                    <div class="form-group"> <label for="desc">Deskripsi Coa</label>
                        <input type="text" class="form-control" id="desc" name="desc" placeholder="Contoh: coa untuk pendapatan" required>
                    </div>
                    <div class="form-group">
                        <label for="account_type">Account Type</label>
                        <select class="form-control" id="account_type" name="account_type" required> <option value="" selected disabled>Pilih Account...</option>
                            <option value="0">Pilih Account</option>
                            <option value="current_asset">Current Asset</option>
                            <option value="fixed_asset">Fixed Asset</option>
                            <option value="inventory">inventory</option>
                            <option value="non_current_asset">Non Current Asset</option>
                            <option value="prepayment">Prepayment</option>
                            <option value="equity">equity</option>
                            <option value="expenses">expenses</option>
                            <option value="overhead">overhead</option>
                            <option value="current_liability">current liability</option>
                            <option value="liability">Liability</option>
                            <option value="non_current_liability">Non Current Liability</option>
                            <option value="other_income">Other Income</option>
                            <option value="revenue">Revenue</option>
                            <option value="sales">Sales</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSave">Simpan</button>
                </div>
            </form>
        </div>
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
            render: function(data) {
                let final_view = data.replaceAll('_', ' ');
                return final_view;
            }
        },
        {
            data: 'desc',
            name: 'desc'
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

   

     table = initGlobalDataTableToken(
        '#tableCoa',
        `{{ route('get-all-coa') }}`,
        columnCoa,
        { "kolom_name": "name" }
    );

});
</script>
@endpush
