@extends('layouts.app')

@section('content')
<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Tracking Category</h5>
        <button id="btnAddTracking" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Baru
        </button>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered" id="table_tracking">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>Nama Parent Category</th>
                    <th>Total Item</th>
                    <th>Items Category</th>                    {{-- KOLOM BARU --}}
                    <th width="20%" class="text-center">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

{{-- MODAL (Detail + Edit Lines Category) --}}
<div class="modal fade" id="modalTracking" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Detail Tracking Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formTracking">
                    <input type="hidden" id="id_tracking" value="0">

                    <div class="form-group">
                        <label>Nama Parent Category <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name_parent_category" required>
                    </div>

                    <hr>
                    <h6 class="mb-3">📋 Detail Lines Category</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="table-lines">
                            <thead class="table-light">
                                <tr>
                                    <th>ID Parent</th>
                                    <th>Nama Item Category</th>
                                    <th>UUID Item Category</th>
                                    <th width="10%" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="lines-body"></tbody>
                        </table>
                    </div>
                    <button type="button" id="btnAddLine" class="btn btn-success btn-sm mt-2">
                        <i class="fas fa-plus"></i> Tambah Item Baru
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" id="btnSaveTracking" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let table;
    let currentLines = [];

    // Render tabel Lines di modal
    function renderLinesTable() {
        const tbody = $('#lines-body');
        tbody.empty();

        currentLines.forEach((line, index) => {
            const rowHtml = `
                <tr data-index="${index}">
                    <td><input type="text" class="form-control form-control-sm line-idparent" disabled value="${line.id_parent || ''}"></td>
                    <td><input type="text" class="form-control form-control-sm line-name" value="${line.item_name_category || ''}"></td>
                    <td><input type="text" class="form-control form-control-sm line-uuid" disabled value="${line.item_uuid_category || ''}"></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-danger delete-line" data-index="${index}">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                </tr>`;
            tbody.append(rowHtml);
        });
    }

    function resetModal() {
        $('#id_tracking').val(0);
        $('#name_parent_category').val('');
        currentLines = [];
        renderLinesTable();
        $('#modalTitle').text('Tambah Tracking Category Baru');
    }

    const columnTracking = [
        { data: null, className: "text-center", render: (d, t, r, m) => m.row + m.settings._iDisplayStart + 1 },
        { data: 'name_parent_category', name: 'name_parent_category' },
        {
            data: 'lines_category',
            name: 'lines_category',
            render: (data) => {
                if (!data) return 0;
                const lines = typeof data === 'string' ? JSON.parse(data) : data;
                return Array.isArray(lines) ? lines.length : 0;
            }
        },
        {
            data: 'lines_category',
            name: 'lines_category',
            render: (data) => {                                 // ← KOLOM BARU
                if (!data) return '-';
                const lines = typeof data === 'string' ? JSON.parse(data) : data;
                if (!Array.isArray(lines) || lines.length === 0) return '-';
                return lines.map(l =>
                    `<span class="badge badge-info mr-1">${l.item_name_category}</span>`
                ).join('');
            }
        },
        {
            data: null,
            className: "text-center",
            render: (data, type, row) => `
                <button class="btn btn-sm btn-info detail-tracking mx-1" data-id="${row.id}" title="Detail & Edit">
                    <i class="ti ti-pencil"></i>
                     Detail
                </button>
                <button class="btn btn-sm btn-danger delete-tracking" data-id="${row.id}" title="Hapus">
                    <i class="ti ti-trash"></i>
                </button>
            `
        }
    ];

    // Inisialisasi DataTable
    table = initGlobalDataTableToken(
        '#table_tracking',
        `{{ route('get-all-track') }}`,
        columnTracking,
        { "kolom_name": "name_parent_category" }
    );

    // ====================== TAMBAH BARU ======================
    $('#btnAddTracking').on('click', function () {
        resetModal();
        $('#modalTracking').modal('show');
    });

    // ====================== DETAIL + EDIT ======================
    $('#table_tracking').on('click', '.detail-tracking', function () {
        const id = $(this).data('id');

        ajaxRequest(`{{ route('find-track') }}`, 'GET', { id: id }, localStorage.getItem("token"))
            .then(response => {
                if (response.data.status === true) {
                    const data = response.data.data;
                   // console.log('ss',data)
                    $('#id_tracking').val(data.id);
                    $('#name_parent_category').val(data.name_parent_category);

                    currentLines = data.lines_category || [];
                    if (typeof currentLines === 'string') {
                        currentLines = JSON.parse(currentLines);
                    }

                    renderLinesTable();
                    $('#modalTitle').text('Detail & Edit Tracking Category');
                    $('#modalTracking').modal('show');
                }
            })
            .catch(err => {
                Swal.fire('Gagal!', err.message || 'Tidak dapat memuat data', 'error');
            });
    });

    // ====================== TAMBAH / HAPUS LINE ITEM ======================
    $('#btnAddLine').on('click', function () {
        currentLines.push({ id_parent: '', item_name_category: '', item_uuid_category: '' });
        renderLinesTable();
    });

    $(document).on('click', '.delete-line', function () {
        const index = $(this).data('index');
        currentLines.splice(index, 1);
        renderLinesTable();
    });

    // ====================== SIMPAN ======================
    $('#btnSaveTracking').on('click', function () {
        currentLines = [];
        $('#lines-body tr').each(function () {
            const idparent = $(this).find('.line-idparent').val().trim();
            const name     = $(this).find('.line-name').val().trim();
            const uuid     = $(this).find('.line-uuid').val().trim();
            if (name || uuid) {
                currentLines.push({
                    id_parent: idparent,
                    item_name_category: name,
                    item_uuid_category: uuid
                });
            }
        });

        const payload = {
            id: $('#id_tracking').val(),
            name_parent_category: $('#name_parent_category').val().trim(),
            lines_category: currentLines
        };

        ajaxRequest(`{{ route('save-track') }}`, 'POST', payload, localStorage.getItem("token"))
            .then(response => {

                if (response.data.status === true) {
                    Swal.fire('Berhasil!', 'Data berhasil disimpan', 'success');
                    $('#modalTracking').modal('hide');
                    table.ajax.reload();
                }
            })
            .catch(err => {
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan', 'error');
            });
    });

    // ====================== DELETE ======================
    $('#table_tracking').on('click', '.delete-tracking', function () {
        const id = $(this).data('id');
        const rowData = table.row($(this).parents('tr')).data();
        const name = rowData ? rowData.name_parent_category : 'data ini';

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: `Anda akan menghapus "${name}". Data tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest(`{{ route('delete-track') }}`, 'POST', { id: id }, localStorage.getItem("token"))
                    .then(response => {
                        if (response.data.status === true) {
                            Swal.fire('Sukses!', 'Data berhasil dihapus', 'success');
                            table.ajax.reload(null, false);
                        }
                    })
                    .catch(err => Swal.fire('Gagal!', err.message || 'Terjadi kesalahan', 'error'));
            }
        });
    });
});
</script>
@endpush
