@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
     <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Bank</h5>

        <div class="d-flex gap-2">

        <!-- Button Tambah COA -->
             <button type="button" onclick="" id="button_add_bank" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateHotel" style="height:42px;">
                Tambah Bank
             </button>

            <!-- Button Sync dari Xero -->
            <div class="d-flex flex-column align-items-end">
                <button onclick="syncCoaFromXero()" 
                        type="button" 
                        class="btn btn-success shadow-sm fw-bold">
                    <i class="fas fa-sync-alt me-1"></i> Sync dari Xero
                </button>
                <span class="text-muted mt-1 small" style="font-size: 11px;">
                    Sinkronisasi semua Bank dari Xero
                </span>
            </div>

        </div>
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
                    <th>No Rekening</th>
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
                <h5 class="modal-title">Tambah Bank Baru</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formCreateHotel">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id" id="idHotelInput">
                    <div class="form-group"> <label for="nameHotel">Nama Bank</label>
                        <input type="text" class="form-control" id="nameHotel" name="name" placeholder="Contoh: Bank BCA Namiroh" required>
                    </div>

                    <div class="form-group"> <label for="account_number">No Rek Bank</label>
                        <input type="text" class="form-control" id="account_number" name="account_number" placeholder="Contoh: 017382****" required>
                    </div>
                    <div class="form-group"> <label for="code">code</label>
                        <input type="text" class="form-control" id="code" name="code" placeholder="Contoh: 12233****" required>
                    </div>
                    <div class="form-group"> <label for="currency_code">Jenis Mata Uang</label>
                          <select class="form-control" id="currency_code" name="currency_code" required> <option value="" selected disabled>Pilih Jenis Mata Uang...</option>
                            <option value="0">--pilih jenis mata uang--</option>
                            <option value="SAR">SAR</option>
                            <option value="IDR">IDR</option>
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
            data: 'account_number',
            name: 'account_number',
            render: function(data) {
               return `<span class="badge badge-success">${data}</span>`; // BS4 pakai badge-success
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
                //let btnHapus = `<a href="javascript:;" data-id="${data}" class="text-danger deleted-hotel"><i class="ti ti-trash"></i></a>`;
                return btnEdit ;
            },
        }
    ];

    $('#tableHotel').on('click', '.edit-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data(); // Ambil data baris tersebut

        $('#idHotelInput').val(id);
         $('#code').val(rowData.code);
        $('#nameHotel').val(rowData.name);
        $('#account_number').val(rowData.account_number); // .change() untuk memicu update jika pakai select2
        $("#currency_code").val(rowData.currency_code).trigger('change')
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

    $("#button_add_bank").on("click",function(){
        $("#nameHotel").val('')
        $("#currency_code").val(0).trigger('change')
        $('#idHotelInput').val(0);
        $("#code").val('');
        $('#account_number').val('');
    });

    function tambahDataHotel(){
          $("#idHotelInput").val(0)
    }

     table = initGlobalDataTableToken(
        '#tableHotel',
        `{{ route('bank-list') }}`,
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
            code: params.get('code'),
            account_number: params.get('account_number'),
            currency_code: params.get('currency_code')
        };

        let jsonResult = JSON.stringify(selectedData);
         ajaxRequest( `{{ route('create-bank') }}`,'POST',selectedData, localStorage.getItem("token"))
            .then(response =>{
                 $("#modalCreateHotel").modal('hide')
                if(response.status == 200){
                     Swal.fire({
                        icon: 'success',
                        title: 'Simpan Berhasil!',
                        html: `
                            <div style="text-align: left; font-size: 14px;">
                                <p class="mb-1"> berhasil simpan bank </p>
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

function syncCoaFromXero(){

        Swal.fire({
            title: 'Sinkronisasi Bank dari Xero?',
            html: 'Apakah Anda yakin ingin mengambil <strong>semua bank </strong> dari Xero?<br><br>Proses ini akan memperbarui data bank lokal Anda.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',   // hijau
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Sync Sekarang',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {

                Swal.fire({
                    title: 'Sedang Sinkronisasi...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                ajaxRequest( `{{ route('all-bank-xero') }}`,'GET',{ is_sync: 1 }, localStorage.getItem("token"))
                .then(response =>{
                    Swal.close();
                        if (response.status === 200) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: `Berhasil sinkronisasi ${response.total_saved} data COA`,
                                timer: 2000
                            });
                            // Refresh table
                            $('#tableHotel').DataTable().ajax.reload();
                        } else {
                            Swal.fire('Gagal', response.data.data.message, 'error');
                        }
                })
                .catch((err)=>{
                    Swal.close();
                    Swal.fire('Error', 'Terjadi kesalahan saat sinkronisasi', 'error');
                    console.error(xhr.responseText);
                })
            }
        })
    }
</script>
@endpush
