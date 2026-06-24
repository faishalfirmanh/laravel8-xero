@extends('layouts.app')

@section('content')

<style>
    .modal-xxl {
        max-width: 95% !important;
        width: 95% !important;
    }
    
    #modalCreateHotel .modal-content {
        min-height: 85vh;
    }
    
    #itemTable th, #itemTable td {
        vertical-align: middle;
    }

    .select2-dropdown {
        min-width: 350px !important;   /* Lebar dropdown Account */
    }

    .dropdown-menu {
       z-index: 1060 !important;
    }

    /*untuk dropzone*/
    /* Styling agar thumbnail Dropzone bisa di-klik */

    #buktiDropzone {
        border: 2px dashed #1ab394;
        border-radius: 5px;
        background: #f4fdf9;
        min-height: 120px;
        padding: 10px;
        cursor: pointer;
    }

    #buktiDropzone .dz-message {
        color: #1ab394;
        font-weight: 600;
        font-size: 14px;
        margin: 2em 0;
    }

    #buktiDropzone .dz-preview .dz-image {
        cursor: pointer;
        transition: transform 0.2s;
    }
    #buktiDropzone .dz-preview .dz-image:hover {
        transform: scale(1.05); /* Sedikit membesar saat di-hover */
    }
</style>

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Hotel</h5>

        <div>
            <button type="button" id="btnProsesSelected" class="btn btn-success me-2">
                <i class="ti ti-check me-1"></i> Cek Data Terpilih
            </button>

             <button type="button" onclick="syncBillFromXero()"  id="button_sync_bill" class="btn btn-danger" data-toggle="modal" data-target="#modalSyncBill">
                <i class="ti ti-plus me-1"></i> Sync Bills
            </button>

            <button type="button" onclick="" id="button_add_hotel" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateHotel">
                <i class="ti ti-plus me-1"></i> Tambah Bills
            </button>
        </div>
         <div class="form-group mb-0">
            <select id="filter_status" class="form-select form-select-sm">
                <option value="0">DRAFT</option>
                <option value="1">AWAITING PAYMENT</option>
                <option value="2">PAID</option>
            </select>
        </div>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered mt-0" id="tableHotel">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>From</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Due Date</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Due</th>
                       
                    <th width="15%">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalCreateHotel" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" 
         style="max-width: 95% !important; width: 95% !important;" 
         role="document">
        <div class="modal-content">
            
            <div class="modal-header">
                <h5 class="modal-title">Tambah Invoice / Bill Baru</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="formCreateHotel" novalidate>
                @csrf
                <input type="hidden" name="idHotelInput" id="idHotelInput">

                <ul class="nav nav-tabs px-3 pt-3" id="myTab" role="tablist">
                    <li class="nav-item"><a class="nav-link active" id="header-tab" data-toggle="tab" href="#headerTab">Header</a></li>
                    <li class="nav-item"><a class="nav-link" id="detail-tab" data-toggle="tab" href="#detailTab">Detail Item</a></li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active p-3" id="headerTab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>From (Supplier / Contact)</label>
                                    <select class="form-control select2-contact" name="uuid_from" id="contact_id" style="width: 100%;" required></select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Date</label>
                                    <input type="date" class="form-control" id="date_req" name="date_req" value="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Due Date</label>
                                    <input type="date" id="due_date" class="form-control" name="due_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reference</label>
                                    <input type="text" id="ref_id" class="form-control" name="reference">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Currency</label>
                                    <select class="form-control" id="cur_id" name="currency" required>
                                        <option value="0">Pilih mata uang</option>
                                        <option value="IDR">IDR - Indonesian Rupiah</option>
                                        <option value="SAR">SAR - Saudi Riyal</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade p-3" id="detailTab">
                        <div class="table-responsive">
                            <input type="hidden" id="d_id_parent_bill" name="bills_parent_id"/>
                            <table class="table table-bordered table-hover" id="itemTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40" class="text-center">No</th>
                                        <th style="min-width: 150px;">Item</th>
                                        <th style="min-width: 280px;">Description</th>
                                        <th width="80" class="text-center">Qty</th>
                                        <th width="130" class="text-right">Unit Price</th>
                                        <th style="min-width: 220px;">Account</th>
                                        <th width="100" class="text-center">Tax Rate (%)</th>
                                        <th style="min-width: 180px;">Nama Paket</th>
                                        <th style="min-width: 160px;">Divisi</th>
                                        <th width="140" class="text-right">Amount (IDR)</th>
                                        <th width="50" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="lineItemsBody"></tbody>
                            </table>
                        </div>
                        <div class="table-actions">
                            <button type="button" class="btn-dashed" onclick="addNewRow()">
                                <i class="ti ti-plus"></i> Add a new line
                            </button>
                            <button type="button" class="btn-dashed" id="btn-show-dropzone" style="border-color: #007bff; color: #007bff; margin-left: 10px;">
                                <i class="ti ti-upload" style="font-size:12px;"></i> Upload Bukti
                            </button>
                        </div>

                        <div id="dropzone-container" style="display: none; margin-bottom: 20px;">
                            <div class="dropzone" id="buktiDropzone">
                                <div class="dz-message" data-dz-message><span>Klik atau Drop gambar bukti di sini (Bisa pilih banyak file)</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-end">
                    <input type="hidden" name="action_type" id="actionTypeValue" value="">

                    <button type="button" class="btn btn-secondary mr-2" data-dismiss="modal">Cancel</button>

                    <div class="btn-group">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Save
                        </button>
                        <div class="dropdown-menu dropdown-menu-right shadow" style="z-index: 1000">
                            <button type="submit" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="1">
                                <i class="ti ti-calendar mr-2" style="font-size: 1.2rem;"></i>
                                <span>Approve</span>
                            </button>
                            <button type="submit" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="0">
                                <i class="ti ti-bookmark mr-2" style="font-size: 1.2rem;"></i>
                                <span>Save draft</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-light border-top">
                    <h6 class="font-weight-bold mb-3 text-dark">Make a payment</h6>
                    <div class="row align-items-end mb-4" id="modal_pay">
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold text-muted mb-1">Amount Paid <span class="payment-currency" id="label_payment"></span></label>
                                <input type="number" step="0.01" class="form-control form-control-sm" name="nominal_spend" min="1" value="0">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold text-muted mb-1">Date Paid</label>
                                <input type="date" class="form-control form-control-sm" name="date_transaction" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold text-muted mb-1">Paid From</label>
                                <select class="form-control select2-payment-bank" id="payment_bank" name="uuid_bank" style="width: 100%;">
                                    <option value=""></option> 
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold text-muted mb-1">Reference</label>
                                <input type="text" class="form-control form-control-sm" name="reference_detail">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-success btn-sm w-100 font-weight-bold" id="btnRecordPayment">Record payment</button>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <h6 class="font-weight-bold text-muted mb-3">History & Notes</h6>
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-info btn-sm mr-2 font-weight-bold">History Payment </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white" id="payment_history_bill">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-muted">No</th>
                                        <th class="text-muted">Date</th>
                                        <th class="text-muted">Bank Name</th>
                                        <th class="text-muted">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody class="small text-muted">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- modal preview -->
<div class="modal fade" id="previewImageModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">
                    <span aria-hidden="true" style="font-size: 2.5rem;">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="previewImageModalSrc" src="" alt="Preview Gambar" style="max-width: 100%; max-height: 80vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// PENTING: Harus dieksekusi SEGERA di top-level (bukan di dalam $(document).ready)
// Dropzone.js listen ke event DOMContentLoaded untuk auto-attach ke semua
// elemen class="dropzone" di halaman. Kalau autoDiscover=false ditaruh di
// dalam $(document).ready, listener Dropzone (yang didaftarkan lebih dulu
// saat dropzone.min.js dimuat) akan keburu jalan duluan dan auto-attach ke
// #buktiDropzone TANPA url yang valid (karena cuma <div>, bukan <form>),
// menyebabkan "Uncaught Error: No URL provided." — lalu saat kode kita
// mencoba new Dropzone("#buktiDropzone", {...}) sendiri, elemen itu sudah
// terpasang Dropzone lain → "Uncaught Error: Dropzone already attached."
Dropzone.autoDiscover = false;

$(document).ready(function() {
    var table;

    // --- HELPER FUNCTIONS ---
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    // FIX 2: Added missing convertStringDate function to prevent ReferenceError
    function convertStringDate(dateString) {
        if (!dateString) return '-';
        let d = new Date(dateString);
        let options = { day: 'numeric', month: 'short', year: 'numeric' };
        return d.toLocaleDateString('en-GB', options);
    }

    function syncBillFromXero(){
          Swal.fire({
            title: 'Sinkronisasi Bill dari Xero?',
            html: 'Apakah Anda yakin ingin mengambil <strong>semua bill </strong> dari Xero?<br><br>Proses ini akan memperbarui data bill Anda.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',   // hijau
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Sync Sekarang',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {

                Swal.fire({
                    title: 'Sedang Sinkronisasi...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                ajaxRequest( `{{ route('sync-bill-xero') }}`,'GET',{ is_sync: 1 }, localStorage.getItem("token"))
                .then(response =>{
                    Swal.close();
                        if (response.status === 200) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: `Berhasil sinkronisasi ${response.total_saved} data COA`,
                                timer: 2000
                            });
                            // Refresh table
                            $('#tableHotel').DataTable().ajax.reload();
                        } else {
                            Swal.fire('Gagal', response.data.data.message, 'error');
                        }
                })
                .catch((err)=>{
                    cathError(err)
                })
            }
        })
    }

    // --- 1. DATATABLE CONFIG ---
    let columnBills = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        { data: 'name_contact_bill', name: 'name_contact_bill' },
        {
            data: 'status',
            name: 'status',
            render: function(data) {
                if(data == 0) return '<span class="badge badge-secondary">Draft</span>'; 
                if(data == 1) return '<span class="badge badge-success">Awaiting Payment</span>'; 
                if(data == 2) return '<span class="badge badge-info">Paid</span>'; 
                return '-';
            }
        },
        { data: 'reference', name: 'reference' },
        { data: 'date_req', name: 'date_req' },
        { data: 'due_date', name: 'due_date' },
        { 
            data: 'total', 
            name: 'total', 
            render: function(data,type,row){
                return formatCurrency(data)
            } 
        },
        { 
            data: 'nominal_paid', 
            name: 'nominal_paid', 
            render: function(data,type,row){
                return formatCurrency(data)
            } 
        },
        { 
            data: 'nominal_due', 
            name: 'nominal_due' ,
            render: function(data,type,row){
                return formatCurrency(data)
            }
        },
        {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data, type, row) {
                return `<a href="javascript:;" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-pencil"></i></a>`;
            },
        }
    ];

    // table = initGlobalDataTableTokenSelected(
    //     '#tableHotel',
    //     `{{ route('purchase-bills') }}`,
    //     columnBills,
    //     { "kolom_name": "uuid_from" },
    //     {
    //         rowCallback: function(row, data) {
    //             $(row).css('cursor', 'pointer'); 
    //             $(row).off('click').on('click', function() {
    //                 if ($(this).hasClass('selected')) {
    //                     $(this).removeClass('selected table-active');
    //                 } else {
    //                     table.$('tr.selected').removeClass('selected table-active');
    //                     $(this).addClass('selected table-active');
    //                 }
    //             });
    //         }
    //     }
    // );



    let myDropzone;
    let isClearingDropzone = false;

    {
        // 1. Inisialisasi Dropzone
        myDropzone = new Dropzone("#buktiDropzone", {
            url: "{{ route('uploadImage-bill') }}",
            autoProcessQueue: false, // PENTING: Jangan langsung upload saat gambar dipilih
            uploadMultiple: true,
            parallelUploads: 10,
            maxFiles: 10,
            acceptedFiles: "image/*",
            addRemoveLinks: true,
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem("token"),
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            init: function() {
                // Saat proses upload berjalan, kirimkan ID Invoice
                this.on("sending", function(file, xhr, formData) {
                    // Ambil ID dari hidden input (Bisa dari edit, atau ID baru setelah save form)
                    formData.append("bill_id", $('#idHotelInput').val()); 
                });

                // Jika semua file berhasil diupload
                this.on("successmultiple", function(files, response) {
                    Swal.fire('Sukses!', 'Data bill dan bukti berhasil disimpan.', 'success');
                    $('#modalCreateHotel').modal('hide');
                    table.ajax.reload(null, false);
                });

                // Jika terjadi error saat upload
                this.on("errormultiple", function(files, response) {
                    Swal.fire('Peringatan', 'bill tersimpan, namun gagal mengupload gambar.', 'warning');
                    table.ajax.reload(null, false);
                });

                //hapus
                this.on("removedfile", function(file) {
                    // Hanya eksekusi AJAX hapus jika file tersebut berasal dari server

                    if (isClearingDropzone) {
                        return; 
                    }

                    if (file.isFromServer) {
                        $.ajax({
                            url: "{{ route('remove-image-bill') }}",
                            type: "POST",
                            data: { file_name: file.name },
                            headers: {
                                'Authorization': 'Bearer ' + localStorage.getItem("token"),
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                if(response.success) {
                                    console.log("Berhasil:", response.message);
                                    // Optional: Tampilkan toast / notifikasi kecil bahwa gambar dihapus
                                }
                            },
                            error: function(err) {
                                console.error("Gagal menghapus gambar:", err);
                                Swal.fire('Gagal!', 'Gambar gagal dihapus dari server.', 'error');
                            }
                        });
                    }
                    // Jika file.isFromServer false/undefined, Dropzone hanya akan menghapus antrean di browser secara diam-diam.
                });


                this.on("addedfile", function(file) {
                file.previewElement.addEventListener("click", function(e) {
                    // Cegah klik agar tidak memicu dialog "Pilih File" Dropzone lagi jika diklik pas di gambar
                    e.stopPropagation(); 
                    e.preventDefault();
                    let imageUrl = file.url || file.dataURL;
                    if (!imageUrl && file.status === Dropzone.ADDED) {
                        imageUrl = URL.createObjectURL(file);
                    }
                    if (imageUrl) {
                        $('#previewImageModalSrc').attr('src', imageUrl);
                        $('#previewImageModal').modal('show');
                    }
                });
            });
            }
        });

        // 2. Toggle Tampilkan/Sembunyikan Area Dropzone
        $('#btn-show-dropzone').on('click', function() {
            $('#dropzone-container').slideToggle(200);
        });
    }


    function loadDropzoneImages(billid) {
        // Kosongkan Dropzone terlebih dahulu jika ada gambar dari sesi sebelumnya
        if(myDropzone) {
            isClearingDropzone = true;       
            myDropzone.removeAllFiles(true); 
            isClearingDropzone = false;
        }

        ajaxRequest("{{ route('get-image-bill') }}", 'GET', { bill_id: billid }, localStorage.getItem("token"))
            .then(response => {
            if (response.data.success && response.data.data.length > 0) {
                    $('#dropzone-container').show();
                    // Looping data gambar dari server
                    $.each(response.data.data, function(key, value) {
                        let mockFile = { 
                            name: value.name, 
                            size: value.size, 
                            accepted: true,
                            status: Dropzone.ADDED,
                            url: value.url,
                            isFromServer: true
                        };

                        // Emit event agar Dropzone membuatkan thumbnail di UI
                        myDropzone.emit("addedfile", mockFile);
                        myDropzone.emit("thumbnail", mockFile, value.url);
                        myDropzone.emit("complete", mockFile);

                        // Tambahkan file ke array internal Dropzone agar tidak bentrok
                        myDropzone.files.push(mockFile);
                    
                    });
                }
            })
            .catch((err) => {
                cathError(err)
                //Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
            })
    }


    $("#btnRecordPayment").on('click', function(e){
        e.preventDefault();
        
        let send_payment = {
            uuid_bank: $('#modal_pay select[name="uuid_bank"]').val(),
            nominal_spend: $('#modal_pay input[name="nominal_spend"]').val(),
            reference_detail: $('#modal_pay input[name="reference_detail"]').val(),
            date_transaction: $('#modal_pay input[name="date_transaction"]').val(),
            id_parent_bill: $('#idHotelInput').val()
        };

         ajaxRequest(`{{ route('save-pay-bill') }}`, 'POST', send_payment, localStorage.getItem("token"))
            .then(response => {
                if(response.status == 200){
                    Swal.fire('Sukses!', 'Data berhasil disimpan.', 'success');
                    $('#modalCreateHotel').modal('hide');
                    table.ajax.reload(null, false);
                }
            })
            .catch((err) => {
                cathError(err)
                // console.log('err',err)
                // Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
            });
    });

    $('#btnProsesSelected').on('click', function() {
        let selectedRowData = table.row('.selected').data();
        if (!selectedRowData) {
            Swal.fire('Oops!', 'Pilih data terlebih dahulu dengan mengklik salah satu baris di tabel!', 'warning');
            return;
        }
        Swal.fire('Berhasil', 'Anda memilih Reference: ' + selectedRowData.reference, 'success');
    });

    // --- 2. INITIALIZE GLOBAL SELECT2 ---
    function initAllSelect2() {
       $('.select2-contact').select2({
            placeholder: "Cari nama contact...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('list-contact-select2') }}",  
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { page: params.page || 1, keyword: params.term || '', limit: 10 };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data.data, function(item) {
                            return { id: item.id, text: item.full_name, phone: item.phone_number || '-' };
                        }),
                        pagination: { more: response.data.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${item.phone})</small></span>`);
            }
        });
    }
    initAllSelect2();

    function initAllSelectBank() {
       $('.select2-payment-bank').select2({
            placeholder: "Cari nama bank...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('getbankselect2') }}",  
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { page: params.page || 1, keyword: params.term || '', limit: 10, kolom_name: 'name' };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data.data, function(item) {
                            return { id: item.id, text: item.name, currency_code: item.currency_code || '-' };
                        }),
                        pagination: { more: response.data.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${item.currency_code})</small></span>`);
            }
        });
    }
    initAllSelectBank();

    // --- 3. MODAL & BUTTON EVENTS ---
    $("#button_add_hotel").on("click", function(){
        $("#modal_pay").addClass('d-none');
        $('#idHotelInput').val(0);
        $('#cur_id').val(0); 
        $("#ref_id").val('');
        $("#d_id_parent_bill").val(0);
        $('#formCreateHotel')[0].reset();
        
        $('.modal-title').text('Tambah Invoice / Bill Baru');
    });

    $('#modalCreateHotel').on('show.bs.modal', function () {
        $('#header-tab').tab('show');
        
        if ($('#idHotelInput').val() == 0) {
            $('#contact_id').empty().trigger('change'); 
            $('#itemTable tbody').empty();
            addNewRow(); 
        }

        $('#dropzone-container').hide();
    });

    // --- 4. EDIT FUNCTIONALITY ---
    $('#tableHotel').on('click', '.edit-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data(); 

        $('#idHotelInput').val(id);
        $('.modal-title').text('Edit Bill ' + (rowData.reference || ''));

        $('#modal_pay input[name="nominal_spend"]').val(0);
        $('#modal_pay select[name="uuid_bank"]').val(0).trigger('change');
        $('#modal_pay input[name="reference_detail"]').val('');

        
        $('#modalCreateHotel').modal('show');

        $('#modalCreateHotel').one('shown.bs.modal', function () {
            loadBills(id);
            loadDropzoneImages(id);
        })
    });

    function loadBills(id){
        $("#idHotelInput").val(id);
        $('#itemTable tbody').empty(); 
        $('#contact_id').prop('disabled', true); 

        ajaxRequest(`{{ route('detail-bills') }}`, 'GET', {id : id}, localStorage.getItem("token"))
            .then(response =>{
                if(response.status == 200){
                    let data_res = response.data.data;
                    
                    let contactId = data_res.uuid_from;
                    let contactName = data_res.get_contact_from ? data_res.get_contact_from.full_name : 'Nama tidak ditemukan';
                    let newOption = new Option(contactName, contactId, true, true);

                    $("#label_payment").text(data_res.currency);
                    $('#contact_id').empty().append(newOption).trigger('change');
                    
                    $("#ref_id").val(data_res.reference || '');
                    $('#cur_id').val(data_res.currency).trigger('change');
                    $('#date_req').val(data_res.date_req).trigger('change');
                    $('#due_date').val(data_res.due_date).trigger('change');

                    let details = data_res.get_detail;
                    if (details && details.length > 0) {
                        details.forEach(function(item) { addNewRow(item); });
                    } else {
                        addNewRow(); 
                    }
                    console.log('pay-idnya : ',id, data_res.get_payment)

                    if(data_res.status == 1 || (data_res.get_payment && data_res.get_payment.length > 0)) {
                        $("#modal_pay").removeClass('d-none');
                        
                        // (Opsional) Jika status sudah Paid (2), sembunyikan form input pembayaran 
                        // agar user hanya bisa melihat histori tanpa bisa membayar lagi.
                        if(data_res.status == 2) {
                            $("#btnRecordPayment").closest('.row').hide(); 
                        } else {
                            $("#btnRecordPayment").closest('.row').show();
                        }
                    } else {
                        $("#modal_pay").addClass('d-none');
                    }

                    let tbody = $('#payment_history_bill tbody');
                    console.log('payment',data_res.get_payment)
                    tbody.empty();
                    if (data_res.get_payment.length > 0) {
                        console.log('ada pembayaran')
                        $.each(data_res.get_payment, function(index, payment) {
                             let row = `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${convertStringDate(payment.date_transaction)}</td>
                                    <td>${payment.name_bank}</td>
                                    <td>${formatCurrency(payment.nominal_spend)}</td> 
                                </tr>
                            `;
                             tbody.append(row);
                        });
                        
                    } else {
                        tbody.append(`
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">
                                    <em>No payment history found.</em>
                                </td>
                            </tr>
                        `);
                    }
                
                    calculateGrandTotal();
                }
            })
            .catch((err)=>{
                console.log('error',err);
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
            })
            .finally(() => {
                $('#contact_id').prop('disabled', false); 
            });
    }

    $('.action-submit').on('click', function() {
        let actionValue = $(this).val();
        $('#actionTypeValue').val(actionValue);
    });

    // --- 6. SAVE FUNCTIONALITY ---
    $('#formCreateHotel').on('submit', function(e) {
        e.preventDefault();
        
        $('.select2-item, .select2-account, .select2-paket, .select2-divisi').each(function() {
            if ($(this).data('select2')) { $(this).trigger('change'); }
        });

        let formData = $(this).serialize();
        let params = new URLSearchParams(formData);
        let idInput = params.get('idHotelInput');
        let id_bill = (idInput && idInput > 0) ? idInput : null;
        let action_selected = params.get('action_type');

        let selectedData = {
            item_code : $('select[name="item_code[]"]').map(function(){ return $(this).val(); }).get(),
            id: id_bill,
            uuid_from: params.get('uuid_from'),
            date_req: params.get('date_req'),
            due_date: params.get('due_date'),
            reference: params.get('reference'),
            currency: params.get('currency'),
            action_save : action_selected,
            account_id: $('select[name="account_id[]"]').map(function(){ return $(this).val(); }).get(),
            desc: $('input[name="description[]"]').map(function(){ return $(this).val(); }).get(),
            qty: $('input[name="qty[]"]').map(function(){ return $(this).val(); }).get(),
            unit_price: $('input[name="unit_price[]"]').map(function(){ return $(this).val(); }).get(),
            tax_rate: $('input[name="tax_rate[]"]').map(function(){ return $(this).val(); }).get(),
            paket_tracking_uuid: $('select[name="nama_paket[]"]').map(function(){ return $(this).val(); }).get(),
            divisi_travel_tracking_uuid: $('select[name="divisi[]"]').map(function(){ return $(this).val(); }).get(),
            id_detail:$('input[name="id_detail[]"]').map(function(){ return $(this).val(); }).get(),
        };

        ajaxRequest(`{{ route('save-p-bills') }}`, 'POST', selectedData, localStorage.getItem("token"))
            .then(response => {
                if(response.status == 200){
                    // Swal.fire('Sukses!', 'Data berhasil disimpan.', 'success');
                    // $('#modalCreateHotel').modal('hide');
                    // table.ajax.reload(null, false);
                        if (action_selected == "1" && myDropzone.getQueuedFiles().length > 0) {
                            let savedInvoiceId = id_bill ? id_bill : response.data.id; 
                            $('#idHotelInput').val(savedInvoiceId); 
                            $('.action-submit').prop('disabled', true);
                            myDropzone.processQueue(); 
                        } else {
                            // Jika Save Draft (0) ATAU tidak ada gambar yang dipilih, langsung tutup dan sukses
                            Swal.fire('Sukses!', 'Data berhasil disimpan.', 'success');
                            $('#modalCreateHotel').modal('hide');
                            table.ajax.reload(null, false);
                        }
                }
            })
            .catch((err) => {
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
            });
    });

    // --- 7. GLOBAL FUNCTIONS EXPOSED TO WINDOW ---
    window.switchToDetailTab = function() {
        $('#detail-tab').tab('show');
    };

    window.removeRow = function(btn) {
        $(btn).closest('tr').remove();
        calculateGrandTotal();
    };

    window.addNewRow = function(item = null) {
        let rowCount = $('#itemTable tbody tr').length + 1;

        let id_detail_row = item ? item.id : 0;
        let desc = item ? (item.desc || '') : '';
        let qty = item ? item.qty : 1;
        let price = item ? parseFloat(item.unit_price) : '';
        let taxRate = item ? (item.tax_rate !== null ? item.tax_rate : 0) : 0;
        let amount = item ? parseFloat(item.amount) : '';

        let newRow = `
            <tr>
                <td class="text-center">${rowCount}</td>
                <input type="hidden" name="id_detail[]" value="${id_detail_row}"/>
                <td>
                    <select class="select2-item form-control" required name="item_code[]" style="width:100%;">
                        <option value="">Pilih Item...</option>
                    </select>
                </td>
                <td><input type="text" class="form-control desc-input" required name="description[]" value="${desc}" placeholder="Deskripsi item"></td>
                <td><input type="number" class="form-control qty-input" required name="qty[]" min="1" value="${qty}"></td>
                <td><input type="number" class="form-control price-input" required name="unit_price[]" min="1" step="0.01" value="${price}"></td>
                <td>
                    <select class="select2-account form-control" required name="account_id[]" required style="width:100%;">
                        <option value="">Pilih Account...</option>
                    </select>
                </td>
                <td><input type="text" class="form-control" name="tax_rate[]" value="${taxRate}"></td>
                <td>
                    <select class="select2-paket form-control" name="nama_paket[]" style="width:100%;">
                        <option value="">Pilih Paket...</option>
                    </select>
                </td>
                <td>
                    <select class="select2-divisi form-control" name="divisi[]" style="width:100%;">
                        <option value="">Pilih Divisi...</option>
                    </select>
                </td>
                <td><input type="text" class="form-control text-right amount-row" name="amount[]" value="${amount}" readonly></td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button></td>
            </tr>`;

        $('#itemTable tbody').append(newRow);

        let $lastRow = $('#itemTable tbody tr:last');
        //select2-item

        $lastRow.find('.select2-item').select2({
            placeholder: "Pilih Item...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: '{{ route("list-paket-select2") }}',
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { page: params.page || 1, keyword: params.term || '', limit: 5 };
                },
                processResults: function(response) {
                    if (response.status != 'success') return { results: [], pagination: { more: false } };;
                    return {
                        results: $.map(response.data.results, function(item) {
                            console.log('code',item)
                            return { 
                                id        : item.code,
                                text      : item.nama_paket,
                                harga     : item.price_sales,
                                item_code : item.code,
                                paket_id  : item.id, 
                            };
                        }),
                    }
                },
                cache: true
                },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${formatCurrency(item.harga)})</small></span>`);
            }
        }).on('select2:select', function (e) {
            // Auto-fill desc dari field nama_paket response
            const d = e.params.data;
            const $row = $(this).closest('tr');
            $row.find('.desc-input').val(d.text || '');

            if (d.harga !== undefined && d.harga !== null) {
               $row.find('.price-input').val(parseFloat(d.harga).toFixed(2));
            }
            $row.find('.price-input').trigger('input');
        });

        $lastRow.find('.select2-account').select2({
            placeholder: "Pilih Account...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('get-all-coa-select2') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { keyword: params.term || '', page: params.page || 1, type :'EXPENSE' };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data?.data || [], function(item) {
                            return { id: item.id, text: item.name, account_type: item.account_type || '-' };
                        }),
                        pagination: { more: response.data?.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${item.account_type})</small></span>`);
            }
        });

        $lastRow.find('.select2-paket').select2({
            placeholder: "Pilih Paket...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function() { return { name_parent_category: 'nama paket' }; },
                processResults: function(response) {
                    if (!response.status || !response.data?.lines_category) return { results: [] };
                    return {
                        results: $.map(response.data.lines_category, function(item) {
                            return { id: item.item_uuid_category || item.id, text: item.item_name_category };
                        })
                    };
                },
                cache: true
            }
        });

        $lastRow.find('.select2-divisi').select2({
            placeholder: "Pilih Divisi...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function() { return { name_parent_category: 'divisi' }; },
                processResults: function(response) {
                    if (!response.status || !response.data?.lines_category) return { results: [] };
                    return {
                        results: $.map(response.data.lines_category, function(item) {
                            return { id: item.item_uuid_category || item.id, text: item.item_name_category };
                        })
                    };
                },
                cache: true
            }
        });

        if (item) {
            if (item.item_code) {
                let itemLabel = item.desc || item.item_code; // fallback ke code kalau desc kosong
                let itemOpt = new Option(itemLabel, item.item_code, true, true);
                $lastRow.find('.select2-item').append(itemOpt).trigger('change');
            }
            if (item.account_id_coa) {
                ajaxRequest(`{{ route('coaDetail') }}`, 'get', {id: item.account_id_coa}, localStorage.getItem("token"))
                .then(response => {
                    if(response.status == 200){
                        let coa_data = response.data.data;
                        let accName = coa_data.name || ('Akun ID: ' + coa_data.account_type);
                        let accOpt = new Option(accName, item.account_id_coa, true, true);
                        $lastRow.find('.select2-account').append(accOpt).trigger('change');
                    }
                })
                .catch((err) => {
                    console.error(err);
                });
            }

            if (item.paket_tracking_uuid) {
                 ajaxRequest(`{{ route('tracking-detail') }}`, 'get', {name_parent_category: 'nama paket',id:item.paket_tracking_uuid}, localStorage.getItem("token"))
                .then(response => {
                    if(response.status == 200){
                        let data_paket = response.data.data
                        let pktName = data_paket.item_name_category || ('Paket ID: ' + data_paket.item_uuid_category);
                        let pktOpt = new Option(pktName, item.paket_tracking_uuid, true, true);
                        $lastRow.find('.select2-paket').append(pktOpt).trigger('change');
                    }
                });
            }

            if (item.divisi_travel_tracking_uuid) {
                ajaxRequest(`{{ route('tracking-detail') }}`, 'get', {name_parent_category: 'divisi',id:item.divisi_travel_tracking_uuid}, localStorage.getItem("token"))
                .then(response => {
                    if(response.status == 200){
                        let data_divisi = response.data.data
                        let divName =  data_divisi.item_name_category || ('Divisi ID: ' + data_divisi.item_uuid_category);
                        let divOpt = new Option(divName, item.divisi_travel_tracking_uuid, true, true);
                        $lastRow.find('.select2-divisi').append(divOpt).trigger('change');
                    }
                });
            }
        }

        const $qty = $lastRow.find('input[name="qty[]"]');
        const $unitPrice = $lastRow.find('input[name="unit_price[]"]');
        const $amount = $lastRow.find('input[name="amount[]"]');

        function calculateAmount() {
            let rowQty = parseFloat($qty.val()) || 0;
            let rowPrice = parseFloat($unitPrice.val()) || 0;
            let total = rowQty * rowPrice;
            $amount.val(total.toFixed(2));
            calculateGrandTotal();
        }

        $qty.on('input keyup', calculateAmount);
        $unitPrice.on('input keyup', calculateAmount);

        if (!item) { calculateAmount(); }
    };

    window.calculateGrandTotal = function() {
        let total_grand = 0;
        $('#itemTable tbody tr').each(function() {
            let amountStr = $(this).find('input[name="amount[]"]').val();
            let amount_row = parseFloat(amountStr) || 0;
            total_grand += amount_row;
        });

        let $tfoot = $('#itemTable tfoot');
        if ($tfoot.length === 0) {
            $('#itemTable').append(`
                <tfoot>
                    <tr class="table-info">
                        <td colspan="9" class="text-right font-weight-bold">Total Amount</td>
                        <td class="text-right font-weight-bold" id="grandTotal" style="font-size: 1.1em;">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            `);
        }
        $('#grandTotal').text(formatCurrency(total_grand));
    };

    function loadTable(status_type) {
         table = initGlobalDataTableTokenSelected(
            '#tableHotel',
            `{{ route('purchase-bills') }}`,
            columnBills,
            { 'kolom_name': 'reference', 'status' : status_type } 
        );
     }

    let initialType = $('#filter_status').val(); 
    loadTable(initialType);

     $('#filter_status').on('change', function() {
       let selectedType = $(this).val();
       loadTable(selectedType);
    });

});
</script>
@endpush