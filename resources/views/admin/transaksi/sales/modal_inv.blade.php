{{-- ===================================================================== --}}
{{-- MODAL CREATE INVOICE — XERO STYLE — LARAVEL 8 — FIXED                --}}
{{-- ===================================================================== --}}

<style>

</style>

<div class="modal fade" id="modalCreateHotel" tabindex="-1" role="dialog"
     aria-labelledby="modalCreateHotelLabel" aria-hidden="true">

    {{-- modal-dialog dikendalikan CSS di atas --}}
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">

            {{-- ── TOPBAR ── --}}
            <div class="xero-modal-header">
                <div class="breadcrumb-nav">
                    <i class="ti ti-chevron-left" style="font-size:12px;"></i>
                    <span>Sales overview &rsaquo; </span><b>Invoices</b>
                </div>
                <div class="header-actions">
                    {{-- <button type="button" class="btn-xero-ghost" data-dismiss="modal">
                        Save &amp; close
                    </button> --}}
                    <div class="d-flex">
                        {{-- <button type="button" class="btn-xero-approve" id="btnApproveTop">
                            Approve
                        </button>
                        <button type="button" class="btn-xero-approve-caret">
                            <i class="ti ti-chevron-down" style="font-size:11px;"></i>
                        </button> --}}
                    </div>
                    <span style="color:#9bb4cc;font-size:18px;cursor:pointer;">&#8942;</span>
                </div>
            </div>

            {{-- ── BANNER ── --}}
            <div class="xero-banner">
               
                <button class="btn-add-pay" id="btn_add_payment_inv">
                    <i class="ti ti-credit-card" style="font-size:11px;"></i> Add payments
                </button>
            </div>

            {{-- ── FORM ── --}}
            <form id="formCreateHotel">
                @csrf
                <div class="modal-body" style="padding: 20px 26px;">

                    <div class="invoice-title-row">
                        <h5 id="modalInvoiceTitle">New invoice</h5>
                        <span class="badge-draft" id="invoiceStatusBadge">Draft</span>
                    </div>

                    <input type="hidden" name="idHotelInput" id="idHotelInput">

                    {{-- ── BARIS 1: Contact | Issue date | Due date | Invoice no ── --}}
                    <div class="xero-fields-grid">
                        <div class="xero-field">
                            <label>Contact <span class="text-danger">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-user"></i>
                                <select class="form-control select2-contact"
                                        name="contact_id" id="contact_id"
                                        style="width:100%;" required>
                                </select>
                            </div>
                        </div>

                        <div class="xero-field">
                            <label>Issue date <span class="text-danger">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-calendar"></i>
                                <input type="date" class="form-control"
                                       name="issue_date" id="issue_date" required>
                            </div>
                        </div>

                        <div class="xero-field">
                            <label>Due date</label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-calendar"></i>
                                <input type="date" class="form-control"
                                       name="due_date" id="due_date">
                            </div>
                        </div>

                        <div class="xero-field">
                            <label>Invoice number</label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-hash"></i>
                                <input type="text" class="form-control"
                                       id="invoice_number_display"
                                       placeholder="Auto" readonly
                                       style="background:#f9f9f9;">
                            </div>
                        </div>
                    </div>

                    {{-- ── BARIS 2: Reference | Currency | Amounts are ── --}}
                    <div class="xero-row-2">
                        <div class="xero-field">
                            <label>Reference</label>
                            <div class="input-icon-wrap">
                                <i class="ti ti-bookmark"></i>
                                <input type="text" class="form-control"
                                       name="reference" id="reference">
                            </div>
                        </div>

                        <div class="xero-field">
                            <label>Currency <span class="text-danger">*</span></label>
                            <select class="form-control" name="currency_selected"
                                    id="currency_selected" required>
                                <option value="IDR" selected>IDR &ndash; Rupiah</option>
                                <option value="SAR">SAR &ndash; Saudi Riyal</option>
                            </select>
                        </div>

                        <div class="xero-field">
                            <label>Amounts are</label>
                            <select class="form-control" name="amount_are" id="amount_are">
                                <option value="0" selected>No Tax</option>
                                <option value="1">Tax Exclusive</option>
                                <option value="2">Tax Inclusive</option>
                            </select>
                        </div>
                    </div>

                    {{-- ── KURS ALERT ── --}}
                    <div class="kurs-alert">
                        <i class="ti ti-info-circle" style="font-size:15px;color:#f0a500;"></i>
                        <span><strong>Info Kurs:</strong> Harga 1 SAR saat ini diestimasi
                            <b id="text_currency">–</b>
                        </span>
                    </div>

                    {{-- ── LINE ITEMS TABLE ── --}}
                    <div class="line-items-wrap">
                        <table class="line-items-table" id="lineItemsTable">
                            <colgroup>
                                <col style="width:28px;">      {{-- drag --}}
                                <col style="width:200px;">     {{-- Item --}}
                                <col style="width:180px;">     {{-- Desc --}}
                                <col style="width:62px;">      {{-- Qty --}}
                                <col style="width:140px;">     {{-- Price --}}
                                <col style="width:66px;">      {{-- Disc --}}
                                <col style="width:160px;">     {{-- Account --}}
                                <col style="width:100px;">     {{-- Tax rate --}}
                                <col style="width:88px;">      {{-- Tax amt --}}
                                <col style="width:110px;">     {{-- Amount IDR --}}
                                <col style="width:150px;">     {{-- Nama Paket --}}
                                <col style="width:130px;">     {{-- Divisi --}}
                                <col style="width:28px;">      {{-- delete --}}
                            </colgroup>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th class="text-center">Qty</th>
                                    <th>Price</th>
                                    <th>Disc.</th>
                                    <th>Account</th>
                                    <th>Tax rate</th>
                                    <th class="col-r">Tax amt</th>
                                    <th class="col-r">Amount IDR</th>
                                    <th>Nama Paket</th>
                                    <th>Divisi</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="lineItemsBody">
                                {{-- diisi JS --}}
                            </tbody>
                        </table>
                    </div>

                    <div class="table-actions">
                        <button type="button" class="btn-dashed" id="btn-add-row">
                            <i class="ti ti-plus" style="font-size:12px;"></i> Add row
                        </button>
                    </div>

                    {{-- ── SUMMARY ── --}}
                    <div class="invoice-summary">
                        <table class="summary-table">
                            <tr>
                                <td>Subtotal (SAR)</td>
                                <td id="summarySubtotalSAR">–</td>
                            </tr>
                            <tr>
                                <td>Subtotal (IDR)</td>
                                <td id="summarySubtotalIDR">Rp 0</td>
                            </tr>
                            <tr>
                                <td>Total tax</td>
                                <td id="summaryTax">0.00</td>
                            </tr>
                            <tr class="row-total">
                                <td>Total</td>
                                <td id="summaryTotal">Rp 0</td>
                            </tr>
                        </table>
                    </div>

                    {{-- ── PAYMENT HISTORY (tampil saat edit, diisi JS) ── --}}
                    <div id="paymentHistorySection">
                        <div class="pay-history-wrap d-none">

                            {{-- Header --}}
                            <div class="pay-history-head">
                                <span>
                                    <i class="ti ti-history" style="font-size:13px; vertical-align:-2px; margin-right:5px;"></i>
                                    Riwayat Pembayaran
                                </span>
                                <small id="payHistoryCount"></small>
                            </div>

                            {{-- Table --}}
                            <div class="table-responsive" style="margin:0;">
                                <table class="pay-history-table">
                                    <thead>
                                        <tr>
                                            <th style="width:32px;" class="text-center">No</th>
                                            <th>Tanggal</th>
                                            <th>Akun Bank</th>
                                            <th>Reference</th>
                                            <th class="text-right">Nominal</th>
                                        </tr>
                                    </thead>
                                    <tbody id="paymentHistoryBody">
                                        {{-- diisi JS --}}
                                    </tbody>
                                    <tfoot id="paymentHistoryFoot" style="display:none;">
                                        <tr>
                                            <td colspan="4" class="text-right" style="color:#555;">
                                                Total Dibayar
                                            </td>
                                            <td class="text-right" id="payHistoryTotal"
                                                style="color:#1ab394;"></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-right" style="color:#555;">
                                                Sisa Tagihan
                                            </td>
                                            <td class="text-right" id="payHistoryRemaining"
                                                style="color:#e74c3c;"></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
{{-- /.paymentHistorySection --}}

                    <div class="payment-form-section" id="paymentFormSection">
                        <div class="payment-form-inner">

                            {{-- Title bar --}}
                            <div class="payment-form-title">
                                <i class="ti ti-credit-card" style="font-size:15px; color:#1ab394;"></i>
                                <span>Add Payment</span>
                                <button type="button" class="btn-close-payment" id="btnClosePayment"
                                        title="Tutup">&times;</button>
                            </div>

                            {{-- Fields sejajar --}}
                            <div class="payment-fields-grid">

                                {{-- Date Paid --}}
                                <div class="xero-field">
                                    <label>Date Paid <span class="text-danger">*</span></label>
                                    <div class="input-icon-wrap">
                                        <i class="ti ti-calendar"></i>
                                        <input type="date" class="form-control"
                                            name="date_transaction" id="date_paid">
                                    </div>
                                </div>

                                {{-- Account Bank --}}
                                <div class="xero-field">
                                    <label>Account Bank <span class="text-danger">*</span></label>
                                    <select class="form-control select2-account-bank"
                                            name="uuid_bank" id="account_bank"
                                            style="width:100%;">
                                        <option value="">-- Pilih Akun --</option>
                                        {{-- populate via JS / AJAX --}}
                                    </select>
                                </div>

                                {{-- Amount Paid --}}
                                <div class="xero-field">
                                    <label>Amount Paid <span class="text-danger">*</span></label>
                                    <div class="input-icon-wrap">
                                        <i class="ti ti-cash"></i>
                                        <input type="number" class="form-control"
                                            name="nominal_receive" id="amount_paid"
                                            min="0" step="0.01" placeholder="0.00">
                                    </div>
                                </div>

                                {{-- Reference --}}
                                <div class="xero-field">
                                    <label>Reference</label>
                                    <div class="input-icon-wrap">
                                        <i class="ti ti-bookmark"></i>
                                        <input type="text" class="form-control"
                                            name="reference_detail" id="payment_reference"
                                            placeholder="Optional">
                                    </div>
                                </div>

                            </div>{{-- /.payment-fields-grid --}}

                            {{-- Actions --}}
                            <div class="payment-form-footer">
                                <button type="button" class="btn-payment-cancel" id="btnCancelPayment">
                                    Batal
                                </button>
                                <button type="button" class="btn-payment-save" id="btnSavePayment">
                                    <i class="ti ti-check" style="font-size:12px;"></i>
                                    Save Payment
                                </button>
                            </div>

                        </div>{{-- /.payment-form-inner --}}
                    </div>

                </div>{{-- /.modal-body --}}

                <div class="modal-footer bg-white"
                     style="border-top:1px solid #eee; padding:10px 22px;">
                    <input type="hidden" name="action_type" id="actionTypeValue" value="">
                    <button type="button" class="btn btn-secondary btn-sm"
                            data-dismiss="modal">
                        <i class="ti ti-x mr-1"></i> Batal
                    </button>
                    <div class="btn-group dropup">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Save
                        </button>
                        <div class="dropdown-menu dropdown-menu-right shadow">
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
            </form>

        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════
     PAYMENT FORM — appended, di bawah save button
     Toggle via #btn_add_payment_inv (sudah ada di banner)
     ═══════════════════════════════════════════════════════════════════ --}}
{{-- /#paymentFormSection --}}