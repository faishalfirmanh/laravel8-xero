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

    /**fix view select2 **/
    /* ===== Select2 single (agent, hotel, tipe room) ===== */
.select2-container .select2-selection--single {
    position: relative;
    display: flex !important;
    align-items: center;
    height: calc(1.5em + 0.75rem + 4px) !important;
    border: 2px solid #888 !important;
    border-radius: .25rem;
    background-color: #fff;
}

/* Teks terpilih: satu baris, ada ruang untuk × dan panah */
.select2-container .select2-selection--single .select2-selection__rendered {
    display: block !important;
    width: 100%;
    margin: 0 !important;
    padding: 0 3.25rem 0 .75rem !important;
    line-height: 1.5 !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Placeholder */
.select2-container .select2-selection--single .select2-selection__placeholder {
    color: #6c757d;
}

/* Tombol clear (×): tidak float, diposisikan absolut di kanan */
.select2-container .select2-selection--single .select2-selection__clear {
    position: absolute !important;
    top: 50%;
    right: 2rem;
    transform: translateY(-50%);
    float: none !important;
    margin: 0 !important;
    padding: 0 !important;
    line-height: 1;
    font-size: 1.25rem;
    color: #999;
    z-index: 2;
}
.select2-container .select2-selection--single .select2-selection__clear:hover {
    color: #dc3545;
}

/* Panah dropdown */
.select2-container .select2-selection--single .select2-selection__arrow {
    position: absolute !important;
    top: 0 !important;
    right: .4rem;
    height: 100% !important;
    width: 1.5rem;
}

/* Saat fokus / terbuka */
.select2-container--default.select2-container--open .select2-selection--single,
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--bootstrap4.select2-container--open .select2-selection--single,
.select2-container--bootstrap4.select2-container--focus .select2-selection--single {
    border: 2px solid #007bff !important;
    box-shadow: none !important;
}
</style>


<div class="card shadow mb-5">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 font-weight-bold text-primary">List Invoices Hotel</h5>

        <button type="button" id="button_add_hotel" class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#modalCreateHotel">
            <i class="ti ti-plus me-1"></i> Add Invoices Hotel
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
                    <th>Nama Jamaah</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Nama Hotel</th>
                    <th>Total Tagihan</th>
                     {{-- <th>Status</th> --}}
                    <th width="15%" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalCreateHotel"  data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="modalCreateHotelLabel" aria-hidden="true">
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

                            {{-- PILIH MATA UANG (default SAR) --}}
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label class="font-weight-bold d-block">Mata Uang Harga Hotel <span class="text-danger">*</span></label>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="cur_sar" name="type_currency" value="SAR" class="custom-control-input" checked>
                                        <label class="custom-control-label" for="cur_sar">SAR (Riyal)</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="cur_idr" name="type_currency" value="IDR" class="custom-control-input">
                                        <label class="custom-control-label" for="cur_idr">IDR (Rupiah)</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="order_name" class="font-weight-bold">Nama Agent / Pemesan <span class="text-danger">*</span></label>
                                    <select class="form-control select2" name="order_name" id="order_name" style="width: 100%;" required>
                                        <option value="" disabled selected>Pilih Agent</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="btn_add_contact">
                                        <i class="ti ti-plus mr-1"></i> Tambah Contact
                                    </button>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="hotel_id" class="font-weight-bold">Nama Hotel <span class="text-danger">*</span></label>
                                    <select class="form-control select2" name="hotel_id" id="hotels" style="width: 100%;" required>
                                        <option value="" disabled selected>Pilih Hotel</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-success btn-sm mt-2" id="btn_add_master_hotel">
                                        <i class="ti ti-plus mr-1"></i> Tambah Hotel Baru
                                    </button>
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

                            {{-- Kurs hanya tampil saat SAR. Saat IDR disembunyikan & otomatis bernilai 1 --}}
                            <div class="form-row" id="wrap_kurs">
                                <div class="form-group col-md-12">
                                     <label for="nominal_currency_rp" class="font-weight-bold">Nominal 1 sar dalam rupiah <span class="text-danger">*</span></label>
                                     <input type="number" class="form-control" name="nominal_currency_rp" id="nominal_currency_rp" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="info_kurs">
                        <div class="alert alert-warning d-flex align-items-center py-2" role="alert">
                            <i class="ti ti-info-circle mr-2"></i>
                            <small><strong>Info Kurs:</strong> Harga 1 Real saat ini diestimasi <b id="text_currency"></b></small>
                        </div>
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
                                <label class="small font-weight-bold">Harga Satuan (<span class="cur-text">SAR</span>) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text font-weight-bold text-xs cur-text">SAR</span>
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
          <h5 class="font-weight-bold"><span id="cur_total_label">SAR</span> <span id="total_payment"></span></h5>
          <h5 class="font-weight-bold" id="total_payment_rp_wrap">Rp <span id="total_payment_rp"></span></h5>
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

{{-- ================= MODAL TAMBAH CONTACT (dibuka dari modalCreateHotel) ================= --}}
{{-- Letakkan DI LUAR #modalCreateHotel dan DI LUAR form #formCreateHotel --}}
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
<input type="text" class="form-control" id="full_name" placeholder="Nama lengkap jamaah">
</div>

<div class="form-group mb-3">
<label for="phone_number">No. Telepon <span class="text-danger">*</span></label>
<input type="text" class="form-control" id="phone_number" placeholder="Contoh: 081234567890">
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
<button type="button" id="btn_save_jamaah" class="btn btn-primary" onclick="simpanJamaah()">Simpan</button>
</div>
</div>
</div>
</div>

{{-- ================= MODAL TAMBAH MASTER HOTEL (dibuka dari modalCreateHotel) ================= --}}
<div class="modal fade" id="modalMasterHotel" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
<div class="modal-content">
<div class="modal-header bg-success text-white">
<h5 class="modal-title">Tambah Master Hotel</h5>
<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>

<form id="formMasterHotel">
<div class="modal-body">
<div class="form-row">
<div class="form-group col-md-8">
<label class="small font-weight-bold">Nama Hotel <span class="text-danger">*</span></label>
<input type="text" class="form-control" id="mh_name" required maxlength="191">
</div>
<div class="form-group col-md-4">
<label class="small font-weight-bold">Lokasi <span class="text-danger">*</span></label>
<select class="form-control" id="mh_type_location_hotel" required>
<option value="">-- Pilih --</option>
<option value="1">Makkah</option>
<option value="2">Madinah</option>
</select>
</div>
</div>

<div class="form-group">
<label class="small font-weight-bold">Alamat</label>
<textarea class="form-control" id="mh_address" rows="2"></textarea>
</div>

<div class="form-row">
<div class="form-group col-md-6">
<label class="small font-weight-bold">Jarak (m)</label>
<input type="number" class="form-control" id="mh_distance" min="0" step="1">
</div>
<div class="form-group col-md-6">
<label class="small font-weight-bold">Bintang (1-5)</label>
<select class="form-control" id="mh_star">
<option value="">-</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">5</option>
</select>
</div>
</div>
</div>

<div class="modal-footer bg-white">
<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
<button type="submit" class="btn btn-success btn-sm font-weight-bold" id="btnSaveMasterHotel">
<i class="ti ti-check mr-1"></i> Simpan Hotel
</button>
</div>
</form>
</div>
</div>
</div>

 @include('admin.transaksi.hotel_payment_modal') 

<div class="card d-none" style="margin-bottom: 10px;">
    <h2>Cek Total Penjualan Hotel (filter data)</h2>
    <form id="form_filter_hotels">
        <div class="form-group">
            <label for="input_date_start_filter">Tanggal awal</label>
            <input type="date" class="form-control" name="input_date_start_filter" id="input_date_start_filter">
        </div>
         <div class="form-group">
            <label for="input_date_end_filter">Tanggal akhir</label>
            <input type="date" class="form-control" name="input_date_end_filter" id="input_date_end_filter">
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
</div>
<div class="d-none">
    <h5>SAR : <span id="total_sar_amount"> </h5>
    <h5>Rupiah : <span id="total_rupiah_amount"> </h5>
    <h5>Rupiah Sudah Dibayar : <span id="paid_rupiah_amount"> </h5>
    <h5>Rupiah Belum Dibayar : <span id="remain_rupiah_amount"> </h5>
</div>

@endsection

@push('scripts')
@php
    // !!! GANTI 'save-master-hotel' dengan nama route simpan master hotel
    // (di halaman paket Anda: paketRoutes.saveMasterHotel).
    // Pakai Route::has supaya halaman tidak error 500 kalau nama route belum cocok.
    $urlSaveMasterHotel = \Illuminate\Support\Facades\Route::has('save-master-hotel')
        ? route('saveMasterHotel_v2')
        : '';
@endphp
<script>
    const URL_SAVE_MASTER_HOTEL = 'saveMasterHotel_v2';//@json($urlSaveMasterHotel);

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

    // ===== Helper global: set nilai Select2 (ajax) dari data yang baru disimpan =====
    function setSelect2Value(selector, id, text) {
        const $el = $(selector);
        // hapus option lama dengan value sama supaya tidak dobel
        $el.find('option').filter(function () {
            return String(this.value) === String(id);
        }).remove();
        $el.append(new Option(text, id, true, true)).trigger('change');
    }

    // ===== Simpan contact baru (dipanggil onclick dari #modalCreateJamaah) =====
    function simpanJamaah(){
        let payload = {
            id: $('#id_jamaah').val() || null,
            full_name: $('#full_name').val().trim(),
            phone_number: $('#phone_number').val().trim(),
            nik: $('#nik').val().trim(),
            detail_address: $('#detail_address').val().trim(),
        };

        if(!payload.full_name || !payload.phone_number){
            Swal.fire('Peringatan', 'Nama lengkap dan No. Telepon wajib diisi', 'warning');
            return;
        }

        $('#btn_save_jamaah').prop('disabled', true).text('Menyimpan...');

        ajaxRequest(`{{ route('save-jamaah') }}`, 'POST', payload, localStorage.getItem('token'))
            .then(response => {
                if(response.status == 200){
                    // ⚠️ sesuaikan dengan struktur response backend
                    const saved = response.data?.data || response.data || {};
                    console.log('savedj', response.data);
                    $('#modalCreateJamaah').modal('hide');
                    Swal.fire({ title: 'Berhasil', text: 'Data contact tersimpan', icon: 'success', timer: 1500, showConfirmButton: false });

                    if (saved.id) {
                        // isi otomatis select "Nama Agent / Pemesan" di form invoice hotel
                        setSelect2Value('#order_name', saved.id, saved.full_name || payload.full_name);
                    } else {
                        console.warn('save-jamaah tidak mengembalikan id, contact tidak bisa auto-select', response);
                    }
                } else {
                    Swal.fire('Gagal', response?.data?.data?.message || 'Gagal menyimpan data contact', 'error');
                }
            })
            .catch((err) => {
                cathError(err);
            })
            .finally(() => {
                $('#btn_save_jamaah').prop('disabled', false).text('Simpan');
            });
    }

$(document).ready(function() {
    var table;


    const input_filter_start = document.getElementById('input_date_start_filter');
    if (!input_filter_start) return;

    const now = new Date();
    const year  = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');

    input_filter_start.value = `${year}-${month}-01`;

    const input_filter_end = document.getElementById('input_date_end_filter');
    const hari_ini = String(new Date().getDate()).padStart(2, '0');

    input_filter_end.value = `${year}-${month}-${hari_ini}`;

    $("#form_filter_hotels").on("click",function(e){
        e.preventDefault();
        ajaxRequest(`{{ route('total-amount-revanue-hotel') }}`, 'GET',
        {
            date_start :  document.getElementById('input_date_start_filter').value,
            date_end :  document.getElementById('input_date_end_filter').value,
        },
        localStorage.getItem('token'))
        .then(response => {
            let res_success = response.data.data;
            $("#total_sar_amount").text(formatCurrency(res_success.sar))
            $("#total_rupiah_amount").text(formatCurrency(res_success.rupiah))
            $("#paid_rupiah_amount").text(formatCurrency(res_success.payment_idr))
            $("#remain_rupiah_amount").text(formatCurrency(res_success.remaining_idr))
        })
        .catch((err) => console.log('error currency', err));

    })

    $('#modalCreateHotel').on('hide.bs.modal', function (e) {
        if ($('.select2-container--open').length) e.preventDefault();
    });

    function hitungTotalHari() {
        const checkIn  = $('#check_in').val();
        const checkOut = $('#check_out').val();

        if (!checkIn || !checkOut) {
            $('#total_days').val('');
            return;
        }
        const startDate = new Date(checkIn);
        const endDate   = new Date(checkOut);

        // if (endDate < startDate) {
        //     alert('Tanggal Check Out harus setelah Check In');
        //     $('#check_out').val('');
        //     return;
        // }
        const diffTime = endDate.getTime() - startDate.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        $('#total_days').val(diffDays);
    }
    $('#check_in, #check_out').on('change', hitungTotalHari);

    // --- HELPER 2: Currency ---
    ajaxRequest(`{{ route('getByIdCurrency', ['idMaster' => 1]) }}`, 'GET', {}, localStorage.getItem('token'))
        .then(response => {
            $("#text_currency").text(`Rp. ${Number(response.data.data.nominal_rupiah_1_riyal)}`);
        })
        .catch((err) => console.log('error currency', err));

    // --- MATA UANG (SAR / IDR) ---
    let lastSarRate = ''; // simpan kurs SAR terakhir supaya tidak hilang saat pindah ke IDR lalu balik lagi

    function getCurrency() {
        return $('input[name="type_currency"]:checked').val() || 'SAR';
    }

    // IDR: kurs disembunyikan & dipaksa 1 | SAR: kurs tampil & wajib diisi
    function applyCurrencyMode(type) {
        const isIdr = (type === 'IDR');
        const $rate = $('#nominal_currency_rp');

        if (isIdr) {
            const cur = $rate.val();
            if (cur && Number($rate.val()) !== 1) {
                lastSarRate = cur;
            }
            $rate.val(1);
        } else if (Number($rate.val()) === 1) {
            // nilai 1 berasal dari mode IDR, kembalikan kurs SAR sebelumnya (atau kosongkan)
            $rate.val(lastSarRate);
        }

        $('#wrap_kurs, #info_kurs').toggle(!isIdr);
        $('#modalCreateHotel .cur-text').text(isIdr ? 'IDR' : 'SAR');
    }

    $(document).on('change', 'input[name="type_currency"]', function() {
        applyCurrencyMode($(this).val());
    });

    const $modalParent = $('#modalCreateHotel .modal-content');

    // --- 3. SELECT2 STATIC (Hotel & Agent) ---
    $('#hotels').select2({
        theme: 'bootstrap4',
        dropdownParent: $modalParent,
        placeholder: "Pilih Hotel",
        allowClear: true,
        ajax: {
            url: `{{ route('search_hotel_select2_v2') }}`,
            dataType: 'json',
            delay: 700,
            headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') },
            data: function (params) { return { keyword: params.term, page: params.page || 1 }; },
            processResults: function (response, params) {
                params.page = params.page || 1;
                return {
                    results: (response.data.data || []).map(item => ({ id: item.id, text: item.name })),
                    pagination: { more: response.data.current_page < response.data.last_page }
                };
            },
            cache: true
        }
    });

    $('#order_name').select2({
        theme: 'bootstrap4',
        dropdownParent: $modalParent,//$('#modalCreateHotel'),
        placeholder: 'Pilih Contact',
        allowClear: true,
        cache          : false, 
        minimumInputLength: 3,
        language: {
            inputTooShort : () => 'Ketik minimal 3 huruf untuk mencari contact...',
            searching     : () => 'Mencari...',
            noResults     : () => 'Contact tidak ditemukan',
            loadingMore   : () => 'Memuat lebih banyak...',
        },

        ajax: {
            url: '{{ route("list-contact-select2") }}',
            dataType: 'json',
            delay: 450,
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
            cache: false
        }
    });

    $('#modalCreateJamaah, #modalMasterHotel').on('show.bs.modal', function () {
        $(this).css('z-index', 1055);
        setTimeout(function () {
            $('.modal-backdrop').last().css('z-index', 1052);
        }, 0);
    });

    $('#modalCreateJamaah, #modalMasterHotel').on('hidden.bs.modal', function () {
        $(this).css('z-index', '');
        // Bootstrap 4 menghapus class modal-open saat satu modal ditutup,
        // padahal modalCreateHotel masih terbuka -> kembalikan supaya scroll tetap benar.
        if ($('.modal.show').length) {
            $('body').addClass('modal-open');
        }
    });

    // Tombol Tambah Contact
    $('#btn_add_contact').on('click', function () {
        $('#modalJamaahTitle').text('Tambah Contact');
        $('#modalCreateJamaah').modal('show');
    });

    $('#modalCreateJamaah').on('shown.bs.modal', function () {
        $('#full_name').trigger('focus');
    }).on('hidden.bs.modal', function () {
        $('#id_jamaah, #full_name, #phone_number, #nik, #detail_address').val('');
    });

    // Tombol Tambah Hotel Baru
    $('#btn_add_master_hotel').on('click', function () {
        $('#modalMasterHotel').modal('show');
    });

    $('#modalMasterHotel').on('shown.bs.modal', function () {
        $('#mh_name').trigger('focus');
    }).on('hidden.bs.modal', function () {
        $('#formMasterHotel')[0].reset();
    });

    // Simpan master hotel -> otomatis terpilih di select #hotels
    $('#formMasterHotel').on('submit', async function (e) {
        e.preventDefault();

        // if (!URL_SAVE_MASTER_HOTEL) {
        //     Swal.fire('Konfigurasi belum lengkap', 'Route simpan master hotel belum diatur di view ini (lihat variabel $urlSaveMasterHotel).', 'error');
        //     return;
        // }

        const payload = {
            name: $('#mh_name').val().trim(),
            type_location_hotel: $('#mh_type_location_hotel').val(),
            address: $('#mh_address').val().trim() || null,
            distance: $('#mh_distance').val() || null,
            star: $('#mh_star').val() || null,
        };

        const $btn = $('#btnSaveMasterHotel').prop('disabled', true);

        try {
            const res = await ajaxRequest(`{{ route('saveMasterHotel_v2') }}`, 'POST', payload, localStorage.getItem('token'));
            const saved = res.data.data;

            $('#modalMasterHotel').modal('hide');
            Swal.fire({ icon: 'success', title: 'Berhasil', text: res.data.message, timer: 1200, showConfirmButton: false });

            if (saved && saved.id) {
                setSelect2Value('#hotels', saved.id, saved.name || payload.name);
            } else {
                console.warn('save master hotel tidak mengembalikan id, hotel tidak bisa auto-select', res);
            }
        } catch (err) {
            cathError(err);
        } finally {
            $btn.prop('disabled', false);
        }
    });

    // --- 4. LOGIC DYNAMIC ROW (INI YANG KURANG SEBELUMNYA) ---

    // Fungsi Init Select2 untuk baris baru
    function initRoomSelect2(element) {
        $(element).select2({
            theme: 'bootstrap4',
            dropdownParent:$modalParent,
            placeholder: "Pilih Tipe",
            allowClear: true
        });
    }

    // FUNGSI UTAMA: Membuat HTML Baris Kamar (Bisa dipakai Edit & Tambah Baru)
    window.appendRoomRow = function(typeVal = '', qtyVal = 1, priceVal = '', isFirstRow = false) {
        // Helper selected
        const isSel = (val) => (String(typeVal) === String(val) ? 'selected' : '');
        // Label mata uang mengikuti pilihan radio saat ini
        const curLabel = getCurrency();

        // Tombol hapus disabled jika baris pertama
        const btnDelete = isFirstRow
            ? `<button type="button" class="btn btn-outline-danger btn-sm btn-remove-row" disabled><i class="ti ti-trash"></i></button>`
            : `<button type="button" class="btn btn-danger btn-sm btn-remove-row-dynamic" style="margin-top: 0px;"><i class="ti ti-trash"></i></button>`;

        var newRow = `
            <div class="room-row form-row mb-2 animate__animated animate__fadeIn">
                <div class="form-group col-md-5">
                    <select class="form-control select2-room" name="tipe_room[]" style="width: 100%;" required>
                        <option value="" disabled ${typeVal==''?'selected':''}>Pilih Tipe</option>
                        <option value="4" ${isSel(4)}>Quad</option>
                        <option value="2" ${isSel(2)}>Double</option>
                        <option value="3" ${isSel(3)}>Triple</option>
                        <option value="5" ${isSel(5)}>Quint</option>
                        <option value="8" ${isSel(8)}>Room Only</option>
                        <option value="9" ${isSel(9)}>Bed</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <input type="number" class="form-control" name="qty[]" min="1" value="${qtyVal}" placeholder="Qty" required>
                </div>
                <div class="form-group col-md-4">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text font-weight-bold text-xs cur-text">${curLabel}</span>
                        </div>
                        <input type="number" class="form-control" name="price_hotel[]" value="${priceVal}" placeholder="Harga" required>
                    </div>
                </div>
                <div class="form-group col-md-1 text-center">
                    ${btnDelete}
                </div>
            </div>
        `;

        $('#room-container').append(newRow);
        initRoomSelect2($('#room-container .select2-room').last());
    }

    // Init baris pertama (default)
    initRoomSelect2('.select2-room');

    // Event Klik Tombol Tambah
    $('#btn-add-row').click(function() {
        appendRoomRow(); // Panggil fungsi (kosong = tambah baru)
    });

    // Event Klik Hapus Baris
    $(document).on('click', '.btn-remove-row-dynamic', function() {
        $(this).closest('.room-row').remove();
    });

    // Reset Form saat Modal Tutup
    $('#modalCreateHotel').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();   // radio otomatis kembali ke SAR (checked default)
        lastSarRate = '';
        $('#idHotelInput').val('');
        $('#order_name, #hotels').val(null).trigger('change');
        applyCurrencyMode('SAR');

        // Reset container kamar, sisakan 1 default
        $('#room-container').empty();
        appendRoomRow('', 1, '', true);
    });


    let columnHotel = [
        {
            data: null, className: "text-center",
            render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
        },
        { data: 'no_invoice_hotel', name: 'no_invoice_hotel' },
        { data: 'nama_pemesan', name: 'nama_pemesan' },
        { data: 'check_in', name: 'check_in' },
        { data: 'check_out', name: 'check_out' },
        {
            data: 'hotel_name', name: 'hotel_name' }, // Sesuaikan jika nama hotel ada relasi
        // {
        //     data: 'status', name: 'status',
        //     render: function(data){
        //         if(data == 1){
        //             return '<span class="badge badge-danger">Belum dibayar</span>'
        //         }else if(data == 2){
        //             return '<span class="badge light badge-warning">Proses Pembayaran</span>'
        //         }else{
        //             return '<span class="badge badge-primary">Lunas</span>'
        //         }
        //     }
        // },
         {  data: 'total_payment',
            className: "text-center",
            render(data,type,row){
                return formatCurrency(data,row.type_currency)
            } 
        },
        {
            data: "id", className: "text-center", orderable: false, searchable: false,
            render: function(data,type,row) {
                let btnEdit = `<a href="javascript:;" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-pencil"></i></a>`;
                let btnHapus = `<a href="javascript:;" data-id="${data}" class="text-danger deleted-hotel"><i class="ti ti-trash"></i></a>`;
                let btn_detail = `<a href="javascript:;" style="margin-left:5px;" data-id="${data}" class="text-success view-hotel"><i class="ti ti-eye"></i></a>`;
                let btn_detail_pay = row.get_inv_xero ? `<a href="javascript:;" style="margin-left:5px;" data-id="${data}" class="text-info payment-hotel"><i class="ti ti-cash"></i></a>` : '';
               
                return btnEdit + btnHapus + btn_detail + btn_detail_pay;
            }
        }
    ];

    table = initGlobalDataTableToken('#tableHotel', `{{ route('list-revanue-hotel') }}`, columnHotel, { "kolom_name": "nama_pemesan" });

    function formatDate(date) {
        const [y, m, d] = date.split('-');
        return `${d}/${m}/${y}`;
    }

    function loadListPayment(idInv){
         ajaxRequest(`{{ route('get-allpayment-hotelby-idinvoice') }}`, 'GET', { id: idInv }, localStorage.getItem("token"))
            .then(response => {
                let date_get = response.data.data;
               // $("#row_payment_id").val(idnya)
                if(date_get.length > 0){
                    let rows_item = '';
                    let payment_conculsion= date_get[0].get_invoice;

                    // IDR: tampilkan semua angka dalam Rupiah (kolom *_idr), baris Rp kedua disembunyikan
                    // SAR: tampilan lama (SAR + Rp)
                    const isIdrInv = String(payment_conculsion.type_currency || 'SAR').toUpperCase() === 'IDR';
                    if (isIdrInv) {
                        $("#summary_total").text(formatCurrency(Number(payment_conculsion.total_payment_rupiah),"IDR"))
                        $("#summary_paid").text(formatCurrency(Number(payment_conculsion.final_payment_idr),"IDR"))
                        $("#summary_remaining").text(formatCurrency(Number(payment_conculsion.less_payment_idr),"IDR"))
                    } else {
                        $("#summary_total").text(formatCurrency(Number(payment_conculsion.total_payment),"SAR"))
                        $("#summary_paid").text(formatCurrency(Number(payment_conculsion.final_payment_sar),"SAR"))
                        $("#summary_remaining").text(formatCurrency(Number(payment_conculsion.less_payment_sar),"SAR"))
                        $("#sum_paid_rp").text(formatCurrency(Number(payment_conculsion.final_payment_idr),"IDR"))
                        $("#sum_pem_rp").text(formatCurrency(Number(payment_conculsion.less_payment_idr),"IDR"))
                    }
                    $("#sum_paid_rp, #sum_pem_rp").toggle(!isIdrInv);

                    let i = 1;
                    date_get.forEach((item)=>{
                    let get_edit = `<a href="javascript:;" style="margin-left:5px;" data-id="${item.id}" class="text-success get-edit-pay"><i class="ti ti-pencil"></i></a>`;
                    let delete_payment = `<a href="javascript:;" style="margin-left:5px;" data-id="${item.id}" class="text-info delete-pay"><i class="ti ti-trash"></i></a>`;
                        let cek_date_tf = item.date_transfer ? item.date_transfer : '-';
                        let cek_desc = item.desc ? item.desc : '-';
                        rows_item +=
                        `<tr>
                            <td>${i}</td>
                            <td> ${convertStringDate(cek_date_tf)}</td>
                            <td class="text-center">${cek_desc}</td>
                            <td class="text-right">Rp. ${formatNumber(item.payment_idr)}</td>
                            <td class="">
                                 ${get_edit} ${delete_payment}
                            </td>
                        </tr>`
                        i++;
                    })
                     document.getElementById('payment_list_body').innerHTML = rows_item;
                    $("#no_invoice_display").text(date_get[0].get_invoice.no_invoice_hotel)
                    $("#hotel_name_display").text(date_get[0].get_invoice.hotel_name)
                }else{
                    $("#hotel_name_display").text('empty')
                    $("#no_invoice_display").text('empty')
                     document.getElementById('payment_list_body').innerHTML = 'data kosong';
                }

            })
            .catch((err)=>{
                console.log('error',err)
            })
    }

    $('#tableHotel').on('click', '.payment-hotel', function () {
        const row = $('#tableHotel').DataTable().row($(this).parents('tr')).data();
        const inv = row.get_inv_xero;   // dari relasi getInvXero
        if (!inv) {
            Swal.fire('Info', 'Invoice global untuk invoice hotel ini belum ada.', 'info');
            return;
        }
        hpOpenPayment(inv.id, row.no_invoice_hotel);
    });

    $("#tablePaymentList").on('click','.delete-pay',function(){
        let idRow = $(this).data('id');
        console.log('id parent inv',$("#invoices_id_parent").val())
         Swal.fire({
            title: 'Hapus Data?', text: "Data tidak bisa dikembalikan!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest(`{{ route('delete-by-row-payment-hotel') }}`, 'POST', { id: idRow }, localStorage.getItem("token"))
                    .then(res => {
                        if (res.status == 200) Swal.fire("Sukses", "Data terhapus", "success");
                         loadListPayment($("#invoices_id_parent").val())
                    })
                    .catch(err => Swal.fire('Gagal', err.message, 'error'));
            }
        });
    })

    $("#tablePaymentList").on('click','.get-edit-pay',function(){
          let idRow = $(this).data('id');
          $("#row_payment_id").val(idRow)
            ajaxRequest(`{{ route('get-by-row-payment-hotel') }}`, 'GET', { id: idRow }, localStorage.getItem("token"))
            .then(response => {
                if(response.data.status){
                    let res_data = response.data.data
                    console.log('res get edti',res_data)
                    $("#input_payment_date").val(res_data.date_transfer)
                    $("#nominal_currency_rp").val(Number(res_data.nominal_currency_rp))
                    $("#input_payment_nominal").val(Number(res_data.payment_idr))
                    //res_data.payment_sar ?? $("#input_payment_ref").val(res_data.payment_sar)
                    $("#input_payment_ref").val(res_data.desc)


                    //console.log('aa',res_data.get_invoice.hotel_name)
                }
                console.log("get edit",response.data.status)
            })
            .catch(err=>{
                console.log("error",err)
            })
    })

    $(document).on('click', '.clear-edit-pay', function() {
        $("#row_payment_id").val(0)
        $('#formSubmitPayment')[0].reset();
    });

     $('#formSubmitPayment').on('submit', function(e) {
        e.preventDefault();
        //btn.html('<i class="fa fa-spin fa-spinner"></i> Menyimpan...').prop('disabled', true);

        let formData = new FormData(this);
        let data_json = Object.fromEntries(formData);

        data_json.id = data_json.row_payment_id;
        delete data_json.row_payment_id;

        let cek_url = Number(data_json.id) > 0 ?
          `{{ route('updated-by-row-payment-hotel') }}` : `{{ route('create-by-row-payment-hotel') }}`;
         ajaxRequest(cek_url, 'POST', data_json, localStorage.getItem("token"))
            .then(response => {
                if(response.status == 200){
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data disimpan' });
                    loadListPayment(response.data.data.invoices_id)
                }else{
                    Swal.fire('Gagal!', 'Terjadi kesalahan.', 'error');
                }
            })
            .catch(err=>{
                console.log("Raw Error:", err); // Debugging
                let pesanError = 'Terjadi kesalahan pada server.';
                if (err.error && err.error.message) {
                    let rawMessage = err.error.message;
                    try {
                        let parsedMessage = JSON.parse(rawMessage);
                        let listPesan = [];
                        Object.keys(parsedMessage).forEach(key => {
                            listPesan.push(parsedMessage[key][0]);
                        });
                        pesanError = listPesan.join('<br>');
                    } catch (e) {
                        pesanError = rawMessage;
                    }
                }
                else if (err.statusText) {
                    pesanError = err.statusText;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Validasi!',
                    html: pesanError
                });
            })
    })


    function formatNumber(num) {
        return parseFloat(num).toLocaleString('id-ID');
    }

    $("#tableHotel").on('click','.view-hotel',function(){
        let id = $(this).data('id');
        $("#id_invoice_view_detail").val(id)
          ajaxRequest(`{{ route('byid-revanue-hotel') }}`, 'GET', { id: id }, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200) {
                    let data = response.data.data;

                    if (!data) { Swal.fire('Error', 'Data tidak ditemukan', 'error'); return; }

                    // IDR: total & harga tampil dalam Rp, baris "Rp" kedua disembunyikan
                    // SAR: tampilan lama
                    const isIdrView = String(data.type_currency || 'SAR').toUpperCase() === 'IDR';
                    const curPrefix = isIdrView ? 'Rp' : 'Sar';

                    document.getElementById('hotel_name').innerText = data.hotel_name;
                    document.getElementById('no_invoice').innerText = data.no_invoice_hotel;
                    document.getElementById('nama_pemesan').innerText = data.nama_pemesan;
                    document.getElementById('check_in_inv').innerText = formatDate(data.check_in);
                    document.getElementById('check_out_inv').innerText = formatDate(data.check_out);
                    document.getElementById('cur_total_label').innerText = isIdrView ? 'Rp' : 'SAR';
                    document.getElementById('total_payment').innerText = formatNumber(data.total_payment);
                    document.getElementById('total_payment_rp').innerText = formatNumber(data.total_payment_rupiah);
                    $('#total_payment_rp_wrap').toggle(!isIdrView);
                    if(data.date_transaction){
                        document.getElementById('date_trans_inv').innerText = convertStringDate(data.date_transaction);
                    }


                    let rows = '';
                    data.details.forEach(item => {
                        rows += `
                            <tr>
                                <td>${item.type_room_desc}</td>
                                <td class="text-center">${item.qty}</td>
                                <td class="text-right">${curPrefix} ${formatNumber(item.price_each_item)}</td>
                                <td class="text-right">${data.total_days}</td>
                                <td class="text-right">${curPrefix} ${formatNumber(item.total_amount)}</td>
                            </tr>
                        `;
                    });

                    document.getElementById('detail_rows').innerHTML = rows;
                    $('#invoiceModal').modal('show');
                }
            })
            .catch((err) => {
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
            });
    })

    // --- KLIK EDIT ---
    $('#tableHotel').on('click', '.edit-hotel', function() {
        let id = $(this).data('id');

        // Reset & Persiapan
        $('#formCreateHotel')[0].reset();
        $('#room-container').empty();
        $('#idHotelInput').val('');
        lastSarRate = '';
        applyCurrencyMode('SAR');
        $('#modalCreateHotelLabel').text('Edit Invoice Hotel (Loading...)');

        ajaxRequest(`{{ route('byid-revanue-hotel') }}`, 'GET', { id: id }, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200) {
                    let data = response.data.data;

                    if (!data) { Swal.fire('Error', 'Data tidak ditemukan', 'error'); return; }

                    // Mata uang: data lama (type_currency kosong) dianggap SAR
                    const tc = String(data.type_currency || 'SAR').toUpperCase() === 'IDR' ? 'IDR' : 'SAR';
                    $('input[name="type_currency"][value="' + tc + '"]').prop('checked', true);
                    applyCurrencyMode(tc);
                    if (tc === 'SAR') {
                        $("#nominal_currency_rp").val(Number(data.nominal_currency_rp));
                    }

                    // Isi Header
                    $("#date_transaction").val(data.date_transaction)
                    $('#idHotelInput').val(data.id);
                    $('#check_in').val(data.check_in);
                    $('#check_out').val(data.check_out);
                    $('#total_days').val(data.total_days);

                    // Isi Select2
                    if (data.uuid_user_order) {
                        let optionAgent = new Option(data.nama_pemesan, data.uuid_user_order, true, true);
                        $('#order_name').append(optionAgent).trigger('change');
                    }
                    if (data.hotel_id) {
                        let optionHotel = new Option(data.hotel_name, data.hotel_id, true, true);
                        $('#hotels').append(optionHotel).trigger('change');
                    }

                    // Isi Dynamic Rows (Looping)
                    if (data.details && data.details.length > 0) {
                        data.details.forEach((item, index) => {
                            let price = parseFloat(item.price_each_item);
                            // Panggil fungsi yang sekarang SUDAH ADA
                            appendRoomRow(item.type_room, item.qty, price, (index === 0));
                        });
                    } else {
                        appendRoomRow('', 1, '', true);
                    }

                    $('#modalCreateHotelLabel').text('Edit Invoice: ' + data.no_invoice_hotel);
                    $('#modalCreateHotel').modal('show');
                }
            })
            .catch((err) => {
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
            });
    });

    // --- 6. KLIK HAPUS ---
    $('#tableHotel').on('click', '.deleted-hotel', function() {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Data?', text: "Data tidak bisa dikembalikan!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest(`{{ route('deleted-revanue-hotel') }}`, 'POST', { id: id }, localStorage.getItem("token"))
                    .then(res => {
                        if (res.status == 200) Swal.fire("Sukses", "Data terhapus", "success");
                        table.ajax.reload();
                    })
                    .catch(err => Swal.fire('Gagal', err.message, 'error'));
            }
        });
    });

    $("#close_modal_payment").on('click',function(){
         table.ajax.reload();
         console.log('reload')
    })

    // Trigger Tambah Baru
    $("#button_add_hotel").on("click", function() {
        $('#modalCreateHotelLabel').text('Add New Invoices Hotel');
        // Reset handled by modal hidden event
    });

    // --- 7. SIMPAN DATA ---
    $('#formCreateHotel').on('submit', function(e) {
        e.preventDefault();
        var btn = $('#btnSave');
        var originalText = btn.html();
        btn.html('<i class="fa fa-spin fa-spinner"></i> Menyimpan...').prop('disabled', true);

        // Pastikan IDR selalu kirim kurs = 1 (controller juga memaksa 1)
        if (getCurrency() === 'IDR') {
            $('#nominal_currency_rp').val(1);
        }

        let formData = new FormData(this);
        let idInput = $('#idHotelInput').val();
        if (idInput) formData.append('id', idInput);
        $.ajax({
            url: `{{ route('save-revenue-hotel') }}`,
            type: "POST",
            data: formData,
            processData: false, contentType: false,
            headers: {
                  'Authorization': `Bearer ${localStorage.getItem('token')}`,
                'Accept': 'application/json'
             },
            success: function(response) {
                console.log('sukses',response)
                $("#modalCreateHotel").modal('hide');
                if (response.status) {
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data disimpan' });
                    table.ajax.reload();
                } else {
                     cathError(response.message)
                   // Swal.fire('Gagal!', response.meta.message || 'Error', 'error');
                }
            },
            error: function(xhr) {
                  console.log('gagal',xhr.responseJSON.message)
                  let data_err = JSON.parse(xhr.responseJSON.message);
                let message = Object.values(data_err).flat()[0];

                //cathError(xhr.responseJSON.message)
                // let msg = (xhr.responseJSON && xhr.responseJSON.meta) ? xhr.responseJSON.meta.message : 'Error';
                 Swal.fire('Gagal!', message, 'error');
            },
            complete: function() {
                btn.html(originalText).prop('disabled', false);
            }
        });
    });

});
</script>
@endpush