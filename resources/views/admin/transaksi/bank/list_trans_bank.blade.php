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
</style>

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0" id="title_header">List Transaction</h5>

        <div>
            {{-- <button type="button" id="btnProsesSelected" class="btn btn-success me-2">
                <i class="ti ti-check me-1"></i> Cek Data Terpilih
            </button> --}}

            {{-- <button type="button" onclick="" id="button_add_hotel" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateHotel">
                <i class="ti ti-plus me-1"></i> add new transaction
            </button> --}}
            <div class="btn-group dropup">
                <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    New Transaction
                </button>
                <div class="dropdown-menu dropdown-menu-right shadow">
                    <button type="button" id="button_receive_money" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="1">
                        <i class="ti ti-arrow-up-from-arc mr-2" style="font-size: 1.2rem;"></i>
                        <span>Receive Money</span>
                    </button>
                    <button type="button" id="button_spend_money" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="0">
                        <i class="ti ti-arrow-down-from-arc mr-2" style="font-size: 1.2rem;"></i>
                        <span>Spend Money</span>
                    </button>
                </div>
            </div>
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
                    <th>Date</th>
                    <th>Name Contact</th>
                    <th>Payment Ref Transfer</th>
                    <th>Ref</th>
                    <th>Spend</th>
                    <th>Received</th>
                       
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
                <input type="hidden" name="is_spend" id="is_spend">

                <ul class="nav nav-tabs px-3 pt-3" id="myTab" role="tablist">
                    <li class="nav-item"><a class="nav-link active" id="header-tab" data-toggle="tab" href="#headerTab">Header</a></li>
                    <li class="nav-item"><a class="nav-link" id="detail-tab" data-toggle="tab" href="#detailTab">Detail Item</a></li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active p-3" id="headerTab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label id="title_to_from"></label>
                                    <select class="form-control select2-contact" name="uuid_to" id="contact_id" style="width: 100%;" required></select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Date</label>
                                    <input type="date" class="form-control" id="date_h" name="date_h" value="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Reference</label>
                                    <input type="text" id="ref_id" class="form-control" name="reference">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Ammounts Are</label>
                                <select class="form-control" id="ammounts_are" name="ammounts_are" required>
                                    <option value="7">-- selected value --</option>
                                    <option value="2">TAX EXCLUSIVE</option>
                                    <option value="1">TAX INCLUSIVE</option>
                                    <option value="0">NO TAX</option>
                                </select>
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
                                        <th style="min-width: 280px;">Item / Description</th>
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
                                <tbody></tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm mt-2" onclick="addNewRow()">
                            <i class="ti ti-plus"></i> Add a new line
                        </button>
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-end">
                    <input type="hidden" name="action_type" id="actionTypeValue" value="">

                    <button type="button" class="btn btn-secondary mr-2" data-dismiss="modal">Cancel</button>

                    <div class="btn-group dropup">
                        <button type="submit" class="btn btn-primary"  aria-haspopup="true" aria-expanded="false">
                            Save
                        </button>
                        {{-- <div class="dropdown-menu dropdown-menu-right shadow">
                            <button type="submit" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="1">
                                <i class="ti ti-calendar mr-2" style="font-size: 1.2rem;"></i>
                                <span>Approve</span>
                            </button>
                            <button type="submit" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="0">
                                <i class="ti ti-bookmark mr-2" style="font-size: 1.2rem;"></i>
                                <span>Save draft</span>
                            </button>
                        </div> --}}
                    </div>
                </div>

                <div class="p-4 bg-light border-top" id="modal_pay">
                    <h6 class="font-weight-bold mb-3 text-dark">Make a payment</h6>
                    <div class="row align-items-end mb-4">
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

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var table;
    let full_url = window.location.href;
    const segments = full_url.split('/').filter(Boolean);
    const lastSegment = segments.pop();
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

    // --- 1. DATATABLE CONFIG ---
    let columnBills = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        { 
            data: 'date_transaction', 
            name: 'date_transaction', 
            render: function(data,type,row){
               return convertStringDate(data)
            } 
        },
        { 
            data: null, 
            name: null, 
            render: function(data,type,row){
                if(data.get_pbill){
                    return data.get_pbill.name_contact_bill
                }else if(data.get_p_bank){
                   // console.log('aaa',data)
                    return data.get_p_bank.name_contact_trans_bank;
                }else if(data.get_inv){
                     return data.get_inv.contact_name;
                }else{
                    return  '-'
                }
            } 
        },
       { 
            data: 'reference_detail', 
            name: 'reference_detail', 
            render: function(data,type,row){
                console.log(data)
                return data
            } 
        },
        { 
            data: null, 
            name: null, 
            render: function(data, type, row){
                if(data.get_pbill != null){
                    // Menggunakan backtick dan ${} untuk menyisipkan variabel
                    return `<b style="color:#B05327">bill</b> | ${data.get_pbill.reference}`; 
                } else if(data.get_p_bank != null){
                    return `<b style="color:#8F1470">bank</b> | ${data.get_p_bank.reference}`;
                } else if(data.get_inv != null){
                    return `<b style="color:#627FF5">invoice</b> | ${data.get_inv.reference}`;
                }
                else {
                    return '-';
                }
            }
        },
        { 
            data: 'nominal_spend', 
            name: 'nominal_spend' ,
            render: function(data,type,row){
                return formatCurrency(data)
            }
        },
        { 
            data: 'nominal_receive', 
            name: 'nominal_receive' ,
            render: function(data,type,row){
                return formatCurrency(data)
            }
        },
        {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data,type,row) {
                let btn_edit = `<a href="javascript:;" style="margin-right:14px;" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-pencil"></i></a> &nbsp &nbsp`;
                let btn_detail =`<a href="javascript:;" data-id="${data}" class="text-primary view-tr mr-2"><i class="ti ti-eye"></i></a>`;
                let kondisi_btn_edit = row.get_p_bank ? btn_edit : '';
                return kondisi_btn_edit + btn_detail
            },
        }
    ];

    table = initGlobalDataTableTokenSelected(
        '#tableHotel',
        `{{ route('bank-trans-allByIdBank') }}`,
        columnBills,
        { "kolom_name": "reference_detail" ,'bank_id_xero' : lastSegment}
    );


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
                //Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
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
    // $("#button_add_hotel").on("click", function(){
    //     $("#modal_pay").addClass('d-none');
    //     $('#idHotelInput').val(0);
    //     $('#cur_id').val(0); 
    //     $("#ref_id").val('');
    //     $("#d_id_parent_bill").val(0);
    //     $('#formCreateHotel')[0].reset();
        
    //     $('.modal-title').text('Tambah Invoice / Bill Baru');
    // });


     $("#button_receive_money").on("click", function(){
        $("#modalCreateHotel").modal('show');
         $("#title_to_from").text('From')
         $("#is_spend").val(0)
        $("#modal_pay").addClass('d-none');
        $('#idHotelInput').val(0);
        $('#cur_id').val(0); 
        $("#ref_id").val('');
        $("#d_id_parent_bill").val(0);
        $('#formCreateHotel')[0].reset();
        
        $('.modal-title').text('Create New Receive Money');
    });


     $("#button_spend_money").on("click", function(){
        $("#modalCreateHotel").modal('show');
         $("#title_to_from").text('To')
        $("#is_spend").val(1)
        $("#modal_pay").addClass('d-none');
        $('#idHotelInput').val(0);
        $('#cur_id').val(0); 
        $("#ref_id").val('');
        $("#d_id_parent_bill").val(0);
        $('#formCreateHotel')[0].reset();
        
        $('.modal-title').text('Create New Spend Money');
    });

    $('#modalCreateHotel').on('show.bs.modal', function () {
        $('#header-tab').tab('show');
        
        if ($('#idHotelInput').val() == 0) {
            $('#contact_id').empty().trigger('change'); 
            $('#itemTable tbody').empty();
            addNewRow(); 
        }
    });

    // --- 4. EDIT FUNCTIONALITY ---
    $('#tableHotel').on('click', '.edit-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data(); 

        console.log('addd edit ',rowData)
        let kondisi_bank = rowData.is_spend ? 'Spend ' : ' Receive'
        $('#idHotelInput').val(id);
        $('.modal-title').text(`Edit Bank Trans ${kondisi_bank}` + (rowData.reference || ''));

        $('#modal_pay input[name="nominal_spend"]').val(0);
        $('#modal_pay select[name="uuid_bank"]').val(0).trigger('change');
        $('#modal_pay input[name="reference_detail"]').val('');

        loadBankTrans(rowData.get_p_bank.id);
        
        $('#modalCreateHotel').modal('show');
    });

    function loadBankTrans(id){
        $("#idHotelInput").val(id);
        $('#itemTable tbody').empty(); 
        $('#contact_id').prop('disabled', true); 

        ajaxRequest(`{{ route('detail-bank-trans') }}`, 'GET', {id : id}, localStorage.getItem("token"))
            .then(response =>{
                if(response.status == 200){
                    let data_res = response.data.data;
                   
                    $("#ammounts_are").val(data_res.amounts_are).trigger('change');
                    
                    let contactId = data_res.uuid_to;
                    let contactName = data_res.get_contact_from ? data_res.get_contact_from.full_name : 'Nama tidak ditemukan';
                    let newOption = new Option(contactName, contactId, true, true);

                    $("#label_payment").text(data_res.currency);
                    $('#contact_id').empty().append(newOption).trigger('change');
                    $("#is_spend").val(data_res.is_spend);
                    $("#ref_id").val(data_res.reference || '');
                    $('#cur_id').val(data_res.currency).trigger('change');
                    $('#date_h').val(data_res.date_h).trigger('change');
                  
                    let details = data_res.get_detail;
                    if (details && details.length > 0) {
                        details.forEach(function(item) { addNewRow(item); });
                    } else {
                        addNewRow(); 
                    }

                    if(data_res.status == 1){
                        $("#modal_pay").removeClass('d-none');
                    }else{
                        $("#modal_pay").addClass('d-none');
                    }

                    let tbody = $('#payment_history_bill tbody');
                    tbody.empty();
                    if (data_res.get_payment && data_res.get_payment.length > 0) {
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
                cathError(err)
                // console.log('error',err);
                // Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
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
        
        $('.select2-account, .select2-paket, .select2-divisi').each(function() {
            if ($(this).data('select2')) { $(this).trigger('change'); }
        });

        let formData = $(this).serialize();
        let params = new URLSearchParams(formData);
        let idInput = params.get('idHotelInput');
        let id_bill = (idInput && idInput > 0) ? idInput : null;
        let action_selected = params.get('action_type');

        let selectedData = {
            id: id_bill,
            uuid_to: params.get('uuid_to'),
            date_h: params.get('date_h'),
            is_spend:params.get('is_spend'),
            ammounts_are : params.get('ammounts_are'),
            bank_id_xero :lastSegment,
            reference: params.get('reference'),
            account_id: $('select[name="account_id[]"]').map(function(){ return $(this).val(); }).get(),
            desc: $('input[name="description[]"]').map(function(){ return $(this).val(); }).get(),
            qty: $('input[name="qty[]"]').map(function(){ return $(this).val(); }).get(),
            unit_price: $('input[name="unit_price[]"]').map(function(){ return $(this).val(); }).get(),
            // tax_rate: $('input[name="tax_rate[]"]').map(function(){ return $(this).val(); }).get(),
            paket_tracking_uuid: $('select[name="nama_paket[]"]').map(function(){ return $(this).val(); }).get(),
            divisi_travel_tracking_uuid: $('select[name="divisi[]"]').map(function(){ return $(this).val(); }).get(),
            id_detail:$('input[name="id_detail[]"]').map(function(){ return $(this).val(); }).get(),
        };

        ajaxRequest(`{{ route('save-p-bank-trans') }}`, 'POST', selectedData, localStorage.getItem("token"))
            .then(response => {
                if(response.status == 200){
                    Swal.fire('Sukses!', 'Data berhasil disimpan.', 'success');
                    $('#modalCreateHotel').modal('hide');
                    table.ajax.reload(null, false);
                }
            })
            .catch((err) => {
                cathError(err)
               // Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
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
                <td><input type="text" class="form-control" required name="description[]" value="${desc}" placeholder="Deskripsi item"></td>
                <td><input type="number" class="form-control" required name="qty[]" min="1" value="${qty}"></td>
                <td><input type="number" class="form-control" required name="unit_price[]" min="1" step="0.01" value="${price}"></td>
                <td>
                    <select class="select2-account form-control" required name="account_id[]" style="width:100%;">
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
                <td><input type="text" class="form-control text-right" name="amount[]" value="${amount}" readonly></td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button></td>
            </tr>`;

        $('#itemTable tbody').append(newRow);

        let $lastRow = $('#itemTable tbody tr:last');

        let cek_spend_for_coa = $("#is_spend").val() == 1 ? 'EXPENSE' : 'REVENUE';

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
                    return { keyword: params.term || '', page: params.page || 1 , type : cek_spend_for_coa };
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
                    cathError(err)
                    //console.error(err);
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
                        <td colspan="8" class="text-right font-weight-bold">Total Amount</td>
                        <td class="text-right font-weight-bold" id="grandTotal" style="font-size: 1.1em;">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            `);
        }
        $('#grandTotal').text(formatCurrency(total_grand));
    };

});
</script>
@endpush