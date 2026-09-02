@extends('layouts.app')

@section('content')

<div class="card">
    <div class="card-header">
        Konfigurasi Mata Uang
    </div>
    <div class="card-body">
        <table class="table table-bordered" id="table_currency">
            <thead>
                <tr>
                    <th style="width: 50px">No</th>
                    <th>Kode Mata Uang</th>
                    <th>Satu Rupiah</th>
                    <th>Nominal Currency</th>
                    <th style="width: 120px">Status</th>
                    <th style="width: 160px">Aksi</th>
                </tr>
            </thead>
            <tbody id="table_currency_body">
                <tr><td colspan="6" class="text-center">Memuat data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {

    function renderRow(item, index) {
        let statusBadge = item.is_active == 1
            ? '<span class="badge badge-success">Aktif</span>'
            : '<span class="badge badge-secondary">Non Aktif</span>';

        return `
        <tr data-id="${item.id}">
            <td class="text-center">${index + 1}</td>
            <td>${item.code_curr ?? '-'}</td>
            <td class="col-satu-rupiah" data-value="${item.satu_rupiah ?? 0}">${item.satu_rupiah ?? 0}</td>
            <td class="col-nominal" data-value="${item.nominal_currency ?? 0}">${Number(item.nominal_currency ?? 0)}</td>
            <td class="col-status" data-value="${item.is_active ?? 0}">${statusBadge}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-warning btn-edit">Edit</button>
                <button type="button" class="btn btn-sm btn-success btn-save d-none">Save</button>
                <button type="button" class="btn btn-sm btn-secondary btn-cancel d-none">Cancel</button>
            </td>
        </tr>`;
    }

    function getData() {
        ajaxRequest(`{{ route('get_config_currency_all') }}`, 'GET', { page: 1, limit: 50 }, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200) {
                    // sesuaikan baris ini jika struktur pagination repo Anda berbeda
                    let list = response.data.data.data ?? response.data.data ?? [];

                    if (!list.length) {
                        $('#table_currency_body').html('<tr><td colspan="6" class="text-center">Belum ada data</td></tr>');
                        return;
                    }

                    let rows = '';
                    list.forEach((item, index) => rows += renderRow(item, index));
                    $('#table_currency_body').html(rows);
                }
            })
            .catch((err) => {
                console.log('err getData', err);
                $('#table_currency_body').html('<tr><td colspan="6" class="text-center text-danger">Gagal memuat data</td></tr>');
            });
    }

    getData();

    function toggleEditMode($tr, isEdit) {
        let satuRupiahVal = $tr.find('.col-satu-rupiah').attr('data-value');
        let nominalVal = $tr.find('.col-nominal').attr('data-value');
        let statusVal = $tr.find('.col-status').attr('data-value');

        if (isEdit) {
            //$tr.find('.col-satu-rupiah').html(`<input type="number" class="form-control form-control-sm inp-satu-rupiah" value="${satuRupiahVal}">`);
            $tr.find('.col-nominal').html(`<input type="number" step="any" class="form-control form-control-sm inp-nominal" value="${nominalVal}">`);
            $tr.find('.col-status').html(`
                <select class="form-control form-control-sm inp-status">
                    <option value="1" ${statusVal == 1 ? 'selected' : ''}>Aktif</option>
                    <option value="0" ${statusVal == 0 ? 'selected' : ''}>Non Aktif</option>
                </select>`);

            $tr.find('.btn-edit').addClass('d-none');
            $tr.find('.btn-save, .btn-cancel').removeClass('d-none');
        } else {
            $tr.find('.btn-edit').removeClass('d-none');
            $tr.find('.btn-save, .btn-cancel').addClass('d-none');
        }
    }

    function restoreRow($tr) {
        let satuRupiahVal = $tr.find('.col-satu-rupiah').attr('data-value');
        let nominalVal = $tr.find('.col-nominal').attr('data-value');
        let statusVal = $tr.find('.col-status').attr('data-value');
        let statusBadge = statusVal == 1
            ? '<span class="badge badge-success">Aktif</span>'
            : '<span class="badge badge-secondary">Non Aktif</span>';

        $tr.find('.col-satu-rupiah').text(satuRupiahVal);
        $tr.find('.col-nominal').text(Number(nominalVal));
        $tr.find('.col-status').html(statusBadge);
    }

    // klik Edit
    $(document).on('click', '.btn-edit', function() {
        toggleEditMode($(this).closest('tr'), true);
    });

    // klik Cancel
    $(document).on('click', '.btn-cancel', function() {
        let $tr = $(this).closest('tr');
        restoreRow($tr);
        toggleEditMode($tr, false);
    });

    // klik Save
    $(document).on('click', '.btn-save', function() {
        let $tr = $(this).closest('tr');
        let id = $tr.data('id');

        let satu_rupiah = $tr.find('.inp-satu-rupiah').val();
        let nominal_currency = $tr.find('.inp-nominal').val();
        let is_active = $tr.find('.inp-status').val();

        if (nominal_currency === '' || isNaN(nominal_currency)) {
            Swal.fire('Gagal!', 'Nominal Currency wajib diisi angka.', 'warning');
            return;
        }

        let payload = {
            id: id,
            nominal_currency: nominal_currency,
            satu_rupiah: satu_rupiah,
            is_active: is_active
        };

        ajaxRequest(`{{ route('saveConfigCurrency') }}`, 'POST', payload, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Simpan Berhasil!',
                        html: `
                            <div style="text-align: left; font-size: 14px;">
                                <p class="mb-1">Berhasil simpan mata uang</p>
                                <hr>
                            </div>
                        `,
                        confirmButtonText: 'Sukses'
                    });

                    $tr.find('.col-satu-rupiah').attr('data-value', satu_rupiah);
                    $tr.find('.col-nominal').attr('data-value', nominal_currency);
                    $tr.find('.col-status').attr('data-value', is_active);
                    restoreRow($tr);
                    toggleEditMode($tr, false);
                }
            })
            .catch((err) => {
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                console.log('error save currency', err);
            });
    });

});
</script>
@endpush