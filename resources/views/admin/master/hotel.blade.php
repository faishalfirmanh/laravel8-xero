@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Hotel</h5>
        
        <button type="button" onclick="" id="button_add_hotel" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateHotel">
            <i class="ti ti-plus me-1"></i> Tambah Hotel
        </button>
    </div>
    
    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>
    
    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered mt-0" id="tableHotel">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>Name</th>
                    <th>Lokasi</th>
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
                <h5 class="modal-title">Tambah Hotel Baru</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formCreateHotel">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="idHotelInput" id="idHotelInput">
                    <div class="form-group"> <label for="nameHotel">Nama Hotel</label>
                        <input type="text" class="form-control" id="nameHotel" name="name" placeholder="Contoh: Hotel Hilton" required>
                    </div>

                    <div class="form-group">
                        <label for="typeLocation">Lokasi</label>
                        <select class="form-control" id="typeLocation" name="type_location_hotel" required> <option value="" selected disabled>Pilih Lokasi...</option>
                            <option value="0">PIlih Lokasi</option>
                            <option value="1">Makkah</option>
                            <option value="2">Madinah</option>
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
    var table;
    // --- 1. DATATABLE ---
    let columnHotel = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        { data: 'name', name: 'name' },
        {
            data: 'type_location_hotel',
            name: 'type_location_hotel',
            render: function(data) {
                if(data == 1) return '<span class="badge badge-success">Makkah</span>'; // BS4 pakai badge-success
                if(data == 2) return '<span class="badge badge-info">Madinah</span>';   // BS4 pakai badge-info
                return '-';
            }
        },
        {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data, type, row) {
                console.log('idd',data)
                // Di sini saya asumsikan Edit juga pakai Modal, jadi nanti pakai data-toggle="modal" juga
                let btnEdit = `<a href="javascript:;" onclick="${loadDataHotel(data)}" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-pencil"></i></a>`;
                let btnHapus = `<a href="javascript:;" data-id="${data}" class="text-danger deleted-hotel"><i class="ti ti-trash"></i></a>`;
                return btnEdit + btnHapus;
            },
        }
    ];

    $('#tableHotel').on('click', '.edit-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data(); // Ambil data baris tersebut
       
        $('#idHotelInput').val(id);
        $('#nameHotel').val(rowData.name);
        $('#typeLocation').val(rowData.type_location_hotel).change(); // .change() untuk memicu update jika pakai select2
        
        // Ubah Judul Modal dan Tampilkan
        $('.modal-title').text('Edit Hotel ' +rowData.name);
        $('#modalCreateHotel').modal('show');
    });

    function loadDataHotel(id){
        $("#idHotelInput").val(id)

    }

    $('#tableHotel').on('click', '.deleted-hotel', function() {
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
                ajaxRequest( `{{ route('deleteMasterHotel') }}`,'POST',{id : id}, null)
                .then(response =>{
                    if(response.status == 200){
                        Swal.fire({
                            title: "Hapus hotel sukses",
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

     table = initGlobalDataTable(
        '#tableHotel',
        `{{ route('getAllHotelApi') }}`, 
        columnHotel,
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
            type_location_hotel: params.get('type_location_hotel')
        };

        let jsonResult = JSON.stringify(selectedData);
         ajaxRequest( `{{ route('saveMasterHotel') }}`,'POST',selectedData, null)
            .then(response =>{
                 $("#modalCreateHotel").modal('hide')
                if(response.status == 200){
                     Swal.fire({
                        icon: 'success',
                        title: 'Simpan Berhasil!',
                        html: `
                            <div style="text-align: left; font-size: 14px;">
                                <p class="mb-1"> berhasil simpan hotel </p>
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