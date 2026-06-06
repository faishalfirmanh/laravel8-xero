
@extends('layouts.app')

@section('content')

<style>
    /* Styling khusus untuk tombol hapus agar sejajar vertikal */
    .btn-remove-row {
        margin-top: 32px; /* Menyesuaikan tinggi label */
    }


    .select2-container .select2-selection--single {
        border: 2px solid #888 !important; /* Ubah 2px sesuai ketebalan yg diinginkan */
        height: calc(1.5em + 0.75rem + 4px) !important; /* Sesuaikan tinggi agar isi tidak gepeng */
    }

    .select2-container--default.select2-container--open .select2-selection--single,
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--bootstrap4.select2-container--focus .select2-selection {
        border: 2px solid #007bff !important; /* Warna biru primary saat aktif */
        box-shadow: none !important; /* Opsional: Hilangkan glow jika ingin flat */
    }

    .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: calc(1.5em + 0.75rem) !important;
        margin-top: 1px; /* Geser sedikit text biar tengah */
    }
    .select2-container .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + 0.75rem) !important;
        top: 2px !important; /* Geser panah biar tengah */
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #printArea, #printArea * {
            visibility: visible;
        }

        #printArea {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
    }
</style>


<div class="card shadow mb-5">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 font-weight-bold text-primary">List Invoices From Xero </h5>

        <button type="button" id="button_add_hotel" class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#modalCreateHotel">
            <i class="ti ti-plus me-1"></i> Add Invoices
        </button>
    </div>

    <div id="loadingIndicator" class="text-center my-5" style="display:none;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="sr-only">Loading...</span>
        </div>
        <div class="mt-3 text-muted font-weight-bold">Memuat data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-hover table-striped table-bordered w-100" id="tableHotel">
            <thead class="thead-dark">
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th>No Invoice</th>
                    <th>Nama Contact</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

@include('admin.transaksi.sales.modal_inv')

<!-- Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content" id="printArea">

      <div class="modal-header">
        <h5 class="modal-title">Invoice Hotel</h5>
        <button type="button" class="close d-print-none" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body">

        <!-- HEADER -->
        <div class="text-center mb-3">
          <h5 class="font-weight-bold" id="hotel_name"></h5>
          <small>No Invoice: <span id="no_invoice"></span></small>
        </div>

        <!-- INFO -->
        <div class="row">
          <div class="col-12 col-md-6">
            <input type="hidden" id="id_invoice_view_detail" name="id_invoice_view_detail"/>
            <p><strong>Nama Pemesan:</strong><br><span id="nama_pemesan"></span></p>
          </div>
          <div class="col-6 col-md-3">
            <p><strong>Check In:</strong><br><span id="check_in_inv"></span></p>
          </div>
          <div class="col-6 col-md-3">
            <p><strong>Check Out:</strong><br><span id="check_out_inv"></span></p>
          </div>
        </div>
        <div class="row">
            <div class="col-12 col-md-6">
                <p><strong>Tanggal transaksi :</strong><br><span id="date_trans_inv"></span></p>
            </div>
        </div>

        <hr>

        <!-- DETAIL TABLE -->
        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead class="thead-light">
              <tr>
                <th>Type Room</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Harga</th>
                <th class="text-right">Total Malam</th>
                <th class="text-right">Total</th>
              </tr>
            </thead>
            <tbody id="detail_rows"></tbody>
          </table>
        </div>

        <div class="text-right">
          <h6>Total Payment:</h6>
          <h5 class="font-weight-bold">SAR <span id="total_payment"></span></h5>
          <h5 class="font-weight-bold">Rp <span id="total_payment_rp"></span></h5>
        </div>

      </div>

      <div class="modal-footer d-print-none">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" onclick="printInvoice()">
          <i class="fa fa-print"></i> Download PDF
        </button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content" id="printArea">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="paymentModalLabel">
            <i class="fa fa-money-bill-wave mr-2"></i> Form Pembayaran
        </h5>
        <button type="button" class="close text-white d-print-none" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">

        <div class="text-center mb-4">
          <h4 class="font-weight-bold mb-1" id="hotel_name_display"></h4>
          <span class="badge badge-light border px-3 py-2">
              No Invoice: <span id="no_invoice_display" class="font-weight-bold text-primary">...</span>
          </span>
        </div>

        <div class="row mb-4 text-center">
            <div class="col-md-4">
                <div class="card border-primary mb-2">
                    <div class="card-body py-2">
                        <small class="text-muted font-weight-bold text-uppercase">Total Tagihan</small>
                        <h5 class="font-weight-bold text-primary mb-0" id="summary_total">0</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success mb-2">
                    <div class="card-body py-2">
                        <small class="text-muted font-weight-bold text-uppercase">Sudah Dibayar</small>
                        <h5 class="font-weight-bold text-success mb-0" id="summary_paid">0</h5>
                        <h5 class="font-weight-bold text-success mb-0" id="sum_paid_rp">0</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-danger mb-2">
                    <div class="card-body py-2">
                        <small class="text-muted font-weight-bold text-uppercase">Sisa Kekurangan</small>
                        <h5 class="font-weight-bold text-danger mb-0" id="summary_remaining">0</h5>
                        <h5 class="font-weight-bold text-danger mb-0" id="sum_pem_rp">0</h5>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <h6 class="font-weight-bold mb-3"><i class="fa fa-history mr-1"></i> Riwayat Pembayaran</h6>
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-striped table-sm text-center" id="tablePaymentList">
                <thead class="thead-dark">
                    <tr>
                        <th width="5%">No</th>
                        <th>Tanggal</th>
                        <th>Keterangan / Ref</th>
                        <th>Nominal</th>
                        <th width="10%" class="d-print-none">Aksi</th>
                    </tr>
                </thead>
                <tbody id="payment_list_body">
                    <tr><td colspan="5" class="text-muted">Belum ada data pembayaran</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card bg-light d-print-none">
            <div class="card-body">
                <h6 class="font-weight-bold mb-3 text-primary"><i class="fa fa-plus-circle mr-1"></i> Tambah Pembayaran Baru</h6>
                <a href="javascript:;" style="margin-left:5px;" class="text-info clear-edit-pay"><i class="ti ti-plus"></i></a>
                <form id="formSubmitPayment">
                    <input type="hidden" id="row_payment_id" name="row_payment_id">
                    <input type="hidden" id="invoices_id_parent" name="invoices_id_parent">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Tanggal Bayar</label>
                            <input type="date" class="form-control form-control-sm" id="input_payment_date" name="date_transfer" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small font-weight-bold">Nominal (Rupiah)</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text font-weight-bold">Rupiah</span>
                                </div>
                                <input type="number" class="form-control font-weight-bold" id="input_payment_nominal" name="payment_idr" placeholder="0" min="1"  required>
                            </div>
                        </div>
                        <div class="form-group col-md-5">
                            <label class="small font-weight-bold">Catatan / Ref</label>
                            <input type="text" class="form-control form-control-sm" id="input_payment_ref" name="desc" placeholder="Contoh: Transfer Bank / Cash">
                        </div>
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-success shadow-sm" id="btnSavePayment">
                            <i class="fa fa-save mr-1"></i> Simpan Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>

      </div>

      <div class="modal-footer d-print-none bg-white">
        <button type="button" id="close_modal_payment" class="btn btn-secondary" data-dismiss="modal">
            <i class="fa fa-times mr-1"></i> Tutup
        </button>
        {{-- <button type="button" class="btn btn-info" onclick="window.print()">
          <i class="fa fa-print mr-1"></i> Print Laporan
        </button> --}}
      </div>

    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
     function printInvoice() {
      let id_nya = $("#id_invoice_view_detail").val();
        if(!id_nya) {
            alert("ID Invoice tidak ditemukan");
            return;
        }
        let url = "{{ route('invoice_hotel_print', ':id') }}";
        url = url.replace(':id', id_nya);
        window.open(url, '_blank');
    }


    var table;

    const now = new Date();
    const year  = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');






    function initRoomSelect2(element) {
        $(element).select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalCreateHotel'),
            placeholder: "Pilih Tipe",
            allowClear: true
        });
    }

    // FUNGSI UTAMA: Membuat HTML Baris Kamar (Bisa dipakai Edit & Tambah Baru)
   


    let columnHotel = [
        {
            data: null, className: "text-center",
            render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
        },
        { data: 'invoice_number', name: 'invoice_number' },
        { data: 'contact_name', name: 'contact_name' },
        { data: 'issue_date', name: 'issue_date' },
        { data: 'due_date', name: 'due_date' },
        {
            data: 'invoice_amount', 
            render: function(data,type,row){
                return formatCurrency(data)
            } 
        }, // Sesuaikan jika nama hotel ada relasi
        {
            data: 'status', name: 'status',
            render: function(data){
              
                if(data != 'PAID'){
                    return '<span class="badge badge-danger">Belum dibayar</span>'
                }else{
                    return '<span class="badge badge-primary">Lunas</span>'
                }
            }
        }
       ];

    table = initGlobalDataTableToken('#tableHotel', `{{ route('list-inv-xero-local') }}`, columnHotel, { "kolom_name": "contact_name" });

    function formatDate(date) {
        const [y, m, d] = date.split('-');
        return `${d}/${m}/${y}`;
    }



    function formatNumber(num) {
        return parseFloat(num).toLocaleString('id-ID');
    }


    $("#close_modal_payment").on('click',function(){
         table.ajax.reload();
       
    })

    // Trigger Tambah Baru
    $("#button_add_hotel").on("click", function() {
        $('.modal-title').text('Add New Invoices Hotel');
        // Reset handled by modal hidden event
    });

// =========================================================
// INVOICE MODAL — JAVASCRIPT
// =========================================================

// ── Kurs rate (diisi dari response server jika ada) ───────
let kursRate = 0;  // set dari luar: kursRate = 4350;

// ── Template HTML satu baris item ────────────────────────
function buildRow() {
    return `
    <tr class="line-item-row">
        <td><span class="drag-handle">&#x28FF;</span></td>

          <input type="hidden" name="id_detail[]"/>
        {{-- Item — Select2 AJAX --}}
        <td>
            <select class="form-control select2-item" name="item_id[]"
                    style="width:100%;" required>
            </select>
        </td>

        {{-- Desc — auto-fill dari item --}}
        <td>
            <input type="text" class="form-control desc-input"
                   name="desc[]" placeholder="(otomatis dari item)" required>
        </td>

        {{-- Qty --}}
        <td>
            <input type="number" class="form-control qty-input"
                   name="qty[]" min="1" value="1"
                   style="text-align:right;" required>
        </td>

        {{-- Price dengan label currency --}}
        <td>
            <div class="price-col-wrap">
                <span class="currency-label price-currency-label">SAR</span>
                <input type="number" class="form-control price-input"
                       name="unit_price[]" placeholder="0" required>
            </div>
        </td>

        {{-- Disc --}}
        <td>
            <input type="text" class="form-control"
                   name="disc[]" placeholder="0%">
        </td>

        {{-- Account — Select2 AJAX --}}
        <td>
            <select class="form-control select2-account" name="coa_id[]" required
                    style="width:100%;">
            </select>
        </td>

        {{-- Tax rate --}}
        <td>
            <select class="form-control" name="tax_rate[]">
                <option value="0">No tax</option>
                <option value="11">11% PPN</option>
            </select>
        </td>

        {{-- Tax amount (readonly) --}}
        <td>
            <input type="text" class="form-control tax-amount"
                   name="tax_amount[]" placeholder="0.00"
                   readonly style="text-align:right;background:#fafafa;">
        </td>

        {{-- Amount IDR (readonly) --}}
        <td>
            <input type="text" class="form-control amount-idr"
                   name="amount_idr[]" placeholder="0" required
                   readonly style="text-align:right;background:#fafafa;">
        </td>

        {{-- Nama Paket — Select2 AJAX --}}
        <td>
            <select class="form-control select2-paket" name="paket_tracking_uuid[]"
                    style="width:100%;">
            </select>
        </td>

        {{-- Divisi — Select2 AJAX --}}
        <td>
            <select class="form-control select2-divisi" name="divisi_travel_tracking_uuid[]"
                    style="width:100%;">
            </select>
        </td>

        <td>
            <button type="button" class="btn-del-line" title="Hapus baris">
                <i class="ti ti-trash"></i>
            </button>
        </td>
    </tr>`;
}

// ── Init semua Select2 pada satu baris ($row) ─────────────
function initRowSelect2($row) {
    const modalEl = $('#modalCreateHotel');

    // Item — AJAX list-paket-select2
    $row.find('.select2-item').select2({
        theme: 'bootstrap4',
        dropdownParent: modalEl,
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
                if (response.status != 'success') return { results: [], pagination: { more: false } };;
                return {
                    results: $.map(response.data.results, function(item) {
                        return { 
                            id        : item.id,
                            text      : item.nama_paket,
                            harga     : item.price_sales
                        };
                    }),
                }
            },
            cache: true
        }
    }).on('select2:select', function (e) {
        // Auto-fill desc dari field nama_paket response
        const d = e.params.data;
        $(this).closest('tr').find('.desc-input')
               .val(d.nama_paket || d.text || '');
        $(this).closest('tr').find('.price-input').val(Number(d.harga) || ''); 
        recalcSummary();
    });

    // Account — AJAX get-all-coa-select2
    $row.find('.select2-account').select2({
        theme: 'bootstrap4',
        dropdownParent: modalEl,
        placeholder: 'Account',
        allowClear: true,
        ajax: {
            url: '{{ route("get-all-coa-select2") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return { page: params.page || 1, keyword: params.term || '', limit: 5 };
            },
            processResults: function(response, params) {
                params.page = params.page || 1;
                return {
                    results: $.map(response.data?.data || [], function(item) {
                        return { id: item.id, text: item.name, account_type: item.account_type || '-' };
                    })
                };
            },
            cache: true
        }
    });

    // Nama Paket — AJAX tracking-by-parent
    $row.find('.select2-paket').select2({
        theme: 'bootstrap4',
        dropdownParent: modalEl,
        placeholder: 'Nama Paket',
        allowClear: true,
        ajax: {
            url: '{{ route("tracking-by-parent") }}',
            dataType: 'json',
            delay: 300,
            data: (params) => ({
                keyword: params.term,
                name_parent_category: 'nama paket'
            }),
            processResults: function(response) {
                if (!response.status || !response.data?.lines_category) return { results: [] };
                return {
                    results: $.map(response.data.lines_category, function(item) {
                        return { id: item.item_uuid_category || item.id, text: item.item_name_category };
                    })
                };
            },
            cache: false
        }
    });

    // Divisi — AJAX tracking-by-parent
    $row.find('.select2-divisi').select2({
        theme: 'bootstrap4',
        dropdownParent: modalEl,
        placeholder: 'Divisi',
        allowClear: true,
        ajax: {
            url: '{{ route("tracking-by-parent") }}',
            dataType: 'json',
            delay: 300,
            data: (params) => ({
                keyword: params.term,
                name_parent_category: 'divisi'
            }),
           processResults: function(response) {
                if (!response.status || !response.data?.lines_category) return { results: [] };
                return {
                    results: $.map(response.data.lines_category, function(item) {
                        return { id: item.item_uuid_category || item.id, text: item.item_name_category };
                    })
                };
            },
            cache: false
        }
    });
}

// ── Init Contact Select2 di form atas ─────────────────────
function initContactSelect2() {
    $('#contact_id').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#modalCreateHotel'),
        placeholder: 'Pilih Agent / Contact',
        allowClear: true,
        ajax: {
            url: '{{ route("list-contact-select2") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                    return { page: params.page || 1, keyword: params.term || '', limit: 5 };
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
        }
    });
}

// ── Tambah baris ──────────────────────────────────────────
$('#btn-add-row').on('click', function () {
    const $row = $(buildRow());
    $('#lineItemsBody').append($row);
    initRowSelect2($row);
    syncCurrencyLabels();
    updateDeleteButtons();
});

// ── Hapus baris ───────────────────────────────────────────
$(document).on('click', '.btn-del-line', function () {
    $(this).closest('tr').remove();
    updateDeleteButtons();
    recalcSummary();
});

function updateDeleteButtons() {
    const $rows = $('#lineItemsBody tr');
    $rows.find('.btn-del-line').prop('disabled', $rows.length <= 1);
}

// ── Currency parent — ubah label SAR/IDR di semua baris ───
$('#currency_selected').on('change', function () {
    syncCurrencyLabels();
    recalcSummary();
});

function syncCurrencyLabels() {
    const cur = $('#currency_selected').val() || 'SAR';
    $('.price-currency-label').text(cur);
}

// ── Recalc saat input berubah ─────────────────────────────
$(document).on('input change', '.qty-input, .price-input, [name="tax_rate[]"]',
    recalcSummary);

function recalcSummary() {
    const cur     = $('#currency_selected').val() || 'SAR';
    const isSAR   = (cur === 'SAR');
    let totalSAR  = 0;
    let totalIDR  = 0;
    let totalTax  = 0;

    $('#lineItemsBody tr').each(function () {
        const qty      = parseFloat($(this).find('.qty-input').val())  || 0;
        const price    = parseFloat($(this).find('.price-input').val()) || 0;
        const taxRate  = parseFloat($(this).find('[name="tax_rate[]"]').val()) || 0;
        const subtotal = qty * price;
        const taxAmt   = subtotal * (taxRate / 100);
        const total    = subtotal + taxAmt;

        $(this).find('.tax-amount').val(taxAmt > 0 ? taxAmt.toFixed(2) : '');

        if (isSAR) {
            const amtIDR = total * kursRate;
            $(this).find('.amount-idr').val(
                amtIDR > 0 ? Math.round(amtIDR).toLocaleString('id-ID') : ''
            );
            totalSAR += subtotal;
            totalIDR += amtIDR;
        } else {
            $(this).find('.amount-idr').val(
                total > 0 ? Math.round(total).toLocaleString('id-ID') : ''
            );
            totalIDR += total;
        }
        totalTax += taxAmt;
    });

    $('#summarySubtotalSAR').text(isSAR ? 'SAR ' + totalSAR.toLocaleString('id-ID') : '–');
    $('#summarySubtotalIDR').text('Rp ' + Math.round(totalIDR).toLocaleString('id-ID'));
    $('#summaryTax').text(totalTax > 0 ? totalTax.toFixed(2) : '0.00');
    $('#summaryTotal').text('Rp ' + Math.round(totalIDR).toLocaleString('id-ID'));
}

// ── Reset saat modal ditutup ──────────────────────────────
$('#modalCreateHotel').on('hidden.bs.modal', function () {
    $('#formCreateHotel')[0].reset();
    $('#lineItemsBody').empty();
    addFirstRow();                     // selalu ada 1 baris kosong
    syncCurrencyLabels();
    $('#summarySubtotalSAR, #summarySubtotalIDR, #summaryTax, #summaryTotal')
        .text('–');
});

// ── Inisialisasi awal saat DOM ready ─────────────────────
function addFirstRow() {
    const $row = $(buildRow());
    $('#lineItemsBody').append($row);
    initRowSelect2($row);
    updateDeleteButtons();
}

$(function () {
    initContactSelect2();
    addFirstRow();
    syncCurrencyLabels();
});

//submit
    $('.action-submit').on('click', function() {
        let actionValue = $(this).val();
        $('#actionTypeValue').val(actionValue);
    });


        $('#formCreateHotel').on('submit', function(e) {
            e.preventDefault();
            
            $('.select2-account, .select2-paket, select2-item, .select2-divisi').each(function() {
                if ($(this).data('select2')) { $(this).trigger('change'); }
            });

            let formData = $(this).serialize();
            let params = new URLSearchParams(formData);
            let idInput = params.get('idHotelInput');
            let id_inv = (idInput && idInput > 0) ? idInput : null;
            let action_selected = params.get('action_type');

            let selectedData = {
                id: id_inv,
                contact_id: params.get('contact_id'),
                issue_date: params.get('issue_date'),
                due_date: params.get('due_date'),
                reference: params.get('reference'),
                // currency: params.get('currency'),
                action_save : action_selected,

                item_id : $('select[name="item_id[]"]').map(function(){ return $(this).val(); }).get(),
                coa_id: $('select[name="coa_id[]"]').map(function(){ return $(this).val(); }).get(),
                desc: $('input[name="desc[]"]').map(function(){ return $(this).val(); }).get(),
                qty: $('input[name="qty[]"]').map(function(){ return $(this).val(); }).get(),
                unit_price: $('input[name="unit_price[]"]').map(function(){ return $(this).val(); }).get(),
                //tax_rate: $('input[name="tax_rate[]"]').map(function(){ return $(this).val(); }).get(),
                paket_tracking_uuid: $('select[name="nama_paket[]"]').map(function(){ return $(this).val(); }).get(),
                divisi_travel_tracking_uuid: $('select[name="divisi[]"]').map(function(){ return $(this).val(); }).get(),
                id_detail:$('input[name="id_detail[]"]').map(function(){ return $(this).val(); }).get(),
            };

            ajaxRequest(`{{ route('save-sales-inv') }}`, 'POST', selectedData, localStorage.getItem("token"))
                .then(response => {
                    if(response.status == 200){
                        Swal.fire('Sukses!', 'Data berhasil disimpan.', 'success');
                        $('#modalCreateHotel').modal('hide');
                        table.ajax.reload(null, false);
                    }
                })
                .catch((err) => {
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                });
        });

</script>
@endpush
