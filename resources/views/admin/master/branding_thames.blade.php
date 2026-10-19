@extends('layouts.app')

@section('content')
<div class="card shadow mb-5">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 font-weight-bold text-primary">Branding Theme</h5>
        <button type="button" class="btn btn-primary shadow-sm" id="btnAddTheme">
            <i class="ti ti-plus mr-1"></i> Tambah Tema
        </button>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-hover table-bordered w-100" id="tableTheme">
            <thead class="thead-dark">
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th>Logo</th>
                    <th>Nama Tema</th>
                    <th>Perusahaan (Travel)</th>
                    <th>Warna</th>
                    <th class="text-center">Default</th>
                    <th width="12%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody id="themeBody"></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalTheme" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalThemeTitle">Tambah Tema</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>

            <form id="formTheme">
                @csrf
                <input type="hidden" id="theme_id" name="theme_id">

                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Nama Tema <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="name" required maxlength="150">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Perusahaan (Travel) <span class="text-danger">*</span></label>
                            <select class="form-control" name="travel_id" id="travel_id" required>
                                <option value="">-- Pilih Travel --</option>
                                @foreach ($travels as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="small font-weight-bold">Warna Utama <span class="text-danger">*</span></label>
                            <input type="color" class="form-control" name="primary_color" id="primary_color" value="#3d76f1" required>
                        </div>
                        <div class="form-group col-md-8">
                            <label class="small font-weight-bold">Logo (dari data travel)</label>
                            <div class="form-control-plaintext">
                                <img id="logoPreview" src="" alt="logo" style="max-height:60px; display:none;">
                                <span id="logoEmpty" class="text-muted small">Logo diambil dari travel yang dipilih</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold">No Rekening <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="bank_accounts" id="bank_accounts" rows="4" required
                                  placeholder="BCA 614-077-750-0 an. PT AN NAMIROH TRAVELINDO&#10;MANDIRI 142-001-628-348-2 an. PT AN NAMIROH TRAVELINDO"></textarea>
                        <small class="text-muted">Satu rekening per baris.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Nama Penandatangan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="signature_name" id="signature_name" required maxlength="191">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Jabatan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="signature_title" id="signature_title" required maxlength="191">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold">Syarat & Ketentuan</label>
                        <textarea class="form-control" name="terms" id="terms" rows="4"></textarea>
                        <small class="text-muted">Satu syarat per baris. Kosongkan untuk memakai syarat default.</small>
                    </div>

                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="is_default" name="is_default" value="1">
                        <label class="custom-control-label" for="is_default">Jadikan tema default</label>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="btnSaveTheme">
                        <i class="ti ti-check mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const themeRoutes = {
    list      : "{{ route('branding-theme-list') }}",
    show      : "{{ route('branding-theme-show', ['id' => '__ID__']) }}",
    store     : "{{ route('branding-theme-store') }}",
    update    : "{{ route('branding-theme-update', ['id' => '__ID__']) }}",
    delete    : "{{ route('branding-theme-delete') }}",
    travelLogo: "{{ route('branding-theme-travel-logo', ['id' => '__ID__']) }}",
};

function themeUrl(template, id) {
    return template.replace('__ID__', id);
}

function themeAjaxHeaders() {
    return {
        'Authorization': 'Bearer ' + localStorage.getItem('token'),
        'X-CSRF-TOKEN' : $('meta[name="csrf-token"]').attr('content'),
    };
}

function esc(s) {
    return $('<div>').text(s == null ? '' : String(s)).html();
}

function resetThemeForm() {
    $('#formTheme')[0].reset();
    $('#theme_id').val('');
    $('#primary_color').val('#3d76f1');
    $('#travel_id').val('').trigger('change');
    setLogoPreview(null);
}


function loadThemes() {
    $.ajax({
        url: themeRoutes.list,
        method: 'GET',
        headers: themeAjaxHeaders(),
    }).done(function (res) {
        const rows = res.data || [];
        let html = '';

        if (rows.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted">Belum ada tema</td></tr>';
        }

        rows.forEach(function (t, i) {
            const travelName = t.travel ? t.travel.name : '-';
            html += `
                <tr>
                    <td class="text-center">${i + 1}</td>
                    <td>${t.logo_url ? `<img src="${t.logo_url}" style="max-height:36px;">` : '-'}</td>
                    <td>${t.name}</td>
                    <td>${travelName}</td>
                    <td><span style="display:inline-block;width:18px;height:18px;border-radius:3px;background:${t.primary_color};vertical-align:middle;"></span> ${t.primary_color}</td>
                    <td class="text-center">${t.is_default ? '<span class="badge badge-success">Default</span>' : ''}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-xs btn-warning btn-edit-theme" data-id="${t.id}" title="Edit">
                            <i class="ti ti-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-xs btn-danger btn-delete-theme" data-id="${t.id}" title="Hapus">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                </tr>`;
        });

        $('#themeBody').html(html);
    }).fail(function () {
        Swal.fire('Gagal', 'Gagal memuat daftar tema', 'error');
    });
}

function setLogoPreview(url) {
    if (url) {
        $('#logoPreview').attr('src', url).show();
        $('#logoEmpty').hide();
    } else {
        $('#logoPreview').attr('src', '').hide();
        $('#logoEmpty').show();
    }
}

function resetThemeForm() {
    $('#formTheme')[0].reset();
    $('#theme_id').val('');
    $('#primary_color').val('#3d76f1');
    setLogoPreview(null);
}

// Pilih travel -> tampilkan logo dari travel_names.location_path_image
$('#travel_id').on('change', function () {
    const id = $(this).val();
    if (!id) { setLogoPreview(null); return; }
    $.ajax({
        url: themeUrl(themeRoutes.travelLogo, id),
        method: 'GET',
        headers: themeAjaxHeaders(),
    }).done(function (res) {
        setLogoPreview(res.data ? res.data.logo_url : null);
    }).fail(function () {
        setLogoPreview(null);
    });
});

$('#btnAddTheme').on('click', function () {
    resetThemeForm();
    $('#modalThemeTitle').text('Tambah Tema');
    $('#modalTheme').modal('show');
});

$(document).on('click', '.btn-edit-theme', function () {
   const id = $(this).data('id');
    $.ajax({
        url: themeUrl(themeRoutes.show, id),
        method: 'GET',
        headers: themeAjaxHeaders(),
    }).done(function (res) {
        const t = res.data;
        resetThemeForm();
        $('#modalThemeTitle').text('Edit Tema');
        $('#theme_id').val(t.id);
        $('#name').val(t.name);
        $('#primary_color').val(t.primary_color);
        $('#bank_accounts').val(t.bank_accounts);
        $('#signature_name').val(t.signature_name);
        $('#signature_title').val(t.signature_title);
        $('#terms').val(t.terms);
        $('#is_default').prop('checked', !!t.is_default);
        $('#travel_id').val(t.travel_id).trigger('change');
        $('#modalTheme').modal('show');
    }).fail(function () {
        Swal.fire('Gagal', 'Gagal memuat data tema', 'error');
    });
});

$('#formTheme').on('submit', function (e) {
    e.preventDefault();

    const id   = $('#theme_id').val();
    const url  = id ? themeUrl(themeRoutes.update, id) : themeRoutes.store;
    const data = new FormData(this);
    data.set('is_default', $('#is_default').is(':checked') ? '1' : '0');

    const $btn = $('#btnSaveTheme').prop('disabled', true);

    $.ajax({
        url: url,
        method: 'POST',
        data: data,
        processData: false,
        contentType: false,
        headers: themeAjaxHeaders(),
    }).done(function (res) {
        $('#modalTheme').modal('hide');
        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message || 'Tema disimpan', timer: 1500, showConfirmButton: false });
        loadThemes();
    }).fail(function (xhr) {
        const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menyimpan tema';
        Swal.fire('Gagal', msg, 'error');
    }).always(function () {
        $btn.prop('disabled', false);
    });
});

$(document).on('click', '.btn-delete-theme', function () {
    const id = $(this).data('id');

    Swal.fire({
        title: 'Hapus tema ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: themeRoutes.delete,
            method: 'POST',
            data: { id: id },
            headers: themeAjaxHeaders(),
        }).done(function (res) {
            Swal.fire({ icon: 'success', title: 'Dihapus', text: res.message, timer: 1200, showConfirmButton: false });
            loadThemes();
        }).fail(function (xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menghapus tema';
            Swal.fire('Gagal', msg, 'error');
        });
    });
});

$(function () {
    loadThemes();
});
</script>
@endpush