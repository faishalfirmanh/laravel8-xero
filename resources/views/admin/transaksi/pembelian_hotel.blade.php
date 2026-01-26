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
            <p><strong>Nama Pemesan:</strong><br><span id="nama_pemesan"></span></p>
          </div>
          <div class="col-6 col-md-3">
            <p><strong>Check In:</strong><br><span id="check_in_inv"></span></p>
          </div>
          <div class="col-6 col-md-3">
            <p><strong>Check Out:</strong><br><span id="check_out_inv"></span></p>
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
          <i class="fa fa-print"></i> Print PDF
        </button>
      </div>

    </div>
  </div>
</div>


@endsection

@push('scripts')
<script>
     function printInvoice() {
        window.print();
    }
$(document).ready(function() {
    var table;

    // --- HELPER 1: Hitung Hari ---

    function hitungTotalHari() {
        const checkIn  = $('#check_in').val();
        const checkOut = $('#check_out').val();

        if (!checkIn || !checkOut) {
            $('#total_days').val('');
            return;
        }
        const startDate = new Date(checkIn);
        const endDate   = new Date(checkOut);

        if (endDate < startDate) {
            alert('Tanggal Check Out harus setelah Check In');
            $('#check_out').val('');
            return;
        }
        const diffTime = endDate.getTime() - startDate.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        $('#total_days').val(diffDays);
    }
    $('#check_in, #check_out').on('change', hitungTotalHari);

    // --- HELPER 2: Currency ---
    ajaxRequest(`{{ route('getByIdCurrency') }}`, 'GET', { id : 1 }, localStorage.getItem('token'))
        .then(response => {
            $("#text_currency").text(`Rp. ${Number(response.data.data.nominal_rupiah_1_riyal)}`);
        })
        .catch((err) => console.log('error currency', err));

    // --- 3. SELECT2 STATIC (Hotel & Agent) ---
    $('#hotels').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#modalCreateHotel'),
        placeholder: "Pilih Hotel",
        allowClear: true,
        ajax: {
            url: `{{ route('search_hotel_select2') }}`,
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
        dropdownParent: $('#modalCreateHotel'),
        placeholder: 'Pilih Agent / Mitra',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: `{{ route('search-contact-select2') }}`,
            dataType: 'json',
            delay: 1000,
            headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') },
            data: function (params) { return { name: params.term, page: params.page || 1 }; },
            processResults: function (response, params) {
                params.page = params.page || 1;
                return {
                    results: (response.data.Contacts || []).map(item => ({ id: item.ContactID, text: item.Name })),
                    pagination: { more: (response.data.Contacts || []).length === 100 }
                };
            },
            cache: true
        }
    });

    // --- 4. LOGIC DYNAMIC ROW (INI YANG KURANG SEBELUMNYA) ---

    // Fungsi Init Select2 untuk baris baru
    function initRoomSelect2(element) {
        $(element).select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalCreateHotel'),
            placeholder: "Pilih Tipe",
            allowClear: true
        });
    }

    // FUNGSI UTAMA: Membuat HTML Baris Kamar (Bisa dipakai Edit & Tambah Baru)
    window.appendRoomRow = function(typeVal = '', qtyVal = 1, priceVal = '', isFirstRow = false) {
        // Helper selected
        const isSel = (val) => (String(typeVal) === String(val) ? 'selected' : '');

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
                            <span class="input-group-text font-weight-bold text-xs">SAR</span>
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
        $(this).find('form')[0].reset();
        $('#idHotelInput').val('');
        $('#order_name, #hotels').val(null).trigger('change');

        // Reset container kamar, sisakan 1 default
        $('#room-container').empty();
        appendRoomRow('', 1, '', true);
    });


    // --- 5. DATATABLE & EDIT ---

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
        {
            data: "id", className: "text-center", orderable: false, searchable: false,
            render: function(data) {
                let btnEdit = `<a href="javascript:;" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-pencil"></i></a>`;
                let btnHapus = `<a href="javascript:;" data-id="${data}" class="text-danger deleted-hotel"><i class="ti ti-trash"></i></a>`;
                let btn_detail = `<a href="javascript:;" style="margin-left:5px;" data-id="${data}" class="text-success view-hotel"><i class="ti ti-eye"></i></a>`;
                return btnEdit + btnHapus + btn_detail;
            }
        }
    ];

    table = initGlobalDataTableToken('#tableHotel', `{{ route('list-revanue-hotel') }}`, columnHotel, { "kolom_name": "nama_pemesan" });

    function formatDate(date) {
        const [y, m, d] = date.split('-');
        return `${d}/${m}/${y}`;
    }

    function formatNumber(num) {
        return parseFloat(num).toLocaleString('id-ID');
    }

    $("#tableHotel").on('click','.view-hotel',function(){
        let id = $(this).data('id');
          ajaxRequest(`{{ route('byid-revanue-hotel') }}`, 'GET', { id: id }, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200) {
                    let data = response.data.data;
                    document.getElementById('hotel_name').innerText = data.hotel_name;
                    document.getElementById('no_invoice').innerText = data.no_invoice_hotel;
                    document.getElementById('nama_pemesan').innerText = data.nama_pemesan;
                    document.getElementById('check_in_inv').innerText = formatDate(data.check_in);
                    document.getElementById('check_out_inv').innerText = formatDate(data.check_out);
                    document.getElementById('total_payment').innerText = formatNumber(data.total_payment);
                    document.getElementById('total_payment_rp').innerText = formatNumber(data.total_payment_rupiah);

                    let rows = '';
                    data.details.forEach(item => {
                        rows += `
                            <tr>
                                <td>${item.type_room_desc}</td>
                                <td class="text-center">${item.qty}</td>
                                <td class="text-right">Rp ${formatNumber(item.price_each_item)}</td>
                                <td class="text-right">Rp ${formatNumber(item.total_amount)}</td>
                            </tr>
                        `;
                    });

                    document.getElementById('detail_rows').innerHTML = rows;
                    $('#invoiceModal').modal('show');

                    if (!data) { Swal.fire('Error', 'Data tidak ditemukan', 'error'); return; }
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
        $('.modal-title').text('Edit Invoice Hotel (Loading...)');

        ajaxRequest(`{{ route('byid-revanue-hotel') }}`, 'GET', { id: id }, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200) {
                    let data = response.data.data;

                    if (!data) { Swal.fire('Error', 'Data tidak ditemukan', 'error'); return; }

                    // Isi Header
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

                    $('.modal-title').text('Edit Invoice: ' + data.no_invoice_hotel);
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

    // Trigger Tambah Baru
    $("#button_add_hotel").on("click", function() {
        $('.modal-title').text('Add New Invoices Hotel');
        // Reset handled by modal hidden event
    });

    // --- 7. SIMPAN DATA ---
    $('#formCreateHotel').on('submit', function(e) {
        e.preventDefault();
        var btn = $('#btnSave');
        var originalText = btn.html();
        btn.html('<i class="fa fa-spin fa-spinner"></i> Menyimpan...').prop('disabled', true);

        let formData = new FormData(this);
        let idInput = $('#idHotelInput').val();
        if (idInput) formData.append('id', idInput);

        $.ajax({
            url: "{{ route('save-revanue-hotel') }}",
            type: "POST",
            data: formData,
            processData: false, contentType: false,
            headers: { 'Authorization': localStorage.getItem("token") },
            success: function(response) {
                $("#modalCreateHotel").modal('hide');
                if (response.status) {
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data disimpan' });
                    table.ajax.reload();
                } else {
                    Swal.fire('Gagal!', response.meta.message || 'Error', 'error');
                }
            },
            error: function(xhr) {
                let msg = (xhr.responseJSON && xhr.responseJSON.meta) ? xhr.responseJSON.meta.message : 'Error';
                Swal.fire('Gagal!', msg, 'error');
            },
            complete: function() {
                btn.html(originalText).prop('disabled', false);
            }
        });
    });

});
</script>
@endpush
