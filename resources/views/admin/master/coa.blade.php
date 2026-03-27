@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Coa</h5>

        <button type="button" onclick="" id="button_add_hotel" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateHotel">
            <i class="ti ti-plus me-1"></i> Tambah Coa
        </button>
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
                // Di sini saya asumsikan Edit juga pakai Modal, jadi nanti pakai data-toggle="modal" juga
                let btnEdit = `<a href="javascript:;" onclick="${loadDataHotel(data)}" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-pencil"></i></a>`;
                let btnHapus = `<a href="javascript:;" data-id="${data}" class="text-danger deleted-hotel"><i class="ti ti-trash"></i></a>`;
                return btnEdit + btnHapus;
            },
        }
    ];

    $('#tableCoa').on('click', '.edit-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data(); // Ambil data baris tersebut

        $('#idHotelInput').val(id);
        $('#nameHotel').val(rowData.name);
        $('#account_type').val(rowData.account_type).change();
        $('#desc').val(rowData.desc);
        // Ubah Judul Modal dan Tampilkan
        $('.modal-title').text('Edit Coa ' +rowData.name);
        $('#modalCreateHotel').modal('show');
    });

    function loadDataHotel(id){
        $("#idHotelInput").val(id)

    }

    $('#tableCoa').on('click', '.deleted-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data();
        let hotelName = rowData ? rowData.name : 'Data ini';

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: `Anda akan menghapus hotel "${hotelName}". Data yang dihapus tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33', // Merah untuk bahaya
            cancelButtonColor: '#3085d6', // Biru untuk batal
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest( `{{ route('delete-coa') }}`,'POST',{id : id}, localStorage.getItem("token"))
                .then(response =>{
                    if(response.status == 200){
                        Swal.fire({
                            title: "Hapus coa sukses",
                            text: "Berhasil hapus",
                            icon: "success"
                        });
                    }
                    table.ajax.reload()
                })
                .catch((err)=>{
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                    //console.log('error select2 invoice',err);
                })
            }
        });

    });

    $("#button_add_hotel").on("click",function(){
        $('#idHotelInput').val(0);
        $('#nameHotel').val('');
        $('#typeLocation').val(0).change();
    });

    function tambahDataHotel(){
          $("#idHotelInput").val(0)
    }

     table = initGlobalDataTableToken(
        '#tableCoa',
        `{{ route('get-all-coa') }}`,
        columnCoa,
        { "kolom_name": "name" }
    );

    // --- 2. AJAX SUBMIT ---
    $('#formCreateHotel').on('submit', function(e) {
        e.preventDefault();

        let formData = $(this).serialize();
        let params = new URLSearchParams(formData);
        let idInput = params.get('idHotelInput');
        let idHotel = (idInput && idInput > 0) ? idInput : null;


        let selectedData = {
            id: idHotel,
            name: params.get('name'),
            account_type: params.get('account_type'),
            desc : params.get('desc')
        };

        let jsonResult = JSON.stringify(selectedData);
         ajaxRequest( `{{ route('save-coa') }}`,'POST',selectedData, localStorage.getItem("token"))
            .then(response =>{
                 $("#modalCreateHotel").modal('hide')
                if(response.status == 200){
                     Swal.fire({
                        icon: 'success',
                        title: 'Simpan Berhasil!',
                        html: `
                            <div style="text-align: left; font-size: 14px;">
                                <p class="mb-1"> berhasil simpan coa </p>
                                <hr>
                            </div>
                        `,
                        confirmButtonText: 'Sukses'
                    })

                }
                table.ajax.reload()
            })
            .catch((err)=>{
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                console.log('error select2 invoice',err);
            })
    });

});
</script>
@endpush
