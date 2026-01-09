@extends('layouts.app')

@section('content')


<div class="card mb-4">
    <div class="card-body">
        <form id="invoicePaymentForm">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Pilih Invoice</label>
                    <select class="form-control" id="invoiceSelect" name="invoice_ids[]" multiple>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        Proses Pembayaran
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Input Nominal Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="selectedInvoiceIds">

                <div class="mb-3">
                    <label class="form-label">Nominal</label>
                    <input type="number" class="form-control" id="paymentAmount" placeholder="Masukkan nominal">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="button" class="btn btn-success" id="confirmPayment">
                    Simpan
                </button>
            </div>
        </div>
    </div>
</div>


<div class="card shadow mb-5">
    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>



    <div class="table-responsive">
        <table class="table table-striped table-bordered mt-0" id="invoice_web_list">
            <thead class="table-dark">
                <tr>
                    <th style="">#</th>
                    <th style="">No Invoice</th>
                    <th style="">Name Jamaah</th>
                    <th style="">Name Paket</th>
                    <th style="">Date</th>
                    <th style="">Due Date</th>
                    <th style="">Nominal Paid</th>
                    <th style="">Total</th>
                    <th style="">Status</th>
                    <th style="" class="text-center" width="10%">
                        <div class="form-check d-flex flex-column align-items-center justify-content-center">
                            <input class="form-check-input" type="checkbox" id="checkAll">
                            <label class="form-check-label small mt-1" for="checkAll" style="cursor: pointer;">
                                All
                            </label>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody id="invoiceTableBody">
            </tbody>
        </table>
        <div class="d-flex justify-content-between mt-3">
            <button class="btn btn-secondary" id="btnPrev" style="display:none;">
                ← Previous
            </button>

            <button class="btn btn-primary" id="btnNext" style="display:none;">
                Next →
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let currentPage = 1;
    let isLoading = false;

    // === LOAD PERTAMA ===
    loadInvoices(currentPage);

    function loadInvoices(page) {
        if (isLoading) return;

        isLoading = true;
        showLoading(true);

        $.ajax({
            url: `{{ route('list-invoice-web') }}`,
            data: { page: page },
            method: 'GET',
            success: function(response) {

                renderTable(response.data);

                // Pagination
                $('#btnPrev').toggle(page > 1);
                $('#btnNext').toggle(response.has_more);

            },
            error: function(err){
                console.error(err);
                alert('Gagal memuat data');
            },
            complete: function () {
                isLoading = false;
                showLoading(false);
            }
        });
    }

    $('#btnNext').on('click', function () {
        if (isLoading) return;
        currentPage++;
        loadInvoices(currentPage);
    });

    $('#btnPrev').on('click', function () {
        if (currentPage > 1 && !isLoading) {
            currentPage--;
            loadInvoices(currentPage);
        }
    });

    function showLoading(show) {
        $('#loadingIndicator').toggle(show);
        $('#btnNext, #btnPrev').prop('disabled', show);
        $('#invoice_web_list').toggle(!show);
    }

    function renderTable(data) {
        let html = '';
        let no = ((currentPage - 1) * 10) + 1;

        data.forEach((item) => {
            html += `
                <tr>
                    <td>${no++}</td>
                    <td>${item.no_invoice}</td>
                    <td>${item.nama_jamaah}</td>
                    <td>${item.items.length ? item.items[0].paket_name : '-'}</td>
                    <td>${item.tanggal}</td>
                    <td>-</td>
                    <td>${formatRupiah(item.amount_paid)}</td>
                    <td>${formatRupiah(item.total)}</td>
                    <td>${item.status}</td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input">
                    </td>
                </tr>
            `;
        });

        $('#invoiceTableBody').html(html);
    }

    function formatRupiah(number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(number);
    }
</script>
@endpush




