@extends('layouts.app') {{-- sesuaikan dengan nama file layout admin Anda --}}

@section('content')
<style>
  #vaCheckCard { transition: box-shadow .2s ease; }
  #vaCheckCard:hover { box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08) !important; }
  #vaResult { animation: vaFadeIn .3s ease; }
  @keyframes vaFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: none; }
  }
  #va_number { letter-spacing: .02em; }
  #statusBadge { font-size: .8rem; letter-spacing: .03em; }

  /* Sembunyikan sidebar (navbar hitam di kiri) khusus untuk halaman ini saja */
  #wrapper > *:not(#page-content-wrapper) {
    display: none !important;
  }
  #page-content-wrapper {
    width: 100% !important;
    flex: 1 1 100%;
  }
</style>

<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-5">
    <div class="card shadow-sm border-0" id="vaCheckCard">
      <div class="card-body p-4">

        <div class="d-flex align-items-center mb-2">
          <span class="ti ti-receipt-2 text-success" style="font-size: 1.5rem;"></span>
          <h5 class="mb-0 ml-2">Cek Status Pembayaran VA</h5>
        </div>
        <p class="text-muted small mb-4">Masukkan nomor Virtual Account (VA) untuk melihat status pembayarannya.</p>

        <form id="vaCheckForm" novalidate>
          <div class="form-group">
            <label for="va_number" class="small text-uppercase text-muted font-weight-bold">Nomor Virtual Account</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text bg-white"><span class="ti ti-credit-card"></span></span>
              </div>
              <input
                type="text"
                id="va_number"
                name="va_number"
                class="form-control"
                placeholder="Contoh: 198100049200040"
                inputmode="numeric"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
              >
            </div>
          </div>

          <button type="submit" id="btnCekVa" class="btn btn-success btn-block">
            <span class="btn-label">Cek Pembayaran</span>
            <span class="spinner-border spinner-border-sm ml-2 d-none" id="btnSpinner" role="status" aria-hidden="true"></span>
          </button>
        </form>

        <div id="vaResult" class="mt-4 d-none">
          <hr>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="text-muted small text-uppercase font-weight-bold">Detail Transaksi</span>
            <span id="statusBadge" class="badge badge-pill px-3 py-2"></span>
          </div>
          <table class="table table-sm table-borderless mb-0">
            <tbody>
              <tr><td class="text-muted" style="width: 45%;">No. Invoice</td><td class="text-right" id="resInv"></td></tr>
              <tr><td class="text-muted">Nomor VA</td><td class="text-right" id="resVa"></td></tr>
              <tr><td class="text-muted">Paket</td><td class="text-right" id="resPaket"></td></tr>
              <tr><td class="text-muted">Bank</td><td class="text-right" id="resBank"></td></tr>
              <tr><td class="text-muted">Atas Nama</td><td class="text-right" id="resNama"></td></tr>
              <tr><td class="text-muted">Total Tagihan</td><td class="text-right" id="resTotal"></td></tr>
              <tr><td class="text-muted">Sudah Dibayar</td><td class="text-right" id="resBayar"></td></tr>
              <tr class="font-weight-bold"><td>Sisa Tagihan</td><td class="text-right text-success" id="resSisa"></td></tr>
              <tr><td class="text-muted small">Diperbarui</td><td class="text-right text-muted small" id="resUpdated"></td></tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {

    function formatRupiah(value) {
        var number = Number(value) || 0;
        try {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
        } catch (e) {
            return 'Rp' + number.toLocaleString('id-ID');
        }
    }

    function formatTanggal(value) {
        if (!value) return '-';
        // Normalisasi microseconds ("....000000Z") ke milliseconds agar diterima Date() di semua browser
        var normalized = String(value).replace(/(\.\d{3})\d*(Z)?$/, '$1$2');
        var d = new Date(normalized);
        if (isNaN(d.getTime())) return value;
        try {
            return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(d) + ' WIB';
        } catch (e) {
            return value;
        }
    }

    function renderVaResult(data) {
        data = data || {};
        var total = Number(data.total_nominal) || 0;
        var bayar = Number(data.payment) || 0;
        var sisa = Math.max(total - bayar, 0);
        var lunas = total > 0 && bayar >= total;

        $('#statusBadge')
            .text(lunas ? 'LUNAS' : 'BELUM LUNAS')
            .removeClass('badge-success badge-warning')
            .addClass(lunas ? 'badge-success' : 'badge-warning');

        $('#resInv').text(data.inv_number || '-');
        $('#resVa').text(data.va_number || '-');
        $('#resPaket').text((data.paket_name && data.paket_name !== '--') ? data.paket_name : '-');
        $('#resBank').text(data.bank_name || '-');
        $('#resNama').text(data.name_contact || '-');
        $('#resTotal').text(formatRupiah(total));
        $('#resBayar').text(formatRupiah(bayar));
        $('#resSisa').text(formatRupiah(sisa));
        $('#resUpdated').text(formatTanggal(data.updated_at));

        $('#vaResult').removeClass('d-none');
    }

    function setLoading(isLoading) {
        $('#btnCekVa').prop('disabled', isLoading);
        $('#btnSpinner').toggleClass('d-none', !isLoading);
        $('#btnCekVa .btn-label').text(isLoading ? 'Memeriksa...' : 'Cek Pembayaran');
    }

    $('#vaCheckForm').on('submit', function (e) {
        e.preventDefault();

        var vaNumber = $('#va_number').val().trim();
        if (!vaNumber) {
            Swal.fire('Nomor VA belum diisi', 'Masukkan nomor VA yang tertera pada rekening Anda.', 'warning');
            $('#va_number').focus();
            return;
        }

        $('#vaResult').addClass('d-none');
        setLoading(true);

        var param_send = {
            va_number: vaNumber,
            key: 'namiroh123'
        };

        ajaxRequest(`{{ route('get-va') }}`, 'GET', param_send, null)
            .then(function (response) {
                var body = response.data;
                if (body && body.status) {
                    renderVaResult(body.data);
                } else {
                    Swal.fire('Tidak ditemukan', (body && body.message) || 'Nomor VA tidak ditemukan.', 'error');
                }
            })
            .catch(function (err) {
                var msg = (err && err.error && err.error.message) || (err && err.message) || 'Terjadi kesalahan. Coba lagi beberapa saat lagi.';
                Swal.fire('Gagal', msg, 'error');
            })
            .finally(function () {
                setLoading(false);
            });
    });

});
</script>
@endpush