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

    /*fix item select2*/
    /* kolom fixed: nama item panjang tidak melebarkan kolom lain */
#itemTable { table-layout: fixed; width: 100%; }
#itemTable td { overflow: hidden; }
#itemTable .select2-container { width: 100% !important; }

/* selection box seragam untuk Item/Account/Paket/Divisi */
#itemTable .select2-selection--single {
    position: relative;
    display: flex;
    align-items: center;
    height: calc(1.5em + .75rem + 2px);
    border: 1px solid #ced4da;
    border-radius: .25rem;
}
#itemTable .select2-selection--single .select2-selection__rendered {
    display: block;
    flex: 1 1 auto;
    min-width: 0;
    line-height: 1.5;
    padding-left: .6rem;
    padding-right: 42px;          /* ruang untuk × dan ▾ */
    overflow: hidden;
    text-overflow: ellipsis;      /* teks panjang dipotong "...", tidak turun baris */
    white-space: nowrap;
}
#itemTable .select2-selection--single .select2-selection__clear {
    position: absolute;
    right: 22px;
    top: 50%;
    transform: translateY(-50%);
    float: none;
    margin: 0;
    line-height: 1;
}
#itemTable .select2-selection--single .select2-selection__arrow {
    position: absolute;
    right: 4px;
    top: 0;
    height: 100%;
}

/* opsi di dropdown boleh wrap supaya nama paket panjang terbaca penuh */
.select2-results__option { white-space: normal; word-break: break-word; }

/*modal transfer*/

/* ── MODAL TRANSFER ── */
#modalTransfer .modal-dialog { max-width: 760px; }
#modalTransfer .modal-content { border: none; border-radius: 10px; overflow: hidden; }

#modalTransfer .tf-header {
    background: linear-gradient(135deg, #1c3a5e, #2a5688);
    color: #fff; padding: 14px 22px;
    display: flex; align-items: center; justify-content: space-between;
}
#modalTransfer .tf-header h5 { margin: 0; font-size: 16px; font-weight: 600; }
#modalTransfer .tf-header small { color: #9bb4cc; font-size: 11px; display: block; margin-top: 2px; }
#modalTransfer .tf-header .close { color: #fff; opacity: .8; text-shadow: none; }
#modalTransfer .tf-header .close:hover { opacity: 1; }

#modalTransfer .modal-body { padding: 22px; background: #f7f9fb; }

/* dua kartu + panah di tengah */
#modalTransfer .tf-route {
    display: grid;
    grid-template-columns: 1fr 44px 1fr;
    gap: 10px; align-items: center;
    margin-bottom: 18px;
}
@media (max-width: 640px) {
    #modalTransfer .tf-route { grid-template-columns: 1fr; }
    #modalTransfer .tf-arrow { transform: rotate(90deg); margin: 0 auto; }
}
#modalTransfer .tf-card {
    background: #fff; border: 1px solid #e3e8ee; border-radius: 8px;
    padding: 14px 16px; border-top: 3px solid #ccc;
}
#modalTransfer .tf-card.from { border-top-color: #e74c3c; }
#modalTransfer .tf-card.to   { border-top-color: #1ab394; }
#modalTransfer .tf-card-title {
    display: flex; align-items: center; gap: 6px;
    font-size: 11px; font-weight: 700; letter-spacing: .5px;
    text-transform: uppercase; margin-bottom: 8px;
}
#modalTransfer .tf-card.from .tf-card-title { color: #e74c3c; }
#modalTransfer .tf-card.to   .tf-card-title { color: #1ab394; }

#modalTransfer .tf-arrow {
    width: 40px; height: 40px; border-radius: 50%;
    background: #fff; border: 1px solid #dde3ea; color: #1c3a5e;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 18px; transition: all .2s;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
}
#modalTransfer .tf-arrow:hover { background: #1c3a5e; color: #fff; transform: rotate(180deg); }

/* kartu detail */
#modalTransfer .tf-detail {
    background: #fff; border: 1px solid #e3e8ee; border-radius: 8px; padding: 16px;
}
#modalTransfer .tf-detail label,
#modalTransfer .tf-card label.tf-label {
    font-size: 11px; font-weight: 600; color: #555; margin-bottom: 4px;
}
#modalTransfer .tf-detail .form-control { height: 36px; font-size: 13px; border-color: #d5dbe3; }
#modalTransfer .tf-detail .form-control:focus { border-color: #1ab394; box-shadow: 0 0 0 .15rem rgba(26,179,148,.15); }
#modalTransfer .tf-amount-wrap { position: relative; }
#modalTransfer .tf-amount-wrap .tf-prefix {
    position: absolute; left: 0; top: 0; bottom: 0; width: 38px;
    display: flex; align-items: center; justify-content: center;
    background: #f0f3f6; border: 1px solid #d5dbe3; border-right: none;
    border-radius: 4px 0 0 4px; font-size: 12px; font-weight: 600; color: #555;
}
#modalTransfer .tf-amount-wrap .form-control {
    padding-left: 48px; font-size: 16px; font-weight: 600; text-align: right;
}

/* ringkasan */
#modalTransfer .tf-summary {
    margin-top: 14px; padding: 10px 14px; border-radius: 6px;
    background: #eef6ff; border: 1px dashed #b9d4f1;
    font-size: 12px; color: #1c3a5e; display: none;
}
#modalTransfer .tf-summary.show { display: block; }

#modalTransfer .modal-footer { background: #fff; border-top: 1px solid #eee; padding: 12px 22px; }
#modalTransfer .btn-transfer {
    background: #1ab394; border: none; color: #fff; font-weight: 600;
    padding: 7px 24px; border-radius: 5px; font-size: 13px;
}
#modalTransfer .btn-transfer:hover { background: #17a085; color: #fff; }
#modalTransfer .btn-transfer:disabled { opacity: .6; }

/* select2 seragam */
#modalTransfer .select2-container { width: 100% !important; }
#modalTransfer .select2-container--default .select2-selection--single {
    height: 36px; border: 1px solid #d5dbe3; border-radius: 4px;
}
#modalTransfer .select2-selection--single .select2-selection__rendered {
    line-height: 34px; font-size: 13px; padding-left: 10px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
#modalTransfer .select2-selection--single .select2-selection__arrow { height: 34px; }
</style>

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0" id="title_header">List Transaction</h5>
        <div id="summary">
              <h6 id="receive_nom"></h6>
            <h6 id="spen_nom"></h6>
             <h6 id="final_nom"></h6>
        </div>
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
                    <button type="button" id="button_trans_money" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="0">
                        <i class="ti ti-switch-horizontal" style="font-size: 1.2rem;"></i>
                        <span> Transfer</span>
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
                    <th>Transfer</th>
                    <th width="15%">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalTransfer" tabindex="-1" role="dialog" aria-hidden="true"
     data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            {{-- Header --}}
            <div class="tf-header">
                <div>
                    <h5><i class="ti ti-switch-horizontal mr-1"></i> Transfer Money</h5>
                    <small>Pindahkan dana antar akun bank</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="formTransfer">
                @csrf
                <div class="modal-body">

                    {{-- FROM → TO --}}
                    <div class="tf-route">
                        <div class="tf-card from">
                            <div class="tf-card-title">
                                <i class="ti ti-arrow-up-right"></i> From <span class="text-danger">*</span>
                            </div>
                            <select class="form-control select2-account"
                                    id="from_account" name="bank_id_from">
                            </select>
                        </div>

                        <div class="tf-arrow" id="btnSwapTransfer" title="Tukar From / To">
                            <i class="ti ti-arrows-exchange"></i>
                        </div>

                        <div class="tf-card to">
                            <div class="tf-card-title">
                                <i class="ti ti-arrow-down-right"></i> To <span class="text-danger">*</span>
                            </div>
                            <select class="form-control select2-account"
                                    id="to_account" name="bank_id_to">
                            </select>
                        </div>
                    </div>

                    {{-- Detail --}}
                    <div class="tf-detail">
                        <div class="form-row">
                            <div class="form-group col-md-4 mb-3">
                                <label for="date_trans_transfer">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control"
                                       name="date_trans" id="date_trans_transfer"
                                       value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="form-group col-md-8 mb-3">
                                <label for="amount_tranfser">Amount <span class="text-danger">*</span></label>
                                <div class="tf-amount-wrap">
                                    <span class="tf-prefix">Rp</span>
                                    <input type="number" step="0.01" min="0" class="form-control"
                                           name="amount" id="amount_tranfser" value="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="reference_transfer_bank">Reference <span class="text-danger">*</span></label>
                            <input type="text" class="form-control"
                                   name="reference_transfer_bank" id="reference_transfer_bank" required
                                   maxlength="255" placeholder="Catatan / nomor referensi (opsional)">
                        </div>
                    </div>

                    {{-- Ringkasan --}}
                    <div class="tf-summary" id="tfSummary"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm border" data-dismiss="modal">
                        <i class="ti ti-x mr-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn-transfer" id="btnSubmitTransfer">
                        <i class="ti ti-send mr-1"></i> Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCreateHotel" tabindex="-1" role="dialog" aria-hidden="true"
     data-backdrop="static" data-keyboard="false">
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
                                    <label id="title_to_from"><span class="text-danger">*</span></label>
                                    <select class="form-control select2-contact" name="uuid_to" id="contact_id" style="width: 100%;" required></select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_h" name="date_h" value="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Reference <span class="text-danger">*</span></label>
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
                                    <option value="0" selected>NO TAX</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade p-3" id="detailTab">
                        <div class="table-responsive">
                            <input type="hidden" id="d_id_parent_bill" name="bills_parent_id"/>
                            <table class="table table-bordered table-hover" id="itemTable">
                                <colgroup>
                                    <col style="width:40px;">   <!-- No -->
                                    <col style="width:240px;">  <!-- Item -->
                                    <col style="width:240px;">  <!-- Description -->
                                    <col style="width:70px;">   <!-- Qty -->
                                    <col style="width:130px;">  <!-- Unit Price -->
                                    <col style="width:220px;">  <!-- Account -->
                                    <col style="width:90px;">   <!-- Tax Rate -->
                                    <col style="width:180px;">  <!-- Nama Paket -->
                                    <col style="width:160px;">  <!-- Divisi -->
                                    <col style="width:140px;">  <!-- Amount -->
                                    <col style="width:50px;">   <!-- Action -->
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th width="40" class="text-center">No</th>
                                        <th style="min-width: 200px;">Item</th>
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
    const modalParent = $('#modalCreateHotel .modal-content');
    let full_url = window.location.href;
   // const segments = full_url.split('/').filter(Boolean);
    const segments = window.location.pathname.split('/');
    const lastSegment = segments.filter(Boolean).pop();//segments.pop();
    let currentBank = { id: lastSegment, text: null };
    // --- HELPER FUNCTIONS ---
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    //untuk auto open dari report
    const urlParams = new URLSearchParams(window.location.search);
    const openId = urlParams.get('open');

     //console.log('******',openId)
    if (openId) {
        console.log('open modal',openId)
        openDetailModal(openId);
    }

    function openDetailModal(p_transBank) {
        $('#idHotelInput').val(p_transBank);
        $('.modal-title').text(`Edit Bank Trans `);
        loadBankTrans(p_transBank); 
        $('#modalCreateHotel').modal('show');
    }
    //untuk auto open dari report

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
                     return '-';//ata.get_inv.contact_name;
                }else{
                    return  '-'
                }
            } 
        },
       { 
            data: 'reference_detail', 
            name: 'reference_detail', 
            render: function(data,type,row){
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
                    return `<b style="color:#627FF5">invoice</b> | ${data.get_inv.invoice_number} | ${data.get_inv.reference}`;
                }
                 else if(data.get_fee_over != null){
                    return `<b style="color:#4CE6D9">Fee diskon</b> | ${data.get_fee_over.invoice_number} | ${data.get_fee_over.reference}`;
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
                 let cek_sar = row.nominal_currency > 1 ? 'SAR' :'IDR';
                return  `${cek_sar} - ` + formatCurrency(data)
            }
        },
        { 
            data: 'nominal_receive', 
            name: 'nominal_receive' ,
            render: function(data,type,row){
                let cek_sar = row.nominal_currency > 1 ? 'SAR' :'IDR';
                return  `${cek_sar} - ` + formatCurrency(data)
            }
        },
        { 
            data: 'nominal_transfer', 
            name: 'nominal_transfer' ,
            render: function(data,type,row){
                let cek_n = data > 0  ? 'masuk history: '+  formatCurrency(data) : '-';
                    return cek_n;

            }
        },
       {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data, type, row) {
                console.log('row',row)
                let base_url = window.location.origin
                 $("#title_header").text('Daftar Transaksi ' + row.get_bank.name);
                $("#spen_nom").text('Pengeluaran ' + formatCurrency(row.get_bank.sum_curr_spend));
                $("#receive_nom").text('Penerimaan ' + formatCurrency(row.get_bank.sum_curr_receive));
                $("#final_nom").text('Saldo akhir ' + formatCurrency(row.get_bank.final_nominal_currency));
               
                currentBank.text = row.get_bank.name + ' (' + (row.get_bank.currency_code || '-') + ')';
                let url = '';
                let id = '';
                if (row.get_pbill && row.get_pbill.id) {
                    // url = "{{ route('web-purchase-bill', ':idBill') }}"
                    //     .replace(':idBill', row.get_pbill.id);
                    url =  `${base_url}/travel/admin/transaksi/purchase-bills/?open=${row.get_pbill.id}`;
                    id = row.get_pbill.id;
                } else if (row.get_inv && row.get_inv.id) {
                    // url = "{{ route('web-sales-inv', ':idInvoice') }}"
                    //     .replace(':idInvoice', row.get_inv.id);
                    url = `${base_url}/travel/admin/transaksi/sales-invoice/?open=${row.get_inv.id}`
                    id = row.get_inv.id;
                }
                else if (row.get_p_bank && row.get_p_bank.id) {
                    url = `${base_url}/travel/admin/transaksi/bank-trans/?open=${row.get_p_bank.id}`
                    id = row.get_p_bank.id
                } else {
                    //url = `${base_url}/travel/admin/transaksi/bank-trans/${row.uuid_bank}/?open=${row.get_p_bank.id}`
                    //id = row.get_p_bank.id;
                }

                let btn_detail = `
                    <a href="${url}"
                    data-id="${id}"
                    title="detail"
                    class="text-primary view-tr mr-2">
                        <i class="ti ti-eye"></i>
                    </a>
                `;
                let btn_edit = '';
                if (row.get_p_bank && row.get_p_bank.id) {
                    btn_edit = `
                        <a href="javascript:;"
                        data-id="${row.get_p_bank.id}"
                        title="Edit"
                        class="text-primary edit-hotel mr-2">
                            <i class="ti ti-pencil"></i>
                        </a>
                    `;
                }

                return btn_edit ;
            },
        }
    ];

    table = initGlobalDataTableTokenSelected(
        '#tableHotel',
        `{{ route('bank-trans-allByIdBank') }}`,
        columnBills,
        { "kolom_name": "reference" ,'bank_id_xero' : lastSegment}
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



     $('#formTransfer').on('submit', function(e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
        let payload_trans = {
            'bank_id_from' : $("#from_account").val(),
            'bank_id_to' : $('#to_account').val(),
            'code_tracking_divisi_to' : $("#to_divisi").val(),
            'code_tracking_divisi_from' : $("#from_divisi").val(),
            'code_tracking_paket_to' : $("#to_paket").val(),
            'code_tracking_paket_from' : $("#from_paket").val(),
            'date_trans' :$("#date_trans_transfer").val(),
            'amount' : $("#amount_tranfser").val(),
            'reference_transfer_bank' :$("#reference_transfer_bank").val()
        }
        // console.log('sss',payload_trans)
         ajaxRequest(`{{ route('save-bank-transfer') }}`, 'POST', payload_trans, localStorage.getItem("token"))
            .then(response =>{
                    if(response.status == 200){
                        let data_res = response.data.data;
                        Swal.fire({
                            icon : 'success',
                            title: 'Berhasil',
                            text : response.message || 'Transfer berhasil disimpan.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            $('#modalTransfer').modal('hide');
                            // table.ajax.reload();
                        });
                    }
            })
            .catch((err)=>{
                cathError(err)
            })
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

    $("#button_trans_money").on("click", function () {
        initSelect2ModalTransfer();
        setDefaultFromBank();
        $("#modalTransfer").modal('show');
    });


    function setDefaultFromBank() {
        const $from = $('#from_account');
        $('#to_account').empty().trigger('change');
        $from.empty();

        const apply = function (text) {
            $from.append(new Option(text, currentBank.id, true, true)).trigger('change');
        };

        if (currentBank.text) {
            apply(currentBank.text);
            return;
        }

        // Fallback: tabel masih kosong (belum ada transaksi), jadi nama bank belum ada.
        // Ambil dari route select2 bank.
        $.getJSON("{{ route('getbankselect2') }}", { page: 1, keyword: '', limit: 200, kolom_name: 'name' })
            .done(function (res) {
                const list  = res.data?.data || [];
                const found = list.find(b => String(b.id) === String(currentBank.id));
                currentBank.text = found
                    ? found.name + ' (' + (found.currency_code || '-') + ')'
                    : 'Bank #' + currentBank.id;
                apply(currentBank.text);
            })
            .fail(function () {
                apply('Bank #' + currentBank.id);   // value tetap benar, hanya label
            });
    }


     $("#button_receive_money").on("click", function () {
        $("#modal_pay").addClass('d-none');
        $('#idHotelInput').val(0);
        $('#cur_id').val(0);
        $("#ref_id").val('');
        $("#d_id_parent_bill").val(0);
        $('#formCreateHotel')[0].reset();

        $("#title_to_from").html('From <span class="text-danger">*</span>');
        $("#is_spend").val(0);
        $('#modalCreateHotel .modal-title').text('Create New Receive Money');

        $("#modalCreateHotel").modal('show');   // ← terakhir
    });


    function initSelect2ModalTransfer() {

        $('#from_account, #to_account, #from_paket, #to_paket, #from_divisi, #to_divisi').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
        });
        // --- FROM ACCOUNT ---
        $('#from_account').select2({
            placeholder: "Cari nama bank...",
            allowClear: true,
            dropdownParent: $('#modalTransfer'),
            ajax: {
                url: "{{ route('getbankselect2') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        page: params.page || 1,
                        keyword: params.term || '',
                        limit: 10,
                        kolom_name: 'name'
                    };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data.data, function(item) {
                            return {
                                id: item.id,
                                text: item.name,
                                currency_code: item.currency_code || '-'
                            };
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

        // --- TO ACCOUNT ---
        $('#to_account').select2({
            placeholder: "Cari nama bank...",
            allowClear: true,
            dropdownParent: $('#modalTransfer'),
            ajax: {
                url: "{{ route('getbankselect2') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        page: params.page || 1,
                        keyword: params.term || '',
                        limit: 10,
                        kolom_name: 'name'
                    };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data.data, function(item) {
                            return {
                                id: item.id,
                                text: item.name,
                                currency_code: item.currency_code || '-'
                            };
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

        // --- FROM PAKET ---
        $('#from_paket').select2({
            placeholder: "Pilih Paket...",
            allowClear: true,
            dropdownParent: $('#modalTransfer'),
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function() {
                    return { name_parent_category: 'nama paket' };
                },
                processResults: function(response) {
                    if (!response.status || !response.data?.lines_category) return { results: [] };
                    return {
                        results: $.map(response.data.lines_category, function(item) {
                            return {
                                id: item.item_uuid_category || item.id,
                                text: item.item_name_category
                            };
                        })
                    };
                },
                cache: true
            }
        });

        // --- TO PAKET ---
        $('#to_paket').select2({
            placeholder: "Pilih Paket...",
            allowClear: true,
            dropdownParent: $('#modalTransfer'),
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function() {
                    return { name_parent_category: 'nama paket' };
                },
                processResults: function(response) {
                    if (!response.status || !response.data?.lines_category) return { results: [] };
                    return {
                        results: $.map(response.data.lines_category, function(item) {
                            return {
                                id: item.item_uuid_category || item.id,
                                text: item.item_name_category
                            };
                        })
                    };
                },
                cache: true
            }
        });

        // --- FROM DIVISI ---
        $('#from_divisi').select2({
            placeholder: "Pilih Divisi...",
            allowClear: true,
            dropdownParent: $('#modalTransfer'),
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function() {
                    return { name_parent_category: 'divisi' };
                },
                processResults: function(response) {
                    if (!response.status || !response.data?.lines_category) return { results: [] };
                    return {
                        results: $.map(response.data.lines_category, function(item) {
                            return {
                                id: item.item_uuid_category || item.id,
                                text: item.item_name_category
                            };
                        })
                    };
                },
                cache: true
            }
        });

        // --- TO DIVISI ---
        $('#to_divisi').select2({
            placeholder: "Pilih Divisi...",
            allowClear: true,
            dropdownParent: $('#modalTransfer'),
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function() {
                    return { name_parent_category: 'divisi' };
                },
                processResults: function(response) {
                    if (!response.status || !response.data?.lines_category) return { results: [] };
                    return {
                        results: $.map(response.data.lines_category, function(item) {
                            return {
                                id: item.item_uuid_category || item.id,
                                text: item.item_name_category
                            };
                        })
                    };
                },
                cache: true
            }
        });
    }


     $("#button_spend_money").on("click", function () {
        $("#modal_pay").addClass('d-none');
        $('#idHotelInput').val(0);
        $('#cur_id').val(0);
        $("#ref_id").val('');
        $("#d_id_parent_bill").val(0);
        $('#formCreateHotel')[0].reset();

        $("#title_to_from").html('To <span class="text-danger">*</span>');
        $("#is_spend").val(1);
        $('#modalCreateHotel .modal-title').text('Create New Spend Money');

        $("#modalCreateHotel").modal('show');   // ← terakhir
    });

    $('#modalCreateHotel').on('show.bs.modal', function () {
        $('#header-tab').tab('show');
        
        if ($('#idHotelInput').val() == 0) {
            $('#contact_id').empty().trigger('change'); 
            $('#itemTable tbody').empty();
            addNewRow(); 
        }
    });

    $('#modalCreateHotel').on('hidden.bs.modal', function () {
        $('#idHotelInput').val(0);
        $('#itemTable tbody').empty();
        $('#contact_id').empty().trigger('change');
    });

    // --- 4. EDIT FUNCTIONALITY ---
    $('#tableHotel').on('click', '.edit-hotel', function () {
        const id = $(this).data('id');   // = get_p_bank.id
        if (!id) return;

        // reset section payment
        $('#modal_pay input[name="nominal_spend"]').val(0);
        $('#modal_pay select[name="uuid_bank"]').val(null).trigger('change');
        $('#modal_pay input[name="reference_detail"]').val('');

        $('#idHotelInput').val(id);              // dipakai submit sebagai param "id"
        $('#modalCreateHotel').modal('show');
        loadBankTrans(id);                       // → route detail-bank-trans
    });

    function loadBankTrans(id){
        $("#idHotelInput").val(id);
        $('#itemTable tbody').empty(); 
        $('#contact_id').prop('disabled', true); 

        ajaxRequest(`{{ route('detail-bank-trans') }}`, 'GET', {id : id}, localStorage.getItem("token"))
            .then(response =>{
                if(response.status == 200){
                    let data_res = response.data.data;
                    const isSpend = data_res.is_spend == 1;

                    $('#modalCreateHotel .modal-title').text(
                        (isSpend ? 'Edit Spend Money ' : 'Edit Receive Money ') + (data_res.reference || '')
                    );
                    $('#title_to_from').html((isSpend ? 'To' : 'From') + ' <span class="text-danger">*</span>');

                    $("#ammounts_are").val(data_res.amounts_are).trigger('change');

                    let contactId   = data_res.uuid_to;
                    let contactName = data_res.get_contact_from ? data_res.get_contact_from.full_name : 'Nama tidak ditemukan';
                    let newOption   = new Option(contactName, contactId, true, true);

                    $("#label_payment").text(data_res.currency);
                    $('#contact_id').empty().append(newOption).trigger('change');
                    $("#is_spend").val(data_res.is_spend);   // harus sebelum addNewRow (menentukan tipe COA)
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
            item_code: $('select[name="item_code[]"]').map(function(){ return $(this).val(); }).get(),
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
                    console.log('saved',response)
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
        let item_select = item ? (item.item_code || '') : '';
        let qty = item ? item.qty : 1;
        let price = item ? parseFloat(item.unit_price) : '';
        let taxRate = item ? (item.tax_rate !== null ? item.tax_rate : 0) : 0;
        let amount = item ? parseFloat(item.amount) : '';

        console.log('select',item_select)
        let newRow = `
            <tr>
                <td class="text-center">${rowCount}</td>
                <input type="hidden" name="id_detail[]" value="${id_detail_row}"/>
                <td>
                    <select class="form-control select2-item" name="item_code[]" value="${item_select}"
                            style="width:100%;" required>
                    </select>
                </td>
                <td><input type="text" class="form-control desc-input" required name="description[]" value="${desc}" placeholder="Deskripsi item"></td>
                <td><input type="number" class="form-control" required name="qty[]" min="1" value="${qty}"></td>
                <td><input type="number" class="form-control price-input" required name="unit_price[]" min="1" step="0.01" value="${price}"></td>
                <td>
                    <select class="select2-account form-control" name="account_id[]" style="width:100%;" required>
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

        // --- ITEM select2 ---
        $lastRow.find('.select2-item').select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalCreateHotel'),
            placeholder: 'Pilih Item',
            allowClear: true,
            ajax: {
                url: '{{ route("list-paket-select2") }}',
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return { page: params.page || 1, keyword: params.term || '', limit: 5 };
                },
                processResults: function(response) {
                    if (response.status != 'success') return { results: [], pagination: { more: false } };
                    return {
                        results: $.map(response.data.results, function(item) {
                            return {
                                id   : item.id,
                                text : item.nama_paket,
                                harga: item.price_sales
                            };
                        }),
                    };
                },
                cache: true
            }
        }).on('select2:select', function(e) {
            const d = e.params.data;
            // Auto-fill desc dari nama_paket response
            $(this).closest('tr').find('.desc-input')
                .val(d.nama_paket || d.text || '');
            // Auto-fill unit price
            $(this).closest('tr').find('.price-input')
                .val(Number(d.harga) || '').trigger('keyup');
        }).on('select2:clear', function() {
            $(this).closest('tr').find('.desc-input').val('');
            $(this).closest('tr').find('.price-input').val('').trigger('keyup');
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
            if (item.item_code) {
                // Restore selected item — buat Option langsung tanpa ajax call tambahan
                // (item.item_name sudah tersimpan saat save, atau pakai desc sebagai fallback)
                let itemLabel = item.item_name || item.desc || ('Item ID: ' + item.item_code);
                console.log('itemid',item)
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
                        <td colspan="9" class="text-right font-weight-bold">Total Amount</td>
                        <td class="text-right font-weight-bold" id="grandTotal" style="font-size: 1.1em;">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            `);
        }
        $('#grandTotal').text(formatCurrency(total_grand));
    };


    // modall
    // ── Ringkasan "dari → ke" ──
function updateTransferSummary() {
    const from   = $('#from_account').select2('data')[0]?.text;
    const to     = $('#to_account').select2('data')[0]?.text;
    const amount = parseFloat($('#amount_tranfser').val()) || 0;
    const $box   = $('#tfSummary');

    if (from && to && amount > 0) {
        $box.html(
            `<i class="ti ti-info-circle mr-1"></i>` +
            `Transfer <b>Rp ${amount.toLocaleString('id-ID')}</b> ` +
            `dari <b>${$('<div>').text(from).html()}</b> ` +
            `ke <b>${$('<div>').text(to).html()}</b>`
        ).addClass('show');
    } else {
        $box.removeClass('show').empty();
    }
}

$(document).on('change input', '#from_account, #to_account, #amount_tranfser', updateTransferSummary);

// ── Swap From <-> To ──
$('#btnSwapTransfer').on('click', function () {
    const f = $('#from_account').select2('data')[0];
    const t = $('#to_account').select2('data')[0];

    $('#from_account').empty();
    $('#to_account').empty();

    if (t && t.id) $('#from_account').append(new Option(t.text, t.id, true, true));
    if (f && f.id) $('#to_account').append(new Option(f.text, f.id, true, true));

    $('#from_account, #to_account').trigger('change');
});

// ── Validasi ringan sebelum submit (taruh di awal handler #formTransfer submit) ──
// if ($('#from_account').val() === $('#to_account').val()) {
//     Swal.fire('Peringatan', 'Bank asal dan tujuan tidak boleh sama.', 'warning'); return;
// }
// if (!(parseFloat($('#amount_tranfser').val()) > 0)) {
//     Swal.fire('Peringatan', 'Nominal harus lebih dari 0.', 'warning'); return;
// }

// ── Reset saat modal ditutup ──
$('#modalTransfer').on('hidden.bs.modal', function () {
    $('#formTransfer')[0].reset();
    $('#from_account, #to_account').empty().trigger('change');
    $('#tfSummary').removeClass('show').empty();
    $('#btnSubmitTransfer').prop('disabled', false)
        .html('<i class="ti ti-send mr-1"></i> Transfer');
});
});
</script>
@endpush