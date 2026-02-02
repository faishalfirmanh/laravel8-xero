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
                $('#xero-table tbody').html('');
                Swal.fire({
                    icon: 'info',
                    title: 'Data tidak tersedia',
                    text: 'Tidak ada data invoice yang ditemukan'
                });
                return;
            }

            let tbody = '';
            res.data.forEach((inv, index) => {
                tbody += `
                    <tr>
                        <td>${(res.currentPage - 1) * res.perPage + index + 1}</td>
                        <td>${inv.InvoiceNumber ?? '-'}</td>
                        <td>${inv.Reference ?? '-'}</td>
                        <td>${inv.Contact?.Name ?? '-'}</td>
                        <td>${formatDate(inv.Date)}</td>
                        <td>${formatDate(inv.DueDate)}</td>
                        <td>${Number(inv.AmountDue ?? 0).toLocaleString('id-ID')}</td>
                        <td>${inv.Status}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-void void-btn"
                                data-id="${inv.InvoiceID}"
                                data-status="${inv.Status}">
                                Delete
                            </button>
                        </td>
                    </tr>
                `;
            });

            $('#xero-table tbody').html(tbody);

            let totalPages = Math.ceil(res.total / res.perPage);
            let html = '';
            for(let i = 1; i <= totalPages; i++){
                html += `
                    <li class="page-item ${i === res.currentPage ? 'active' : ''}">
                        <a class="page-link" href="#">${i}</a>
                    </li>
                `;
            }
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
        currentPage = parseInt($(this).text());

        let filters = {};
        $('#filter-form').serializeArray().forEach(item => {
            if(item.value) filters[item.name] = item.value;
        });

        loadXero(filters);
    });

    // DELETE / VOID (TIDAK DIUBAH)
    $(document).on('click','.void-btn',function(){
        let id = $(this).data('id');
        let status = $(this).data('status');

        console.log('Invoice ID:', id);
        console.log('Invoice Status:', status);

        let url = (status === 'AUTHORISED' || status === 'PAID')
            ? `xero.void${id}`
            : `xero.delete${id}`;

        let method = (status === 'AUTHORISED' || status === 'PAID') ? 'POST' : 'DELETE';

        Swal.fire({
            title: 'Yakin?',
            text: 'Invoice akan dihapus!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Ya, hapus!'
        }).then((result) => {
            if(result.isConfirmed){
                Swal.showLoading();
                $.ajax({
                    url: url,
                    method: method,
                    headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'},
                    success:function(){
                        Swal.fire('Berhasil','Invoice berhasil dihapus','success');
                        loadXero();
                    },
                    error:function(){
                        Swal.fire('Gagal','Gagal hapus invoice','error');
                    }
                });
            }
        });
    });

});
</script>
@endsection
