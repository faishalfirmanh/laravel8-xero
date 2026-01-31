@extends('layouts.app')

@section('content')
<div class="container">
    <h3>List Transaksi Xero (AJAX)</h3>

    <form id="filter-form" class="mb-3">
        <div class="row">
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
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success">Filter</button>
                <button type="button" class="btn btn-primary create-btn">Create Invoice</button>
            </div>
        </div>
    </form>

    <table class="table table-bordered" id="xero-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Number</th>
                <th>Ref</th>
                <th>To</th>
                <th>Date</th>
                <th>Due Date</th>
                <th>Due</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <nav>
        <ul class="pagination" id="pagination"></ul>
    </nav>
</div>

<!-- MODAL -->
<div class="modal fade" id="invoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="invoice-form">
                    <input type="hidden" name="InvoiceID">
                    <div class="mb-2">
                        <label>Invoice Number</label>
                        <input type="text" name="InvoiceNumber" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Reference</label>
                        <input type="text" name="Reference" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Contact</label>
                        <input type="text" name="ContactName" class="form-control" readonly>
                    </div>
                    <div class="mb-2">
                        <label>Date</label>
                        <input type="date" name="Date" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Due Date</label>
                        <input type="date" name="DueDate" class="form-control">
                    </div>

                    <hr>
                    <h6>Items</h6>
                    <table class="table table-bordered" id="invoice-items">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Description</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Disc</th>
                                <th>Account</th>
                                <th>Tax Rate</th>
                                <th>Tax Amount</th>
                                <th>Amount IDR</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button type="submit" class="btn btn-success">Save Invoice</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    let currentPage = 1;

function formatDate(date) {
    if (!date) return '-';
    let d = null;
    if (typeof date === 'string' && date.includes('/Date(')) {
        const match = date.match(/\d+/);
        if (match) d = new Date(parseInt(match[0]));
    } else {
        d = new Date(date);
    }
    if (!d || isNaN(d.getTime())) return '-';
    const day = String(d.getDate()).padStart(2,'0');
    const monthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    const month = monthNames[d.getMonth()];
    const year = d.getFullYear();
    return `${day} ${month} ${year}`;
}

function loadXero(filters = {}){
    filters.page = currentPage;
    $.ajax({
        url: '/api/xero/list-transaksi',
        method: 'GET',
        data: filters,
        success: function(res){
            if(!res.data || !Array.isArray(res.data)){
                alert('Data Xero tidak valid');
                return;
            }
            let tbody = '';
            res.data.forEach((inv,index)=>{
                let contactName = inv.Contact?.Name ?? '-';
                let reference = inv.Reference ?? '-';
                let amountDue = inv.AmountDue ? Number(inv.AmountDue).toLocaleString('id-ID') : '0';
                let status = inv.Status ?? '-';
                tbody += `<tr>
                    <td>${(res.currentPage-1)*res.perPage + index + 1}</td>
                    <td>${inv.InvoiceNumber ?? '-'}</td>
                    <td>${reference}</td>
                    <td>${contactName}</td>
                    <td>${formatDate(inv.Date)}</td>
                    <td>${formatDate(inv.DueDate)}</td>
                    <td>${amountDue}</td>
                    <td>${status}</td>
                    <td>
                        <button class="btn btn-sm btn-info view-btn" data-id="${inv.InvoiceID}">View</button>
                        <button class="btn btn-sm btn-warning update-btn" data-id="${inv.InvoiceID}">Update</button>
                        <button class="btn btn-sm btn-danger void-btn" data-id="${inv.InvoiceID}">Void</button>
                    </td>
                </tr>`;
            });
            $('#xero-table tbody').html(tbody);
            let totalPages = Math.ceil(res.total/res.perPage);
            let html = '';
            for(let i=1;i<=totalPages;i++){
                html += `<li class="page-item ${i===res.currentPage?'active':''}"><a class="page-link" href="#">${i}</a></li>`;
            }
            $('#pagination').html(html);
        },
        error: function(xhr){
            console.error(xhr.responseText);
            alert('Gagal mengambil data Xero');
        }
    });
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
    loadXero($('#filter-form').serializeArray().reduce((o,i)=>{ if(i.value)o[i.name]=i.value; return o; }, {}));
});

// Void
$(document).on('click','.void-btn',function(){
    if(!confirm('Yakin mau void invoice ini?')) return;
    let id = $(this).data('id');
    $.ajax({
        url:`/api/xero/void/${id}`,
        method:'POST',
        headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'},
        success:function(){ alert('Invoice berhasil di-void'); loadXero(); },
        error:function(xhr){ console.error(xhr.responseText); alert('Gagal void invoice'); }
    });
});

// View
$(document).on('click','.view-btn',function(){
    let id = $(this).data('id');
    $.ajax({
        url:`/api/xero/view/${id}`,
        method:'GET',
        success:function(res){
            $('#invoiceModal').modal('show');
            $('#invoice-form [name=InvoiceID]').val(res.InvoiceID);
            $('#invoice-form [name=InvoiceNumber]').val(res.InvoiceNumber);
            $('#invoice-form [name=Reference]').val(res.Reference);
            $('#invoice-form [name=ContactName]').val(res.Contact?.Name ?? '');
            $('#invoice-form [name=Date]').val(res.Date?.slice(0,10));
            $('#invoice-form [name=DueDate]').val(res.DueDate?.slice(0,10));
            let itemsBody = '';
            (res.LineItems ?? []).forEach(item=>{
                itemsBody += `<tr>
                    <td>${item.ItemCode ?? ''}</td>
                    <td>${item.Description ?? ''}</td>
                    <td>${item.Quantity ?? ''}</td>
                    <td>${item.UnitAmount ?? ''}</td>
                    <td>${item.Discount ?? ''}</td>
                    <td>${item.AccountCode ?? ''}</td>
                    <td>${item.TaxType ?? ''}</td>
                    <td>${item.TaxAmount ?? ''}</td>
                    <td>${item.LineAmount ?? ''}</td>
                </tr>`;
            });
            $('#invoice-items tbody').html(itemsBody);
        }
    });
});

// Update / Create
$('.create-btn').click(function(){
    $('#invoiceModal').modal('show');
    $('#invoice-form')[0].reset();
    $('#invoice-form [name=InvoiceID]').val('');
});

$('#invoice-form').submit(function(e){
    e.preventDefault();
    let id = $('#invoice-form [name=InvoiceID]').val();
    let method = id ? 'PUT' : 'POST';
    let url = id ? `/api/xero/update/${id}` : '/api/xero/create';
    let data = $(this).serializeArray().reduce((obj,item)=>{ obj[item.name]=item.value; return obj; },{});
    $.ajax({
        url: url,
        method: 'POST',
        headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'},
        data: JSON.stringify(data),
        contentType: 'application/json',
        success:function(res){
            alert('Invoice berhasil disimpan');
            $('#invoiceModal').modal('hide');
            loadXero();
        },
        error:function(xhr){ console.error(xhr.responseText); alert('Gagal simpan invoice'); }
    });
});

});
</script>
@endsection
