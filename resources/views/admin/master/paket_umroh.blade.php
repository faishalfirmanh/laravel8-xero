@extends('layouts.app')

<style>
    #modalPaket .modal-dialog {
        max-width: 1100px;
        margin: 1.75rem auto;
    }
    #modalPaket .modal-content {
        max-height: calc(100vh - 3.5rem);
        display: flex;
        flex-direction: column;
    }
    #formPaket {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
    }
    #modalPaket .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
    }
    #modalPaket .modal-footer {
        flex-shrink: 0;
    }

    /*--rapikan select2*/
    /* ══════════════════════════════════════════════
   MODAL ADD JAMAAH — Select2 rapi di input-group
   ══════════════════════════════════════════════ */
#modalAddJamaah .input-group {
    flex-wrap: nowrap;
    align-items: stretch;
}
#modalAddJamaah .input-group > .select2-container {
    flex: 1 1 auto;
    width: 1% !important;      /* trik agar flex-grow bekerja di input-group */
    min-width: 0;
    position: relative;
}
#modalAddJamaah .select2-container--bootstrap4 .select2-selection--single {
    height: calc(1.5em + 0.75rem + 2px) !important;
    display: flex !important;
    align-items: center !important;
    position: relative !important;
    border: 1px solid #ced4da !important;
    border-radius: 0.25rem 0 0 0.25rem !important;  /* sambung dengan tombol di kanan */
    background-color: #fff !important;
}
#modalAddJamaah .select2-selection__rendered {
    display: block !important;
    flex: 1 1 auto;
    min-width: 0;
    line-height: 1.5 !important;
    padding-left: 0.6rem !important;
    padding-right: 2.2rem !important;
    font-size: 0.875rem !important;
    color: #333 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}
#modalAddJamaah .select2-selection__clear {
    position: absolute !important;
    right: 1.5rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    margin: 0 !important;
    line-height: 1 !important;
    color: #888 !important;
}
#modalAddJamaah .select2-selection__arrow {
    position: absolute !important;
    right: 0.4rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    height: auto !important;
}
#modalAddJamaah .select2-selection__placeholder {
    color: #aaa !important;
    font-size: 0.875rem !important;
}

.kota-badge { font-weight: 600; font-size: 0.8em; }
.kota-madinah { color: #198754; }  /* hijau */
.kota-makkah  { color: #b8860b; }  /* emas */
</style>
@section('content')
<div class="card shadow mb-5">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap">
        <h5 class="mb-0 font-weight-bold text-primary">Paket Umroh</h5>
        <div class="d-flex flex-wrap">
            <select id="filterTipe" class="form-control form-control-sm mr-2" style="width:160px;">
                <option value="">Semua Tipe</option>
            </select>
            <input type="text" id="filterKeyword" class="form-control form-control-sm mr-2" placeholder="Cari nama paket" style="width:320px;">
            <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="btnFilter">Cari</button>
            <button type="button" class="btn btn-sm btn-outline-success mr-2" id="btnDownloadImg">
                <i class="ti ti-photo-down mr-1"></i> Download Gambar
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="btnAddPaket">
                <i class="ti ti-plus mr-1"></i> Tambah Paket
            </button>
        </div>
    </div>

    <div class="table-responsive p-3"  id="tableCapture">
        <div id="captureTitle" style="display:none;" class="mb-3">
            <h5 class="font-weight-bold mb-0">Daftar Paket Umroh</h5>
            <small class="text-muted" id="captureDate"></small>
        </div>
        <table class="table table-hover table-bordered w-100">
            <thead class="thead-dark">
                <tr>
                    <th class="text-center" width="4%">No</th>
                    <th>Status</th>
                    <th>Nama Paket</th>
                    <th>Maskapai</th>
                    <th>Rute</th>
                    <th>Hotel</th>
                    <th class="text-center">Hari</th>
                    <th class="text-center">Quota</th>
                    <th class="text-center">Terisi</th>
                    <th class="text-center">Tersedia</th>
                    <th class="text-right">Harga Quad</th>
                    <th class="text-right">Harga Triple</th>
                    <th class="text-right">Harga Double</th>
                    <th class="text-right">Fasilitas Tambahan</th>
                    <th class="text-center no-capture" width="12%">Aksi</th>
                </tr>
            </thead>
            <tbody id="paketBody"></tbody>
        </table>
        <div id="pager" class="d-flex align-items-center justify-content-end"></div>
    </div>
</div>

{{-- ================= MODAL PAKET ================= --}}
<div class="modal fade" id="modalPaket" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalPaketTitle">Tambah Paket Umroh</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>

            <form id="formPaket">
                <input type="hidden" id="paket_id">

                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Tipe <span class="text-danger">*</span></label>
                            <select class="form-control" name="tipe" id="tipe" required></select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small font-weight-bold">Travel <span class="text-danger">*</span></label>
                            <select class="form-control" name="travel" id="travel" required></select>
                        </div>           
                        <div class="form-group col-md-5">
                            <label class="small font-weight-bold d-block">
                                Status Jual <span class="text-danger">*</span>
                            </label>

                            <div class="form-check form-check-inline">
                                <input
                                    type="radio"
                                    class="form-check-input"
                                    name="is_posting"
                                    id="is_posting_1"
                                    value="1"
                                    required
                                >
                                <label class="form-check-label" for="is_posting_1">
                                    Posting
                                </label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input
                                    type="radio"
                                    class="form-check-input"
                                    name="is_posting"
                                    id="is_posting_0"
                                    value="0"
                                    required
                                >
                                <label class="form-check-label" for="is_posting_0">
                                    Draft
                                </label>
                            </div>
                        </div>
                         <div class="form-group col-md-5">
                            <label class="small font-weight-bold d-block">
                                Paket <span class="text-danger">*</span>
                            </label>

                            <div class="form-check form-check-inline">
                                <input
                                    type="radio"
                                    class="form-check-input"
                                    name="is_internal"
                                    id="is_internal_1"
                                    value="1"
                                    required
                                >
                                <label class="form-check-label" for="is_internal_1">
                                    Internal (agent)
                                </label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input
                                    type="radio"
                                    class="form-check-input"
                                    name="is_internal"
                                    id="is_internal_0"
                                    value="0"
                                    required
                                >
                                <label class="form-check-label" for="is_internal_0">
                                    External (kantor)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Tgl Berangkat <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tgl_berangkat" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small font-weight-bold">Jumlah Hari <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="jumlah_hari" min="1" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Maskapai</label>
                            <input type="text" class="form-control" placeholder="Lion" name="maskapai" maxlength="100">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small font-weight-bold">Kode Flight</label>
                            <input type="text" placeholder="GA 321" class="form-control" name="kodeflight" maxlength="50">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Keberangkatan <span class="text-danger">*</span> </label>
                            <input type="text" placeholder="Surabaya atau SBY" class="form-control" name="keberangkatan" required maxlength="100">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Landing <span class="text-danger">*</span></label>
                            <input type="text" placeholder="Jeddah atau JED" class="form-control" name="landing" required maxlength="100">
                        </div>
                          <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Nama Paket <span class="text-danger">*</span></label>
                            <input type="text" class="form-control"  style="width:420px;" name="nama_paket" required  id="nama_paket"
                                   placeholder="contoh: Umroh 12 hari an namiroh">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label class="small font-weight-bold">Harga Infant</label>
                            <input type="number" class="form-control" name="hrginfant" min="0" step="0.01">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small font-weight-bold">Harga Quad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="hrgquad" min="0" step="0.01" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small font-weight-bold">Harga Triple <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="hrgtriple" min="0" step="0.01" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small font-weight-bold">Harga Double <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="hrgdouble" min="0" step="0.01" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small font-weight-bold">Perlengkapan</label>
                            <input type="number" class="form-control" name="hrgperlengkapan" min="0" step="0.01" value="0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label class="small font-weight-bold">Max Slot <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="max_slot" min="1" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small font-weight-bold">Tersedia <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="available_slot" min="0" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small font-weight-bold">Terisi <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="filled_slot" min="0" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Gambar Paket</label>
                            <input type="file" class="form-control-file" name="imgpaketumroh" accept="image/*">
                            <img id="previewImg" src="" alt="" style="max-height:50px;display:none;" class="mt-1">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Itinerary (PDF)</label>
                            <input type="file" class="form-control-file" name="itinerary" accept="application/pdf">
                            <a id="linkItinerary" href="#" target="_blank" class="small" style="display:none;">Lihat itinerary saat ini</a>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="fasilitas_tambahan" class="small font-weight-bold">
                                Fasilitas Tambahan
                            </label>

                            <textarea
                                id="fasilitas_tambahan"
                                name="fasilitas_tambahan"
                                class="form-control"
                                rows="3"
                                placeholder="Masukkan fasilitas tambahan..."
                            ></textarea>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="font-weight-bold mb-0">Hotel</h6>
                        <div>
                            <button type="button" class="btn btn-outline-success btn-sm mr-1" id="btnAddMasterHotel">
                                <i class="ti ti-building"></i> Tambah Master Hotel
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddHotel">
                                <i class="ti ti-plus"></i> Tambah Hotel
                            </button>
                        </div>
                    </div>
                    <div id="hotelRows"></div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="btnSavePaket">
                        <i class="ti ti-check mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ================= MODAL MASTER HOTEL ================= --}}
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

{{-- ================= MODAL ADD JAMAAH ================= --}}
<div class="modal fade" id="modalAddJamaah" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Add Jamaah - <span id="aj_paket_name"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>

            <form id="formAddJamaah">
                <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                    <div class="alert alert-light border small mb-3">
                        Terisi <b id="aj_filled">0</b> / <b id="aj_max">0</b>, sisa slot <b id="aj_sisa">0</b>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label class="small font-weight-bold">Jamaah <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select id="aj_jamaah" class="form-control" required></select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-success" id="btnAddMasterJamaah">
                                        <i class="ti ti-user-plus"></i> Master
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small font-weight-bold">Jumlah jamaah dibawa <span class="text-danger">*</span></label>
                            <input type="number" id="aj_jumlah" class="form-control" min="1" required>
                        </div>
                    </div>

                    <hr>
                    <h6 class="font-weight-bold">Jamaah pada paket ini</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-dark">
                                <tr>
                                    <th width="6%" class="text-center">No</th>
                                    <th>Nama</th>
                                    <th>NIK</th>
                                    <th class="text-center" width="15%">Jumlah</th>
                                    <th class="text-center" width="10%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="aj_list"></tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-info btn-sm font-weight-bold" id="btnSaveAddJamaah">
                        <i class="ti ti-check mr-1"></i> Simpan Jamaah
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ================= MODAL MASTER JAMAAH ================= --}}
<div class="modal fade" id="modalMasterJamaah" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Tambah Master Jamaah</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>

            <form id="formMasterJamaah">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="small font-weight-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="mj_full_name" required maxlength="150">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">NIK <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="mj_nik" required maxlength="32">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">No HP</label>
                        <input type="text" class="form-control" id="mj_phone" maxlength="20">
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Alamat</label>
                        <textarea class="form-control" id="mj_address" rows="2" maxlength="500"></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold" id="btnSaveMasterJamaah">
                        <i class="ti ti-check mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ================= MODAL PINDAH PAKET ================= --}}
<div class="modal fade" id="modalPindah" tabindex="-1" role="dialog" data-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Pindah Paket</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <form id="formPindah">
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        Jamaah: <b id="pp_nama">-</b><br>
                        Jumlah di paket ini: <b id="pp_jumlah_asal">0</b>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold">Paket tujuan <span class="text-danger">*</span></label>
                        <select id="pp_paket" class="form-control" required></select>
                    </div>

                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Jumlah orang dipindah <span class="text-danger">*</span></label>
                        <input type="number" id="pp_jumlah" class="form-control" min="1" required>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm font-weight-bold" id="btnSavePindah">
                        <i class="ti ti-arrows-exchange mr-1"></i> Pindahkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
const paketRoutes = {
    list           : "{{ route('paket-umroh.index') }}",
    show           : "{{ route('paket-umroh.show', ['id' => '__ID__']) }}",
    store          : "{{ route('paket-umroh.store') }}",
    update         : "{{ route('paket-umroh.update', ['id' => '__ID__']) }}",
    destroy        : "{{ route('paket-umroh.destroy', ['id' => '__ID__']) }}",
    options        : "{{ route('paket-umroh.options') }}",
    saveMasterHotel: "{{ route('saveMasterHotel') }}",
};

// ── Download tabel sebagai gambar HD ──
// ── Download tabel sebagai gambar HD ──
$('#btnDownloadImg').on('click', async function () {
    const el = document.getElementById('tableCapture');
    if (!el) {
        Swal.fire('Gagal', 'Elemen #tableCapture tidak ditemukan.', 'error');
        return;
    }
    if (!$('#paketBody tr').length) {
        Swal.fire('Peringatan', 'Belum ada data untuk diunduh.', 'warning');
        return;
    }

    const $btn = $(this).prop('disabled', true);
    const oldHtml = $btn.html();
    $btn.html('<span class="spinner-border spinner-border-sm mr-1"></span> Memproses...');

    // Wrapper sementara di dalam halaman (CSS Bootstrap tetap berlaku)
    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:fixed;top:0;left:0;z-index:-1;background:#fff;padding:16px;'
                       + 'width:' + el.scrollWidth + 'px;';

    try {
        const clone = el.cloneNode(true);

        // Judul tampil
        const title = clone.querySelector('#captureTitle');
        if (title) {
            title.style.display = 'block';
            const dt = title.querySelector('#captureDate');
            if (dt) dt.textContent = 'Dicetak: ' + new Date().toLocaleString('id-ID');
        }

        // Buang kolom Aksi dan pager langsung dari clone
        clone.querySelectorAll('.no-capture, #pager').forEach(n => n.remove());

        // Hindari id ganda di halaman
        clone.querySelectorAll('[id]').forEach(n => n.removeAttribute('id'));
        clone.style.overflow = 'visible';

        wrap.appendChild(clone);
        document.body.appendChild(wrap);

        // Beri waktu browser menghitung layout
        await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

        const w = wrap.scrollWidth;
        const h = wrap.scrollHeight;
        const scale = Math.min(3, 16000 / Math.max(w, h));

        const canvas = await html2canvas(wrap, {
            scale: scale,
            backgroundColor: '#ffffff',
            useCORS: true,
            logging: false,
        });

        canvas.toBlob(function (blob) {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `paket-umroh-${new Date().toISOString().slice(0, 10)}.png`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
        }, 'image/png');

    } catch (err) {
        console.error(err);
        Swal.fire('Gagal', 'Tidak bisa membuat gambar: ' + err.message, 'error');
    } finally {
        if (wrap.parentNode) wrap.remove();
        $btn.prop('disabled', false).html(oldHtml);
    }
});

let opts = { tipe: [], travel: [], hotels: [] };
let hotelIdx = 0;

const BULAN_ID = ['januari', 'februari', 'maret', 'april', 'mei', 'juni',
                   'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];

let namaPaketManual = false;


function formatHotels(hotels) {
    if (!Array.isArray(hotels) || hotels.length === 0) return '-';

    // Madinah dulu (0), lalu Makkah (1), lainnya di belakang
    const rank = k => {
        const x = String(k || '').toLowerCase();
        if (x === 'madinah') return 0;
        if (x === 'makkah')  return 1;
        return 2;
    };

    return [...hotels]
        .sort((a, b) => rank(a.kota) - rank(b.kota))
        .map(h => {
            const kota  = String(h.kota || '');
            const cls   = kota.toLowerCase() === 'madinah' ? 'kota-madinah'
                        : kota.toLowerCase() === 'makkah'  ? 'kota-makkah' : '';
            const night = h.night ? ` ${esc(h.night)}N` : '';
            return `${esc(h.nama_hotel.toLowerCase())} <span class="kota-badge ${cls}">(${esc(kota)})</span>${night}`;
        })
        .join(' - ');
}

function buildNamaPaket() {
    if (namaPaketManual) return;

    const tipe        = ($('#tipe').val() || '').trim();
    const hari        = ($('#formPaket [name="jumlah_hari"]').val() || '').trim();
    const travelId    = ($('#travel').val() || '').trim();
    const travel_name = travelId ? $('#travel option:selected').text().trim() : '';  // ambil NAMA, bukan ID
    const tgl         = ($('#formPaket [name="tgl_berangkat"]').val() || '').trim(); // YYYY-MM-DD
    const maskapai    = ($('#formPaket [name="maskapai"]').val() || '').trim();

    if (!tipe || !hari || !tgl || !travel_name) {
        $('#nama_paket').val('');
        return;
    }

    const [y, m, d] = tgl.split('-').map(Number);

    let nama = `${tipe.toLowerCase()} - ${d} ${BULAN_ID[m - 1]} ${y} -${hari} H  - ${travel_name.toLowerCase()}`;
    if (maskapai) nama += ` - ${maskapai}`;

    $('#nama_paket').val(nama);
}

// Trigger saat tipe, hari, tanggal, atau maskapai berubah
$('#formPaket').on('change input',
    'select[name="tipe"], select[name="travel"], input[name="jumlah_hari"], input[name="tgl_berangkat"], input[name="maskapai"]',
    buildNamaPaket
);

// Deteksi input manual pada nama paket
$('#nama_paket').on('input', function () {
    namaPaketManual = $(this).val().trim() !== '';
});

const token = () => localStorage.getItem('token');

function paketUrl(template, id) {
    return template.replace('__ID__', id);
}

function esc(s) {
    return $('<div>').text(s == null ? '' : String(s)).html().replace(/"/g, '&quot;');
}

function fillSelect($sel, items, placeholder) {
    let html = `<option value="">${placeholder}</option>`;
    items.forEach(i => {
        html += `<option value="${esc(i.value)}">${esc(i.label)}</option>`;
    });
    $sel.html(html);
}

// ── Options (tipe, travel, hotel) ──
async function loadOptions() {
    try {
        const res = await ajaxRequest(paketRoutes.options, 'GET', null, token());
        opts = res.data.data;

        fillSelect($('#tipe'), opts.tipe.map(t => ({ value: t, label: t })), '-- Pilih Tipe --');
        fillSelect($('#filterTipe'), opts.tipe.map(t => ({ value: t, label: t })), 'Semua Tipe');
        fillSelect($('#travel'), opts.travel.map(t => ({ value: t.id, label: t.name })), '-- Pilih Travel --');
    } catch (err) {
        cathError(err);
    }
}

// ── List ──
async function loadPaket(page = 1) {
    try {
        const params = { page: page, per_page: 10 };

        const tipe = $('#filterTipe').val();
        if (tipe) params.tipe = tipe;

        const keyword = $('#filterKeyword').val().trim();
        if (keyword) params.keyword = keyword;

        const res = await ajaxRequest(paketRoutes.list, 'GET', params, token());
        const pg = res.data.data;

        renderRows(pg.data || []);
        renderPager(pg);
    } catch (err) {
        cathError(err);
    }
}

function renderRows(rows) {
    if (rows.length === 0) {
        $('#paketBody').html('<tr><td colspan="13" class="text-center text-muted">Belum ada data</td></tr>');
        return;
    }

    let html = '';
    rows.forEach((p, i) => {
        let cek_status_paket = p.is_internal ? 'agen' : 'umum'
        let cek_status_posting = p.is_posting ? 'posting' : 'draf';
        html += `
            <tr>
                <td class="text-center">${i + 1}</td>
                <td class="text-center">${cek_status_paket}-${cek_status_posting}</td>
                <td>${esc(p.nama_paket)}</td>
                <td>${esc(p.maskapai)}</td>
                <td>${esc(p.keberangkatan)}-${esc(p.landing)}</td>
                <td style="font-size:13px;">${formatHotels(p.hotels)}</td>
                <td class="text-center">${esc(p.jumlah_hari)}</td>
                <td class="text-center font-weight-bold">${esc(p.max_slot)}</td>
                <td class="text-center font-weight-bold">${esc(p.filled_slot)}</td>
                <td class="text-center font-weight-bold">${esc(p.available_slot)}</td>
                <td class="text-right">${Number(p.hrgquad || 0).toLocaleString('id-ID')}</td>
                <td class="text-right">${Number(p.hrgtriple || 0).toLocaleString('id-ID')}</td>
                <td class="text-right">${Number(p.hrgdouble || 0).toLocaleString('id-ID')}</td>
                <td class="text-right">${p.fasilitas_tambahan}</td>
                <td class="text-center no-capture">
                    <button type="button" class="btn btn-xs btn-info btn-add-jamaah"
                            data-id="${p.id}"
                            data-name="${esc(p.nama_paket)}"
                            data-max="${p.max_slot}"
                            data-filled="${p.filled_slot}"
                            title="Add Jamaah">
                        <i class="ti ti-users"></i>
                    </button>
                    <button type="button" class="btn btn-xs btn-warning btn-edit-paket" data-id="${p.id}" title="Edit">
                        <i class="ti ti-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-xs btn-danger btn-delete-paket" data-id="${p.id}" title="Hapus">
                        <i class="ti ti-trash"></i>
                    </button>
                </td>
            </tr>`;
    });
    $('#paketBody').html(html);
}

function renderPager(pg) {
    $('#pager').html(`
        <span class="small text-muted mr-2">Hal ${pg.current_page} / ${pg.last_page} (total ${pg.total})</span>
        <button class="btn btn-sm btn-outline-secondary mr-1" ${pg.current_page <= 1 ? 'disabled' : ''}
                onclick="loadPaket(${pg.current_page - 1})">Prev</button>
        <button class="btn btn-sm btn-outline-secondary" ${pg.current_page >= pg.last_page ? 'disabled' : ''}
                onclick="loadPaket(${pg.current_page + 1})">Next</button>`);
}

// ── Form paket ──

function resetForm() {
    $('#formPaket')[0].reset();
    $('#paket_id').val('');
    $('#hotelRows').empty();
    $('#previewImg').attr('src', '').hide();
    $('#linkItinerary').attr('href', '#').hide();
    // Default saat create
    $('#formPaket input[name="is_posting"][value="1"]')
        .prop('checked', true);

    $('#formPaket input[name="is_internal"][value="1"]')
        .prop('checked', true);

    $('#formPaket [name="fasilitas_tambahan"]').val('');

    namaPaketManual = false;
}




function fillForm(p) {
    resetForm();

    $('#paket_id').val(p.id);

    // Isi field biasa
    [
        'tipe',
        'nama_paket',
        'tgl_berangkat',
        'jumlah_hari',
        'maskapai',
        'keberangkatan',
        'landing',
        'kodeflight',
        'hrginfant',
        'hrgquad',
        'hrgtriple',
        'hrgdouble',
        'hrgperlengkapan',
        'max_slot',
        'available_slot',
        'filled_slot',
        'fasilitas_tambahan'
    ].forEach(function (k) {
        $('#formPaket [name="' + k + '"]').val(p[k] ?? '');
    });

    // Status Jual: pilih sesuai data API
    $('#formPaket input[name="is_posting"]')
        .prop('checked', false);

    if (p.is_posting !== undefined && p.is_posting !== null) {
        $('#formPaket input[name="is_posting"][value="' +
            String(p.is_posting) + '"]')
            .prop('checked', true);
    }

    // Jenis Paket: pilih sesuai data API
    $('#formPaket input[name="is_internal"]')
        .prop('checked', false);

    if (p.is_internal !== undefined && p.is_internal !== null) {
        $('#formPaket input[name="is_internal"][value="' +
            String(p.is_internal) + '"]')
            .prop('checked', true);
    }

    // Format tanggal menjadi YYYY-MM-DD
    if (p.tgl_berangkat) {
        $('#formPaket [name="tgl_berangkat"]')
            .val(String(p.tgl_berangkat).substring(0, 10));
    }
    // Travel
    $('#formPaket [name="travel"]').val(p.travel_id);

    // Nama paket
    namaPaketManual = false;

    // Preview gambar dan itinerary
    if (p.imgpaketumroh) {
        $('#previewImg').attr('src', p.imgpaketumroh).show();
    }

    if (p.itinerary) {
        $('#linkItinerary').attr('href', p.itinerary).show();
    }

    // Hotel
    (p.hotels || []).forEach(function (h) {
        addHotelRow(h);
    });
}


// ── Event: paket ──
$('#btnAddPaket').on('click', function () {
    resetForm();
    $('#modalPaketTitle').text('Tambah Paket Umroh');
    addHotelRow();
    $('#modalPaket').modal('show');
});

$('#btnAddHotel').on('click', function () {
    addHotelRow();
});

$(document).on('click', '.btn-del-hotel', function () {
    $(this).closest('.hotel-row').remove();
});

// Pilih travel -> isi nama paket awal jika masih kosong
// $('#travel').on('change', function () {
//     const id = $(this).val();
//     const travel = opts.travel.find(t => String(t.id) === String(id));
//     const $nama = $('#formPaket [name="nama_paket"]');
//     if (travel && !$nama.val()) {
//         $nama.val('umroh ' + travel.name);
//     }
// });

$(document).on('click', '.btn-edit-paket', async function () {
    const id = $(this).data('id');
    try {
        const res = await ajaxRequest(paketUrl(paketRoutes.show, id), 'GET', null, token());
        fillForm(res.data.data);
        $('#modalPaketTitle').text('Edit Paket Umroh');
        $('#modalPaket').modal('show');
    } catch (err) {
        cathError(err);
    }
});

$(document).on('click', '.btn-delete-paket', function () {
    const id = $(this).data('id');

    Swal.fire({
        title: 'Hapus paket ini?',
        text: 'Data hotel paket dan file terkait ikut dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then(async function (result) {
        if (!result.isConfirmed) return;

        try {
            const res = await ajaxRequest(paketUrl(paketRoutes.destroy, id), 'DELETE', null, token());
            Swal.fire({ icon: 'success', title: 'Dihapus', text: res.data.message, timer: 1200, showConfirmButton: false });
            loadPaket(1);
        } catch (err) {
            cathError(err);
        }
    });
});

$('#formPaket').on('submit', async function (e) {
    e.preventDefault();

    const id   = $('#paket_id').val();
    const url  = id ? paketUrl(paketRoutes.update, id) : paketRoutes.store;
    const data = new FormData(this);

    const $btn = $('#btnSavePaket').prop('disabled', true);

    try {
        const res = await ajaxRequest(url, 'POST', data, token());
        $('#modalPaket').modal('hide');
        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.data.message, timer: 1500, showConfirmButton: false });
        loadPaket(1);
    } catch (err) {
        cathError(err);
    } finally {
        $btn.prop('disabled', false);
    }
});

$('#btnFilter').on('click', function () {
    loadPaket(1);
});

$('#filterKeyword').on('keypress', function (e) {
    if (e.which === 13) loadPaket(1);
});

// ── Master hotel ──

// Muat ulang daftar hotel dan isi dropdown baris hotel yang ada.
// Pilihan yang sudah dipilih tetap dipertahankan.
function hotelOptionsHtml(selectedId = null) {
    let html = '<option value="">-- Pilih Hotel --</option>';
    opts.hotels.forEach(h => {
        const sel = String(h.id) === String(selectedId) ? 'selected' : '';
        html += `<option value="${esc(h.id)}" ${sel}>${esc(h.name)} (${esc(h.lokasi)})</option>`;
    });
    return html;
}

function addHotelRow(d = {}) {
    const i = hotelIdx++;

    $('#hotelRows').append(`
        <div class="border rounded p-2 mb-2 hotel-row d-flex align-items-end">
            <div class="form-group mb-0 flex-grow-1 mr-2">
                <label class="small mb-0">Hotel (master)</label>
                <select class="form-control form-control-sm" name="hotels[${i}][idhotelmitra]" required>
                    ${hotelOptionsHtml(d.hotel_id)}
                </select>
            </div>
            <div class="form-group mb-0 mr-2" style="width:120px;">
                <label class="small mb-0">Night <span class="text-danger">*</span></label>
                <input type="number" class="form-control form-control-sm"
                       name="hotels[${i}][night]"
                       value="${esc(d.night ?? '')}"
                       min="1" step="1" required>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger btn-del-hotel">
                <i class="ti ti-trash"></i> Hapus
            </button>
        </div>`);
}

async function refreshHotelOptions(selectValueForEmpty = null) {
    const res = await ajaxRequest(paketRoutes.options, 'GET', null, token());
    opts.hotels = res.data.data.hotels || [];

    $('#hotelRows select[name$="[idhotelmitra]"]').each(function () {
        const $sel = $(this);
        const current = $sel.val();

        $sel.html(hotelOptionsHtml(current || selectValueForEmpty));
        if (current) $sel.val(current);
    });
}

$('#btnAddMasterHotel').on('click', function () {
    $('#formMasterHotel')[0].reset();
    $('#modalMasterHotel').modal('show');
});

$('#formMasterHotel').on('submit', async function (e) {
    e.preventDefault();

    const payload = {
        name: $('#mh_name').val().trim(),
        type_location_hotel: $('#mh_type_location_hotel').val(),
        address: $('#mh_address').val().trim() || null,
        distance: $('#mh_distance').val() || null,
        star: $('#mh_star').val() || null,
    };

    const $btn = $('#btnSaveMasterHotel').prop('disabled', true);

    try {
        const res = await ajaxRequest(paketRoutes.saveMasterHotel, 'POST', payload, token());
        const saved = res.data.data;

        $('#modalMasterHotel').modal('hide');
        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.data.message, timer: 1200, showConfirmButton: false });

        await refreshHotelOptions(saved && saved.id ? saved.id : null);
    } catch (err) {
        cathError(err);
    } finally {
        $btn.prop('disabled', false);
    }
});


const jamaahRoutes = {
    select2   : "{{ route('paket-umroh.jamaah-select2') }}",
    select2_alhid   : "{{ route('paket-umroh.jamaah-alhid-select2') }}",
    list      : "{{ route('paket-umroh.jamaah.index', ['id' => '__ID__']) }}",
    list_alhid      : "{{ route('paket-umroh.jamaah-alhid', ['id' => '__ID__']) }}",
    store     : "{{ route('paket-umroh.jamaah.store', ['id' => '__ID__']) }}",
    destroy   : "{{ route('paket-umroh.jamaah.destroy', ['id' => '__ID__', 'rowId' => '__ROW__']) }}",
    saveMaster: "{{ route('save-jamaah') }}",
    saveMasterAlhid: "{{ route('save-jamaah-alhid') }}",
    select2paket : "{{ route('paket-umroh-select2') }}",
    pindah       : "{{ route('paket-umroh.jamaah.pindah', ['id' => '__ID__', 'rowId' => '__ROW__']) }}",
};

let currentPaketId = null;


function initCariPaketSelect2() {
    const $el = $('#pp_paket');
    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }

    $el.select2({
        theme: 'bootstrap4',
        width: '100%',
        dropdownParent: $('#modalPindah'),
        placeholder: 'Ketik nama paket...',
        allowClear: true,
        minimumInputLength: 3,
        language: {
            inputTooShort: () => 'Ketik minimal 3 huruf / angka...',
            searching    : () => 'Mencari...',
            noResults    : () => 'Paket tidak ditemukan',
        },
        ajax: {
            url: jamaahRoutes.select2paket,
            dataType: 'json',
            delay: 400,
            cache: false,
            data: params => ({ keyword: params.term || '' }),
            transport: function (params, success, failure) {
                const req = $.ajax({
                    url: params.url,
                    data: params.data,
                    dataType: 'json',
                    headers: { 'Authorization': 'Bearer ' + token() },
                });
                req.then(success);
                req.fail(failure);
                return req;
            },
            // Paket asal tidak ditampilkan sebagai pilihan
            processResults: response => ({
                results: (response.data.results || [])
                    .filter(r => String(r.id) !== String(currentPaketId))
            }),
        },
    });
}

// ── Select2 jamaah (ambil dari data_jamaah_xeros) ──
function initJamaahSelect2() {
    const $el = $('#aj_jamaah');
    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }

    $el.select2({
        theme: 'bootstrap4',
        width: '100%',
        dropdownParent: $('#modalAddJamaah'),
        placeholder: 'Ketik nama atau NIK jamaah...',
        allowClear: true,
        minimumInputLength: 3,
        language: {
            inputTooShort: () => 'Ketik minimal 3 huruf / angka...',
            searching    : () => 'Mencari...',
            noResults    : () => 'Jamaah tidak ditemukan',
        },
        ajax: {
            url: jamaahRoutes.select2_alhid,
            dataType: 'json',
            delay: 400,
            cache: false,
            data: params => ({ keyword: params.term || '' }),
            // Select2 memakai transport custom agar header Authorization ikut terkirim
            transport: function (params, success, failure) {
                const req = $.ajax({
                    url: params.url,
                    data: params.data,
                    dataType: 'json',
                    headers: { 'Authorization': 'Bearer ' + token() },
                });
                req.then(success);
                req.fail(failure);
                return req;
            },
            processResults: response => ({ results: response.data.results || [] }),
        },
    });
}

// ── Buka modal Add Jamaah ──
$(document).on('click', '.btn-add-jamaah', function () {
    currentPaketId = $(this).data('id');
    $('#aj_paket_name').text($(this).data('name'));
    $('#formAddJamaah')[0].reset();
    initJamaahSelect2();
    $('#modalAddJamaah').modal('show');
});

$('#modalAddJamaah').on('shown.bs.modal', function () {
    loadPaketJamaah();
});

$('#modalAddJamaah').on('hidden.bs.modal', function () {
    const $el = $('#aj_jamaah');
    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }
    loadPaket(1);
});

// ── Daftar jamaah + slot ──
async function loadPaketJamaah() {
    try {
        const url = jamaahRoutes.list_alhid.replace('__ID__', currentPaketId);
        const res = await ajaxRequest(url, 'GET', null, token());
        const d = res.data.data;

        $('#aj_max').text(d.paket.max_slot);
        $('#aj_filled').text(d.paket.filled_slot);
        $('#aj_sisa').text(Math.max(0, d.paket.max_slot - d.paket.filled_slot));

        console.log('d',d)
        renderPaketJamaah(d.jamaah || []);
    } catch (err) {
        cathError(err);
    }
}

function renderPaketJamaah(rows) {
    if (rows.length === 0) {
        $('#aj_list').html('<tr><td colspan="5" class="text-center text-muted">Belum ada jamaah</td></tr>');
        return;
    }

    let html = '';
    rows.forEach((r, i) => {
       let jam_alhid = r.jamaah_alhid;
       console.log('rr',r)
       console.log(jam_alhid)
        html += `
            <tr>
                <td class="text-center">${i + 1}</td>
                <td>${esc(jam_alhid.nama_jamaah ?? '-')}</td>
                <td>${esc(jam_alhid.no_ktp ?? '-')}</td>
                <td class="text-center">${esc(r.jumlah_jamaah)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-warning btn-pindah-jamaah mr-1"
                            data-row-id="${r.id}"
                            data-nama="${esc(jam_alhid.nama_jamaah ?? '-')}"
                            data-jumlah="${esc(r.jumlah_jamaah)}"
                            title="Pindah paket" style="margin:10px;">
                        <i class="ti ti-arrows-exchange"></i>
                    </button>
                    <button type="button" class="btn btn-xs btn-danger btn-del-jamaah" data-row-id="${r.id}" title="Hapus">
                        <i class="ti ti-trash"></i>
                    </button>
                </td>
            </tr>`;
    });
    $('#aj_list').html(html);
}

// ── Simpan booking jamaah ──
$('#formAddJamaah').on('submit', async function (e) {
    e.preventDefault();

    const payload = {
        data_jamaah_id: $('#aj_jamaah').val(),
        jumlah_jamaah : parseInt($('#aj_jumlah').val(), 10),
    };

    if (!payload.data_jamaah_id) {
        Swal.fire('Peringatan', 'Pilih jamaah terlebih dahulu.', 'warning');
        return;
    }

    const $btn = $('#btnSaveAddJamaah').prop('disabled', true);

    try {
        const url = jamaahRoutes.store.replace('__ID__', currentPaketId);
        const res = await ajaxRequest(url, 'POST', payload, token());

        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.data.message, timer: 1200, showConfirmButton: false });

        $('#aj_jamaah').val(null).trigger('change');
        $('#aj_jumlah').val('');
        await loadPaketJamaah();
    } catch (err) {
        cathError(err);
    } finally {
        $btn.prop('disabled', false);
    }
});

// ── Hapus booking jamaah ──
$(document).on('click', '.btn-del-jamaah', function () {
    const rowId = $(this).data('row-id');

    Swal.fire({
        title: 'Hapus jamaah dari paket ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then(async function (result) {
        if (!result.isConfirmed) return;

        try {
            const url = jamaahRoutes.destroy
                .replace('__ID__', currentPaketId)
                .replace('__ROW__', rowId);
            const res = await ajaxRequest(url, 'DELETE', null, token());

            Swal.fire({ icon: 'success', title: 'Dihapus', text: res.data.message, timer: 1000, showConfirmButton: false });
            await loadPaketJamaah();
        } catch (err) {
            cathError(err);
        }
    });
});

// ── Master jamaah ──
$('#btnAddMasterJamaah').on('click', function () {
    $('#formMasterJamaah')[0].reset();
    $('#modalMasterJamaah').modal('show');
});

// Bootstrap 4 + select2: cegah fokus modal merebut input di dalam select2
$('#modalMasterJamaah').on('shown.bs.modal', function () {
    $(document).off('focusin.modal');
});

$('#formMasterJamaah').on('submit', async function (e) {
    e.preventDefault();

    const payload = {
        full_name     : $('#mj_full_name').val().trim(),
        nik           : $('#mj_nik').val().trim(),
        phone_number  : $('#mj_phone').val().trim() || null,
        detail_address: $('#mj_address').val().trim() || null,
    };

    const $btn = $('#btnSaveMasterJamaah').prop('disabled', true);

    try {
        const res = await ajaxRequest(jamaahRoutes.saveMasterAlhid, 'POST', payload, token());
        const j = res.data.data;

        // Langsung pilih jamaah baru di select2
        // const opt = new Option(`${j.full_name} (${j.nik})`, j.id, true, true);//xero
        const opt = new Option(`${j.nama_jamaah} (${j.no_ktp})`, j.id, true, true);//alhid
        $('#aj_jamaah').append(opt).trigger('change');

        $('#modalMasterJamaah').modal('hide');
        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.data.message, timer: 1000, showConfirmButton: false });
    } catch (err) {
        cathError(err);
    } finally {
        $btn.prop('disabled', false);
    }
});
// ── Init ──

// ── Pindah paket jamaah ──
let pindahRowId = null;

$(document).on('click', '.btn-pindah-jamaah', function () {
    pindahRowId = $(this).data('row-id');
    const jumlah = parseInt($(this).data('jumlah'), 10) || 1;

    $('#formPindah')[0].reset();
    $('#pp_nama').text($(this).data('nama'));
    $('#pp_jumlah_asal').text(jumlah);
    $('#pp_jumlah').attr('max', jumlah).val(jumlah);

    initCariPaketSelect2();
    $('#modalPindah').modal('show');
});

// Modal bertumpuk: backdrop kedua harus di atas modal Add Jamaah
$('#modalPindah').on('shown.bs.modal', function () {
    $('.modal-backdrop').last().css('z-index', 1055);
});

$('#modalPindah').on('hidden.bs.modal', function () {
    const $el = $('#pp_paket');
    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }
    // Bootstrap 4 melepas modal-open saat modal kedua tutup, padahal Add Jamaah masih terbuka
    if ($('#modalAddJamaah').hasClass('show')) {
        $('body').addClass('modal-open');
    }
});

$('#formPindah').on('submit', async function (e) {
    e.preventDefault();

    const targetId = $('#pp_paket').val();
    if (!targetId) {
        Swal.fire('Peringatan', 'Pilih paket tujuan terlebih dahulu.', 'warning');
        return;
    }

    const payload = {
        target_paket_id: targetId,
        jumlah_jamaah  : parseInt($('#pp_jumlah').val(), 10),
    };

    const $btn = $('#btnSavePindah').prop('disabled', true);

    try {
        const url = jamaahRoutes.pindah
            .replace('__ID__', currentPaketId)
            .replace('__ROW__', pindahRowId);
        const res = await ajaxRequest(url, 'PUT', payload, token());

        $('#modalPindah').modal('hide');
        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.data.message, timer: 1200, showConfirmButton: false });

        await loadPaketJamaah();   // refresh daftar + sisa slot paket asal
    } catch (err) {
        cathError(err);
    } finally {
        $btn.prop('disabled', false);
    }
});
$(async function () {
    await loadOptions();
    loadPaket(1);
});
</script>
@endpush