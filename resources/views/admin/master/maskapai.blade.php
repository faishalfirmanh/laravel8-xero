@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Nama Maskapai</h5>
        <button class="btn btn-primary" id="btnTambah">
            <i class="ti ti-plus"></i> Tambah Maskapai
        </button>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-bordered" id="tableMaskapai">
            <thead class="table-dark">
                <tr>
                    <th>No</th>
                    <th>Nama Maskapai</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Action</th>
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
                    <h5 class="modal-title">Form Maskapai</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        &times;
                    </button>
                </div>

                <div class="modal-body text-left">
                    <input type="hidden" id="id_maskapai" name="id">

                    <div class="form-group">
                        <label>Nama Maskapai</label>
                        <input
                            type="text"
                            class="form-control"
                            id="nama_maskapai"
                            name="nama_maskapai"
                            required
                        >
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
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

   
    // DATATABLE
    // =====================
table = initGlobalDataTableToken(
    '#tableMaskapai',
    `{{ route('maskapai.getdata') }}`,
    [
        {
            data: null,
            className: "text-center",
            render: function (data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            }
        },
        { data: 'nama_maskapai', name: 'nama_maskapai' },
        {
            data: 'is_active',
            name: 'is_active',
            render: function (data) {
                return data == 1
                    ? '<span class="badge badge-primary">Aktif</span>'
                    : '<span class="badge badge-danger">Tidak Aktif</span>';
            }
        },
        {
            data: 'nama_pembuat',
            name: 'nama_pembuat',
            render: d => d ?? '-'
        },
        {
            data: 'id',
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function () {
                return `
                    <button class="btn btn-sm btn-info edit">
                        <i class="ti ti-pencil"></i>
                    </button>
                `;
            }
        }
    ],
    {
        kolom_name: 'nama_maskapai'
    }
);

    // =====================
    // TAMBAH DATA
    // =====================
    $('#btnTambah').click(function () {
        $('#formMaskapai')[0].reset();
        $('#id_maskapai').val('');
        $('input[name="is_active"][value="1"]').prop('checked', true);
        $('#modalMaskapai').modal('show');
    });

    // =====================
    // EDIT DATA
    // =====================
    $('#tableMaskapai').on('click', '.edit', function () {
        let data = table.row($(this).closest('tr')).data();

        $('#id_maskapai').val(data.id);
        $('#nama_maskapai').val(data.nama_maskapai);

        $('input[name="is_active"]').prop('checked', false);
        $(`input[name="is_active"][value="${data.is_active}"]`)
            .prop('checked', true);

        $('#modalMaskapai').modal('show');
    });

    // =====================
    // SUBMIT (TAMBAH / EDIT)
    // =====================
    $('#formMaskapai').submit(function (e) {
        e.preventDefault();

        let isEdit = $('#id_maskapai').val() !== '';

        let payload = {
            id: $('#id_maskapai').val() || null,
            nama_maskapai: $('#nama_maskapai').val(),
            is_active: $('input[name="is_active"]:checked').val()
        };

        ajaxRequest( `{{ route('maskapai.save') }}`,'POST',
            payload,
            localStorage.getItem("token")
        )
        .then(res => {
            $('#modalMaskapai').modal('hide');

            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: isEdit
                    ? 'Data maskapai berhasil diperbarui'
                    : 'Data maskapai berhasil ditambahkan'
            });

            table.ajax.reload(null, false);
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: err?.responseJSON?.message || 'Terjadi kesalahan'
            });
        });
    });

});
</script>
@endpush
