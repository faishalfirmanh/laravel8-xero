@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Maskapai</h5>

        <button type="button" id="btnAddMaskapai" class="btn btn-primary" data-toggle="modal" data-target="#modalMaskapai">
            <i class="ti ti-plus me-1"></i> Tambah Maskapai
        </button>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered" id="tableMaskapai">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>Nama Maskapai</th>
                    <th>Status</th>
                    <th width="15%">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

{{-- MODAL --}}
<div class="modal fade" id="modalMaskapai" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formMaskapai">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Maskapai</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="idMaskapai" name="id">

                    <div class="form-group">
                        <label>Nama Maskapai</label>
                        <input type="text" class="form-control" id="namaMaskapai" name="nama_maskapai" required>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" id="isActive" name="is_active" required>
                            <option value="1">Aktif</option>
                            <option value="0">Tidak Aktif</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function () {

    let table;

    // ================= DATATABLE =================
    let columnMaskapai = [
        {
            data: null,
            className: "text-center",
            render: (data, type, row, meta) =>
                meta.row + meta.settings._iDisplayStart + 1
        },
        { data: 'nama_maskapai' },
        {
            data: 'is_active',
            className: "text-center",
            render: data =>
                data == 1
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-danger">Tidak Aktif</span>'
        },
        {
            data: 'id',
            orderable: false,
            searchable: false,
            className: "text-center",
            render: id => `
                <a href="javascript:;" class="text-primary edit-maskapai" data-id="${id}">
                    <i class="ti ti-pencil"></i>
                </a>
            `
        }
    ];

    table = initGlobalDataTableToken(
        '#tableMaskapai',
        `{{ route('maskapai.getdata') }}`,
        columnMaskapai,
        { kolom_name: 'nama_maskapai' }
    );

    // ================= ADD =================
    $('#btnAddMaskapai').on('click', function () {
        $('#idMaskapai').val('');
        $('#namaMaskapai').val('');
        $('#isActive').val(1);
        $('.modal-title').text('Tambah Maskapai');
    });

    // ================= EDIT =================
    $('#tableMaskapai').on('click', '.edit-maskapai', function () {
        let rowData = table.row($(this).parents('tr')).data();

        $('#idMaskapai').val(rowData.id);
        $('#namaMaskapai').val(rowData.nama_maskapai);
        $('#isActive').val(rowData.is_active);

        $('.modal-title').text('Edit Maskapai');
        $('#modalMaskapai').modal('show');
    });

    // ================= SAVE =================
    $('#formMaskapai').on('submit', function (e) {
        e.preventDefault();

        let payload = {
            id: $('#idMaskapai').val(),
            nama_maskapai: $('#namaMaskapai').val(),
            is_active: $('#isActive').val()
        };

        ajaxRequest(
            `{{ route('maskapai.save') }}`,
            'POST',
            payload,
            localStorage.getItem("token")
        )
        .then(res => {
            $('#modalMaskapai').modal('hide');
            Swal.fire('Berhasil', 'Data berhasil disimpan', 'success');
            table.ajax.reload();
        })
        .catch(err => {
            Swal.fire('Gagal!', err.message || 'Terjadi kesalahan', 'error');
        });
    });

});
</script>
@endpush
