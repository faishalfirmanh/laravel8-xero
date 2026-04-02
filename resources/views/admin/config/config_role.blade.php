@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Hotel</h5>

        <button type="button" onclick="" id="button_add_hotel" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateHotel">
            <i class="ti ti-plus me-1"></i> Tambah User
        </button>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered mt-0" id="tableUserRole">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role User</th>
                    <th>Travel</th>
                    <th width="15%">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalConfigRole" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="min-height: 80vh;min-width:100vh">
            <div class="modal-header">
                <h5 class="modal-title">Config Menu Role</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formConfigUser">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="idHotelInput" id="idHotelInput">
                    <div class="form-group">
                        <label style="font-size:24px;font-weight:bold" for="nameHotel">Nama User</label>
                        <input type="text" class="form-control" id="nameHotel" name="name" placeholder="Contoh: Hotel Hilton" required>
                    </div>
                    <div class="form-group mt-3">
                        <label style="font-size: 24px;"><strong>Daftar Akses Menu</strong></label>
                        <p class="text-muted small mb-2">Pilih menu apa saja yang dapat diakses oleh user ini. (Centang untuk memberikan akses)</p>
                        <div class="row mt-2">
                            @php
                                $totalMenu = $menu_list->count();
                                $half      = ceil($totalMenu / 2);

                                $kolomKiri  = $menu_list->take($half);
                                $kolomKanan = $menu_list->slice($half);
                            @endphp
                            <!-- Kolom Kiri -->
                            <div class="col-6">
                                @foreach ($kolomKiri as $menu)
                                    <div class="form-check mb-3">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            id="menu_{{ $menu->id }}"
                                            name="menus[]"
                                            value="{{ $menu->id }}">

                                        <label class="form-check-label {{ $menu->parent_id === null ? 'font-weight-bold' : '' }}"
                                            for="menu_{{ $menu->id }}">
                                            {{ $menu->nama_menu }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Kolom Kanan -->
                            <div class="col-6">
                                @foreach ($kolomKanan as $menu)
                                    <div class="form-check mb-3">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            id="menu_{{ $menu->id }}"
                                            name="menus[]"
                                            value="{{ $menu->id }}">

                                        <label class="form-check-label {{ $menu->parent_id === null ? 'font-weight-bold' : '' }}"
                                            for="menu_{{ $menu->id }}">
                                            {{ $menu->nama_menu }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                       <label style="font-size: 24px;"><strong>Role User</strong></label>
                        <p class="text-muted small mb-2">Pilih role user</p>
                        <div class="row mt-2">
                            @php
                                $totalRole = $get_divisi->count();
                                $halfRole     = ceil($totalRole / 2);

                                $kolomKiriRole  = $get_divisi->take($halfRole);
                                $kolomKananRole = $get_divisi->slice($halfRole);
                            @endphp

                            <!-- Kolom Kiri -->
                            <div class="col-6">
                                @foreach ($kolomKiriRole as $rol)
                                    <div class="form-check mb-3">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            id="role_{{ $rol->id }}"
                                            name="roles[]"
                                            value="{{ $rol->id }}">

                                        <label class="form-check-label {{ $rol->id === null ? 'font-weight-bold' : '' }}"
                                            for="role_{{ $rol->id }}">
                                            {{ $rol->nama_role."-".$rol->nama_lini_usaha }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Kolom Kanan -->
                            <div class="col-6">
                                @foreach ($kolomKananRole as $rol_kan)

                                    <div class="form-check mb-3">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            id="rol_{{ $rol_kan->id }}"
                                            name="roles[]"
                                            value="{{ $rol_kan->id }}">

                                        <label class="form-check-label {{ $rol_kan->id === null ? 'font-weight-bold' : '' }}"
                                            for="role_{{ $rol_kan->id }}">
                                            {{ $rol_kan->nama_role."-".$rol_kan->nama_lini_usaha }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 24px;"><strong>Trevel</strong></label>
                        <div class="col-6">
                            @foreach ($travel as $trev)

                                <div class="form-check mb-3">
                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="trevel_{{ $trev->id }}"
                                        name="travel[]"
                                        value="{{ $trev->id }}">

                                    <label class="form-check-label {{ $trev->id === null ? 'font-weight-bold' : '' }}"
                                        for="role_{{ $rol_kan->id }}">
                                        {{ $trev->name }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
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
    console.log("token",localStorage.getItem("token"))
    var table;
    // --- 1. DATATABLE ---
    let columnUser = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        { data: 'name', name: 'name' },
        {
            data: 'email',
            name: 'email'
        },
        {
            data: 'list_role',
            name: 'list_role',
            render: function(data,type,row) {
                console.log('dat',row);
                if (data.length === 0) {
                    return '<span class="badge badge-secondary">-</span>';
                }
                return data.map(function(role) {
                    return `<span class="badge badge-info mr-1">${role}</span>`;
                }).join('');
            }
        },
         {
            data: 'list_travel',
            name: 'list_travel',
            render: function(data,type,row) {
                if (data.length === 0) {
                    return '-';
                }
                return data.map(function(travel) {
                    return `<span class="badge badge-success mr-1">${travel}</span>`;
                }).join('');
            }
        },
        {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data, type, row) {

                // Di sini saya asumsikan Edit juga pakai Modal, jadi nanti pakai data-toggle="modal" juga
                let btnEdit = `<a href="javascript:;" onclick="${loadDataConfigUser(data)}" data-id="${data}" class="text-primary edit-data-user mr-2"><i class="ti ti-pencil"></i></a>`;
                let btnHapus = `<a href="javascript:;" data-id="${data}" class="text-danger deleted-hotel"><i class="ti ti-trash"></i></a>`;
                return btnEdit + btnHapus;
            },
        }
    ];

    $('#tableUserRole').on('click', '.edit-data-user', function() {
        let id = $(this).data('id');
        ajaxRequest( `{{ route('find-user') }}`,'get',{id : id}, localStorage.getItem("token"))
            .then(response =>{
                if(response.data.status){
                    console.log('re',response.data.data)
                    const data = response.data.data;
                    const userMenus = data.menu;
                    const user_select_role = data.role_user;

                      // --- 1. RESET SEMUA CHECKBOX ---
                    $('input[name="menus[]"]').prop('checked', false);
                    $('input[name="roles[]"]').prop('checked', false);

                    // --- 2. AMBIL SEMUA ID DARI RESPONSE (FLATTEN) ---
                    let accessibleIds = [];

                    function collectIds(menus) {
                        menus.forEach(m => {
                            accessibleIds.push(m.id);
                            if (m.children && m.children.length > 0) {
                                collectIds(m.children);
                            }
                        });
                    }

                    collectIds(userMenus);

                    let allValues = $('input[name="roles[]"]').map(function() {
                        return $(this).val();
                    }).get();

                    user_select_role.forEach(role_id =>{
                        $(`#rol_${role_id}`).prop('checked', true);
                    })

                    // --- 3. CENTANG CHECKBOX YANG COCOK ---
                    accessibleIds.forEach(menuId => {
                        $(`#menu_${menuId}`).prop('checked', true);
                    });
                        $('#modalConfigRole').modal('show');
                    }



            })
            .catch((err)=>{
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                //console.log('error select2 invoice',err);
            })
    });

    function loadDataConfigUser(id){
        $("#idHotelInput").val(id)

    }

    $('#tableUserRole').on('click', '.deleted-hotel', function() {
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
        $('#idHotelInput').val(0);
        $('#nameHotel').val('');
        $('#typeLocation').val(0).change();
    });

    function tambahDataHotel(){
          $("#idHotelInput").val(0)
    }

     table = initGlobalDataTableToken(
        '#tableUserRole',
        `{{ route('get-all-user') }}`,
        columnUser,
        { "kolom_name": "name" }
    );

    // --- 2. AJAX SUBMIT ---
    $('#formConfigUser').on('submit', function(e) {
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
         ajaxRequest( `{{ route('saveMasterHotel') }}`,'POST',selectedData, localStorage.getItem("token"))
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
