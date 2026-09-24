@extends('layouts.app')

@section('content')

<style>
    .modal-xxl {
        max-width: 95% !important;
        width: 95% !important;
    }
    
   
    
    #itemTable th, #itemTable td {
        vertical-align: middle;
    }

    .select2-dropdown {
        min-width: 350px !important;   /* Lebar dropdown Account */
    }

    .dropdown-menu {
       z-index: 1060 !important;
    }
</style>

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">List Unit</h5>
          <button class="btn btn-primary" id="btnTambah">
            <i class="ti ti-plus"></i> Tambah Unit
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
                    <th>Code</th>
                    <th>Name</th>
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
                <h5 class="modal-title">Tambah Unit Baru</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formCreateHotel">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id" id="idHotelInput">
                    <div class="form-group"> <label for="name">Nama </label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="KG, PCS" required>
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

    // --- HELPER FUNCTIONS 

    // --- 1. DATATABLE CONFIG ---
    let columnWarehouse = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        { data: 'code', name: 'code' },
    
        { 
            data: 'name', 
            name: 'name'
        },
       
        // { 
        //     data: 'is_active', //'final_nominal', 
        //     name: 'is_active', //'final_nominal',
        //     render: function(data, type, row) { 
        //         let cek_ = data ? '<span class="btn-primary">Active</span>' : '<span class="btn-danger">Not Active</span>'
        //         return cek_
        //     },  
        // },
        {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data, type, row) {//
               // let url = "{{ route('web-bank-trans-detail', ':id') }}";
               // url = url.replace(':id', data);
                let btnView = `<a href="#" data-id="${data}" class="text-primary edit-warehouse mr-2" title="View Detail"><i class="ti ti-pencil"></i></a>`;
                return btnView;

                //return `<a href="javascript:;" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-eye"></i></a>`;
            },
        }
    ];

    table = initGlobalDataTableTokenSelected(
        '#tableHotel',
        `{{ route('get-all-unit') }}`,
        columnWarehouse,
        { "kolom_name": "name" }
    );


    $('#btnTambah').click(function(){
        $('#formCreateHotel')[0].reset();
        $('input[name="is_active"][value="1"]').prop('checked', true);
        $('#modalCreateHotel').modal('show');
    });


    $(document).on('click', '.edit-warehouse', function(e) {
        e.preventDefault();
        let id = $(this).data('id');
        $("#modalCreateHotel").modal('show');
        loadEdit(id)

    });

    function loadEdit(id){
         ajaxRequest(
            `{{ route('find-unit') }}`,
            'GET',
            {id : id},
            localStorage.getItem('token')
        )
        .then((ress) => {
            let final_data = ress.data.data;
            $("#name").val(final_data.name)
            $("#idHotelInput").val(id)
            $("#address").val(final_data.address)
           // $("#is_active").val(final_data.is_active)
              $(`input[name="is_active"][value="${final_data.is_active}"]`)
            .prop('checked', true);
           //console.log(final_data)
        })
        .catch(err => {
            cathError(err)
        });
    }

    $('#formCreateHotel').on('submit', function(e) {
        e.preventDefault();

        let form = this;
        let formData = new FormData(form);

        let payload = {
            id: formData.get('id') || null,
            name :  formData.get('name'),
        };

        ajaxRequest(
            `{{ route('save-unit') }}`,
            'POST',
            payload,
            localStorage.getItem('token')
        )
        .then((ressnya) => {
            $('#modalCreateHotel').modal('hide');
                table.ajax.reload(null, false);

                Swal.fire({
                    icon: 'success',
                    title:'Berhasil',
                    text: formData.get('id')
                            ? 'Unit berhasil diperbarui'
                            : 'Unit berhasil ditambahkan'
            });

            table.ajax.reload(null, false);

        })
        .catch(err => {
            cathError(err)
        });
    });
    // --- 6. SAVE FUNCTIONALITY ---
 

});
</script>
@endpush