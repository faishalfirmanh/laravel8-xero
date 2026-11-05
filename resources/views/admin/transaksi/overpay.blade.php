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

    /*untuk dropzone*/
    /* Styling agar thumbnail Dropzone bisa di-klik */

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
        transform: scale(1.05); /* Sedikit membesar saat di-hover */
    }

    #itemTable {
        table-layout: fixed;
        width: 100%;
    }
    #itemTable td { overflow: hidden; }

    #itemTable .qty-input,
    #itemTable .price-input,
    #itemTable .amount-row {
        width: 100%;
        min-width: 0;
    }

    /* Cegah teks panjang di Select2 (Item/Account/Paket/Divisi) ikut merampas lebar kolom lain */
    #itemTable .select2-container { width: 100% !important; }
    #itemTable .select2-selection--single .select2-selection__rendered {
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }

    /**/
    /* Dropdown bank muncul di atas modal (karena dropdownParent: body) */
    .select2-container--open .select2-dropdown {
        z-index: 1060 !important;
    }
</style>

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Overpay</h5>
        <div class="form-group mb-0 d-flex align-items-center flex-wrap">
            <label class="mb-0 mr-2 small font-weight-bold">Dari</label>
            <input type="date" id="filter_date_start"
                class="form-control form-control-sm mr-2"
                style="width: 150px;">

            <label class="mb-0 mr-2 small font-weight-bold">Sampai</label>
            <input type="date" id="filter_date_end"
                class="form-control form-control-sm mr-2"
                style="width: 150px;">

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
                    <th>Nominal Overpayment</th>
                    <th>No Invoice</th>
                    <th>Nama Bank</th>
                    <th width="15%">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalCreateHotel" tabindex="-1" role="dialog"
     aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    Detail Overpayment <span class="text-muted" id="ov_title_inv"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="idHotelInput">

                {{-- Ringkasan --}}
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <small class="text-muted d-block">Kontak / Jamaah</small>
                        <strong id="ov_name_contact">-</strong>
                    </div>
                    <div class="col-md-3 mb-3">
                        <small class="text-muted d-block">No. Invoice</small>
                        <strong id="ov_inv_number">-</strong>
                    </div>
                    <div class="col-md-3 mb-3">
                        <small class="text-muted d-block">Bank</small>
                        <strong id="ov_bank_name">-</strong>
                        <small class="text-muted d-block" id="ov_bank_code"></small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <small class="text-muted d-block">Nominal Overpayment</small>
                        <strong class="text-danger" id="ov_nominal">Rp 0</strong>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <small class="text-muted d-block">Dibuat</small>
                        <span id="ov_created_at">-</span>
                    </div>
                    <div class="col-md-3 mb-3">
                        <small class="text-muted d-block">Terakhir diubah</small>
                        <span id="ov_updated_at">-</span>
                    </div>
                </div>

                {{-- Riwayat pengembalian --}}
                <div class="border-top pt-3">
                    <h6 class="font-weight-bold mb-3">
                        <i class="ti ti-history mr-1"></i> Riwayat Pengembalian
                        <span class="badge badge-secondary" id="ov_history_count">0</span>
                    </h6>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover bg-white" id="tblHistoryPengembalian">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width:50px">No</th>
                                    <th>Tanggal</th>
                                    <th>Bank</th>
                                    <th>Referensi</th>
                                    <th class="text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr class="font-weight-bold">
                                    <td colspan="4" class="text-right">Total Dikembalikan</td>
                                    <td class="text-right" id="ov_total_refund">Rp 0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalEditOverpay" tabindex="-1" role="dialog"
     aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Overpayment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="formEditOverpay" novalidate>
                @csrf
                <input type="hidden" name="id" id="edit_ov_id">

                <div class="modal-body">
                    <div class="form-group">
                        <label>No. Invoice</label>
                        <input type="text" class="form-control" id="edit_ov_inv_number" disabled>
                    </div>

                    <div class="form-group mb-0">
                        <label>Nominal Overpayment <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="nominal_overpayment"
                               id="edit_ov_nominal" min="0" step="any" required>
                        <small class="text-muted" id="edit_ov_nominal_preview"></small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveOverpay">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- modal preview -->
<div class="modal fade" id="previewImageModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">
                    <span aria-hidden="true" style="font-size: 2.5rem;">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="previewImageModalSrc" src="" alt="Preview Gambar" style="max-width: 100%; max-height: 80vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>


$(document).ready(function() {
    var table;

  
    // --- 1. DATATABLE CONFIG ---
    let columnOver = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
         { data: 'name_contact', name: 'name_contact' },
        { data: 'nominal_overpayment', className: "text-left",
            render: function(data, type, row, meta) {
                return formatCurrency(Number(data));
            }
        },
        {
            data: 'inv_number',
            name: 'inv_number',
          
        },
        {
            data: 'bank_name',
            name: 'bank_name',
          
        },
        {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data, type, row) {
            let show_data = `<a style="margin-left:5px;" href="javascript:;" 
                            data-id="${data}" 
                            class="text-danger view_history" 
                            title="view overpay">
                            <i class="ti ti-eye"></i>
                        </a>`
                return `<a href="javascript:;" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-pencil"></i></a> &nbsp ${show_data}`;
            },
        }
    ];


    $('#tableHotel').on('click', '.view_history', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data(); 
        $('#idHotelInput').val(id);
        $('#modalCreateHotel').modal('show');

        $('#modalCreateHotel').one('shown.bs.modal', function () {
            loadHistoryOverpay(id);
        })
    });

    
   const rupiahFmt = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });

// Preview nominal saat mengetik
$(document).on('input', '#edit_ov_nominal', function () {
    $('#edit_ov_nominal_preview').text(this.value !== '' ? rupiahFmt(this.value) : '');
});

// Klik tombol edit -> hit detail dulu -> isi form -> tampilkan modal
$('#tableHotel').on('click', '.edit-hotel', function () {
    let id = $(this).data('id');
    const $btn = $(this).prop('disabled', true);

    // reset form
    $('#formEditOverpay')[0].reset();
    $('#edit_ov_id').val('');
    $('#edit_ov_inv_number').val('');
    $('#edit_ov_nominal_preview').text('');

    ajaxRequest(`{{ route('detail-list-overpay-kembalian') }}`, 'GET', { id: id }, localStorage.getItem("token"))
        .then(response => {
            if (response.status == 200 && response.data && response.data.data) {
                const d = response.data.data;

                $('#edit_ov_id').val(d.id);
                $('#edit_ov_inv_number').val(d.inv_number || (d.get_inv ? d.get_inv.invoice_number : '') || '');

                // "9968000.0000" -> 9968000
                const nominal = parseFloat(d.nominal_overpayment) || 0;
                $('#edit_ov_nominal').val(nominal);
                $('#edit_ov_nominal_preview').text(rupiahFmt(nominal));

                $('#modalEditOverpay').modal('show');
            } else {
                Swal.fire('Gagal!', 'Data tidak ditemukan.', 'error');
            }
        })
        .catch((err) => {
            console.log('error', err);
            Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
        })
        .finally(() => {
            $btn.prop('disabled', false);
        });
});

// Simpan: hanya id + nominal_overpayment
$('#formEditOverpay').on('submit', function (e) {//update tabel overpayment saja
    e.preventDefault();

    const id      = $('#edit_ov_id').val();
    const nominal = $('#edit_ov_nominal').val();

    if (!id) {
        return Swal.fire('Gagal!', 'ID tidak valid.', 'error');
    }
    if (nominal === '' || isNaN(nominal) || Number(nominal) < 0) {
        return Swal.fire('Perhatian', 'Nominal overpayment wajib diisi dan tidak boleh negatif.', 'warning');
    }

    const $btn = $('#btnSaveOverpay').prop('disabled', true).text('Menyimpan...');

    ajaxRequest(`{{ route('save-update-overpay') }}`, 'POST', {
        id: id,
        nominal_overpayment: nominal,
    }, localStorage.getItem("token"))
        .then(response => {
            if ([200, 201].includes(response.status)) {
                $('#modalEditOverpay').modal('hide');
                Swal.fire('Berhasil!', (response.data && response.data.message) || 'Data berhasil disimpan.', 'success');

                // refresh tabel tanpa reset halaman
                if ($.fn.DataTable.isDataTable('#tableHotel')) {
                    $('#tableHotel').DataTable().ajax.reload(null, false);
                }
            } else {
                Swal.fire('Gagal!', (response.data && response.data.message) || 'Gagal menyimpan.', 'error');
            }
        })
        .catch((err) => {
            console.log('error', err);
            Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
        })
        .finally(() => {
            $btn.prop('disabled', false).text('Simpan');
        });
});
   


    $(document).on('click', '.btn-delete-payment', function() {
        let payId       = $(this).data('id');
        let parentBillId = $('#idHotelInput').val();

        Swal.fire({
            title: 'Hapus Pembayaran?',
            html: 'Data pembayaran ini akan <strong>dihapus permanen</strong><br>dan nominal tagihan akan dikembalikan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest(
                    `{{ route('delete-pay-bill') }}`,
                    'POST',
                    { id: payId, id_parent_bill: parentBillId },
                    localStorage.getItem("token")
                )
                .then(response => {
                    if (response.status == 200) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Dihapus!',
                            text: 'Data pembayaran berhasil dihapus.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            loadBills(parentBillId);
                            table.ajax.reload(null, false);
                        });
                    }
                })
                .catch((err) => {
                    cathError(err);
                });
            }
        });
    });


    const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });

const esc = (s) => $('<div>').text(s ?? '').html();

const fmtDateTime = (s) => {
    if (!s) return '-';
    const d = new Date(s);
    return isNaN(d) ? esc(s) : d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};

const fmtDate = (s) => {
    if (!s) return '-';
    const d = new Date(s + 'T00:00:00');
    return isNaN(d) ? esc(s) : d.toLocaleDateString('id-ID', { dateStyle: 'medium' });
};

function resetOverpayModal() {
    $('#ov_title_inv, #ov_bank_code').text('');
    $('#ov_name_contact, #ov_inv_number, #ov_bank_name, #ov_created_at, #ov_updated_at').text('-');
    $('#ov_nominal, #ov_total_refund').text('Rp 0');
    $('#ov_history_count').text('0');
    $('#tblHistoryPengembalian tbody').html(
        '<tr><td colspan="5" class="text-center text-muted">Memuat data...</td></tr>'
    );
}

function renderOverpay(d) {
    const inv   = d.get_inv    || {};
    const bank  = d.get_bank   || {};
    const jam   = d.get_jamaah || {};
    const hist  = Array.isArray(d.history_pengembalian) ? d.history_pengembalian : [];

    $('#ov_title_inv').text('- ' + (d.inv_number || inv.invoice_number || ''));
    $('#ov_name_contact').text(d.name_contact || jam.full_name || '-');
    $('#ov_inv_number').text(d.inv_number || inv.invoice_number || '-');
    $('#ov_bank_name').text(d.bank_name || bank.name || '-');
    $('#ov_bank_code').text(bank.code ? 'Kode: ' + bank.code : '');
    $('#ov_nominal').text(rupiah(d.nominal_overpayment));
    $('#ov_created_at').text(fmtDateTime(d.created_at));
    $('#ov_updated_at').text(fmtDateTime(d.updated_at));

    const $tbody = $('#tblHistoryPengembalian tbody').empty();
    let total = 0;

    if (!hist.length) {
        $tbody.html('<tr><td colspan="5" class="text-center text-muted">Belum ada pengembalian</td></tr>');
    }

    hist.forEach((h, i) => {
        // SESUAIKAN nama field nominal/bank/referensi dengan isi history_pengembalian di response
        const nominal = Number(h.nominal ?? h.nominal_spend ?? h.nominal_pengembalian ?? h.amount ?? 0);
        total += nominal;

        $tbody.append(`
            <tr>
                <td class="text-center">${i + 1}</td>
                <td>${fmtDate(h.date_transaction)}</td>
                <td>${esc(h.bank_name ?? h.get_bank?.name ?? d.bank_name ?? '-')}</td>
                <td>${esc(h.reference ?? h.reference_detail ?? '-')}</td>
                <td class="text-right">${rupiah(nominal)}</td>
            </tr>
        `);
    });

    $('#ov_history_count').text(hist.length);
    $('#ov_total_refund').text(rupiah(total));
}

    function loadHistoryOverpay(id) {
        $('#idHotelInput').val(id);
        resetOverpayModal();

        ajaxRequest(`{{ route('detail-list-overpay-kembalian') }}`, 'GET', { id: id }, localStorage.getItem("token"))
            .then(response => {
                if (response.status == 200 && response.data && response.data.data) {
                    renderOverpay(response.data.data);
                } else {
                    Swal.fire('Gagal!', 'Data tidak ditemukan.', 'error');
                }
            })
            .catch((err) => {
                console.log('error', err);
                $('#tblHistoryPengembalian tbody').html(
                    '<tr><td colspan="5" class="text-center text-danger">Gagal memuat data</td></tr>'
                );
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
            });
    }
   
   

    function loadTable(status_type) {
        if ($.fn.DataTable.isDataTable('#tableHotel')) {
          $('#tableHotel').DataTable().destroy();
        }

         table = initGlobalDataTableTokenSelected(
            '#tableHotel',
            `{{ route('list-overpay') }}`,
            columnOver,
            {
                'kolom_name' : 'nominal_overpayment'
            }
        );
     }

    $('#filter_date_start, #filter_date_end').on('change', function() {
        let statusVal = $('#filter_status').val();
        loadTable(statusVal);
    });


    $('#filter_date_start, #filter_date_end').on('change', function () {
        let s = $('#filter_date_start').val();
        let e = $('#filter_date_end').val();
        if (s && e && s > e) {
            Swal.fire('Perhatian', 'Tanggal "Dari" tidak boleh lebih besar dari "Sampai".', 'warning');
            $(this).val('');
        }
    });

    let initialType = $('#filter_status').val(); 
    loadTable(initialType);

     $('#filter_status').on('change', function() {
       let selectedType = $(this).val();
       loadTable(selectedType);
    });

});
</script>
@endpush