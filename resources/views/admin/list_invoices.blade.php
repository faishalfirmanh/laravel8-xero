@extends('layouts.app')

@section('content')


<div class="d-flex justify-content-between align-items-center mb-3">

    <h5 class="mb-0">Proses Pembayaran</h5>
    <input type="hidden" id="temp_rate_sar_from_ajax" value="">
    <div class="d-flex">

        <div class="d-flex flex-column align-items-end mr-2">
            <button onclick="deleteDataLocalSync()" type="button" class="btn btn-danger shadow-sm fw-bold text-white">
                <i class="fas fa-trash-alt me-1"></i> Hapus Invoice
            </button>
            <span class="text-danger mt-1 small" style="font-size:10px;">
                (hapus semua invoice & paket local)
            </span>
        </div>

        <div class="d-flex flex-column align-items-end me-2 mr-2">
            <button onclick="syncData()" type="button" class="btn btn-warning text-dark shadow-sm fw-bold">
                <i class="fas fa-sync-alt me-1"></i> Synchronization
            </button>
            <span class="text-danger mt-1 small" style="font-size:10px;">
                (hanya invoice yang sudah paid)
            </span>
        </div>

        <div class="d-flex flex-column align-items-end mr-2">
            <button onclick="openModalCreateTrans()" type="button" class="btn btn-primary text-white shadow-sm fw-bold">
                <i class="fas fa-plus me-1"></i> Tambah Transaksi
            </button>
            <span class="text-primary mt-1 small" style="font-size:10px;">
                (buat transaksi baru)
            </span>
        </div>

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

    <table class="table table-striped table-bordered mt-0" id="pangeluaran_package_invoice_web_list">
        <thead class="table-dark">
            <tr>
                <th width="5%">No</th>
                <th>Nama Paket</th>
                <th>Uang Masuk</th>
                <th>Uang Keluar</th>
                <th>Keuntungan</th>
                <th>Action</th>
            </tr>
        </thead>
    </table>
</div>

<div id="fullScreenLoader" class="position-fixed w-100 h-100 flex-column justify-content-center align-items-center"
     style="display: none; top: 0; left: 0; background-color: rgba(0,0,0,0.7); z-index: 9999;">

    <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
        <span class="sr-only">Loading...</span>
    </div>
    <h4 class="text-white mt-3 font-weight-bold">Sedang Sinkronisasi Data...</h4>
    <p class="text-white-50">Mohon tunggu sebentar</p>
</div>

<div class="modal fade" id="modalCreateTrans" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Tambah Transaksi Baru</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <form id="invoicePaymentForm">
                    <input type="hidden" id="id_header_trans" name="id_header_trans"/>
                    <ul class="nav nav-tabs mb-3" id="transTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="step1-tab" data-toggle="tab" href="#step1" role="tab">
                                1. Header Invoice
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link disabled" id="step2-tab" data-toggle="tab" href="#step2" role="tab">
                                2. Detail Pengeluaran
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="transTabsContent">

                        <div class="tab-pane fade show active" id="step1" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="invoiceSelect">Pilih Invoice <span class="text-danger">*</span></label>
                                        <select class="form-control select2" id="invoiceSelect" name="invoice_ids[]" multiple style="width: 100%;">
                                            </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paket_selected">Pilih Paket <span class="text-danger">*</span></label>
                                        <select class="form-control select2" id="paket_selected" name="paket_selected" style="width: 100%;">
                                            </select>
                                    </div>
                                </div>
                            </div>

                            <div class="text-right mt-3">
                                <button type="button" class="btn btn-primary" onclick="goToStep2()">
                                    Simpan Header <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="step2" role="tabpanel">

                            <div class="row align-items-end">
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label for="m_pengeluaran_name">Pilih Jenis Pengeluaran</label>
                                        <select class="form-control select2" id="m_pengeluaran_name" name="m_pengeluaran_name" style="width: 100%;">
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="qty_input_html">Qty Input</label>
                                        <input type="number" class="form-control" name="qty_input_html" id="qty_input_html" value="1" min="1"/>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <button type="button" id="add_tag_html" class="btn btn-primary btn-block">
                                            <i class="ti ti-plus me-1"></i> Tambah
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div id="pengeluaran_container" style="max-height: 300px; overflow-y: auto;">
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-secondary btn-block" onclick="goToStep1()">
                                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="fas fa-save mr-1"></i> Simpan Detail
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="DetailPengeluaran" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content" id="printArea">

      <div class="modal-header">
        <h5 class="modal-title">Detail Pengeluaran</h5>
        <button type="button" class="close d-print-none" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body">

        <!-- HEADER -->
        <div class="text-center mb-3">
          <h5 class="font-weight-bold" id="name_paket_detail"></h5>
          <small> <span id="name_paket_detail"></span></small>
        </div>

        <hr>

        <!-- DETAIL TABLE -->
        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead class="thead-light">
              <tr>
                <th>Nama Pengeluaran</th>
                <th class="text-right">Nominal</th>
              </tr>
            </thead>
            <tbody id="detail_rows"></tbody>
          </table>
        </div>

        <div class="text-right">
          <h6>Nominal:</h6>
           <h5 class="font-weight-bold">Uang Masuk <span id="money_in"></span></h5>
          <h5 class="font-weight-bold">Uang Keluar <span id="money_out"></span></h5>
          <h5 class="font-weight-bold">Keuntungan <span id="money_profit"></span></h5>
        </div>

      </div>

      <div class="modal-footer d-print-none">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>

    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
    let currentPage = 1;
    var table_pengeluaran ;
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
    }

    function openModalCreateTrans(){
        $("#id_header_trans").val(0)
        $('#invoicePaymentForm')[0].reset();
        $('#invoiceSelect').val(null).trigger('change');
        $('#paket_selected').val(null).trigger('change');
        $('#m_pengeluaran_name').val(null).trigger('change');
        $('#pengeluaran_container').empty(); // Hapus item dinamis lama

        // Reset Tab ke Step 1 & Disable Step 2
        $('#step1-tab').tab('show');
        $('#step2-tab').addClass('disabled');

        // Tampilkan Modal
        $('#modalCreateTrans').modal('show');
    }


    function goToStep2() {
        let invoice = $('#invoiceSelect').val();
        let paket   = $('#paket_selected').val();
        let id_parent =  $("#id_header_trans").val();
        if (!invoice || invoice.length === 0 || !paket) {
            Swal.fire({
                icon: 'warning',
                title: 'Data Belum Lengkap',
                text: 'Harap pilih Invoice dan Paket terlebih dahulu!'
            });
            return;
        }
         ajaxRequest( `{{ route('t_pp_package_create') }}`,'POST',{
           invoice_ids : invoice,
           uuid_paket_item:paket,
           id: id_parent
        }, localStorage.getItem('token'))
        .then(response =>{
           let res_success = response.data;
           if(res_success.status){
                Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data disimpan' });
                $('#step2-tab').removeClass('disabled');
                $('#step2-tab').tab('show');
                $("#id_header_trans").val(res_success.data.id)
           }else{
             Swal.fire('Gagal!', 'Terjadi kesalahan saat simpan.', 'error');
           }
        })
        .catch((err)=>{
            console.log('error select2 invoice',err);
        })

    }

    function goToStep1() {
        $('#step1-tab').tab('show');
    }

    $("#invoicePaymentForm").on("submit",function(e){
        e.preventDefault();
        let formData = new FormData(this);
        let data_json = Object.fromEntries(formData);
        let items_dinamis = getFormData();
        console.log(items_dinamis);
         ajaxRequest( `{{ route('t_pp_package_createdetail') }}`,'POST',items_dinamis
         , localStorage.getItem('token'))
            .then((response) =>{
                let res_data = response.data;
                console.log("saved",res_data)
                if(res_data.status){
                     $('#modalCreateTrans').modal('hide');
                       Swal.fire({
                            title: "Add Transaksi sukses",
                            text: "Berhasil Nambah transaksi",
                            icon: "success"
                        });
                        table_pengeluaran.ajax.reload()
                }else{
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'gagal simpan data'
                    });
                }
            }).catch((err)=>{
                let errorMsg = 'Terjadi kesalahan pada sistem.';
                if(err.responseJSON && err.responseJSON.message) {
                    errorMsg = err.responseJSON.message;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: errorMsg
                });
            })
    })
 //
    let column_table = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        { data: 'name_paket', name: 'name_paket' },
         {
            data: 'nominal_sales',
            name: 'nominal_sales',
            render: function(data) {
                return formatRupiah(data);
            }
        },
        {
            data: 'nominal_purchase',
            name: 'nominal_purchase',
            render: function(data) {
                return formatRupiah(data);
            }
        },
         {
            data: 'nominal_profit',
            name: 'nominal_profit',
            render: function(data) {
                return formatRupiah(data);
            }
        },
        {
            data: "id", className: "text-center", orderable: false, searchable: false,
            render: function(data) {
                let btn_pencil = `<a href="javascript:;" style="margin-left:10px;margin-right:0px;" data-id="${data}" class="text-primary edit-trans-laba"><i class="ti ti-pencil"></i></a>`;
                let btn_detail = `<a href="javascript:;" style="margin-left:5px;" data-id="${data}" class="text-success view-trans-laba"><i class="ti ti-eye"></i></a>`;
                let btn_delete = `<a href="javascript:;" style="margin-left:5px;" data-id="${data}" class="text-danger delete-trans-laba"><i class="ti ti-trash"></i></a>`;
                return btn_detail + btn_pencil + btn_delete;
            }
        }
    ];
    table_pengeluaran = initGlobalDataTableToken(
        '#pangeluaran_package_invoice_web_list',
        `{{ route('t_pp_package_getall') }}`,
        column_table,
        { "kolom_name": "name_paket" }
    );
    function deleteDataLocalSync(){

        Swal.fire({
            title: 'Konfirmasi Hapus',
            text: "Apakah Anda yakin ingin melakukan hapus semua data invoice?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Sedang Memproses...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    ajaxRequest( `{{ route('delete-sync-invoice-paid') }}`,'GET',null, localStorage.getItem('token'))
                    .then(response =>{
                        console.log('response',response)
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: response.message || 'Data berhasil dihapus',
                            timer: 2000, // Opsional: tutup otomatis setelah 2 detik
                            showConfirmButton: true
                        }).then(() => {
                            // Opsional: Reload halaman atau update tabel setelah sukses
                            // window.location.reload();
                            // atau syncData();
                        });
                    })
                    .catch((err)=>{
                        let errorMsg = 'Terjadi kesalahan pada sistem.';
                        if(err.responseJSON && err.responseJSON.message) {
                            errorMsg = err.responseJSON.message;
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: errorMsg
                        });
                    })
                }
            });
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

   $("#pangeluaran_package_invoice_web_list").on('click', ".edit-trans-laba", function() {
    let id = $(this).data('id');
    $("#id_header_trans").val(id);

    // 1. RESET FORM & UI SEBELUM LOAD DATA BARU
    $('#invoicePaymentForm')[0].reset();
    $('#invoiceSelect').empty(); // Kosongkan opsi select2
    $('#paket_selected').empty(); // Kosongkan opsi select2
    $('#pengeluaran_container').empty(); // Hapus baris item dinamis
    $('#step1-tab').tab('show'); // Kembali ke Tab 1
    $('#step2-tab').removeClass('disabled'); // Aktifkan Tab 2 karena ini mode Edit

    // Tampilkan Loading (Optional)
    Swal.fire({
        title: 'Memuat Data...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    loadTransactionData(id).then(() => {
        $('#modalCreateTrans').modal('show');
    });

    // ajaxRequest(`{{ route('t_gbyid_pengeluaran') }}`, 'GET', { id: id }, localStorage.getItem('token'))
    //     .then(response => {
    //         Swal.close(); // Tutup loading
    //         let res_data = response.data.data;

    //         // --- A. POPULATE PAKET (Select2 AJAX) ---
    //         // Kita harus buat Option manual karena datanya belum ada di list AJAX Select2
    //         if (res_data.uuid_paket_item && res_data.name_paket) {
    //             let paketOption = new Option(res_data.name_paket, res_data.uuid_paket_item, true, true);
    //             $('#paket_selected').append(paketOption).trigger('change');

    //             // Update variable global untuk filter paket
    //             uuid_paket_after_change_invoice = [res_data.uuid_paket_item];
    //         }

    //         // --- B. POPULATE INVOICE MULTIPLE (Select2 AJAX) ---
    //         if (res_data.details_local_invoice && res_data.details_local_invoice.length > 0) {
    //             let invoiceIds = [];
    //             res_data.details_local_invoice.forEach((x) => {
    //                 let rawInv = x.get_invoice_all_xero;
    //                 if (rawInv) {
    //                     let id = rawInv.invoice_uuid;
    //                     // Format text sesuai tampilan Select2 Anda
    //                     let text = `${x.inv_number}_${rawInv.contact_name}_${convertStringDate(rawInv.due_date)}`;

    //                     let newOption = new Option(text, id, true, true);
    //                     $('#invoiceSelect').append(newOption);
    //                     invoiceIds.push(id);
    //                 }
    //             });
    //             // Trigger change agar Select2 update UI
    //             $('#invoiceSelect').trigger('change');

    //             // Update variable global invoice saved
    //             invoiceArraySaved = invoiceIds;
    //         }

    //         // --- C. POPULATE ITEM PENGELUARAN (Dynamic Rows) ---
    //         if (res_data.details && res_data.details.length > 0) {
    //             res_data.details.forEach((item, index) => {
    //                 let uniqueId = Date.now() + index; // Biar ID elemen unik

    //                 // Logic Data dari DB
    //                 let namaPengeluaran = item.nama_pengeluaran;
    //                 let idPengeluaran   = item.pengeluaran_id;
    //                 let isIdr           = item.is_idr == 1; // True jika IDR

    //                 // Jika IDR ambil nominal_idr, Jika SAR ambil nominal_sar
    //                 let nominalVal      = isIdr ? item.nominal_idr : item.nominal_sar;

    //                 // Rate (nominal_currency). Jika IDR biasanya 0, Jika SAR berisi Rate.
    //                 let rateVal         = parseFloat(item.nominal_currency);

    //                 // Logic Tampilan HTML
    //                 let checkedIdr = isIdr ? 'checked' : '';
    //                 let checkedSar = !isIdr ? 'checked' : '';
    //                 let classHiddenRate = isIdr ? 'd-none' : ''; // Sembunyikan rate jika IDR
    //                 let requiredRate    = !isIdr ? 'required' : '';
    //                 let idDetail = item.id ? item.id : '';
    //                 let htmlRow = `
    //                 <div class="row align-items-center mb-2 row-item-pengeluaran">
    //                     <input type="hidden" name="detail_id[]" value="${idDetail}">
    //                     <div class="col-md-6">
    //                         <div class="form-group mb-0">
    //                             <label class="small font-weight-bold text-muted mb-1">${namaPengeluaran}</label>
    //                             <input type="hidden" name="pengeluaran_id[]" value="${idPengeluaran}">
    //                             <div class="input-group input-group-sm">
    //                                 <div class="input-group-prepend">
    //                                     <span class="input-group-text">Rp/Sar</span>
    //                                 </div>
    //                                 <input type="number" class="form-control" name="nominal_pengeluaran_dinamis[]" value="${parseFloat(nominalVal)}" placeholder="0" required>
    //                             </div>
    //                         </div>
    //                     </div>

    //                     <div class="col-md-5">
    //                         <div class="form-group mb-0">
    //                             <div class="d-flex align-items-center mt-4">
    //                                 <label class="small font-weight-bold text-muted mr-2 mb-0">Mata Uang:</label>

    //                                 <div class="form-check form-check-inline mb-0">
    //                                     <input class="form-check-input check-currency" type="radio" name="currency_${uniqueId}" id="radio_idr_${uniqueId}" value="1" ${checkedIdr}>
    //                                     <label class="form-check-label small" for="radio_idr_${uniqueId}">IDR</label>
    //                                 </div>

    //                                 <div class="form-check form-check-inline mb-0">
    //                                     <input class="form-check-input check-currency" type="radio" name="currency_${uniqueId}" id="radio_sar_${uniqueId}" value="0" ${checkedSar}>
    //                                     <label class="form-check-label small" for="radio_sar_${uniqueId}">SAR</label>
    //                                 </div>
    //                             </div>

    //                             <div class="box-currency-nominal ${classHiddenRate} mt-1 animate__animated animate__fadeIn">
    //                                 <input type="number" class="form-control form-control-sm" value="${rateVal}" name="currency_nominal[]" placeholder="Masukkan Rate SAR" ${requiredRate}>
    //                             </div>
    //                         </div>
    //                     </div>

    //                     <div class="col-md-1">
    //                         <div class="form-group mb-0 mt-4">
    //                             <button type="button" data-id="${idDetail}" class="btn btn-danger btn-sm btn-block btn-remove-edit" title="Hapus">
    //                                 <i class="ti ti-trash"></i>
    //                             </button>
    //                         </div>
    //                     </div>
    //                 </div>`;

    //                 $('#pengeluaran_container').append(htmlRow);
    //             });
    //         }

    //         // Tampilkan Modal
    //         $('#modalCreateTrans').modal('show');
    //     })
    //     .catch((err) => {
    //         Swal.close();
    //         console.log('err', err);
    //         Swal.fire({
    //             icon: 'error',
    //             title: 'Oops',
    //             text: 'Gagal memuat detail transaksi',
    //         });
    //     });
    });

    $("#pangeluaran_package_invoice_web_list").on("click",".delete-trans-laba",function(){
        let id = $(this).data('id');
        let rowData = table_pengeluaran.row($(this).parents('tr')).data();

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: `Anda akan menghapus transaksi ${rowData.name_paket}. Data yang dihapus tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33', // Merah untuk bahaya
            cancelButtonColor: '#3085d6', // Biru untuk batal
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                 ajaxRequest( `{{ route('t_pp_package_delete') }}`,'POST',{
                    id : id
                    }, localStorage.getItem('token'))
                    .then(response =>{
                        if(response.data.status){
                            table_pengeluaran.ajax.reload()
                        }else{
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops',
                                text: 'gagal hapus ',
                            })
                        }
                        //console.log('res',response)
                    })
                    .catch((err)=>{
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops',
                            text: 'gagal hapus ',
                        })
                    })
            }
        })

    })


    $("#pangeluaran_package_invoice_web_list").on("click",'.view-trans-laba',function(){
         let id = $(this).data('id');

        ajaxRequest( `{{ route('t_gbyid_pengeluaran') }}`,'GET',{
           id : id
        }, localStorage.getItem('token'))
        .then(response =>{
            let res_data = response.data.data
            let rows = '';
            $("#name_paket_detail").text(res_data.name_paket);
            res_data.details.forEach(item => {
                rows += `
                    <tr>
                        <td>${item.nama_pengeluaran}</td>
                        <td class="text-center">${formatCurrency(item.nominal_idr)}</td>
                    </tr>
                `;
            });
            console.log('aa',res_data)
            $("#money_in").text(formatCurrency(res_data.nominal_sales))
            $("#money_out").text(formatCurrency(res_data.nominal_purchase))
            $("#money_profit").text(formatCurrency(res_data.nominal_profit))
            document.getElementById('detail_rows').innerHTML = rows;
           $("#DetailPengeluaran").modal('show')
        })
        .catch((err)=>{
            console.log('err',err)
            Swal.fire({
                icon: 'error',
                title: 'Oops',
                text: 'gagal load detail ',
            })
        })
    })

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



    // === LOAD PERTAMA ===
    //loadInvoices(currentPage);
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

    //ambil data input dinamis

    function getFormData() {
        // 1. Siapkan ARRAY KOSONG untuk setiap kolom
        let package_ids     = [];
        let pengeluaran_ids = [];
        let is_idrs         = [];
        let nom_currencies  = []; // Untuk 'nominal_currency' (Rate)
        let nom_idrs        = []; // Untuk 'nominal_idr'
        let nom_sars        = []; // Untuk 'nominal_sar'
        let detail_ids      = [];

        // 2. Loop setiap baris (Row)
        $('.row-item-pengeluaran').each(function() {
            let row = $(this);

            // Ambil Value dari Input
            let p_id        = row.find('input[name="pengeluaran_id[]"]').val();
            let p_nominal   = row.find('input[name="nominal_pengeluaran_dinamis[]"]').val(); // Input Utama
            let p_rate      = row.find('input[name="currency_nominal[]"]').val();            // Input Rate SAR
            let p_currency  = row.find('input[type="radio"]:checked').val();                 // 1=IDR, 0=SAR

            let p_detail_id = row.find('input[name="detail_id[]"]').val();
            // Parsing ke Angka & Boolean
            let val_nominal = parseFloat(p_nominal) || 0;
            let val_rate    = parseFloat(p_rate) || 0;
            let is_idr_bool = (p_currency == '1');

            // 3. PUSH ke Array masing-masing (Isi Kolom)
            package_ids.push($("#id_header_trans").val());
            pengeluaran_ids.push(p_id);
            is_idrs.push(is_idr_bool ? 1 : 0); // Kirim 1 atau 0
            detail_ids.push(p_detail_id ? p_detail_id : null);
            // Logic Rate (nominal_currency)
            nom_currencies.push(is_idr_bool ? 0 : val_rate);

            // Logic Split Nominal (Isi 0 jika tidak sesuai currency, agar index sinkron)
            if (is_idr_bool) {
                nom_idrs.push(val_nominal); // Isi IDR
                nom_sars.push(0);           // SAR Kosong
            } else {
                nom_idrs.push(0);           // IDR Kosong
                nom_sars.push(val_nominal);    // Isi SAR
            }
        });

        // 4. RETURN OBJECT berisi Array (Sesuai permintaan Laravel)
        return {
            package_expenses_id: package_ids,
            pengeluaran_id:      pengeluaran_ids,
            is_idr:              is_idrs,
            nominal_currency:    nom_currencies,
            nominal_idr:         nom_idrs,
            nominal_sar:         nom_sars,
            detail_id:           detail_ids
        };
    }

    // Function untuk me-load data ke modal (Dipakai saat Edit & Setelah Delete)
    function loadTransactionData(id) {
        // Tampilkan Loading
        Swal.fire({ title: 'Memuat Data...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        return ajaxRequest(`{{ route('t_gbyid_pengeluaran') }}`, 'GET', { id: id }, localStorage.getItem('token'))
            .then(response => {
                Swal.close();
                let res_data = response.data.data;

                // 1. Reset Container
                $('#pengeluaran_container').empty();
                $('#invoiceSelect').empty();
                $('#paket_selected').empty();

                // 2. Isi Paket (Select2 Manual)
                if (res_data.uuid_paket_item) {
                    let paketOption = new Option(res_data.name_paket, res_data.uuid_paket_item, true, true);
                    $('#paket_selected').append(paketOption).trigger('change');
                    uuid_paket_after_change_invoice = [res_data.uuid_paket_item];
                }

                // 3. Isi Invoice (Select2 Manual)
                if (res_data.details_local_invoice) {
                    res_data.details_local_invoice.forEach((x) => {
                        let rawInv = x.get_invoice_all_xero;
                        if (rawInv) {
                            let text = `${x.inv_number}_${rawInv.contact_name}_${convertStringDate(rawInv.due_date)}`;
                            let newOption = new Option(text, rawInv.invoice_uuid, true, true);
                            $('#invoiceSelect').append(newOption);
                        }
                    });
                    $('#invoiceSelect').trigger('change');
                }

                // 4. GENERATE ULANG LIST ITEM
                if (res_data.details) {
                    res_data.details.forEach((item, index) => {
                        let uniqueId = Date.now() + index;
                        let isIdr = item.is_idr == 1;
                        let nominalVal = isIdr ? item.nominal_idr : item.nominal_sar;
                        let rateVal = parseFloat(item.nominal_currency);
                        let checkedIdr = isIdr ? 'checked' : '';
                        let checkedSar = !isIdr ? 'checked' : '';
                        let classHiddenRate = isIdr ? 'd-none' : '';
                         let idDetail = item.id ? item.id : '';

                        let htmlRow = `
                        <div class="row align-items-center mb-2 row-item-pengeluaran">
                            <input type="hidden" name="detail_id[]" value="${idDetail}">
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold text-muted mb-1">${item.nama_pengeluaran}</label>
                                    <input type="hidden" name="pengeluaran_id[]" value="${item.pengeluaran_id}">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">Rp/Sar</span></div>
                                        <input type="number" class="form-control" name="nominal_pengeluaran_dinamis[]" value="${parseFloat(nominalVal)}" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group mb-0">
                                    <div class="d-flex align-items-center mt-4">
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input check-currency" type="radio" name="currency_${uniqueId}" value="1" ${checkedIdr}>
                                            <label class="form-check-label small">IDR</label>
                                        </div>
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input check-currency" type="radio" name="currency_${uniqueId}" value="0" ${checkedSar}>
                                            <label class="form-check-label small">SAR</label>
                                        </div>
                                    </div>
                                    <div class="box-currency-nominal ${classHiddenRate} mt-1">
                                        <input type="number" class="form-control form-control-sm" value="${rateVal}" name="currency_nominal[]" placeholder="Rate SAR">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group mb-0 mt-4">
                                    <button type="button" class="btn btn-danger btn-sm btn-block btn-remove-edit" data-id="${idDetail}" title="Hapus">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>`;
                        $('#pengeluaran_container').append(htmlRow);
                    });
                }
            });
    }

    $('#add_tag_html').click(function() {
        let pengeluaranId   = $('#m_pengeluaran_name').val();
        let pengeluaranText = $('#m_pengeluaran_name option:selected').text();
        let qty             = parseInt($('#qty_input_html').val()) || 1;
        let rateDariAjax    = Number($('#temp_rate_sar_from_ajax').val());
        if (!pengeluaranId) {
            Swal.fire('Warning', 'Harap pilih Jenis Pengeluaran terlebih dahulu!', 'warning');
            return;
        }

        for (let i = 0; i < qty; i++) {
            let uniqueId = Date.now() + i;

            let htmlRow = `
            <div class="row align-items-center mb-2 row-item-pengeluaran">

                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold text-muted mb-1">${pengeluaranText}</label>
                        <input type="hidden" name="pengeluaran_id[]" value="${pengeluaranId}">
                        <div class="input-group input-group-sm"> <div class="input-group-prepend">
                                <span class="input-group-text">Rp/Sar</span>
                            </div>
                            <input type="number" class="form-control" name="nominal_pengeluaran_dinamis[]" placeholder="0" required>
                        </div>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="form-group mb-0">
                        <div class="d-flex align-items-center mt-4"> <label class="small font-weight-bold text-muted mr-2 mb-0">Mata Uang:</label>

                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input check-currency" type="radio" name="currency_${uniqueId}" id="radio_idr_${uniqueId}" value="1" checked>
                                <label class="form-check-label small" for="radio_idr_${uniqueId}">IDR</label>
                            </div>

                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input check-currency" type="radio" name="currency_${uniqueId}" id="radio_sar_${uniqueId}" value="0">
                                <label class="form-check-label small" for="radio_sar_${uniqueId}">SAR</label>
                            </div>
                        </div>

                        <div class="box-currency-nominal d-none mt-1 animate__animated animate__fadeIn">
                            <input type="number" class="form-control form-control-sm" value="${rateDariAjax}" name="currency_nominal[]" placeholder="Masukkan Rate SAR">
                        </div>
                    </div>
                </div>

                <div class="col-md-1">
                    <div class="form-group mb-0 mt-4">
                        <button type="button" class="btn btn-danger btn-sm btn-block btn-remove" title="Hapus">
                            <i class="ti ti-trash"></i>
                        </button>
                    </div>
                </div>

            </div>`;

            $('#pengeluaran_container').append(htmlRow);
        }
        $('#qty_input_html').val(1);
    });

//temp_rate_sar_from_ajax
     ajaxRequest(`{{ route('getByIdCurrency') }}`, 'GET', { id : 1 }, localStorage.getItem('token'))
        .then(response => {
            $("#temp_rate_sar_from_ajax").val(response.data.data.nominal_rupiah_1_riyal);
        })
        .catch((err) => console.log('error currency', err));
    $(document).on('change', '.check-currency', function() {
        let currentRow = $(this).closest('.row-item-pengeluaran');
        let boxInput = currentRow.find('.box-currency-nominal');
        let inputField = currentRow.find('input[name="currency_nominal[]"]');

        // 3. Cek valuenya (0 = SAR, 1 = IDR)
        if ($(this).val() == '0') {
            // Jika SAR, TAMPILKAN input
            boxInput.removeClass('d-none');
            inputField.prop('required', true);

        } else {
            // Jika IDR, SEMBUNYIKAN input
            boxInput.addClass('d-none');
            inputField.val(''); // Kosongkan nilainya
            inputField.prop('required', false);
        }
    });
    // --- 2. LOGIC TOMBOL HAPUS (Event Delegation) ---
    // Menggunakan $(document).on agar elemen yang baru dibuat tetap bisa diklik
    $(document).on('click', '.btn-remove', function() {
        $(this).closest('.row-item-pengeluaran').remove();
    });

    $(document).on('click','.btn-remove-edit',function(){
          let id = $(this).data('id');
           Swal.fire({
            title: 'Apakah Anda yakin?',
            text: `Anda akan menghapus detai . Data yang dihapus tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33', // Merah untuk bahaya
            cancelButtonColor: '#3085d6', // Biru untuk batal
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                 ajaxRequest(`{{ route('t_pp_package_deleteddetail') }}`, 'POST', { id : id }, localStorage.getItem('token'))
                .then(response => {
                    let res_success = response.data.data;
                    if(response.data.status){
                          Swal.fire({
                            title: 'Berhasil!',
                            text: 'berhasil hapus data',
                            icon: 'success',
                            confirmButtonText: 'Mantap'
                        });
                        loadTransactionData(res_success.data_after_saved.id).then(() => {
                            Swal.fire('Terhapus!', 'Data berhasil dihapus dan direfresh.', 'success');
                        });
                    }else{
                        console.log('err')
                    }


                    console.log('re',response.data)

                })
                .catch((err) => console.log('error currency', err));
                }
        })

    })

    $('#m_pengeluaran_name').select2({
        placeholder: 'Pilih Pengeluaran',
        width: '100%',
        allowClear:true,
        ajax: {
            url: `{{ route('md_select2_name_pengeluaran') }}`,
            dataType: 'json',
            delay: 250,
            beforeSend: function(xhr) {
                xhr.setRequestHeader("Authorization", "Bearer " + localStorage.getItem('token'));
            },
            data: function (params) {
                return {
                    keyword: params.term, // Kata yang diketik user
                    page: params.page || 1 // Halaman saat ini (otomatis dari Select2)
                };
            },
            processResults: function (data, params) {
                var apiData = data.results.map(function(item) {
                    return {
                        id: item.id,      // Value option
                        text: `${item.nama_pengeluaran}` //+ ' (' + item.invoice_amount + ')' // Teks yang tampil
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
</script>
@endpush




