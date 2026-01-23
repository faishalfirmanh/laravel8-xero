@extends('layouts.app')

@section('content')

<style>
    /* Styling khusus untuk tombol hapus agar sejajar vertikal */
    .btn-remove-row {
        margin-top: 32px; /* Menyesuaikan tinggi label */
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
                    <th>Name</th>
                    <th>Lokasi</th>
                    <th width="15%" class="text-center">Action</th>
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
                                        <option value="1">ll</option>
                                        <option value="2">xx</option>
                                        <option value="3">ee</option>
                                        <option value="4">ppp</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="hotels" class="font-weight-bold">Nama Hotel <span class="text-danger">*</span></label>
                                    <select class="form-control select2" name="hotels" id="hotels" style="width: 100%;" required>
                                        <option value="" disabled selected>Pilih Hotel</option>
                                        <option value="1">Hotel JW Marriot</option>
                                        <option value="2">Hotel Surya</option>
                                        <option value="3">Hotel Majapahit</option>
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
                        </div>
                    </div>

                    <div class="alert alert-warning d-flex align-items-center py-2" role="alert">
                        <i class="ti ti-info-circle mr-2"></i>
                        <small><strong>Info Kurs:</strong> Harga 1 Real saat ini diestimasi <b>Rp. 46.000</b></small>
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
                                    <option value="0">Room Only</option>
                                    <option value="0">Bed</option>
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

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // 1. Inisialisasi Select2 untuk element STATIC (Agent & Hotel)
    $('#order_name, #hotels').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#modalCreateHotel'),
        placeholder: "Pilih Salah Satu",
        allowClear: true
    });

    // 2. Fungsi inisialisasi Select2 untuk element DYNAMIC (Room Type)
    function initRoomSelect2(element) {
        $(element).select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalCreateHotel'), // PENTING: Tetap arahkan ke modal
            placeholder: "Pilih Tipe",
            allowClear: true
        });
    }

    // Init select2 pada baris pertama (yang sudah ada di HTML)
    initRoomSelect2('.select2-room');

    // 3. Script Tambah Baris (Add Row)
    $('#btn-add-row').click(function() {
        var newRow = `
            <div class="room-row form-row mb-2 animate__animated animate__fadeIn">
                <div class="form-group col-md-5">
                    <select class="form-control select2-room" name="tipe_room[]" style="width: 100%;" required>
                        <option value="" disabled selected>Pilih Tipe</option>
                        <option value="4">Quad</option>
                        <option value="2">Double</option>
                        <option value="3">Triple</option>
                        <option value="5">Quint</option>
                        <option value="0">Room Only</option>
                        <option value="0">Bed</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <input type="number" class="form-control" name="qty[]" min="1" value="1" placeholder="Qty" required>
                </div>
                <div class="form-group col-md-4">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text font-weight-bold text-xs">SAR</span>
                        </div>
                        <input type="number" class="form-control" name="price_hotel[]" placeholder="Harga" required>
                    </div>
                </div>
                <div class="form-group col-md-1 text-center">
                    <button type="button" class="btn btn-danger btn-sm btn-remove-row-dynamic" style="margin-top: 0px;">
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
            </div>
        `;

        // Append ke container
        $('#room-container').append(newRow);

        // Init select2 pada element yang BARU saja ditambahkan (ambil yang terakhir)
        initRoomSelect2($('#room-container .select2-room').last());
    });

    // 4. Script Hapus Baris (Delete Row)
    // Kita pakai 'delegate' event (on click) ke document karena elementnya dinamis
    $(document).on('click', '.btn-remove-row-dynamic', function() {
        $(this).closest('.room-row').remove();
    });

    // 5. Reset Form saat Modal ditutup
    $('#modalCreateHotel').on('hidden.bs.modal', function () {
        var form = $(this).find('form');
        form.trigger('reset'); // Reset input text/number

        // Reset Select2 Static
        $('#order_name, #hotels').val(null).trigger('change');

        // Reset Dynamic Rows (Hapus semua baris tambahan, sisakan 1)
        $('#room-container .room-row').not(':first').remove(); // Hapus selain yang pertama
        $('.select2-room').val(null).trigger('change'); // Reset value baris pertama
    });
});
</script>
@endpush
