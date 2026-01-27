@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Jmaah Mitra pesan hotel</h5>

        {{-- <button type="button" onclick="" id="button_add_hotel" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateHotel">
            <i class="ti ti-plus me-1"></i> Tambah Hotel
        </button> --}}
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered mt-0" id="table_jamaah">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>Name</th>
                    <th>Phone number</th>
                    {{-- <th width="15%">Action</th> --}}
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
    console.log('token',localStorage.getItem('token'))
    let columnJamaah = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        { data: 'full_name', name: 'full_name' },
        {
            data: 'phone_number',
            name: 'phone_number'
        },
        // {
        //     data: "id",
        //     orderable: false,
        //     searchable: false,
        //     className: "text-center",
        //     render: function(data, type, row) {
        //         console.log('idd',data)
        //         // Di sini saya asumsikan Edit juga pakai Modal, jadi nanti pakai data-toggle="modal" juga
        //         let btnEdit = `<a href="javascript:;" onclick="" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-pencil"></i></a>`;
        //         let btnHapus = `<a href="javascript:;" data-id="${data}" class="text-danger deleted-hotel"><i class="ti ti-trash"></i></a>`;
        //         return btnEdit + btnHapus;
        //     },
        // }
    ];

    $('#table_jamaah').on('click', '.edit-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data(); // Ambil data baris tersebut

        $('#id_jamaah_xero').val(id);
        $('#nameHotel').val(rowData.name);
        $('#typeLocation').val(rowData.type_location_hotel).change(); // .change() untuk memicu update jika pakai select2

        // Ubah Judul Modal dan Tampilkan
        $('.modal-title').text('Edit Hotel ' +rowData.name);
        $('#modalCreateHotel').modal('show');
    });

    function loadDataHotel(id){
        $("#id_jamaah_xero").val(id)

    }

    $('#table_jamaah').on('click', '.deleted-hotel', function() {
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
                ajaxRequest( `{{ route('deleteMasterHotel') }}`,'POST',{id : id}, localStorage.getItem("token"))
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
        $('#id_jamaah_xero').val(0);
        $('#nameHotel').val('');
        $('#typeLocation').val(0).change();
    });

    function tambahDataHotel(){
          $("#id_jamaah_xero").val(0)
    }

     table = initGlobalDataTableToken(
        '#table_jamaah',
        `{{ route('getAllContactApi') }}`,
        columnJamaah,
        { "kolom_name": "full_name" }
    );

    // --- 2. AJAX SUBMIT ---

});
</script>
@endpush
