@extends('layouts.app')

@section('content')


<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Proses Pembayaran</h5>
    <button onclick="syncData()" type="button" style="color: white !important;font-weidth:bold;" class="btn btn-warning text-dark shadow-sm fw-bold">
        <i class="fas fa-sync-alt me-1"></i> Synchronization
    </button>
</div>
<div class="card mb-4">
    <div class="card-body">
        <form id="invoicePaymentForm">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Pilih Invoice</label>
                    <select class="form-control select2" multiple id="invoiceSelect" name="invoice_ids[]" multiple>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Pilih Paket</label>
                    <select class="form-control select2" id="paket_selected" name="paket_selected">
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

<div id="fullScreenLoader" class="position-fixed w-100 h-100 flex-column justify-content-center align-items-center"
     style="display: none; top: 0; left: 0; background-color: rgba(0,0,0,0.7); z-index: 9999;">

    <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
        <span class="sr-only">Loading...</span>
    </div>
    <h4 class="text-white mt-3 font-weight-bold">Sedang Sinkronisasi Data...</h4>
    <p class="text-white-50">Mohon tunggu sebentar</p>
</div>

@endsection

@push('scripts')
<script>
    let currentPage = 1;
    let isLoading = false;
    let invoiceArraySaved = $('#invoiceSelect').val() || [];
    let uuid_paket_after_change_invoice = [];
    function syncData(){
        const loader = document.getElementById('fullScreenLoader');
        loader.style.display = 'flex';
            ajaxRequest( `{{ route('sync-invoice-paid') }}`,'GET',{
            }, null)
            .then(response =>{
                console.log('sync',response)
                loader.style.display = 'none';
                const data = response.data ? response.data : response;
                if(response.status == 200){
                    Swal.fire({
                        icon: 'success',
                        title: 'Sinkronisasi Selesai!',
                        html: `
                            <div style="text-align: left; font-size: 14px;">
                                <p class="mb-1">✅ <strong>Invoice:</strong> ${data.pesan_invoice}</p>
                                <p class="mb-3">📦 <strong>Paket:</strong> ${data.pesan_paket}</p>
                                <hr>
                                <p class="mb-0 text-muted"><small>Xero API Limit:</small></p>
                                <ul class="mb-0 pl-3">
                                    <li>Sisa Limit Menit: <b>${data.request_min_tersisa_menit}</b></li>
                                    <li>Sisa Limit Hari: <b>${data.request_min_tersisa_hari}</b></li>
                                </ul>
                            </div>
                        `,
                        confirmButtonText: 'Mantap'
                    })
                }else {
                    Swal.fire('Gagal!', data.message || 'Terjadi kesalahan.', 'error');
                }
            })
            .catch((err)=>{
                loader.style.display = 'none';
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                console.log('error select2 invoice',err);
            })
        // console.log("Mulai Sinkronisasi...");
        // // 4. Set timer 3 detik
        // setTimeout(function() {
        //     // 5. Sembunyikan loader lagi (Tambah class d-none)
        //     loader.style.display = 'none';
        //     console.log("Selesai Sinkronisasi");
        //     // Opsional: Tampilkan notifikasi sukses (karena Anda pakai SweetAlert2)
            // Swal.fire({
            //     icon: 'success',
            //     title: 'Berhasil!',
            //     text: 'Data berhasil disinkronisasi.',
            //     timer: 1500,
            //     showConfirmButton: false
            // });

        // }, 3000);
    }


    $('#invoiceSelect').select2({
        placeholder: 'Pilih Invoice',
        width: '100%',
        allowClear:true,
        ajax: {
            url: `{{ route('list-invoice-select2') }}`, // URL Route Anda
            dataType: 'json',
            delay: 250, // Jeda 250ms saat mengetik sebelum request (biar server gak berat)
            data: function (params) {
                return {
                    keyword: params.term, // Kata yang diketik user
                    page: params.page || 1 // Halaman saat ini (otomatis dari Select2)
                };
            },
            processResults: function (data, params) {
                var apiData = data.results.map(function(item) {
                    return {
                        id: item.invoice_uuid,      // Value option
                        text: `${item.invoice_number}_${item.contact_name}_${convertStringDate(item.due_date)}` //+ ' (' + item.invoice_amount + ')' // Teks yang tampil
                    };
                });

                return {
                    results: apiData,
                    pagination: {
                        more: data.pagination.more
                    }
                };
            },
            cache: true
        }
    });


    $('#invoiceSelect').on('change', function() {
        // .select2('data') mengambil seluruh objek data
        let rawData = $(this).select2('data');
        invoiceArraySaved = rawData.map(item => item.id);
        $('#paket_selected').val(null).trigger('change');
        ajaxRequest( `{{ route('get-item-byinvoice') }}`,'GET',{
           invoice_ids : invoiceArraySaved
        }, null)
        .then(response =>{
           let res_success = response.data.data;
           uuid_paket_after_change_invoice = res_success.map(item =>item.uuid_item);
        //    res_success.forEach((x)=>{
        //         console.log(x.uuid_item)
        //         uuid_paket_after_change_invoice =
        //    })
        })
        .catch((err)=>{
            console.log('error select2 invoice',err);
        })
    });

    $('#paket_selected').select2({
        placeholder: 'Pilih Paket Haji / Umroh',
        width: '100%',
        allowClear:true,
        ajax: {
            url: `{{ route('get-paket-filterby-invoice') }}`, // URL Route Anda
            dataType: 'json',
            delay: 250, // Jeda 250ms saat mengetik sebelum request (biar server gak berat)
            data: function (params) {
                return {
                    keyword: params.term, // Kata yang diketik user
                    page: params.page || 1, // Halaman saat ini (otomatis dari Select2)
                    paket_uuid: uuid_paket_after_change_invoice
                };
            },
            processResults: function (data, params) {
            console.log('select2 haji',data)
                var apiDataPaket = data.data.results.map(function(item) {
                    return {
                        id: item.uuid_proudct_and_service,      // Value option
                        text: item.nama_paket //+ ' (' + item.invoice_amount + ')' // Teks yang tampil
                    };
                });

                return {
                    results: apiDataPaket,
                    pagination: {
                        more: data.data.pagination.more
                    }
                };
            },
            cache: true
        }
    });

    //  function renderPaketSelect(paket) {
    //     const $select = $('#paket_selected');
    //     $select.empty();

    //     paket.forEach(inv => {
    //         const option = new Option(
    //             inv.nama_paket,
    //             inv.uuid_proudct_and_service,
    //             false,
    //             false
    //         );
    //         $select.append(option);
    //     });
    //     $select.trigger('change');
    // }



    // loadInvoiceSelect2(currentPage);
    // function loadInvoiceSelect2(page){
    //      ajaxRequest( `{{ route('list-invoice-select2') }}`,'GET',{
    //            page:page, keyword: keyword.toUpperCase()
    //         }, null)
    //         .then(response =>{
    //             console.log('select3',response.data.data.data)
    //             //renderTable(response.data.data);
    //             renderInvoiceSelect(response.data.data.data);
    //         })
    //         .catch((err)=>{
    //             console.log('error select2 invoice',err);
    //         })
    // }


    // loadPaketSelect2(currentPage);
    // function loadPaketSelect2(page){
    //      ajaxRequest( `{{ route('list-paket-select2') }}`,'GET',{
    //            page:page
    //         }, null)
    //         .then(response =>{
    //             renderPaketSelect(response.data.data.data);
    //         })
    //         .catch((err)=>{
    //             console.log('error select2 invoice',err);
    //         })
    // }

    // === LOAD PERTAMA ===
    loadInvoices(currentPage);
    function loadInvoices(page) {
        if (isLoading) return;

        isLoading = true;
        showLoading(true);

        ajaxRequest( `{{ route('list-invoice-web') }}`,'GET',{
               page:page
            }, null)
            .then(response =>{
                renderTable(response.data.data);
                //renderInvoiceSelect(response.data.data);
                $('#btnPrev').toggle(page > 1);
                $('#btnNext').toggle(response.data.has_more);
                isLoading = false;
                showLoading(false);
            })
            .catch((err)=>{
                Swal.fire({
                    icon: 'error',
                    title: 'Oops',
                    text: 'gagal load list',
                })
                isLoading = false;
                showLoading(false);
            })


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




