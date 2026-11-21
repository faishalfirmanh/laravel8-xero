@extends('layouts.app')

@section('content')

<style>
    #modalCreateHotel .modal-content {
        min-height: 85vh;
    }

    #itemTable th, #itemTable td {
        vertical-align: middle;
    }

    /* ===== Dropzone ===== */
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
        transform: scale(1.05);
    }

    /* ===== Tabel detail item ===== */
    #itemTable {
        table-layout: fixed;
        width: 100%;
        min-width: 1450px;
    }
    #itemTable td { overflow: hidden; }

    #itemTable .qty-input,
    #itemTable .price-input,
    #itemTable .amount-row {
        width: 100%;
        min-width: 0;
    }

    #itemTable .select2-container { width: 100% !important; }
    #itemTable .select2-selection--single .select2-selection__rendered {
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }

    /*
     * Select2 dropdown di-append ke body (dropdownParent: $('body')).
     * z-index harus di atas modal Bootstrap (1050) dan backdrop (1040).
     * min-width agar dropdown tidak terlalu sempit.
     */
    .select2-dropdown {
        z-index: 1070 !important;
        min-width: 300px !important;
    }

    .swal2-container {
        z-index: 2000 !important;
    }

    /* ===== Layout ===== */
    .bill-section {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 14px;
    }
    .bill-section-title {
        font-weight: 700;
        color: #1ab394;
        text-transform: uppercase;
        letter-spacing: .03em;
        font-size: 13px;
        margin-bottom: 10px;
    }

    /*
     * bill-table-wrap hanya scroll horizontal.
     * JANGAN pakai overflow: visible — menghilangkan scroll dan menyebabkan konflik layout.
     */
    .bill-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #dee2e6;
        border-radius: 6px;
    }

    /* ===== Mobile ===== */
    @media (max-width: 767.98px) {
        #modalCreateHotel .modal-dialog {
            margin: .5rem auto;
            max-width: calc(100% - 1rem) !important;
            width: calc(100% - 1rem) !important;
        }
        #modalCreateHotel .modal-content {
            min-height: auto;
        }
        #modalCreateHotel .modal-title {
            font-size: 16px;
        }
        #modalCreateHotel .modal-footer {
            flex-wrap: wrap;
            gap: 6px;
            position: sticky;
            bottom: 0;
            background: #fff;
            z-index: 5;
            border-top: 1px solid #dee2e6;
        }
        /* Font 16px mencegah auto-zoom iOS */
        #modalCreateHotel input.form-control,
        #modalCreateHotel select.form-control,
        #modalCreateHotel textarea.form-control {
            font-size: 16px;
        }
        .bill-section { padding: 10px; }
    }

    /* Loading overlay saat batch init Select2 */
    #rowInitOverlay {
        display: none;
        position: absolute;
        inset: 0;
        background: rgba(255,255,255,0.6);
        z-index: 10;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-size: 13px;
        color: #1ab394;
        font-weight: 600;
        gap: 8px;
    }
    /* ===== Tombol Scroll Down (fixed di dalam modal) ===== */
/* ===== Tombol Scroll Up & Scroll Down (fixed di dalam modal) ===== */
#btnScrollUp,
#btnScrollDown {
    position: fixed;
    right: 20px;
    z-index: 1060;
    display: none;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 50px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, .2);
}
#btnScrollUp   { bottom: 70px; }
#btnScrollDown { bottom: 20px; }

#btnScrollUp.show,
#btnScrollDown.show { display: inline-flex; }

/* Warna ungu untuk tombol scroll up */
#btnScrollUp {
    background-color: #6f42c1;
    border-color: #6f42c1;
}
#btnScrollUp:hover,
#btnScrollUp:focus {
    background-color: #5a32a3;
    border-color: #5a32a3;
}

@media (max-width: 767.98px) {
    #btnScrollUp,
    #btnScrollDown {
        right: 12px;
        padding: 10px 14px;
        font-size: 14px;
    }
    #btnScrollUp.show,
    #btnScrollDown.show {
        display: inline-flex !important;
    }
    #btnScrollUp   { bottom: calc(150px + env(safe-area-inset-bottom, 0px)); }
    #btnScrollDown { bottom: calc(96px + env(safe-area-inset-bottom, 0px)); }
}
.bill-actions .btn + .btn {
    margin-left: 10px;
}

    #rowInitOverlay.show { display: flex; }
</style>

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Daftar Bills</h5>

        <div class="d-flex gap-2 flex-shrink-0">
            <button type="button" onclick="syncBillFromXero()" id="button_sync_bill" class="btn btn-danger d-none text-nowrap">
                <i class="ti ti-plus me-1"></i> Sync Bills
            </button>
            <button type="button" id="button_add_hotel" class="btn btn-primary text-nowrap" data-toggle="modal" data-target="#modalCreateHotel">
                <i class="ti ti-plus me-1"></i> Tambah Bills
            </button>
        </div>

        <div class="form-group mb-0 d-flex align-items-center flex-wrap">
            <label class="mb-0 mr-2 small font-weight-bold">Dari</label>
            <input type="date" id="filter_date_start" class="form-control form-control-sm mr-2" style="width: 150px;">
            <label class="mb-0 mr-2 small font-weight-bold">Sampai</label>
            <input type="date" id="filter_date_end" class="form-control form-control-sm mr-2" style="width: 150px;">
            <select id="filter_status" class="form-select form-select-sm" style="width: 180px;">
                <option value="3">ALL</option>
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
                    <th>Mata Uang</th>
                    <th>Date</th>
                    <th>Saved By</th>
                    <th>Total Tagihan (Rp)</th>
                    <th>Paid (Rp)</th>
                    <th>Due</th>
                    <th class="text-center" style="width:110px; min-width:110px;">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalCreateHotel" tabindex="-1" role="dialog"
     aria-hidden="true" data-backdrop="static" data-keyboard="false">
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

                <div class="bill-form-body p-3">

                    {{-- ===== HEADER ===== --}}
                    <div class="bill-section">
                        <div class="bill-section-title">Header</div>
                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label>From (Supplier / Contact) <span class="text-danger">*</span></label>
                                    <select class="form-control select2-contact" name="uuid_from" id="contact_id" style="width: 100%;" required></select>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-group">
                                    <label>Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_req" name="date_req" value="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-group">
                                    <label>Due Date <span class="text-danger">*</span></label>
                                    <input type="date" id="due_date" class="form-control" name="due_date" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label>Reference <span class="text-danger">*</span></label>
                                    <input type="text" id="ref_id" required class="form-control" name="reference">
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-warning mb-3" id="btn-add-item-contact"
                                        style="border-color:#fd7e14; color:#fd7e14;">
                                    <i class="ti ti-user-plus" style="font-size:12px;"></i> Add Contact
                                </button>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-group">
                                    <label>Currency <span class="text-danger">*</span></label>
                                    <select class="form-control" name="currency" id="cur_id" required>
                                        <option value="IDR" selected>IDR – Rupiah</option>
                                        <option value="SAR">SAR – Saudi Riyal</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6 col-md-3" id="wrap_nominal_currency" style="display:none;">
                                <div class="form-group">
                                    <label>Kurs <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="nominal_currency" id="nominal_currency"
                                           min="1" step="any" placeholder="mis. 4350">
                                    <span class="text-danger font-weight-bold small" id="text_currency">1 SAR = Rp 0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ===== DETAIL ITEM ===== --}}
                    <div class="bill-section" id="detailTab">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                            <div class="bill-section-title mb-0">Detail Item</div>
                            <small class="text-muted d-md-none">Geser tabel ke samping untuk melihat semua kolom</small>
                        </div>

                        <input type="hidden" id="d_id_parent_bill" name="bills_parent_id"/>

                        {{-- Wrapper posisi relative agar overlay bisa absolute di dalamnya --}}
                        <div style="position: relative;">
                            <div id="rowInitOverlay">
                                <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                                <span>Memuat baris...</span>
                            </div>
                            <div class="bill-table-wrap">
                                <table class="table table-bordered table-hover mb-0" id="itemTable">
                                    <colgroup>
                                        <col style="width:40px;">
                                        <col style="width:170px;">
                                        <col style="width:260px;">
                                        <col style="width:70px;">
                                        <col style="width:120px;">
                                        <col style="width:200px;">
                                        <col style="width:90px;">
                                        <col style="width:170px;">
                                        <col style="width:150px;">
                                        <col style="width:130px;">
                                        <col style="width:50px;">
                                    </colgroup>
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">No</th>
                                            <th>Item</th>
                                            <th>Description</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-right">Unit Price</th>
                                            <th>Account <span class="text-danger">*</span></th>
                                            <th class="text-center">Tax Rate (%)</th>
                                            <th>Nama Paket</th>
                                            <th>Divisi</th>
                                            <th class="text-right">Amount (IDR)</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lineItemsBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="table-actions mt-3">
                            <button type="button" class="btn-dashed" onclick="addNewRow()">
                                <i class="ti ti-plus"></i> Add a new line
                            </button>
                            <button type="button" class="btn-dashed" id="btn-show-dropzone"
                                    style="border-color: #007bff; color: #007bff; margin-left: 10px;">
                                <i class="ti ti-upload" style="font-size:12px;"></i> Upload Bukti
                            </button>
                        </div>

                        <div id="dropzone-container" style="display: none; margin-top: 12px;">
                            <div class="dropzone" id="buktiDropzone">
                                <div class="dz-message" data-dz-message>
                                    <span>Klik atau Drop gambar bukti di sini (Bisa pilih banyak file)</span>
                                </div>
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
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-light border-top">
                    <h6 class="font-weight-bold mb-3 text-dark">Make a payment</h6>
                    <div class="row align-items-end mb-4" id="modal_pay">
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold text-muted mb-1">Amount Paid <span class="text-danger">*</span><span class="payment-currency" id="label_payment"></span></label>
                                <input type="number" step="0.01" class="form-control form-control-sm" name="nominal_spend" min="1" value="0">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold text-muted mb-1">Date Paid <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" name="date_transaction" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold text-muted mb-1">Paid From <span class="text-danger">*</span></label>
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
                            <input type="hidden" id="editPaymentId" value="">
                            <button type="button" class="btn btn-secondary btn-sm w-100 mt-1" id="btnCancelEditPayment" style="display:none;">
                                Cancel Edit
                            </button>
                            <button type="button" class="btn btn-success btn-sm w-100 font-weight-bold" id="btnRecordPayment">Record payment</button>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <h6 class="font-weight-bold text-muted mb-3">History & Notes</h6>
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-info btn-sm mr-2 font-weight-bold">History Payment</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white" id="payment_history_bill">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-muted">No</th>
                                        <th class="text-muted">Date</th>
                                        <th class="text-muted">Bank Name</th>
                                        <th class="text-muted">Nominal</th>
                                        <th class="text-muted text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="small text-muted"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <h6 class="font-weight-bold mb-2">
                            <i class="ti ti-history mr-1"></i> Log History
                        </h6>
                        <ul class="list-group list-group-flush small" id="history_log_list" style="max-height: 200px; overflow-y: auto;"></ul>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <button type="button" id="btnScrollUp" class="btn btn-success font-weight-bold">
    <i class="ti ti-arrow-up"></i> Scroll Up
    </button>

    <button type="button" id="btnScrollDown" class="btn btn-primary">
        <i class="ti ti-arrow-down"></i> Scroll Down
    </button>
</div>

<!-- modal preview gambar -->
<div class="modal fade" id="previewImageModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1075;">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"
                        style="opacity: 1; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">
                    <span aria-hidden="true" style="font-size: 2.5rem;">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="previewImageModalSrc" src="" alt="Preview Gambar"
                     style="max-width: 100%; max-height: 80vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL ADD CONTACT ── --}}
<div class="modal fade" id="modalCreateJamaah" tabindex="-1" role="dialog" aria-hidden="true"
     data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalJamaahTitle">Tambah Contact</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="id_jamaah" value="">
                <div class="form-group mb-3">
                    <label for="full_name">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" placeholder="Nama lengkap">
                </div>
                <div class="form-group mb-3">
                    <label for="phone_number">No. Telepon <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="phone_number" placeholder="Contoh: 628123456780">
                </div>
                <div class="form-group mb-3">
                    <label for="nik">NIK <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nik" maxlength="16" placeholder="16 digit NIK">
                </div>
                <div class="form-group mb-3">
                    <label for="detail_address">Alamat</label>
                    <textarea class="form-control" id="detail_address" rows="3" placeholder="Alamat lengkap (opsional)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" id="btn_save_jamaah" class="btn btn-primary">Simpan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
Dropzone.autoDiscover = false;

/* =====================================================================
   KURS / CURRENCY HELPERS
   ===================================================================== */
function toggleKursInput() {
    const cur = $('#cur_id').val() || 'IDR';
    if (cur === 'SAR') {
        $('#wrap_nominal_currency').show();
    } else {
        $('#wrap_nominal_currency').hide();
        $('#nominal_currency').val(1);
    }
    syncKurs();
}

function syncKurs() {
    const cur = $('#cur_id').val() || 'IDR';
    if (cur === 'SAR') {
        const rate = parseFloat($('#nominal_currency').val()) || 0;
        $('#text_currency').text('1 SAR = Rp ' + rate.toLocaleString('id-ID'));
    } else {
        $('#text_currency').text('');
    }
}

function lockCurrency(lock) {
    $('#cur_id, #nominal_currency').prop('disabled', lock);
}

$('#cur_id').on('change', toggleKursInput);
$('#nominal_currency').on('input', syncKurs);

/* =====================================================================
   MAIN
   ===================================================================== */
$(document).ready(function () {
    var table;

    /* =====================================================================
       FIX: Select2 search input tidak bisa diketik di dalam Bootstrap Modal.

       Bootstrap 4 memasang handler focusin.bs.modal di document yang
       mengembalikan fokus ke modal setiap kali elemen di LUAR modal difokus.
       Select2 dengan dropdownParent: $('body') me-render dropdown di body,
       sehingga search input-nya terus kehilangan fokus.

       Ada 3 lapis fix:
       Lapis 1 — override _enforceFocus (method Bootstrap 4) agar elemen Select2 dikecualikan.
       Lapis 2 — capture-phase listener pada document, dijalankan sebelum handler Bootstrap.
       Lapis 3 — paksa fokus ke search input setiap kali dropdown Select2 dibuka.
       ===================================================================== */

    function isSelect2Element($t) {
        return !!(
            $t.closest('.select2-container').length ||
            $t.closest('.select2-dropdown').length ||
            $t.hasClass('select2-search__field')
        );
    }

    // Lapis 1: override method enforceFocus milik Bootstrap.
    // Bootstrap 4 memakai _enforceFocus (dengan this._element, bukan this.$element).
    // enforceFocus tetap di-override sebagai cadangan untuk Bootstrap 3.
    if ($.fn.modal && $.fn.modal.Constructor) {
        var enforceFocusWithSelect2 = function () {
            var self = this;
            $(document)
                .off('focusin.bs.modal')
                .on('focusin.bs.modal', function (e) {
                    var $modal = $(self._element || self.$element);
                    var $t = $(e.target);
                    if (
                        $modal[0] === e.target ||
                        $modal.has(e.target).length ||
                        isSelect2Element($t)
                    ) { return; }
                    $modal[0].focus();
                });
        };

        $.fn.modal.Constructor.prototype._enforceFocus = enforceFocusWithSelect2;
        $.fn.modal.Constructor.prototype.enforceFocus  = enforceFocusWithSelect2;
    }

    // Lapis 2: native capture listener, berjalan sebelum handler Bootstrap
    document.addEventListener('focusin', function (e) {
        if (isSelect2Element($(e.target))) {
            e.stopImmediatePropagation();
        }
    }, true);

    // Lapis 3: paksa fokus ke search input setiap kali dropdown Select2 dibuka
    $(document).on('select2:open', function () {
        setTimeout(function () {
            var $searchInput = $('.select2-container--open .select2-search__field');
            if ($searchInput.length) {
                $searchInput[0].focus();
            }
        }, 10);
    });

    /* ── HELPERS ── */
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    function convertStringDate(dateString) {
        if (!dateString) return '-';
        const d = new Date(dateString);
        return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function setSelectValue($sel, id, label) {
        if (!id) return;
        $sel.children('option').filter(function () { return this.value == id; }).remove();
        $sel.append(new Option(label || id, id, true, true)).trigger('change');
    }

    /* Destroy semua instance Select2 di dalam scope (mencegah dropdown yatim di body) */
    function destroyRowSelect2($scope) {
        $scope.find('.select2-item, .select2-account, .select2-paket, .select2-divisi').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
        });
    }

    /* ── SYNC XERO ── */
    window.syncBillFromXero = function () {
        Swal.fire({
            title: 'Sinkronisasi Bill dari Xero?',
            html: 'Apakah Anda yakin ingin mengambil <strong>semua bill</strong> dari Xero?<br><br>Proses ini akan memperbarui data bill Anda.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Sync Sekarang',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Sedang Sinkronisasi...', text: 'Mohon tunggu sebentar', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                ajaxRequest(`{{ route('sync-bill-xero') }}`, 'GET', { is_sync: 1 }, localStorage.getItem('token'))
                    .then(response => {
                        Swal.close();
                        if (response.status === 200) {
                            Swal.fire({ icon: 'success', title: 'Berhasil!', text: `Berhasil sinkronisasi ${response.total_saved} data COA`, timer: 2000 });
                        } else {
                            Swal.fire('Gagal', response.data.data.message, 'error');
                        }
                    })
                    .catch(err => cathError(err));
            }
        });
    };

    /* ── DATATABLE ── */
    let columnBills = [
        {
            data: null, className: 'text-center',
            render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
        },
        { data: 'name_contact_bill', name: 'name_contact_bill' },
        {
            data: 'status', name: 'status',
            render: function (data) {
                if (data == 0) return '<span class="badge badge-secondary">Draft</span>';
                if (data == 1) return '<span class="badge badge-success">Awaiting Payment</span>';
                if (data == 2) return '<span class="badge badge-info">Paid</span>';
                return '-';
            }
        },
        { data: 'reference', name: 'reference' },
        {
            data: 'currency',
            render: (data, type, row) => data == 'SAR' ? `${data} | ${formatCurrency(row.nominal_currency)}` : data
        },
        { data: 'date_req', name: 'date_req' },
        { data: 'created_by_name', render: d => d != null ? d : '-' },
        { data: 'total_base',         name: 'total_base',         render: d => formatCurrency(d) },
        { data: 'nominal_paid_base',  name: 'nominal_paid_base',  render: d => formatCurrency(d) },
        { data: 'nominal_due_base',   name: 'nominal_due_base',   render: d => formatCurrency(d) },
        {
            data: 'id', orderable: false, searchable: false, className: 'text-center text-nowrap',
            render: function (data) {
                return `
                    <div class="bill-actions d-inline-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary edit-hotel" data-id="${data}" title="Edit">
                            <i class="ti ti-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="dellBill('${data}')" title="Hapus">
                            <i class="ti ti-trash"></i>
                        </button>
                    </div>`;
            }
        }
    ];

    window.dellBill = function (id) {
        Swal.fire({
            title: 'Apakah Anda yakin?', text: 'Data Bills ini akan dihapus.', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true
        }).then(result => {
            if (result.isConfirmed) {
                ajaxRequest(`{{ route('delete-p-bills') }}`, 'POST', { id }, localStorage.getItem('token'))
                    .then(response => {
                        if (response.status == 200) {
                            Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Bills berhasil dihapus.', timer: 1500, showConfirmButton: false });
                            table.ajax.reload();
                        }
                    })
                    .catch(err => cathError(err));
            }
        });
    };

    /* ── DROPZONE ── */
    let myDropzone;
    let isClearingDropzone = false;

    myDropzone = new Dropzone('#buktiDropzone', {
        url: "{{ route('uploadImage-bill') }}",
        autoProcessQueue: false, uploadMultiple: true, parallelUploads: 10,
        maxFiles: 10, acceptedFiles: 'image/*', addRemoveLinks: true,
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token'),
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        init: function () {
            this.on('sending', (file, xhr, formData) => formData.append('bill_id', $('#idHotelInput').val()));

            this.on('successmultiple', function () {
                $('.action-submit').prop('disabled', false);
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Bukti berhasil diupload.', timer: 1500, showConfirmButton: false });
                table.ajax.reload(null, false);
                const currentBillId = $('#idHotelInput').val();
                if (currentBillId) loadDropzoneImages(currentBillId);
            });

            this.on('errormultiple', function () {
                $('.action-submit').prop('disabled', false);
                Swal.fire('Peringatan', 'Bill tersimpan, namun gagal mengupload gambar. Simpan bill dulu, lalu edit.', 'warning');
            });

            this.on('removedfile', function (file) {
                if (isClearingDropzone) return;
                if (file.isFromServer) {
                    $.ajax({
                        url: "{{ route('remove-image-bill') }}", type: 'POST',
                        data: { file_name: file.name },
                        headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token'), 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        error: () => Swal.fire('Gagal!', 'Gambar gagal dihapus dari server.', 'error')
                    });
                }
            });

            this.on('addedfile', function (file) {
                file.previewElement.addEventListener('click', function (e) {
                    e.stopPropagation(); e.preventDefault();
                    let imageUrl = file.url || file.dataURL;
                    if (!imageUrl && file.status === Dropzone.ADDED) imageUrl = URL.createObjectURL(file);
                    if (imageUrl) { $('#previewImageModalSrc').attr('src', imageUrl); $('#previewImageModal').modal('show'); }
                });
            });
        }
    });

    $('#btn-show-dropzone').on('click', () => $('#dropzone-container').slideToggle(200));

    function loadDropzoneImages(billid) {
        if (myDropzone) { isClearingDropzone = true; myDropzone.removeAllFiles(true); isClearingDropzone = false; }
        ajaxRequest("{{ route('get-image-bill') }}", 'GET', { bill_id: billid }, localStorage.getItem('token'))
            .then(response => {
                if (response.data.success && response.data.data.length > 0) {
                    $('#dropzone-container').show();
                    $.each(response.data.data, function (key, value) {
                        const mockFile = { name: value.name, size: value.size, accepted: true, status: Dropzone.ADDED, url: value.url, isFromServer: true };
                        myDropzone.emit('addedfile', mockFile);
                        myDropzone.emit('thumbnail', mockFile, value.url);
                        myDropzone.emit('complete', mockFile);
                        myDropzone.files.push(mockFile);
                    });
                }
            })
            .catch(err => cathError(err));
    }

    /* ── PAYMENT ── */
    function resetPaymentForm() {
        $('#editPaymentId').val('');
        $('#modal_pay input[name="nominal_spend"]').val(0);
        $('#modal_pay input[name="date_transaction"]').val('{{ date("Y-m-d") }}');
        $('#modal_pay input[name="reference_detail"]').val('');
        $('#payment_bank').find('option:not([value=""])').remove();
        $('#payment_bank').val('').trigger('change');
        $('#btnRecordPayment').text('Record payment').removeClass('btn-warning').addClass('btn-success');
        $('#btnCancelEditPayment').hide();
    }

    $('#btnCancelEditPayment').on('click', resetPaymentForm);

    $('#btnRecordPayment').on('click', function (e) {
        e.preventDefault();
        const editPaymentId = $('#editPaymentId').val();
        const isEdit = editPaymentId && parseInt(editPaymentId) > 0;
        const send_payment = {
            id               : isEdit ? editPaymentId : null,
            uuid_bank        : $('#modal_pay select[name="uuid_bank"]').val(),
            nominal_spend    : $('#modal_pay input[name="nominal_spend"]').val(),
            reference_detail : $('#modal_pay input[name="reference_detail"]').val(),
            date_transaction : $('#modal_pay input[name="date_transaction"]').val(),
            id_parent_bill   : $('#idHotelInput').val(),
        };
        const route = isEdit ? `{{ route('update-save-pay-bill') }}` : `{{ route('save-pay-bill') }}`;
        ajaxRequest(route, 'POST', send_payment, localStorage.getItem('token'))
            .then(response => {
                if (response.status == 200) {
                    Swal.fire({ icon: 'success', title: isEdit ? 'Berhasil diupdate!' : 'Berhasil disimpan!',
                        text: 'Data pembayaran berhasil ' + (isEdit ? 'diperbarui.' : 'dicatat.'), timer: 1500, showConfirmButton: false
                    }).then(() => { resetPaymentForm(); loadBills($('#idHotelInput').val()); table.ajax.reload(null, false); });
                }
            })
            .catch(err => cathError(err));
    });

    $(document).on('click', '.btn-edit-payment', function () {
        const $btn = $(this);
        $('#editPaymentId').val($btn.data('id'));
        $('#modal_pay input[name="nominal_spend"]').val(parseFloat($btn.data('nominal')));
        $('#modal_pay input[name="date_transaction"]').val($btn.data('date'));
        $('#modal_pay input[name="reference_detail"]').val($btn.data('reference') || '');
        $('#payment_bank').find('option:not([value=""])').remove();
        $('#payment_bank').append(new Option($btn.data('bank-name'), $btn.data('bank-id'), true, true)).trigger('change');
        $('#btnRecordPayment').text('Update Payment').removeClass('btn-success').addClass('btn-warning');
        $('#btnCancelEditPayment').show();
        $('#modal_pay')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    $(document).on('click', '.btn-delete-payment', function () {
        const payId        = $(this).data('id');
        const parentBillId = $('#idHotelInput').val();
        Swal.fire({
            title: 'Hapus Pembayaran?', html: 'Data pembayaran ini akan <strong>dihapus permanen</strong><br>dan nominal tagihan akan dikembalikan.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true
        }).then(result => {
            if (result.isConfirmed) {
                ajaxRequest(`{{ route('delete-pay-bill') }}`, 'POST', { id: payId, id_parent_bill: parentBillId }, localStorage.getItem('token'))
                    .then(response => {
                        if (response.status == 200) {
                            Swal.fire({ icon: 'success', title: 'Dihapus!', text: 'Data pembayaran berhasil dihapus.', timer: 1500, showConfirmButton: false })
                                .then(() => { loadBills(parentBillId); table.ajax.reload(null, false); });
                        }
                    })
                    .catch(err => cathError(err));
            }
        });
    });

    /* =====================================================================
       SELECT2 GLOBAL (contact & bank)
       dropdownParent: $('body') — z-index diatur CSS
       ===================================================================== */
    function initAllSelect2() {
        $('.select2-contact').select2({
            placeholder: 'Cari nama contact...',
            allowClear: true,
            dropdownParent: $('body'),
            ajax: {
                url: "{{ route('list-contact-select2') }}",
                type: 'GET', dataType: 'json', delay: 250,
                data: params => ({ page: params.page || 1, keyword: params.term || '', limit: 10 }),
                processResults: function (response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data.data, item => ({ id: item.id, text: item.full_name, phone: item.phone_number || '-' })),
                        pagination: { more: response.data.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: item => !item.id ? item.text : $(`<span>${item.text} <small class="text-muted">(${item.phone})</small></span>`)
        });
    }
    initAllSelect2();

    function initAllSelectBank() {
        $('.select2-payment-bank').select2({
            placeholder: 'Cari nama bank...',
            allowClear: true, width: '100%',
            dropdownParent: $('body'),
            ajax: {
                url: "{{ route('getbankselect2') }}",
                type: 'GET', dataType: 'json', delay: 250,
                data: params => ({ page: params.page || 1, keyword: params.term || '', limit: 10, kolom_name: 'name' }),
                processResults: function (response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data.data, item => ({ id: item.id, text: item.name, currency_code: item.currency_code || '-' })),
                        pagination: { more: response.data.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: item => !item.id ? item.text : $(`<span>${item.text} <small class="text-muted">(${item.currency_code})</small></span>`)
        });
    }
    initAllSelectBank();

    /* =====================================================================
       LABEL CACHE — satu request per jenis+id, shared antar baris
       ===================================================================== */
    const labelCache = {};
    const token = () => localStorage.getItem('token');

    // getLabel(kind, id, knownLabel)
    // knownLabel: jika sudah diketahui dari data server, langsung resolve tanpa API call
    function getLabel(kind, id, knownLabel) {
        const key = kind + ':' + id;

        if (knownLabel && knownLabel !== id) {
            if (!labelCache[key]) labelCache[key] = Promise.resolve(knownLabel);
            return labelCache[key];
        }

        if (labelCache[key]) return labelCache[key];

        let req;
        if (kind === 'coa') {
            req = ajaxRequest('{{ route("coaDetail") }}', 'GET', { id }, token())
                .then(r => (r.status == 200 && r.data.data) ? (r.data.data.name || String(id)) : String(id));
        } else if (kind === 'item') {
            // Cari berdasarkan code: exact match dulu, fallback ke partial
            req = ajaxRequest('{{ route("list-paket-select2") }}', 'GET', { keyword: id, page: 1, limit: 10 }, token())
                .then(r => {
                    const list = (r.data && r.data.results) || [];
                    const exact   = list.find(x => String(x.code) === String(id));
                    const partial = list.find(x => String(x.code).includes(String(id)));
                    const found   = exact || partial;
                    return found ? found.nama_paket : String(id);
                });
        } else {
            const cat = kind === 'paket' ? 'nama paket' : 'divisi';
            req = ajaxRequest('{{ route("tracking-detail") }}', 'GET', { name_parent_category: cat, id }, token())
                .then(r => {
                    const d = r.status == 200 ? r.data.data : null;
                    return d ? (d.item_name_category || String(id)) : String(id);
                });
        }

        labelCache[key] = req.catch(() => { delete labelCache[key]; return String(id); });
        return labelCache[key];
    }

    /* =====================================================================
       SELECT2 PER BARIS
       - dropdownParent: $('body') untuk semua Select2 per baris
       - Init TIDAK langsung dipanggil saat load massal (deferInit=true)
         → gunakan initAllPendingRows() setelah semua baris selesai
       - Baris BARU (klik user) tetap init langsung
       ===================================================================== */
    function initRowSelect2($row) {
        if ($row.data('s2-ready')) return;
        $row.data('s2-ready', true);

        const p = $row.data('pending') || {};
        const $parent = $('body');

        $row.find('.select2-item').select2({
            placeholder: 'Pilih Item...',
            allowClear: true,
            dropdownParent: $parent,
            ajax: {
                url: '{{ route("list-paket-select2") }}',
                type: 'GET', dataType: 'json', delay: 250,
                data: params => ({ page: params.page || 1, keyword: params.term || '', limit: 5 }),
                processResults: function (response) {
                    if (response.status != 'success') return { results: [] };
                    return {
                        results: $.map(response.data.results, item => ({
                            id: item.code, text: item.nama_paket,
                            harga: item.price_sales, item_code: item.code, paket_id: item.id
                        }))
                    };
                },
                cache: true
            },
            templateResult: item => !item.id ? item.text : $(`<span>${item.text} <small class="text-muted">(${formatCurrency(item.harga)})</small></span>`)
        }).on('select2:select', function (e) {
            const d = e.params.data;
            const $tr = $(this).closest('tr');
            $tr.find('.desc-input').val(d.text || '');
            if (d.harga !== undefined && d.harga !== null) $tr.find('.price-input').val(parseInt(d.harga)).trigger('input');
            $tr.find('.paket-id-input').val(d.paket_id || '');
        }).on('select2:clear', function () {
            $(this).closest('tr').find('.paket-id-input').val('');
        });

        $row.find('.select2-account').select2({
            placeholder: 'Pilih Account...',
            allowClear: true,
            dropdownParent: $parent,
            ajax: {
                url: "{{ route('get-all-coa-select2') }}",
                type: 'GET', dataType: 'json', delay: 250,
                data: params => ({ keyword: params.term || '', page: params.page || 1, type: ['EXPENSE', 'DIRECTCOSTS'] }),
                processResults: function (response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data?.data || [], item => ({ id: item.id, text: item.name, account_type: item.account_type || '-' })),
                        pagination: { more: response.data?.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: item => !item.id ? item.text : $(`<span>${item.text} <small class="text-muted">(${item.account_type})</small></span>`)
        });

        $row.find('.select2-paket').select2({
            placeholder: 'Pilih Paket...',
            allowClear: true,
            dropdownParent: $parent,
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: 'GET', dataType: 'json', delay: 250,
                data: params => ({ keyword: params.term, name_parent_category: 'nama paket' }),
                processResults: function (response) {
                    if (!response.status || !response.data?.lines_category) return { results: [] };
                    return { results: $.map(response.data.lines_category, item => ({ id: item.item_uuid_category || item.id, text: item.item_name_category })) };
                },
                cache: true
            }
        });

        $row.find('.select2-divisi').select2({
            placeholder: 'Pilih Divisi...',
            allowClear: true,
            dropdownParent: $parent,
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: 'GET', dataType: 'json', delay: 250,
                data: params => ({ keyword: params.term, name_parent_category: 'divisi' }),
                processResults: function (response) {
                    if (!response.status || !response.data?.lines_category) return { results: [] };
                    return { results: $.map(response.data.lines_category, item => ({ id: item.item_uuid_category || item.id, text: item.item_name_category })) };
                },
                cache: true
            }
        });

        // Isi label untuk nilai yang sudah ada di DOM.
        // knownLabel diteruskan agar getLabel tidak perlu API call jika data server sudah lengkap.
        if (p.itemCode)  getLabel('item',   p.itemCode,  p.itemLabel).then(l => setSelectValue($row.find('.select2-item'),    p.itemCode,  l));
        if (p.accountId) getLabel('coa',    p.accountId, p.accountLabel).then(l => setSelectValue($row.find('.select2-account'), p.accountId, l));
        if (p.paketId)   getLabel('paket',  p.paketId,   p.paketLabel).then(l => setSelectValue($row.find('.select2-paket'),   p.paketId,   l));
        if (p.divisiId)  getLabel('divisi', p.divisiId,  p.divisiLabel).then(l => setSelectValue($row.find('.select2-divisi'),  p.divisiId,  l));
    }

    /*
     * Batch init — 10 baris per tick agar browser tidak freeze.
     * Dipanggil SETELAH semua addNewRow(item, true) selesai saat loadBills.
     * Baris yang sudah di-init dilewati (cek data jQuery, bukan atribut DOM).
     */
    function initAllPendingRows(callback) {
        const $rows = $('#itemTable tbody tr').filter(function () {
            return !$(this).data('s2-ready');
        });
        if ($rows.length === 0) { if (callback) callback(); return; }

        const CHUNK = 10;
        let idx = 0;
        $('#rowInitOverlay').addClass('show');

        function processChunk() {
            $rows.slice(idx, idx + CHUNK).each(function () { initRowSelect2($(this)); });
            idx += CHUNK;
            if (idx < $rows.length) {
                setTimeout(processChunk, 0);
            } else {
                $('#rowInitOverlay').removeClass('show');
                if (callback) callback();
            }
        }
        setTimeout(processChunk, 0);
    }

    /* ── MODAL EVENTS ── */
    $('#button_add_hotel').on('click', function () {
        $('#modal_pay').addClass('d-none');
        $('#formCreateHotel')[0].reset();
        $('#idHotelInput').val(0);
        $('#ref_id').val('');
        $('#d_id_parent_bill').val(0);
        resetBillModalState();
        lockCurrency(false);
        $('#cur_id').val('IDR').trigger('change');
        $('#nominal_currency').val('');
        $('.modal-title').text('Tambah Invoice / Bill Baru');
    });

    $('#modalCreateHotel').on('show.bs.modal', function () {
        $('.action-submit').prop('disabled', false);
        if ($('#idHotelInput').val() == 0) {
            $('#contact_id').empty().trigger('change');
            destroyRowSelect2($('#itemTable tbody'));
            $('#itemTable tbody').empty();
            addNewRow();
        }
        $('#dropzone-container').hide();
    });

    /* ── EDIT ── */
    $('#tableHotel').on('click', '.edit-hotel', function () {
        const id = $(this).data('id');
        const rowData = table.row($(this).parents('tr')).data();
        $('#idHotelInput').val(id);
        resetBillModalState();
        $('.modal-title').text('Edit Bill ' + (rowData.reference || ''));
        $('#modal_pay input[name="nominal_spend"]').val(0);
        $('#modal_pay select[name="uuid_bank"]').val(0).trigger('change');
        $('#modal_pay input[name="reference_detail"]').val('');
        $('#modalCreateHotel').modal('show');
        $('#modalCreateHotel').one('shown.bs.modal', function () { loadBills(id); loadDropzoneImages(id); });
    });

    // Buka via URL ?open=ID
    const urlParams = new URLSearchParams(window.location.search);
    const openId = urlParams.get('open');
    if (openId) {
        $('#idHotelInput').val(openId);
        $('#modal_pay input[name="nominal_spend"]').val(0);
        $('#modal_pay select[name="uuid_bank"]').val(0).trigger('change');
        $('#modal_pay input[name="reference_detail"]').val('');
        $('#modalCreateHotel').modal('show');
        $('#modalCreateHotel').one('shown.bs.modal', function () { loadBills(openId); loadDropzoneImages(openId); });
    }

    /* ── LOAD BILLS ── */
    function loadBills(id) {
        $('#idHotelInput').val(id);
        destroyRowSelect2($('#itemTable tbody'));
        $('#itemTable tbody').empty();
        $('#contact_id').prop('disabled', true);

        ajaxRequest(`{{ route('detail-bills') }}`, 'GET', { id }, localStorage.getItem('token'))
            .then(response => {
                if (response.status == 200) {
                    const data_res = response.data.data;

                    const contactName = data_res.get_contact_from ? data_res.get_contact_from.full_name : 'Nama tidak ditemukan';
                    $('#label_payment').text(data_res.currency);
                    $('#contact_id').empty().append(new Option(contactName, data_res.uuid_from, true, true)).trigger('change');
                    $('#ref_id').val(data_res.reference || '');

                    if ($('#cur_id option[value="' + data_res.currency + '"]').length === 0) {
                        $('#cur_id').append(new Option(data_res.currency, data_res.currency));
                    }
                    $('#cur_id').val(data_res.currency).trigger('change');
                    if (data_res.currency !== 'IDR') $('#nominal_currency').val(parseFloat(data_res.nominal_currency) || '');
                    syncKurs();
                    lockCurrency(true);
                    if (data_res.currency !== 'IDR' && !(parseFloat(data_res.nominal_currency) > 0)) $('#nominal_currency').prop('disabled', false);

                    $('#date_req').val(data_res.date_req).trigger('change');
                    $('#due_date').val(data_res.due_date).trigger('change');

                    // Semua baris di-append dulu tanpa init Select2 (deferInit=true)
                    const details = data_res.get_detail;
                    if (details && details.length > 0) {
                        details.forEach(item => addNewRow(item, true));
                    } else {
                        addNewRow(null, false);
                    }

                    // Setelah semua baris ada di DOM, batch-init Select2
                    initAllPendingRows();

                    if (data_res.status == 1 || (data_res.get_payment && data_res.get_payment.length > 0)) {
                        $('#modal_pay').removeClass('d-none');
                        if (data_res.status == 2) {
                            $('#btnRecordPayment').closest('.row').hide();
                        } else {
                            $('#btnRecordPayment').closest('.row').show();
                        }
                    } else {
                        $('#modal_pay').addClass('d-none');
                    }

                    const tbody = $('#payment_history_bill tbody').empty();
                    if (data_res.get_payment.length > 0) {
                        $.each(data_res.get_payment, function (index, payment) {
                            tbody.append(`
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${convertStringDate(payment.date_transaction)}</td>
                                    <td>${escapeHtml(payment.name_bank)}</td>
                                    <td>${formatCurrency(payment.nominal_spend)}</td>
                                    <td class="text-center">
                                        <button type="button" style="margin-right:10px !important;"  class="btn btn-xs btn-warning mr-1 btn-edit-payment" title="Edit"
                                            data-id="${payment.id}" data-nominal="${payment.nominal_spend}"
                                            data-date="${payment.date_transaction}" data-bank-id="${payment.uuid_bank}"
                                            data-bank-name="${escapeHtml(payment.name_bank)}"
                                            data-reference="${escapeHtml(payment.reference_detail || '')}">
                                            <i class="ti ti-pencil"></i>
                                        </button>
                                        <button type="button" style="margin-left:10px;" class="btn btn-xs btn-danger btn-delete-payment" title="Hapus" data-id="${payment.id}">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>`);
                        });
                    } else {
                        tbody.append('<tr><td colspan="5" class="text-center py-3 text-muted"><em>No payment history found.</em></td></tr>');
                    }

                    calculateGrandTotal();
                    renderHistoryLog(data_res.get_history_bills);
                }
            })
            .catch(err => { console.error('loadBills error', err); Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error'); })
            .finally(() => $('#contact_id').prop('disabled', false));
    }

    $('.action-submit').on('click', function () { $('#actionTypeValue').val($(this).val()); });

    /* ── SAVE ── */
    $('#formCreateHotel').on('submit', function (e) {
        e.preventDefault();

        const formData = $(this).serialize();
        const params = new URLSearchParams(formData);
        const idInput = params.get('idHotelInput');
        const id_bill = (idInput && idInput > 0) ? idInput : null;
        const action_selected = params.get('action_type');

        const getVals = selector => $(selector).map(function () { const v = $(this).val(); return v === null || v === undefined ? '' : v; }).get();

        const selectedData = {
            item_code                  : $('select[name="item_code[]"]').map(function () { return $(this).val(); }).get(),
            item_id_prod_serv          : $('input[name="item_id_prod_serv[]"]').map(function () { return $(this).val(); }).get(),
            id: id_bill,
            uuid_from                  : params.get('uuid_from'),
            date_req                   : params.get('date_req'),
            due_date                   : params.get('due_date'),
            reference                  : params.get('reference'),
            currency                   : $('#cur_id').val(),
            nominal_currency           : $('#nominal_currency').val(),
            action_save                : action_selected,
            account_id                 : $('select[name="account_id[]"]').map(function () { return $(this).val(); }).get(),
            desc                       : getVals('.desc-input'),
            qty                        : $('input[name="qty[]"]').map(function () { return $(this).val(); }).get(),
            unit_price                 : $('input[name="unit_price[]"]').map(function () { return $(this).val(); }).get(),
            tax_rate                   : $('input[name="tax_rate[]"]').map(function () { return $(this).val(); }).get(),
            paket_tracking_uuid        : $('select[name="nama_paket[]"]').map(function () { return $(this).val(); }).get(),
            divisi_travel_tracking_uuid: $('select[name="divisi[]"]').map(function () { return $(this).val(); }).get(),
            id_detail                  : $('input[name="id_detail[]"]').map(function () { return $(this).val(); }).get(),
        };

        if (!id_bill && $('#cur_id').val() === 'SAR' && (parseFloat($('#nominal_currency').val()) || 0) <= 0) {
            Swal.fire('Peringatan', 'Isi kurs 1 SAR = berapa Rupiah.', 'warning');
            return;
        }

        ajaxRequest(`{{ route('save-p-bills') }}`, 'POST', selectedData, localStorage.getItem('token'))
            .then(response => {
                if (response.status == 200) {
                    lockCurrency(true);
                    const savedInvoiceId = (id_bill && id_bill > 0) ? id_bill : response.data.data.id;
                    $('#idHotelInput').val(savedInvoiceId);
                    $('.modal-title').text('Edit Bill ' + (params.get('reference') || savedInvoiceId));
                    table.ajax.reload(null, false);
                    if (myDropzone.getQueuedFiles().length > 0) {
                        $('.action-submit').prop('disabled', true);
                        myDropzone.processQueue();
                    } else {
                        Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Data berhasil disimpan.', timer: 1500, showConfirmButton: false })
                            .then(() => loadBills(savedInvoiceId));
                    }
                }
            })
            .catch(err => cathError(err));
    });

    /* ── GLOBAL HELPERS ── */
    window.switchToDetailTab = function () {
        const el = document.getElementById('detailTab');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.removeRow = function (btn) {
        const $tr = $(btn).closest('tr');
        destroyRowSelect2($tr);
        $tr.remove();
        calculateGrandTotal();
    };

    /*
     * addNewRow(item, deferInit)
     * - deferInit=true  → TIDAK init Select2 sekarang (akan di-batch via initAllPendingRows)
     * - deferInit=false → init langsung (untuk baris baru yang diklik user)
     */
    window.addNewRow = function (item = null, deferInit = false) {
        const rowCount    = $('#itemTable tbody tr').length + 1;
        const idDetailRow = item ? item.id : 0;
        const desc        = item ? (item.desc || '') : '';
        const qty         = item ? item.qty : 1;
        const price       = item ? parseFloat(item.unit_price) : '';
        const taxRate     = item ? (item.tax_rate !== null ? item.tax_rate : 0) : 0;
        const amount      = item ? parseFloat(item.amount) : '';

        const itemCode    = item ? (item.item_code || '') : '';
        const itemLabel   = item ? (item.nama_paket || item.item_name || item.item_label || item.name || '') : '';

        const accountId    = item ? (item.account_id_coa || '') : '';
        const accountLabel = item ? (item.coa_name || item.account_name || (item.get_coa && item.get_coa.name) || '') : '';

        const paketId     = item ? (item.paket_tracking_uuid || '') : '';
        const paketLabel  = item ? (item.paket_name || item.tracking_paket_name || '') : '';

        const divisiId    = item ? (item.divisi_travel_tracking_uuid || '') : '';
        const divisiLabel = item ? (item.divisi_name || item.tracking_divisi_name || '') : '';

        const selOpt = (id, label) => id
            ? `<option value="${escapeHtml(id)}" selected>${escapeHtml(label || id)}</option>`
            : '';

        const newRow = `
            <tr>
                <td class="text-center">
                    ${rowCount}
                    <input type="hidden" name="id_detail[]" value="${idDetailRow}"/>
                </td>
                <td>
                    <select class="select2-item form-control" required name="item_code[]" style="width:100%;">
                        <option value="">Pilih Item...</option>
                        ${selOpt(itemCode, itemLabel)}
                    </select>
                    <input type="hidden" name="item_id_prod_serv[]" class="paket-id-input"
                           value="${item && item.item_id_prod_serv ? item.item_id_prod_serv : ''}">
                </td>
                <td><textarea class="form-control desc-input" required name="description[]" rows="4" cols="50" placeholder="Deskripsi item">${escapeHtml(desc)}</textarea></td>
                <td><input type="number" class="form-control qty-input" required name="qty[]" min="1" style="width:70px;" value="${qty}"></td>
                <td><input type="number" class="form-control price-input" required name="unit_price[]" min="1" step="0.01" value="${price}"></td>
                <td>
                    <select class="select2-account form-control" required name="account_id[]" style="width:100%;">
                        <option value="">Pilih Account...</option>
                        ${selOpt(accountId, accountLabel || accountId)}
                    </select>
                </td>
                <td><input type="text" class="form-control" name="tax_rate[]" value="${taxRate}"></td>
                <td>
                    <select class="select2-paket form-control" name="nama_paket[]" style="width:100%;">
                        <option value="">Pilih Paket...</option>
                        ${selOpt(paketId, paketLabel || paketId)}
                    </select>
                </td>
                <td>
                    <select class="select2-divisi form-control" name="divisi[]" style="width:100%;">
                        <option value="">Pilih Divisi...</option>
                        ${selOpt(divisiId, divisiLabel || divisiId)}
                    </select>
                </td>
                <td><input type="text" class="form-control text-right amount-row" name="amount[]" value="${amount}" readonly></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button></td>
            </tr>`;

        $('#itemTable tbody').append(newRow);

        const $lastRow = $('#itemTable tbody tr:last');
        $lastRow.data('pending', {
            itemCode, itemLabel,
            accountId, accountLabel,
            paketId, paketLabel,
            divisiId, divisiLabel,
        });

        if (!deferInit) {
            initRowSelect2($lastRow);
        }

        const $qty       = $lastRow.find('input[name="qty[]"]');
        const $unitPrice = $lastRow.find('input[name="unit_price[]"]');
        const $amount    = $lastRow.find('input[name="amount[]"]');

        function calculateAmount() {
            $amount.val((( parseFloat($qty.val()) || 0 ) * ( parseFloat($unitPrice.val()) || 0 )).toFixed(2));
            calculateGrandTotal();
        }

        $qty.on('input keyup', calculateAmount);
        $unitPrice.on('input keyup', calculateAmount);
        if (!item) calculateAmount();
    };

    window.calculateGrandTotal = function () {
        let total_grand = 0;
        $('#itemTable tbody tr').each(function () {
            total_grand += parseFloat($(this).find('input[name="amount[]"]').val()) || 0;
        });

        const currency         = $('#cur_id').val();
        const nominal_currency = Number($('#nominal_currency').val()) || 0;
        const tot_rpp          = nominal_currency * total_grand;

        let $tfoot = $('#itemTable tfoot');
        if ($tfoot.length === 0) { $('#itemTable').append('<tfoot></tfoot>'); $tfoot = $('#itemTable tfoot'); }

        let html = `
            <tr class="table-info">
                <td colspan="9" class="text-right font-weight-bold">Total Amount</td>
                <td class="text-right font-weight-bold" id="grandTotal" style="font-size:1.1em;">${formatCurrency(total_grand)}</td>
                <td></td>
            </tr>`;

        if (currency === 'SAR') {
            html += `
                <tr id="rowTotalRupiah">
                    <td colspan="9" class="text-right font-weight-bold">Total Amount Rupiah</td>
                    <td class="text-right font-weight-bold" id="totalAmountRupiah" style="font-size:1.1em;">${formatCurrency(tot_rpp)}</td>
                    <td></td>
                </tr>`;
        }
        $tfoot.html(html);
    };

    function renderHistoryLog(historyArr) {
        const $list = $('#history_log_list').empty();
        if (!historyArr || historyArr.length === 0) {
            $list.append('<li class="list-group-item text-muted">Belum ada riwayat.</li>');
            return;
        }
        historyArr.forEach(h => {
            const action = $('<div>').text(`${convertStringDate(h.created_at)} | ${h.action}` || '-').html();
            $list.append('<li class="list-group-item">' + action + '</li>');
        });
    }

    function resetBillModalState() {
        $('#payment_history_bill tbody').html('<tr><td colspan="5" class="text-center py-3 text-muted"><em>No payment history found.</em></td></tr>');
        $('#history_log_list').html('<li class="list-group-item text-muted">Belum ada riwayat.</li>');
        resetPaymentForm();
        $('#label_payment').text('');
        if (myDropzone) { isClearingDropzone = true; myDropzone.removeAllFiles(true); isClearingDropzone = false; }
        $('#dropzone-container').hide();
    }

    /* ── TABEL UTAMA ── */
    function loadTable(status_type) {
        if ($.fn.DataTable.isDataTable('#tableHotel')) $('#tableHotel').DataTable().destroy();
        table = initGlobalDataTableTokenSelected(
            '#tableHotel',
            `{{ route('purchase-bills') }}`,
            columnBills,
            {
                kolom_name : 'reference',
                status     : status_type,
                date_start : $('#filter_date_start').val() || '',
                date_end   : $('#filter_date_end').val() || ''
            }
        );
    }

    $('#filter_date_start, #filter_date_end').on('change', function () {
        const s = $('#filter_date_start').val();
        const e = $('#filter_date_end').val();
        if (s && e && s > e) { Swal.fire('Perhatian', 'Tanggal "Dari" tidak boleh lebih besar dari "Sampai".', 'warning'); $(this).val(''); }
        loadTable($('#filter_status').val());
    });

    loadTable($('#filter_status').val());
    $('#filter_status').on('change', function () { loadTable($(this).val()); });

    /* ── ADD CONTACT ── */
    function setContactSelect2Value(id, text) {
        const $el = $('#contact_id');
        $el.find('option[value!=""]').remove();
        $el.append(new Option(text, id, true, true)).trigger('change');
    }

    $('#btn-add-item-contact').on('click', () => $('#modalCreateJamaah').modal('show'));

    $('#modalCreateJamaah').on('show.bs.modal', function () {
        const z = 1070;
        $(this).css('z-index', z);
        setTimeout(() => $('.modal-backdrop').last().css('z-index', z - 1), 0);
    });

    $('#modalCreateJamaah').on('shown.bs.modal', () => $('#full_name').trigger('focus'));

    $('#modalCreateJamaah').on('hidden.bs.modal', function () {
        $('#id_jamaah, #full_name, #phone_number, #nik, #detail_address').val('');
        $('#btn_save_jamaah').prop('disabled', false).text('Simpan');
        if ($('#modalCreateHotel').hasClass('show')) $('body').addClass('modal-open');
    });

    $('#btn_save_jamaah').on('click', function () {
        const payload = {
            id: $('#id_jamaah').val() || null,
            full_name      : $('#full_name').val().trim(),
            phone_number   : $('#phone_number').val().trim(),
            nik            : $('#nik').val().trim(),
            detail_address : $('#detail_address').val().trim(),
        };
        if (!payload.full_name || !payload.phone_number) { Swal.fire('Peringatan', 'Nama lengkap dan No. Telepon wajib diisi', 'warning'); return; }
        const $btn = $(this).prop('disabled', true).text('Menyimpan...');
        ajaxRequest(`{{ route('save-jamaah') }}`, 'POST', payload, localStorage.getItem('token'))
            .then(response => {
                if (response.status == 200) {
                    const saved = response.data?.data || response.data || {};
                    $('#modalCreateJamaah').modal('hide');
                    Swal.fire({ title: 'Berhasil', text: 'Data contact tersimpan', icon: 'success', timer: 1500, showConfirmButton: false });
                    if (saved.id) setContactSelect2Value(saved.id, saved.full_name || payload.full_name);
                } else {
                    Swal.fire('Gagal', response?.data?.data?.message || 'Gagal menyimpan data contact', 'error');
                }
            })
            .catch(err => cathError(err))
            .finally(() => $btn.prop('disabled', false).text('Simpan'));
    });
    /**/
        /* ── TOMBOL SCROLL DOWN (fixed) ── */
   $('#btnScrollUp, #btnScrollDown').appendTo('body');

    function showScrollButtons() {
        $('#btnScrollUp, #btnScrollDown').addClass('show');
    }

    $('#modalCreateHotel').on('show.bs.modal', showScrollButtons);
    $('#modalCreateHotel').on('shown.bs.modal', showScrollButtons);

    $('#modalCreateHotel').on('hidden.bs.modal', function () {
        $('#btnScrollUp, #btnScrollDown').removeClass('show');
    });

    function smoothScrollModal(top) {
        const el = document.getElementById('modalCreateHotel');
        if (typeof el.scrollTo === 'function') {
            el.scrollTo({ top: top, behavior: 'smooth' });
        } else {
            el.scrollTop = top; // fallback browser lama
        }
    }

    $('#btnScrollUp').on('click', function () {
        smoothScrollModal(0);
    });

    $('#btnScrollDown').on('click', function () {
        const el = document.getElementById('modalCreateHotel');
        smoothScrollModal(el.scrollHeight);
    });
});
</script>
@endpush