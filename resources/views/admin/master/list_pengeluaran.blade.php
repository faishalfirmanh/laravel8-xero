@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Pengeluaran Paket</h5>
        <button class="btn btn-primary" id="btnTambah">
            <i class="ti ti-plus"></i> Tambah Pengeluaran
        </button>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-bordered" id="tablePengeluaran">
            <thead class="table-dark">
                <tr>
                    <th>No</th>
                    <th>Nama Pengeluaran</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

{{-- MODAL --}}
<div class="modal fade" id="modalPengeluaran" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formPengeluaran">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">Form Pengeluaran</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        &times;
                    </button>
                </div>

                <div class="modal-body text-left">
                    <input type="hidden" id="id_pengeluaran">

                    <div class="form-group">
                        <label>Nama Pengeluaran</label>
                        <input type="text" class="form-control" id="nama_pengeluaran" required>
                    </div>

                    <div class="form-group">
                        <label>Status</label><br>
                        <label class="mr-3">
                            <input type="radio" name="is_active" value="1"> Aktif
                        </label>
                        <label>
                            <input type="radio" name="is_active" value="0"> Tidak Aktif
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal">
                        Batal
                    </button>
                    <button class="btn btn-primary">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let table;

$(document).ready(function () {

    table = initGlobalDataTableToken(
        '#tablePengeluaran',
        `{{ route('md_g_pengeluaran') }}`,
        [
            {
                data: null,
                className: "text-center",
                render: (data, type, row, meta) =>
                    meta.row + meta.settings._iDisplayStart + 1
            },
            { data: 'nama_pengeluaran', name: 'nama_pengeluaran' },
            {
                data: 'is_active',
                render: d =>
                    d == 1
                        ? '<span class="badge badge-primary">Aktif</span>'
                        : '<span class="badge badge-danger">Tidak Aktif</span>'
            },
            {
                data: 'nama_pembuat',
                name: 'nama_pembuat',
                render: d=>d ?? '-'
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: () => `
                    <button class="btn btn-sm btn-info edit">
                        <i class="ti ti-pencil"></i>
                    </button>
                `
            }
        ],
        {
            kolom_name: 'nama_pengeluaran'
        }
    );

    // TAMBAH
    $('#btnTambah').click(() => {
        $('#formPengeluaran')[0].reset();
        $('#id_pengeluaran').val('');
        $('input[name=is_active][value=1]').prop('checked', true);
        $('#modalPengeluaran').modal('show');
    });

    // EDIT
    $('#tablePengeluaran').on('click', '.edit', function () {
        let data = table.row($(this).closest('tr')).data();

        $('#id_pengeluaran').val(data.id);
        $('#nama_pengeluaran').val(data.nama_pengeluaran);
        $(`input[name=is_active][value="${data.is_active}"]`)
            .prop('checked', true);

        $('#modalPengeluaran').modal('show');
    });

    // SUBMIT
    $('#formPengeluaran').submit(function (e) {
        e.preventDefault();

        let payload = {
            id: $('#id_pengeluaran').val() || null,
            nama_pengeluaran: $('#nama_pengeluaran').val(),
            is_active: $('input[name=is_active]:checked').val()
        };

        ajaxRequest(
            `{{ route('md_store_pengeluaran') }}`,
            'POST',
            payload,
            localStorage.getItem('token')
        )
        .then(() => {
            $('#modalPengeluaran').modal('hide');
            table.ajax.reload(null, false);
            Swal.fire('Berhasil', 'Data berhasil disimpan', 'success');
        });
    });

});
</script>
@endpush
