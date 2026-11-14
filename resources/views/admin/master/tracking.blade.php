@extends('layouts.app')


<style>
    /* Area atas modal tetap terlihat saat daftar line di-scroll */
    #modalTracking .sticky-top-form {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #fff;
        margin: 0 -1rem .5rem;
        padding: .75rem 1rem .25rem;
        border-bottom: 1px solid #dee2e6;
        box-shadow: 0 2px 4px rgba(0, 0, 0, .06);
    }
    #modalTracking .search-result-box {
        position: absolute;
        left: 0; right: 0; top: 100%;
        z-index: 20;
        max-height: 260px;
        overflow-y: auto;
        box-shadow: 0 4px 10px rgba(0, 0, 0, .15);
    }
</style>
@section('content')
<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Tracking Category</h5>
        <div class="d-flex gap-2"></div>
            <div class="d-flex flex-column align-items-end">
                <button onclick="syncTracking()" 
                        type="button" 
                        class="btn btn-success shadow-sm fw-bold">
                    <i class="fas fa-sync-alt me-1"></i> Sync dari Xero
                </button>
                <span class="text-muted mt-1 small" style="font-size: 11px;">
                    Sinkronisasi 
                </span>
                 <button id="btnAddTracking" style="margin-left: 390px;" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Tambah Baru
            </button>
            </div>
        </div>

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

{{-- MODAL (Detail + Edit Lines Category) sync_tracking--}}
<div class="modal fade" id="modalTracking" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Detail Tracking Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body pt-0">
                <form id="formTracking" onsubmit="return false;">
                    <input type="hidden" id="id_tracking" value="0">

                    <div id="stickyTop" class="sticky-top-form">
                        {{-- Search: hanya tampil saat klik Detail --}}
                        <div class="form-group mb-2 position-relative" id="wrapSearchLine" style="display:none;">
                            <label class="mb-1">🔍 Cari Item Category</label>
                            <input type="text" id="searchLine" class="form-control"
                                   placeholder="Ketik nama item category..." autocomplete="off">
                            <div id="searchResult" class="list-group search-result-box" style="display:none;"></div>
                        </div>

                        <div class="form-row align-items-end">
                            <div class="form-group col-md-7 mb-2">
                                <label class="mb-1">Nama Parent Category <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name_parent_category" required>
                            </div>
                            <div class="form-group col-md-5 mb-2 text-right">
                                <button type="button" id="btnAddLine" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Tambah Item
                                </button>
                                <button type="button" id="btnSaveTracking" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan
                                </button>
                            </div>
                        </div>

                        <h6 class="mb-2">📋 Detail Lines Category <small class="text-muted" id="lineCount"></small></h6>
                    </div>

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
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let table;
    let searchTimer = null;
    let searchSeq = 0;

    // ====================== HELPER ======================
    const esc = (v) => (v === null || v === undefined ? '' : String(v))
        .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
        .replace(/</g, '&lt;').replace(/>/g, '&gt;');

    function lineRowHtml(line) {
        return `
            <tr>
                <td><input type="text" class="form-control form-control-sm line-idparent" disabled value="${esc(line.id_parent)}"></td>
                <td><input type="text" class="form-control form-control-sm line-name" value="${esc(line.item_name_category)}"></td>
                <td><input type="text" class="form-control form-control-sm line-uuid" disabled value="${esc(line.item_uuid_category)}"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger delete-line">
                        <i class="ti ti-trash"></i>
                    </button>
                </td>
            </tr>`;
    }

    function updateLineCount() {
        const n = $('#lines-body tr').length;
        $('#lineCount').text(n ? `(${n} item)` : '');
    }

    function renderLinesTable(lines) {
        const tbody = $('#lines-body');
        tbody.empty();
        (lines || []).forEach(line => tbody.append(lineRowHtml(line)));
        updateLineCount();
    }

    // Ambil isi tabel langsung dari input (supaya ketikan yang belum disimpan tidak hilang)
    function collectLines() {
        const lines = [];
        $('#lines-body tr').each(function () {
            const idparent = $(this).find('.line-idparent').val().trim();
            const name     = $(this).find('.line-name').val().trim();
            const uuid     = $(this).find('.line-uuid').val().trim();
            if (name || uuid) {
                lines.push({ id_parent: idparent, item_name_category: name, item_uuid_category: uuid });
            }
        });
        return lines;
    }

    function scrollToRow($row) {
        const $body   = $('#modalTracking .modal-body');
        const stickyH = $('#stickyTop').outerHeight() || 0;
        const top = $row.offset().top - $body.offset().top + $body.scrollTop() - stickyH - 8;
        $body.animate({ scrollTop: Math.max(top, 0) }, 250);
    }

    function highlightRow($row) {
        $row.addClass('table-warning');
        setTimeout(() => $row.removeClass('table-warning'), 2500);
        const input = $row.find('.line-name')[0];
        if (input) input.focus({ preventScroll: true });
    }

    function resetSearch() {
        clearTimeout(searchTimer);
        searchSeq++;
        $('#searchLine').val('');
        $('#searchResult').hide().empty();
    }

    function resetModal() {
        $('#id_tracking').val(0);
        $('#name_parent_category').val('');
        renderLinesTable([]);
        resetSearch();
        $('#wrapSearchLine').hide();          // search hanya untuk Detail
        $('#modalTitle').text('Tambah Tracking Category Baru');
    }

    // ====================== SEARCH (API search-item-tracking) ======================
    function showSearchMessage(text) {
        $('#searchResult').empty().append(
            $('<div class="list-group-item py-2 text-muted small">').text(text)
        ).show();
    }

    function renderSearchResult(rows) {
        const box = $('#searchResult').empty();
        if (!rows.length) {
            showSearchMessage('Tidak ditemukan');
            return;
        }
        rows.forEach(r => {
            $('<button type="button" class="list-group-item list-group-item-action py-2 search-item">')
                .attr('data-uuid', r.item_uuid_category)
                .append($('<div class="font-weight-bold">').text(r.item_name_category))
                .append($('<small class="text-muted">').text(r.item_uuid_category))
                .appendTo(box);
        });
        box.show();
    }

    function doSearch(keyword) {
        const seq = ++searchSeq;
        showSearchMessage('Mencari...');

        ajaxRequest(`{{ route('search-item-tracking') }}`, 'GET',
            { q: keyword, id_parent: $('#id_tracking').val() },
            localStorage.getItem("token"))
            .then(response => {
                if (seq !== searchSeq) return;            // abaikan response lama
                let rows = response?.data?.data ?? [];
                if (!Array.isArray(rows)) rows = Object.values(rows);
                renderSearchResult(rows);
            })
            .catch(err => {
                if (seq !== searchSeq) return;
                $('#searchResult').hide().empty();
                cathError(err);
            });
    }

    $('#searchLine').on('input', function () {
        const keyword = $(this).val().trim();
        clearTimeout(searchTimer);
        if (keyword.length < 1) {
            searchSeq++;
            $('#searchResult').hide().empty();
            return;
        }
        searchTimer = setTimeout(() => doSearch(keyword), 300);
    });

    $('#searchLine').on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('#searchResult').hide();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            $('#searchResult .search-item').first().trigger('click');
        }
    });

    // Pilih hasil search -> scroll ke baris & sorot
    $(document).on('click', '#searchResult .search-item', function () {
        const uuid = $(this).data('uuid');
        const $row = $('#lines-body tr').filter(function () {
            return $(this).find('.line-uuid').val() === String(uuid);
        }).first();

        $('#searchResult').hide();

        if (!$row.length) {
            Swal.fire({ icon: 'info', text: 'Item tidak ada di daftar (mungkin sudah dihapus dan belum disimpan).', timer: 2200, showConfirmButton: false });
            return;
        }
        scrollToRow($row);
        highlightRow($row);
    });

    // Klik di luar area search -> tutup dropdown
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#wrapSearchLine').length) {
            $('#searchResult').hide();
        }
    });

    $('#modalTracking').on('shown.bs.modal', function () {
        if ($('#wrapSearchLine').is(':visible')) {
            $('#searchLine').trigger('focus');
        } else {
            $('#name_parent_category').trigger('focus');
        }
    });

    $('#modalTracking').on('hidden.bs.modal', resetSearch);

    // ====================== DATATABLE ======================
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
            render: (data) => {
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
                    $('#id_tracking').val(data.id);
                    $('#name_parent_category').val(data.name_parent_category);

                    let lines = data.lines_category || [];
                    if (typeof lines === 'string') {
                        lines = JSON.parse(lines);
                    }

                    renderLinesTable(lines);
                    resetSearch();
                    $('#wrapSearchLine').show();      // search tampil hanya saat Detail
                    $('#modalTitle').text('Detail & Edit Tracking Category');
                    $('#modalTracking').modal('show');
                }
            })
            .catch(err => {
                Swal.fire('Gagal!', err.message || 'Tidak dapat memuat data', 'error');
            });
    });

    // ====================== TAMBAH / HAPUS LINE ITEM ======================
    // Baris baru ditaruh paling atas, lalu langsung di-scroll & difokuskan
    $('#btnAddLine').on('click', function () {
        const $row = $(lineRowHtml({ id_parent: '', item_name_category: '', item_uuid_category: '' }));
        $('#lines-body').prepend($row);
        updateLineCount();
        scrollToRow($row);
        const input = $row.find('.line-name')[0];
        if (input) input.focus({ preventScroll: true });
    });

    $(document).on('click', '.delete-line', function () {
        $(this).closest('tr').remove();
        updateLineCount();
    });

    // ====================== SIMPAN ======================
    $('#btnSaveTracking').on('click', function () {
        const payload = {
            id: $('#id_tracking').val(),
            name_parent_category: $('#name_parent_category').val().trim(),
            lines_category: collectLines()
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
                cathError(err)
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
