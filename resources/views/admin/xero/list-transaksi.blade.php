@extends('layouts.app')

@section('content')

@push('styles')
<style>
.card-header { background-color: #fff; border-bottom: 2px solid #dee2e6; font-weight: bold; }
.table th { background-color: #e9ecef; color: #495057; }

/* Button Void / Delete */
.btn-void { 
    color: #fff; 
    background: linear-gradient(45deg, #e53935, #b71c1c); /* merah gradient biar hidup */
    border: none; 
    padding: 6px 12px; 
    border-radius: 6px; 
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-void:hover { 
    background: linear-gradient(45deg, #b71c1c, #f44336); /* hover lebih cerah */
    transform: scale(1.05);
    color: #fff;
}

.table-responsive { margin-top: 1rem; }
</style>
@endpush
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
@endsection

@push('scripts')
<script>
$(document).ready(function(){
    let currentPage = 1;

    function formatDate(date) {
        if (!date) return '-';
        let d = (typeof date === 'string' && date.includes('/Date('))
                ? new Date(parseInt(date.match(/\d+/)[0]))
                : new Date(date);
        if (!d || isNaN(d.getTime())) return '-';
        const day = String(d.getDate()).padStart(2,'0');
        const monthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
        const month = monthNames[d.getMonth()];
        return `${day} ${month} ${d.getFullYear()}`;
    }

    async function loadXero(filters = {}) {
        filters.page = currentPage;

        Swal.fire({
            title: 'Memuat...',
            text: 'Sedang mengambil data invoice...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        try {
            const res = await ajaxRequest('/api/xero/list-transaksi', 'GET', filters);
            Swal.close();

            if(!res.data || !Array.isArray(res.data.data)){
                Swal.fire('Error','Data Xero tidak valid','error');
                return;
            }

            const data = res.data.data;
            let tbody = '';
            data.forEach((inv, index)=>{
                let contactName = inv.Contact?.Name ?? '-';
                let reference = inv.Reference ?? '-';
                let amountDue = inv.AmountDue ? Number(inv.AmountDue).toLocaleString('id-ID') : '0';
                let status = inv.Status ?? '-';
                tbody += `<tr>
                    <td>${(filters.page-1)*res.data.perPage + index + 1}</td>
                    <td>${inv.InvoiceNumber ?? '-'}</td>
                    <td>${reference}</td>
                    <td>${contactName}</td>
                    <td>${formatDate(inv.Date)}</td>
                    <td>${formatDate(inv.DueDate)}</td>
                    <td>${amountDue}</td>
                    <td>${status}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-void void-btn" 
                                data-id="${inv.InvoiceID}" 
                                data-status="${status}">
                            Delete
                        </button>
                    </td>
                </tr>`;
            });
            $('#xero-table tbody').html(tbody);

            // pagination
            let totalPages = Math.ceil(res.data.total / res.data.perPage);
            let html = '';
            for(let i=1;i<=totalPages;i++){
                html += `<li class="page-item ${i===currentPage?'active':''}">
                            <a class="page-link" href="#">${i}</a>
                         </li>`;
            }
            $('#pagination').html(html);

        } catch(err){
            Swal.close();
            console.error(err);
            Swal.fire('Gagal','Terjadi kesalahan saat mengambil data','error');
        }
    }

    loadXero();

    $('#filter-form').submit(function(e){
        e.preventDefault();
        currentPage = 1;
        let filters = {};
        $(this).serializeArray().forEach(item => { if(item.value) filters[item.name]=item.value; });
        loadXero(filters);
    });

    $(document).on('click','.page-link',function(e){
        e.preventDefault();
        currentPage = parseInt($(this).text());
        let filters = {};
        $('#filter-form').serializeArray().forEach(item => { if(item.value) filters[item.name]=item.value; });
        loadXero(filters);
    });

    // Delete / Void sesuai status
    $(document).on('click','.void-btn', async function(){
        let id = $(this).data('id');
        let status = $(this).data('status');
        let url = (status === 'AUTHORISED') ? `/api/xero/void/${id}` : `/api/xero/delete/${id}`;
        let method = (status === 'AUTHORISED') ? 'POST' : 'DELETE';

        const result = await Swal.fire({
            title: 'Yakin?',
            text: "Invoice akan dihapus!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!'
        });

        if(result.isConfirmed){
            try {
                Swal.fire({
                    title: 'Sedang memproses...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                await ajaxRequest(url, method);
                Swal.close();
                Swal.fire('Berhasil','Invoice berhasil dihapus','success');
                let filters = {};
                $('#filter-form').serializeArray().forEach(item => { if(item.value) filters[item.name]=item.value; });
                loadXero(filters);

            } catch(err){
                Swal.close();
                console.error(err);
                Swal.fire('Gagal','Gagal hapus invoice','error');
            }
        }
    });
});
</script>
@endpush
