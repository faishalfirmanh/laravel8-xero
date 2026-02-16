@extends('layouts.app')

@section('content')
<style>
.card-header { background-color: #fff; border-bottom: 2px solid #dee2e6; font-weight: bold; }
.table th { background-color: #e9ecef; color: #495057; }
.btn-void { color: #fff; background-color: #dc3545; border: none; padding: 4px 10px; border-radius: 4px; }
.btn-void:hover { background-color: #c82333; }
.table-responsive { margin-top: 1rem; }
</style>

<div class="container-fluid">
    <div class="card shadow mb-5">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 font-weight-bold text-primary">List Transaksi Xero</h5>
        </div>

        <div class="card-body">
            <form id="filter-form" class="mb-3">
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" name="contact" class="form-control" placeholder="Cari Contact">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="number" class="form-control" placeholder="Cari Invoice Number">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="AUTHORISED">AUTHORISED</option>
                            <option value="DRAFT">DRAFT</option>
                            <option value="SUBMITTED">SUBMITTED</option>
                            <option value="PAID">PAID</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-success w-100">Filter</button>
                    </div>
                </div>
            </form>

            <!-- Loader -->
            <div id="loadingIndicator" class="text-center my-4 d-none">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                <div class="mt-2 text-muted font-weight-bold">Memuat data...</div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped table-bordered w-100" id="xero-table">
                    <thead class="thead-dark">
                        <tr>
                            <th>No</th>
                            <th>Number</th>
                            <th>Ref</th>
                            <th>To</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th class="text-center" width="10%">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <nav>
                <ul class="pagination justify-content-center mt-3" id="pagination"></ul>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="formUpdateData" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="paymentModalLabel">
                    <i class="fa fa-calendar-alt mr-2"></i> Update Issue Date
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-4">

                <div class="alert alert-light border rounded mb-4 shadow-sm d-flex align-items-center">
                    <div class="mr-3">
                        <i class="fa fa-file-invoice fa-2x text-primary"></i>
                    </div>
                    <div>
                        <small class="text-muted">No Invoice: <span id="no_invoice_display" class="font-weight-bold text-primary">...</span></small>
                    </div>
                </div>

                <form id="formDateUpdate">
                    <div class="form-group">
                        <label for="new_issue_date" class="font-weight-bold text-secondary">
                            <i class="fa fa-edit mr-1"></i> Pilih Tanggal Baru
                        </label>
                        <input type="hidden" id="invoice_uuid" name="invoice_uuid"/>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-right-0"><i class="fa fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control border-left-0" id="issue_date" name="issue_date" required>
                        </div>
                        <small class="form-text text-muted">Pastikan tanggal sesuai dengan periode akuntansi.</small>
                    </div>
                </form>

            </div>

            <div class="modal-footer bg-light">
                <button type="button" id="close_modal_payment" class="btn btn-outline-secondary px-4" data-dismiss="modal">
                    Batal
                </button>
                <button type="submit" form="formDateUpdate" id="save_update" class="btn btn-primary px-4 shadow-sm">
                    <i class="fa fa-save mr-1"></i> Simpan Perubahan
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function(){

    let currentPage = 1;

    // FORMAT TANGGAL (KODE LAMA KAMU, TIDAK DIUBAH)
    function formatDate(date) {
        if (!date) return '-';
        let d = (typeof date === 'string' && date.includes('/Date('))
            ? new Date(parseInt(date.match(/\d+/)[0]))
            : new Date(date);
        if (isNaN(d.getTime())) return '-';
        const day = String(d.getDate()).padStart(2,'0');
        const monthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
        return `${day} ${monthNames[d.getMonth()]} ${d.getFullYear()}`;
    }

    function showLoading(){
        $('#loadingIndicator').removeClass('d-none');
        $('#xero-table tbody').html('');
    }

    function hideLoading(){
        $('#loadingIndicator').addClass('d-none');
    }

    async function loadXero(filters = {}) {
        filters.page = currentPage;
        showLoading();

        try {
            const response = await ajaxRequest(
                `{{ route('xero-list-invoice') }}`,
                'GET',
                filters
            );

            hideLoading();
            const res = response.data;

            if (!res.data || res.data.length === 0) {
                $('#xero-table tbody').html(`
                    <tr>
                        <td colspan="9" class="text-center text-muted font-weight-bold">Data tidak ada</td>
                    </tr>
                `);
                return;
            }

            let tbody = '';
            res.data.forEach((inv, index) => {
                //console.log('inv',inv)
                let button_update_issue = inv.Status == 'PAID' ?
                            `<button class="btn-success btn-sm btn-modal-update-inv"
                                data-id="${inv.InvoiceID}"
                                data-status="${inv.Status}"
                                data-invnumber="${inv.InvoiceNumber}">
                                Update Issue Date
                            </button>` : '';

                tbody += `
                    <tr>
                        <td>${(res.currentPage - 1) * res.perPage + index + 1}</td>
                        <td>${inv.InvoiceNumber ?? '-'}</td>
                        <td>${inv.Reference ?? '-'}</td>
                        <td>${inv.Contact?.Name ?? '-'}</td>
                        <td>${formatDate(inv.Date)}</td>
                        <td>${formatDate(inv.DueDate)}</td>
                        <td>${Number(inv.AmountDue ?? 0).toLocaleString('id-ID')}</td>
                        <td>
                        ${(() => {
                            switch(inv.Status){
                                case 'AUTHORISED': return '<span class="badge badge-primary">AUTHORISED</span>';
                                case 'DRAFT': return '<span class="badge badge-secondary">DRAFT</span>';
                                case 'SUBMITTED': return '<span class="badge badge-warning">SUBMITTED</span>';
                                case 'PAID': return '<span class="badge badge-success">PAID</span>';
                                default: return `<span class="badge badge-dark">${inv.Status}</span>`;
                            }
                        })()}
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-void void-btn"
                                data-id="${inv.InvoiceID}"
                                data-status="${inv.Status}">
                                Delete
                            </button>

                            ${button_update_issue}
                        </td>
                    </tr>
                `;
            });

            $('#xero-table tbody').html(tbody);

            let totalPages = Math.ceil(res.total / res.perPage);
            let html = '';
            let maxVisible = 5;

            // hitung range halaman
            let start = Math.max(1, res.currentPage - 2);
            let end   = Math.min(totalPages, start + maxVisible - 1);

            // tombol prev
            html += `
            <li class="page-item ${res.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${res.currentPage - 1}">&laquo;</a>
            </li>`;

            // angka halaman
            for(let i = start; i <= end; i++){
                html += `
                <li class="page-item ${i === res.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
                `;
            }

            // tombol next
            html += `
            <li class="page-item ${res.currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${res.currentPage + 1}">&raquo;</a>
            </li>`;

            $('#pagination').html(html);

        } catch (error) {
            hideLoading();
            Swal.fire({
                icon: 'error',
                title: 'Gagal ambil data',
                text: 'Terjadi kesalahan saat mengambil data Xero'
            });
        }
    }

    // LOAD AWAL
    loadXero();

    // FILTER
    $('#filter-form').submit(function(e){
        e.preventDefault();
        currentPage = 1;

        let filters = {};
        $(this).serializeArray().forEach(item => {
            if(item.value) filters[item.name] = item.value;
        });

        loadXero(filters);
    });

    // PAGINATION
$(document).on('click','.page-link',function(e){
    e.preventDefault();

    let page = $(this).data('page');
    if (!page || page < 1) return;

    currentPage = page;
    loadXero();
});

$(document).on('click','.btn-modal-update-inv',function(){
    let uuid_inv = $(this).data('id');
    let status = $(this).data('status');
    let inv_label = $(this).data('invnumber')
   // console.log('uuiv id',uuid_inv)
    $("#invoice_uuid").val(uuid_inv)
    $("#formUpdateData").modal('show')
    $("#no_invoice_display").text(inv_label)
})

$('#formDateUpdate').on('submit', function(e) {
    $("#formUpdateData").modal('hide')
     showLoading();
    e.preventDefault();
    let formData = new FormData(this);
    let data_json = Object.fromEntries(formData);
    //console.log('json',data_json)
    $.ajax({
        url: `{{ route('update-invoice-date') }}`,
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        data:data_json,
        success: function(res){
            hideLoading();
            console.log('resss',res)
            if(res.status == 'success'){
                 Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'berhasil update invoice issue date '
                });
                 loadXero();
            }

        },
        error: function(xhr){
            hideLoading();
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: xhr.responseJSON?.error ?? 'Gagal memproses invoice'
            });
             loadXero();
        }
    });
})
//  $("#tableHotel").on('click','.payment-hotel',function(){
//           let idnya = $(this).data('id');
//           $("#invoices_id_parent").val(idnya)
//            $('#formSubmitPayment')[0].reset();
//             loadListPayment(idnya)
//         $('#formUpdateData').modal('show');
//     })
    // DELETE / VOID (TIDAK DIUBAH)
$(document).on('click','.void-btn',function(){
    let uuid = $(this).data('id');
    let status = $(this).data('status');

    console.log('Invoice UUID:', uuid);
    console.log('Invoice Status:', status);

    Swal.fire({
        title: 'Yakin?',
        text: 'Invoice akan diproses (delete / void)',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, lanjut!'
    }).then((result) => {
        if (result.isConfirmed) {

            // 🔥 ALERT PROSES
            Swal.fire({
                title: 'Memproses...',
                text: 'Sedang menghapus / void invoice',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: `/api/xero-integrasi/delete-invoice-byuuid/${uuid}`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(res){
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: res.message ?? 'Invoice berhasil diproses'
                    });

                    loadXero(); // reload data
                },
                error: function(xhr){
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: xhr.responseJSON?.error ?? 'Gagal memproses invoice'
                    });
                }
            });
        }
    });
});
});
</script>
@endsection
