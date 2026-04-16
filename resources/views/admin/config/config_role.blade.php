@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Roles</h5>

    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered mt-0" id="tableMenuRole">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>Nama Role</th>
                    <th>Menu</th>
                    <th>Status</th>
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
            <form id="formConfigRole">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="idHotelInput" id="idHotelInput">
                    <div class="form-group">
                        <label style="font-size:24px;font-weight:bold" for="nameHotel">Nama User</label>
                        <input type="text" class="form-control" id="name_user_config_role" name="name" disabled>
                    </div>
                    <div class="form-group mt-3">
                        <label style="font-size: 24px;"><strong>Daftar Akses Menu</strong></label>
                        <p class="text-muted small mb-2">Pilih menu apa saja yang dapat diakses oleh user ini. (Centang untuk memberikan akses)</p>
                       <div class="row mt-2">
                            @php
                                $parents = $menu_list->whereNull('parent_id')->sortBy('urutan');
                                // Group semua child berdasarkan parent_id
                                $childGroups = $menu_list->whereNotNull('parent_id')->groupBy('parent_id');
                                
                                // Bagi parent jadi 2 kolom (supaya tetap balanced)
                                $half = ceil($parents->count() / 2);
                                $leftParents  = $parents->take($half);
                                $rightParents = $parents->slice($half);
                            @endphp
                            <!-- Kolom Kiri -->
                            <div class="col-6">
                                @foreach ($leftParents as $parent)
                                    <!-- PARENT MENU (bold) -->
                                    <div class="form-check mb-3">
                                        {{-- <input class="form-check-input" 
                                            type="checkbox" 
                                            id="menu_{{ $parent->id }}" 
                                            name="menus[]" 
                                            value="{{ $parent->id }}"> --}}
                                        <label class="form-check-label font-weight-bold" 
                                            for="menu_{{ $parent->id }}" style="font-size: 23px;">
                                            {{ $parent->nama_menu }}
                                        </label>
                                    </div>
                                    <!-- CHILD MENUS (indent) -->
                                    @foreach ($childGroups->get($parent->id, collect()) as $child)
                                        <div class="form-check mb-3 ms-4">
                                            <input class="form-check-input" 
                                                type="checkbox" 
                                                id="menu_{{ $child->id }}" 
                                                name="menus[]" 
                                                value="{{ $child->id }}">
                                            <label class="form-check-label" 
                                                for="menu_{{ $child->id }}">
                                                {{ $child->nama_menu }}
                                            </label>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                            <!-- Kolom Kanan -->
                            <div class="col-6">
                                @foreach ($rightParents as $parent)
                                    <!-- PARENT MENU (bold) -->
                                    <div class="form-check mb-3">
                                        {{-- <input class="form-check-input" 
                                            type="checkbox" 
                                            id="menu_{{ $parent->id }}" 
                                            name="menus[]" 
                                            value="{{ $parent->id }}"> --}}
                                        <label class="form-check-label font-weight-bold" 
                                            for="menu_{{ $parent->id }}" style="font-size: 23px;">
                                            {{ $parent->nama_menu }}
                                        </label>
                                    </div>

                                    <!-- CHILD MENUS (indent) -->
                                    @foreach ($childGroups->get($parent->id, collect()) as $child)
                                        <div class="form-check mb-3 ms-4">
                                            <input class="form-check-input" 
                                                type="checkbox" 
                                                id="menu_{{ $child->id }}" 
                                                name="menus[]" 
                                                value="{{ $child->id }}">
                                            <label class="form-check-label" 
                                                for="menu_{{ $child->id }}">
                                                {{ $child->nama_menu }}
                                            </label>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
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
        { data: 'nama_role', name: 'nama_role' },
        {
            data: 'menus',
            name: 'menus',
            orderable: false,
            searchable: false,
            render: (data) => {
                if (!data) return '-';

                let menus = typeof data === 'string' ? JSON.parse(data) : data;

                if (!Array.isArray(menus) || menus.length === 0) return '-';

                const parents = menus.filter(m => m.parent_id == null || m.parent_id == 0);
                const childGroups = {};

                menus.forEach(menu => {
                    if (menu.parent_id != null && menu.parent_id != 0) {
                        if (!childGroups[menu.parent_id]) childGroups[menu.parent_id] = [];
                        childGroups[menu.parent_id].push(menu);
                    }
                });

                // === BUILD HTML VERTIKAL ===
                let html = '<div style="line-height:1.5; white-space:normal;">';

                // 1. Tampilkan Parent + Child-nya
                parents.forEach(parent => {
                    html += `<div class="fw-bold text-dark mb-1">${parent.nama_menu}</div>`;

                    const children = childGroups[parent.id] || [];
                    children.forEach(child => {
                        html += `<div class="ms-4 text-muted small">• ${child.nama_menu}</div>`;
                    });

                    // jarak antar group
                    if (children.length) html += '<div class="mb-3"></div>';
                });

                // 2. Tampilkan child yang tidak punya parent di list (orphan)
                const orphanChildren = menus.filter(m => 
                    m.parent_id != null && 
                    !parents.some(p => p.id === m.parent_id)
                );

                if (orphanChildren.length) {
                    orphanChildren.forEach(child => {
                        html += `<div class="ms-2 text-muted small">• ${child.nama_menu}</div>`;
                    });
                }

                html += '</div>';

                return html;
            }
        },
        {
            data: 'is_active',
            name: 'is_active',
            render: function(data,type,row) {
                let cek_ = data == 1 ? '<span class="badge badge-success">Active</span>'
                 : '<span class="badge badge-danger">Not Active</span>'
                return cek_;
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
               // let btnHapus = `<a href="javascript:;" data-id="${data}" class="text-danger deleted-hotel"><i class="ti ti-trash"></i></a>`;
                return btnEdit ;
            },
        }
    ];

    $('#tableMenuRole').on('click', '.edit-data-user', function() {
        let id = $(this).data('id');
        $("#idHotelInput").val(id)
        console.log('idd',id)
        ajaxRequest( `{{ route('find-roles-menu') }}`,'get',{id : id}, localStorage.getItem("token"))
            .then(response =>{
            
                if(response.data.status){
                    const success_data = response.data.data;
                    const userMenus = success_data.menus;
                    const user_select_role = success_data.role_user;
                    $("#name_user_config_role").val(success_data.nama_role)
                //       // --- 1. RESET SEMUA CHECKBOX ---
                    $('input[name="menus[]"]').prop('checked', false);
                //     $('input[name="roles[]"]').prop('checked', false);
                //     $('input[name="travel_multi_check[]"]').prop('checked', false);

                console.log('userMenus',userMenus)
                //     // --- 2. AMBIL SEMUA ID DARI RESPONSE (FLATTEN) ---
                     let accessibleIds = [];
                //     let roles_user_id = [];
                //     let list_travelss = [];

                    function collectIds(menus) {
                        menus.forEach(m => {
                            accessibleIds.push(m.id);
                            if (m.children && m.children.length > 0) {
                                collectIds(m.children);
                            }
                        });
                    }

                    collectIds(userMenus);
                    accessibleIds.forEach(menuId => {
                        $(`#menu_${menuId}`).prop('checked', true);
                    });
                        $('#modalConfigRole').modal('show');
                    }


            })
            .catch((err)=>{
                console.log('error',err)
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                //console.log('error select2 invoice',err);
            })
    });

    function loadDataConfigUser(id){
        $("#idHotelInput").val(id)

    }

   

    $("#button_add_hotel").on("click",function(){
        $('#idHotelInput').val(0);
        $('#nameHotel').val('');
        $('#typeLocation').val(0).change();
    });

    function tambahDataHotel(){
          $("#idHotelInput").val(0)
    }

     table = initGlobalDataTableToken(
        '#tableMenuRole',
        `{{ route('get-all-roles') }}`,
        columnUser,
        { "kolom_name": "nama_role" }
    );

    // --- 2. AJAX SUBMIT ---
    $('#formConfigRole').on('submit', function(e) {
          $('#modalConfigRole').modal('hide');
        e.preventDefault();
        let formDataArray = $(this).serializeArray();
        console.log('📋 Semua data form (serializeArray):', formDataArray);

        // =============================================
        // 2. Buat object rapi (paling sering dipakai)
        // =============================================
        let data_form = {
            roles_id: $('#idHotelInput').val().trim(),           // hidden input
            menus: $('input[name="menus[]"]:checked').map(function() {
                return this.value;
            }).get()
        };

         ajaxRequest( `{{ route('save-config-roles-menu') }}`,'POST',data_form, localStorage.getItem("token"))
            .then(response =>{
                console.log('saved',response)
                if(response.status == 200){
                     Swal.fire({
                        icon: 'success',
                        title: 'Simpan Berhasil!',
                        html: `
                            <div style="text-align: center; font-size: 14px;">
                                <p class="mb-1"> berhasil simpan config roles menu </p>
                                <hr>
                            </div>
                        `,
                        confirmButtonText: 'Sukses'
                    })
                    table.ajax.reload()
                }
            })
            .catch((err)=>{
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                console.log('error select2 invoice',err);
            })
    });

});
</script>
@endpush
