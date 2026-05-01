
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

<div class="modal fade" id="modalCreateHotel" tabindex="-1" role="dialog" aria-labelledby="modalCreateHotelLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document"> <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalCreateHotelLabel">Add New Invoices Hotel</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formCreateHotel">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="idHotelInput" id="idHotelInput">

                    <div class="card bg-light mb-3">
                        <div class="card-body py-3">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="order_name" class="font-weight-bold">Nama Agent / Pemesan <span class="text-danger">*</span></label>
                                    <select class="form-control select2" name="order_name" id="order_name" style="width: 100%;" required>
                                        <option value="" disabled selected>Pilih Agent</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="hotel_id" class="font-weight-bold">Nama Hotel <span class="text-danger">*</span></label>
                                    <select class="form-control select2" name="hotel_id" id="hotels" style="width: 100%;" required>
                                        <option value="" disabled selected>Pilih Hotel</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                     <label for="check_in" class="font-weight-bold">Check In <span class="text-danger">*</span></label>
                                     <input type="date" class="form-control" name="check_in" id="check_in" required>
                                </div>
                                <div class="form-group col-md-6">
                                     <label for="check_out" class="font-weight-bold">Check Out <span class="text-danger">*</span></label>
                                     <input type="date" class="form-control" name="check_out" id="check_out" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                     <label for="total_days" class="font-weight-bold">Total Hari <span class="text-danger">*</span></label>
                                     <input type="number" class="form-control" name="total_days" id="total_days" disabled>
                                </div>
                                <div class="form-group col-md-6">
                                     <label for="date_transaction" class="font-weight-bold">Tanggal Transaksi <span class="text-danger">*</span></label>
                                     <input type="date" class="form-control" name="date_transaction" id="date_transaction" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning d-flex align-items-center py-2" role="alert">
                        <i class="ti ti-info-circle mr-2"></i>
                        <small><strong>Info Kurs:</strong> Harga 1 Real saat ini diestimasi <b id="text_currency"></b></small>
                    </div>

                    <hr>

                    <div id="room-container">
                        <label class="font-weight-bold mb-3">Detail Kamar</label>
                        <div class="room-row form-row mb-2">
                            <div class="form-group col-md-5">
                                <label class="small font-weight-bold">Type Room <span class="text-danger">*</span></label>
                                <select class="form-control select2-room" name="tipe_room[]" style="width: 100%;" required>
                                    <option value="" disabled selected>Pilih Tipe</option>
                                    <option value="4">Quad</option>
                                    <option value="2">Double</option>
                                    <option value="3">Triple</option>
                                    <option value="5">Quint</option>
                                    <option value="8">Room Only</option>
                                    <option value="9">Bed</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small font-weight-bold">Qty <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="qty[]" min="1" value="1" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="small font-weight-bold">Harga Satuan (SAR) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text font-weight-bold text-xs">SAR</span>
                                    </div>
                                    <input type="number" class="form-control" name="price_hotel[]" placeholder="0" required>
                                </div>
                            </div>
                            <div class="form-group col-md-1 text-center">
                                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-row" disabled>
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="col-12">
                            <button type="button" class="btn btn-success btn-sm btn-block dashed-border" id="btn-add-row">
                                <i class="ti ti-plus"></i> Tambah Tipe Kamar Lain
                            </button>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="ti ti-close mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary shadow" id="btnSave">
                        <i class="ti ti-save mr-1"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    console.log('aaa');


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
            data: 'invoice_amount', name: 'invoice_amount' }, // Sesuaikan jika nama hotel ada relasi
        {
            data: 'status', name: 'status',
            render: function(data){
                console.log('saaa',data);
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
         console.log('reload')
    })

    // Trigger Tambah Baru
    $("#button_add_hotel").on("click", function() {
        $('.modal-title').text('Add New Invoices Hotel');
        // Reset handled by modal hidden event
    });

</script>
@endpush
