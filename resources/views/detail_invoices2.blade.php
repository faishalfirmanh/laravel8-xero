
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xero Clone Invoice Table</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
     <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css" rel="stylesheet">00
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
<meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { background-color: #f6f7f8; padding: 20px; }
        .card { border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-invoice th { background-color: #f1f3f5; font-size: 13px; font-weight: 600; border-top: none; }
        .table-invoice td { vertical-align: middle; padding: 5px; }
        .table-invoice .form-control { border: 1px solid #dee2e6; font-size: 13px; height: 38px; }
        .table-invoice .form-control:focus { border-color: #00b0ff; box-shadow: none; }
        .btn-add-row { font-size: 13px; font-weight: 600; color: #007bff; background: none; border: none; padding: 10px 0; }
        .totals-section { margin-top: 20px; border-top: 2px solid #ddd; padding-top: 10px; }
        .total-row { font-weight: bold; font-size: 18px; }
    </style>
</head>
<body>




<div class="container-fluid">
    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">
                Edit Invoice: <span id="headerInvoiceNo">...</span>
                <span id="status_header"></span>
            </h4>

            <div class="d-flex" style="gap: 30px;">
                <button class="btn btn-primary btn-sm" onclick="fetchDataDummy()">
                    <i class="fas fa-sync"></i> Load Data from API
                </button>

                <button class="btn btn-success btn-sm" onclick="syncPayment()">
                    <i class="fas fa-sync"></i> Synchronize Payment
                </button>
            </div>

            <input type="hidden" id="val_status"/>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <label>To</label>
                <input type="text" id="contactName" disabled class="form-control" value="">
            </div>
            <div class="col-md-2">
                <label>Date</label>
                <input type="date" id="invoiceDate" disabled class="form-control" value="">
            </div>
            <div class="col-md-2">
                <label>Due Date</label>
                <input type="date" id="dueDate" disabled class="form-control" value="">
            </div>
            <div class="col-md-2">
                <label>Invoice #</label>
                <input type="text" id="invoiceNumber" disabled class="form-control" value="">
                <input type="hidden" id="invoiceCodeParent"/>
            </div>
        </div>

        <form id="invoiceForm">
            <div class="table-responsive">
                <table class="table table-bordered table-invoice" id="invoiceTable">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Item</th>
                            <th>Description</th>
                            <th style="width: 80px;">Qty <span class="text-danger">*</span></th>
                            <th style="width: 130px;">Price</th>
                            <th style="width: 80px;">Disc (Rp)</th>
                            <th style="width: 150px;">Account</th>
                            <th style="width: 120px;">Tax Rate</th>
                            <th style="width: 100px;">Tax Amt</th>
                            <th style="width: 120px;">Agen</th>
                            <th style="width: 100px;">Divisi</th>
                            <th style="width: 150px;">Amount IDR</th>
                            <th style="width: 50px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceTableBody">
                        <tr>
                            <td><select class="form-control item-select"><option>Select Item</option></select></td>
                            <td><input type="text" disabled class="form-control"></td>
                            <td><input type="number" class="form-control qty" min="1" value="1"></td>
                            <td><input type="number" disabled class="form-control price" value="0"></td>
                            <td><input type="number" class="form-control disc" value="0" max="99"></td>
                            <td><select class="form-control account" disabled><option>Select</option></select></td>
                            <td><select class="form-control tax-rate"><option value="0">0%</option></select></td>
                            <td><input type="number" class="form-control tax-amount" value="0"></td>
                            <td><select class="form-control"><option>None</option></select></td>
                            <td><select class="form-control"><option>None</option></select></td>
                            <td><input type="text" class="form-control amount" readonly value="0"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <button type="button" class="btn-add-row" id="addRowBtn">
                        <i class="fas fa-plus"></i> Add a new line
                    </button>
                    <br>
                    {{-- <button type="submit" class="btn btn-success mt-3">Save Invoice</button> --}}
                </div>
                <div class="col-md-6 text-right totals-section">
                    <div class="row">
                        <div class="col-8">Subtotal</div>
                        <div class="col-4" id="subTotalDisplay">0.00</div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-8">Total Tax</div>
                        <div class="col-4" id="taxTotalDisplay">0.00</div>
                    </div>
                    <div id="div_payemnts">
                        <div class="row mt-2">
                            <div class="col-8"></div>
                            <div class="col-4"></div>
                        </div>
                    </div>
                    <div class="row mt-3 total-row">
                        <div class="col-8">Total IDR</div>
                        <div class="col-4" id="grandTotalDisplay">0.00</div>
                    </div>
                    <div class="row mt-3 total-row">
                        <div class="col-8">Amount Paid</div>
                        <div class="col-4" id="TotalAmountPaidDisplay">0.00</div>
                    </div>
                     <div class="row mt-3 total-row"  style="border-bottom: 3px solid #000;">
                        <div class="col-8" style="color:red">Amount Due</div>
                        <div class="col-4" id="TotalAmountDueDisplay">0.00</div>
                    </div>
                    <div id="history_local_payments">
                        <h5>Local history </h5>
                    </div>
                    <div class="row mt-3">
                        <div class="col-8">Amount Paid Xero</div>
                        <div class="col-4" id="TotalAmountXero">0.00</div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-8">Amount Paid History</div>
                        <div class="col-4" id="TotalAmountLocal">0.00</div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-8">Amount Return price</div>
                        <div class="col-4" id="TotalAmountReturn">0.00</div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
{{-- <script src="{{ asset('assets/js/detail_invoices.js?v.12') }}"></script> --}}

<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // --- VARIABEL GLOBAL ---
    let fullUrl = window.location.href;
    let rawId = fullUrl.split('/').pop();
    let code_invoice = rawId.split('?')[0].split('#')[0]; // Ambil ID dari URL
    // Jika code_invoice kosong atau error, ambil dari hidden input jika ada, atau biarkan

    var BASE_URL = "{{ url('/') }}";

    let list_agent = [];
    let list_tax_rate = [];
    let list_divisi = [];
    let list_account = [];
    let availableProducts = [];
    let available_products_not_same = [];
    let list_item_in_currentsRows = [];

    // --- 1. LOAD MASTER DATA ---
    $(document).ready(function() {
        getAgent();
        getDevisi();
        getDataAccount();
        getAllTaxRate();
        getAllitems();
        getAllitemsNotSame();
    });

    function   getAllitemsNotSame(){
        $.ajax({
            url: `${BASE_URL}/api/xero-integrasi/get-product-withoutsame`,
            type: 'GET',
            dataType: 'json',
            data: {
                invoice_id : $("#invoiceCodeParent").val()
            },
            success: function (response) {
                if (response.Items) {
                    available_products_not_same =  response.Items;
                }
            }
        });
    }

    function getAllitems(){
         $.ajax({
            url: `${BASE_URL}/api/xero-integrasi/get-data-no-limit`,
            type: 'GET',
            dataType: 'json',
            data: {
                invoice_id : $("#invoiceCodeParent").val()
            },
            success: function (response) {
                if (response.Items) {
                    availableProducts =  response.Items;
                }
            }
        });
    }

    function getAllTaxRate(){
         $.ajax({
            url: `${BASE_URL}/api/xero-integrasi/tax_rate`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if(response.TaxRates) list_tax_rate = response.TaxRates;
            }
        });
    }

    function getDevisi(){
         $.ajax({
            url: `${BASE_URL}/api/xero-integrasi/get_divisi`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                list_divisi = response[0];
            }
        });
    }

    function getAgent(){
         $.ajax({
            url: `${BASE_URL}/api/xero-integrasi/get_agent`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
               // console.log("list agent",response)
                list_agent = response[0];
            }
        });
    }

    function getDataAccount(){
         $.ajax({
            url: `${BASE_URL}/api/xero-integrasi/getAllAccount`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                list_account = response.GroupedAccounts;
            }
        });
    }

    function formatJsonDate(jsonDate) {
        // Ambil angka timestamp menggunakan Regex
        const timestamp = parseInt(jsonDate.match(/\d+/)[0]);
        const date = new Date(timestamp);

        // Format ke Indonesia (DD MMMM YYYY) atau YYYY-MM-DD sesuai selera
        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }

    function formatDateStringToText(tgl){
        const date = new Date(tgl);
        const hasil = date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
        return hasil;
    }
    // 3. Fungsi Format Rupiah
    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(angka);
    }

    // --- 2. GENERATE HTML TABLE ROW ---
  function generateRowHtml(item = null) {
        const isNew = item === null;
        const lineItemId = isNew ? "" : (item.LineItemID || "");

        const itemCode = isNew ? "" : (item.ItemCode || "");
        const desc = isNew ? "" : (item.Description || "");
        const qty = isNew ? 1 : (item.Quantity || 0);
        const price = isNew ? 0 : (item.UnitAmount || 0);
        const accCode = isNew ? "200" : (item.AccountCode || "200");
        const taxAmt = isNew ? 0 : (item.TaxAmount || 0);
        const lineAmt = isNew ? 0 : (item.LineAmount || 0);

        let discNominal = 0;
        if(!isNew && item.DiscountRate) {
            discNominal = (qty * price * item.DiscountRate) / 100;
        }

        // --- ACCOUNT ---
        let items_option_account = `<option value="">Select Account</option>`;
        list_account.forEach(account => {
            const a_code = account.Code;
            const a_name = `${account.Code} - ${account.Name}`;
            const a_selected = (a_code == accCode) ? 'selected' : '';
            items_option_account += `<option value="${a_code}" ${a_selected}>${a_name}</option>`;
        });

        // --- ITEM ---
      let itemOptionsHtml = `<option value="">Select Item</option>`;
       if(item != null){
            availableProducts.forEach(product => {//saat load pertama data
                //console.log('aaa',product)
                const pCode = product.Code;
                const pName = product.Name;
                const isSelected = (itemCode == pCode) ? 'selected' : '';
                itemOptionsHtml += `<option value="${pCode}" ${isSelected}>${pName}</option>`;
            });
       }else{
            available_products_not_same.forEach(product => {//saat nambah data baru
                // console.log('bbb',product)
                const pCode = product.ItemID;
                const pName = product.Name;
                const isSelected = (itemCode == pCode) ? 'selected' : '';
                itemOptionsHtml += `<option value="${pCode}">${pName}</option>`;
            });
       }



        // --- TAX RATE ---
        let itemOptionsTaxRate = `<option value="" data-rate="0">Select Tax</option>`;
        list_tax_rate.forEach(tx => {
            const code_tax = tx.TaxType;
            const name_tax = tx.Name;
            let rateValue = 0;
            if(name_tax.toLowerCase().includes('ppn')) rateValue = 11;

            const isSelected = item != null ? (code_tax == item.TaxType) ? 'selected' : '' : '';
            itemOptionsTaxRate += `<option value="${code_tax}" data-rate="${rateValue}" ${isSelected}>${name_tax}</option>`;
        });

        // --- AGENT & DIVISI (FIXED LOGIC) ---

        // 1. Cari nilai Agent/Divisi yang tersimpan di Xero (Tracking Category)
        let savedAgentId = "";
        let savedDivisiId = "";

        if (item && item.Tracking && item.Tracking.length > 0) {
            item.Tracking.forEach(track => {
                // Sesuaikan 'Name' dengan nama Kategori persis di Xero Anda (Case Sensitive)
                if (track.Name === "Agent" || track.Name === "Agen") {
                    // Xero kadang mengembalikan OptionID, kadang Option (Nama).
                    // Kita cari ID-nya di list_agent berdasarkan Nama jika ID tidak ada di item
                    if(track.OptionID) {
                        savedAgentId = track.OptionID;
                    } else {
                        // Jika Xero cuma kasih Nama, kita cari ID nya di master data lokal
                        let found = list_agent.find(a => a.Name == track.Option);
                        if(found) savedAgentId = found.TrackingOptionID;
                    }
                }
                if (track.Name === "Divisi" || track.Name === "Division") {
                    if(track.OptionID) {
                        savedDivisiId = track.OptionID;
                    } else {
                        let found = list_divisi.find(d => d.Name == track.Option);
                        if(found) savedDivisiId = found.TrackingOptionID;
                    }
                }
            });
        }

        let itemOptionsAgent = `<option value="">Select Agent</option>`;

        list_agent.forEach(ag => {
            // Cek apakah ID ini sama dengan yang tersimpan
            const isSel = (ag.TrackingOptionID == savedAgentId) ? 'selected' : '';
            itemOptionsAgent += `<option value="${ag.TrackingOptionID}" ${isSel}>${ag.Name}</option>`;
        });

        let itemOptionsDevisi = `<option value="">Select Devisi</option>`;
        list_divisi.forEach(dev => {
            const isSel = (dev.TrackingOptionID == savedDivisiId) ? 'selected' : '';
            itemOptionsDevisi += `<option value="${dev.TrackingOptionID}" ${isSel}>${dev.Name}</option>`;
        });


        return `
            <tr data-id="${lineItemId}">
                <td><select class="form-control item-select">${itemOptionsHtml}</select></td>
                <td><input type="text" class="form-control description" value="${desc}"></td>
                <td><input type="number" class="form-control qty" value="${qty}" required></td>
                <td><input type="number" class="form-control price" value="${price}"></td>
                <td><input type="number" class="form-control disc" value="${discNominal}" placeholder="0"></td>
                <td><select class="form-control account">${items_option_account}</select></td>
                <td><select class="form-control tax-rate">${itemOptionsTaxRate}</select></td>
                <td><input type="number" class="form-control tax-amount" value="${taxAmt}" readonly></td>
                <td><select class="form-control agent">${itemOptionsAgent}</select></td>
                <td><select class="form-control devisi">${itemOptionsDevisi}</select></td>
                <td><input type="text" class="form-control amount" disabled readonly value="${lineAmt}"></td>
                <td class="text-center">
                    <div class="d-flex justify-content-center">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-row mr-2"><i class="fas fa-trash"></i></button>
                        <button type="button" class="btn btn-sm btn-success save-row"><i class="fas fa-save"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }

      setTimeout(() => {
        fetchDataDummy();
    }, 1000);
    // --- 3. FETCH & DISPLAY DATA ---
    function getStatusBadge(status) {
        let color = 'secondary';
        let statusUpper = status ? status.toUpperCase() : 'UNKNOWN';
        if (statusUpper === 'PAID') color = 'success';
        else if (statusUpper === 'AUTHORISED') color = 'primary';
        else if (statusUpper === 'DRAFT') color = 'warning';
        else if (statusUpper === 'VOIDED') color = 'danger';

        $("#status_header").removeClass().addClass(`badge bg-${color}`).text(statusUpper);
    }

    function syncPayment(){//cron-insert-history-payment-local
        // console.log('uuid',code_invoice)
         $('#loadingIndicator').toggle(true);
         $.ajax({
            //   url: `{{ route('cron-insert-history-payment-local') }}`,
                url: "/api/xero-integrasi/insert-payment-inv/" + code_invoice,
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    $('#loadingIndicator').toggle(false);
                    if(response.status == 'success'){

                        Swal.fire({
                            title: "success",
                            text: `berhasil \n singkronise ${response.invoice_number} \n sisa request ke xero perhari ${response.request_min_tersisa_hari}`,
                            icon: "success"
                        });
                    }
                },
                 error: function (xhr, status, error) {
                    $('#loadingIndicator').toggle(false);
                    console.log('Error fetching history local payments :', xhr, status, error);
                     Swal.fire({
                        title: 'Erros!',
                        text: `synchronize error ${error} ${status}`,
                        icon: 'error',
                        confirmButtonText: 'Ok'
                    })

                },
         })
    }

    function fetchDataDummy() {
       // $('#fullPageLoader').removeClass('d-none');
        let urlTarget = `${BASE_URL}/api/xero-integrasi/getDetailInvoice/${code_invoice}`;
          $.ajax({
                url: urlTarget,
                type: 'GET',
                dataType: 'json',
                success: function (response) {

                    $("#TotalAmountPaidDisplay").text(formatRupiah(response.Invoices[0].AmountPaid))
                    $("#TotalAmountDueDisplay").text(formatRupiah(response.Invoices[0].AmountDue))
                    $("#TotalAmountXero").html(`<strong>${formatRupiah(response.custom.total_xero)}</strong>`)
                    $("#TotalAmountLocal").html(`<strong>${formatRupiah(response.custom.total_local)} </strong>`)
                    $("#TotalAmountReturn").html("<strong>" +formatRupiah(response.custom.total_price_return) + "</strong>")
                    response.Invoices[0].LineItems.forEach(element => {
                        list_item_in_currentsRows.push(element.ItemCode)
                    });
                    if(response.Invoices && response.Invoices.length > 0){
                        let data_invoice = response.Invoices[0];
                        getStatusBadge(data_invoice.Status);
                        loadInvoiceToForm(data_invoice);
                        $("#val_status").val(data_invoice.Status)
                        loadHistoryPaymentLocal()
                        //$('#fullPageLoaderInv').addClass('d-none');
                    }
                    //console.log(response.Invoices)
                    if(response.Invoices[0].Payments){
                        $("#div_payemnts")
                        const container = document.getElementById('div_payemnts');
                        let htmlContent = '';
                        response.Invoices[0].Payments.forEach(item => {
                            const tanggalFix = formatJsonDate(item.Date);
                            const amountFix = formatRupiah(item.Amount);
                                htmlContent += `
                                <div class="row mt-2">
                                    <div class="col-8"><strong class="text-dark"><i class="fas fa-calendar-alt"></i> ${tanggalFix}</strong></div>
                                    <div class="col-4">
                                        <span class="" style="font-size: 0.9em;">
                                            ${amountFix}
                                        </span>
                                    </div>
                                </div>
                                `;
                        });
                        container.innerHTML = htmlContent;
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching invoice:', xhr, status, error);
                     Swal.fire({
                        title: 'Erros!',
                        text: `load data detail invoice ${error}`,
                        icon: 'error',
                        confirmButtonText: 'Ok'
                    })

                },
                complete: function(e){
                  //$('#fullPageLoader').addClass('d-none');
                  //$('#fullPageLoader').css('display', 'none');
                }
            });
    }

    function loadHistoryPaymentLocal(){
          let val_invoice = $("#invoiceCodeParent").val();
          let url_history_pay = `${BASE_URL}/api/xero-integrasi/get-history-invoice/${val_invoice}`;
            $.ajax({
                url: url_history_pay,
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                   if(response.data.length >0){
                        const container_local_pay = document.getElementById('history_local_payments');
                        let htmlContent_local = '';
                        // console.log('history',response.data)
                        response.data.forEach(item => {
                            const tanggalFix = formatDateStringToText(item.date);
                            const amountFix = formatRupiah(item.amount);
                                htmlContent_local += `
                                <div class="row mb-2 pb-2" style="border-bottom: 1px solid #eee;">
                                    <div class="col-7">
                                        <div class="text-dark font-weight-bold" style="font-size: 14px;">
                                            ${item.reference}
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-alt mr-1"></i> ${tanggalFix}
                                        </small>
                                    </div>

                                    <div class="col-5 text-right">

                                        <div class="font-weight-bold text-success" style="font-size: 14px;">
                                            ${amountFix}
                                        </div>

                                        <div class="text-muted" style="font-size: 11px; margin-top: 2px;">
                                            <i class="fas fa-university mr-1"></i>${item.name_bank_transfer || '-'}
                                        </div>
                                    </div>
                                </div>`;
                        });
                        container_local_pay.innerHTML = htmlContent_local;
                   }
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching history local payments :', xhr, status, error);
                     Swal.fire({
                        title: 'Erros!',
                        text: `load data detail invoice ${error}`,
                        icon: 'error',
                        confirmButtonText: 'Ok'
                    })

                },
                complete: function(e){
                  //$('#fullPageLoader').addClass('d-none');
                  //$('#fullPageLoader').css('display', 'none');
                }
            });
    }

    function loadInvoiceToForm(invoiceData) {
        $('#contactName').val(invoiceData.Contact.Name);
        $('#invoiceNumber').val(invoiceData.InvoiceNumber);
        $('#headerInvoiceNo').text(invoiceData.InvoiceNumber);
        $("#invoiceCodeParent").val(invoiceData.InvoiceID)

        if (invoiceData.DateString) $('#invoiceDate').val(invoiceData.DateString.split('T')[0]);
        if (invoiceData.DueDateString) $('#dueDate').val(invoiceData.DueDateString.split('T')[0]);

        const tbody = $('#invoiceTableBody');
        tbody.empty();

        if (invoiceData.LineItems && invoiceData.LineItems.length > 0) {
            invoiceData.LineItems.forEach(item => {
                tbody.append(generateRowHtml(item));
            });
        }
        calculateTotal();
    }

    // --- 4. CALCULATIONS ---
    function calculateRow(row) {
        let qty = parseFloat(row.find('.qty').val()) || 0;
        let price = parseFloat(row.find('.price').val()) || 0;
        let discNominal = parseFloat(row.find('.disc').val()) || 0;
        let taxRate = parseFloat(row.find('.tax-rate option:selected').data('rate')) || 0;

        let subtotal = qty * price;

        // Validasi: Diskon tidak boleh lebih besar dari subtotal
        if (discNominal > subtotal) {
            discNominal = subtotal;
            row.find('.disc').val(discNominal);
        }

        let afterDisc = subtotal - discNominal;
        let taxAmt = (afterDisc * taxRate) / 100;
        let lineTotal = afterDisc + taxAmt;

        row.find('.tax-amount').val(taxAmt.toFixed(2));
        row.find('.amount').val(lineTotal.toFixed(2));

        calculateGrandTotal();
    }

    function calculateTotal() { // Alias untuk calculateGrandTotal
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let grandTotal = 0;
        let totalTax = 0;

        $('#invoiceTableBody tr').each(function () {
            let amt = parseFloat($(this).find('.amount').val()) || 0;
            let tax = parseFloat($(this).find('.tax-amount').val()) || 0;
            grandTotal += amt;
            totalTax += tax;
        });

        $('#taxTotalDisplay').text(totalTax.toLocaleString('id-ID'));
        $('#grandTotalDisplay').text(grandTotal.toLocaleString('id-ID'));
        $('#subTotalDisplay').text((grandTotal - totalTax).toLocaleString('id-ID'));
    }


    // --- 5. EVENT LISTENERS ---

    // Add Row
    $('#addRowBtn').click(function () {
        $('#invoiceTableBody').append(generateRowHtml(null));
    });

    // Kalkulasi saat input berubah
    $(document).on('input change', '.qty, .price, .disc, .tax-rate', function () {
        calculateRow($(this).closest('tr'));
    });

    // Auto-fill Product Detail
    $(document).on('change', '.item-select', function() {
        let self = $(this);
        let itemCode = self.val();
        let currentRow = self.closest('tr');
        // console.log("select",self.val())
        if (!itemCode) return;

        let urlProduct = `${BASE_URL}/api/xero-integrasi/get-by-id/${itemCode}`;
        $.ajax({
            url: urlProduct,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.Items && response.Items.length > 0) {
                    let itemData = response.Items[0];
                    let salesDetails = itemData.SalesDetails;

                    currentRow.find('.price').val(salesDetails.UnitPrice || 0);
                    currentRow.find('.description').val(itemData.Description || itemData.Name);
                    if(salesDetails.AccountCode) currentRow.find('.account').val(salesDetails.AccountCode);

                    calculateRow(currentRow);
                }
            },
            error: function(xhr,status, error){
                Swal.fire({
                    title: 'Erros!',
                    text: `load item product & service ${error}`,
                    icon: 'error',
                    confirmButtonText: 'Ok'
                })
            }
        });
    });


    // ============================================================
    // BAGIAN INI YANG SEBELUMNYA HILANG: TOMBOL SAVE PER BARIS
    // ============================================================

    $(document).on('click', '.save-row', function() {
        let btn = $(this);
        let row = btn.closest('tr');
        let accountVal = row.find('.account').val();
        if (!accountVal) {
            Swal.fire({
                title: 'Erros!',
                text: `Account hari di isi`,
                icon: 'error',
                confirmButtonText: 'Ok'
            })
            return;
        }

        let harga_per_item = parseFloat(row.find('.price').val());
        if (harga_per_item < 1) {
            Swal.fire({
                title: 'Erros!',
                text: `Price harus di isi`,
                icon: 'error',
                confirmButtonText: 'Ok'
            })
            return;
        }

        //console.log('saved',row.find('.item-select').val())
        // Ambil Data dari Input
        let payload = {
            invoice_id: code_invoice, // Wajib ada
            line_item_id: row.attr('data-id'), // Kosong jika baris baru

            item_code: row.find('.item-select').val(),
            description: row.find('.description').val(),
            qty: row.find('.qty').val(),
            price: row.find('.price').val(),
            disc_amount: row.find('.disc').val(), // Nominal

            account_code: row.find('.account').val(),
            tax_type: row.find('.tax-rate').val(),

            agent_id: row.find('.agent').val(),   // Kirim ID Agent
            divisi_id: row.find('.devisi').val(),
            status_invoice : $("#val_status").val()
            // agent & divisi bisa ditambahkan sesuai kebutuhan Controller
        };

        // Validasi Sederhana enable agar bisa tidak pilih item
        // if(!payload.item_code || payload.qty <= 0) {
        //     alert("Harap pilih Item dan isi Quantity");
        //     return;
        // }

        // Tampilan Loading
        let originalHtml = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        //console.log("payload ",payload)
        // AJAX CALL KE CONTROLLER SAVE
        $.ajax({
            url: "{{ route('invoice.item.save') }}", // Panggil Route Laravel
            type: "POST",
            data: payload,
            success: function(response) {
                if(response.status === 'success') {
                    // Beri feedback Sukses
                    btn.removeClass('btn-success').addClass('btn-primary').html('<i class="fas fa-check"></i>');

                    // PENTING: Refresh tabel data dari Xero.
                    // Kenapa? Karena saat Create New, Xero akan membuat LineItemID baru (UUID).
                    // Kita harus mengambil UUID itu agar kalau user klik save lagi, sistem tahu itu Edit, bukan Create baru.
                    setTimeout(() => {
                        fetchDataDummy();
                    }, 1000);

                } else {
                    alert("Gagal: " + response.message);
                    btn.html(originalHtml).prop('disabled', false);
                }
            },
            error: function(xhr) {
                let msg = xhr.responseJSON ? xhr.responseJSON.message : "Terjadi kesalahan server";
                console.error(xhr);
                alert("Error: " + msg);
                btn.html(originalHtml).prop('disabled', false);
            }
        });
    });


    // ============================================================
    // BAGIAN INI DIPERBAIKI: DELETE MENGGUNAKAN API
    // ============================================================

    $(document).on('click', '.remove-row', function () {
        let row = $(this).closest('tr');
        let btn = $(this);
        let lineItemId = row.attr('data-id');

        // Jika baris belum pernah disimpan (tidak ada data-id), hapus langsung dari HTML saja
        if (!lineItemId) {
            row.remove();
            calculateGrandTotal();
            return;
        }

        if(!confirm("Anda yakin ingin menghapus baris ini dari Xero?")) return;

        // AJAX CALL KE CONTROLLER DELETE
        let originalHtml = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        $.ajax({
            url: `${BASE_URL}/api/xero-integrasi/invoice/item/${lineItemId}`,
            type: "DELETE",
            data: { invoice_id: code_invoice }, // Kirim Invoice ID juga (diperlukan controller)
            success: function (response) {
                if(response.status === 'success') {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        calculateGrandTotal();
                    });
                    setTimeout(() => {
                        fetchDataDummy();
                        btn.removeClass('btn-outline-danger').addClass('btn-primary').html('<i class="fas fa-check"></i>');
                    }, 1000);
                } else {
                    alert("Gagal menghapus: " + response.message);
                    btn.html(originalHtml).prop('disabled', false);
                }
            },
            error: function (xhr) {
                alert("Gagal menghapus data.");
                btn.html(originalHtml).prop('disabled', false);
            }
        });
    });
</script>

</body>
</html>

