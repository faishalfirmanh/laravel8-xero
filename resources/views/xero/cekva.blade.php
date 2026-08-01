<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0F5C4C">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Cek Virtual Account — An-Namiroh Travelindo</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap');

  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }

  :root {
    --color-bg: #F7F2E7;
    --color-ink: #1F2A24;
    --color-primary: #0F5C4C;
    --color-primary-dark: #0B4438;
    --color-gold: #C9A227;
    --color-gold-light: #E4C766;
    --color-success: #2F9E63;
    --color-pending: #B4690E;
    --color-line: rgba(31, 42, 36, 0.14);
    --color-card: #FFFFFF;
    --radius-lg: 20px;
    --radius-md: 12px;
    --shadow-card: 0 20px 50px -20px rgba(15, 92, 76, 0.35), 0 2px 8px rgba(31, 42, 36, 0.08);
    --font-display: 'Fraunces', Georgia, serif;
    --font-body: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
  }

  body {
    min-height: 100vh;
    min-height: 100dvh;
    font-family: var(--font-body);
    color: var(--color-ink);
    background:
      radial-gradient(circle at 15% 15%, rgba(201, 162, 39, 0.12), transparent 40%),
      radial-gradient(circle at 85% 85%, rgba(15, 92, 76, 0.12), transparent 45%),
      var(--color-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    padding-top: max(24px, env(safe-area-inset-top));
    padding-bottom: max(24px, env(safe-area-inset-bottom));
    -webkit-font-smoothing: antialiased;
  }

  .page { width: 100%; max-width: 440px; }

  .card {
    background: var(--color-card);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    padding: 40px 32px;
    position: relative;
    overflow: hidden;
  }
  @media (max-width: 480px) {
    .card { padding: 30px 22px; border-radius: 18px; }
  }

  .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 26px; }
  .brand__mark {
    flex: none;
    width: 40px; height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--color-gold), var(--color-gold-light));
    color: var(--color-primary-dark);
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 15px;
    display: flex; align-items: center; justify-content: center;
    letter-spacing: 0.02em;
  }
  .brand__eyebrow {
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--color-primary);
  }

  .title {
    font-family: var(--font-display);
    font-size: clamp(23px, 5.5vw, 29px);
    font-weight: 600;
    margin: 0 0 8px;
    line-height: 1.15;
  }
  .subtitle {
    font-size: 14.5px;
    line-height: 1.55;
    color: rgba(31, 42, 36, 0.68);
    margin: 0 0 26px;
  }

  .field-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(31, 42, 36, 0.55);
    margin-bottom: 8px;
  }
  .field-input {
    width: 100%;
    font-family: var(--font-mono);
    font-size: 16px;
    padding: 14px 16px;
    border-radius: var(--radius-md);
    border: 1.5px solid var(--color-line);
    background: #FCFAF4;
    color: var(--color-ink);
    transition: border-color .15s ease, box-shadow .15s ease;
    margin-bottom: 16px;
  }
  .field-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 4px rgba(15, 92, 76, 0.14);
  }
  .field-input::placeholder { color: rgba(31, 42, 36, 0.35); }

  .btn-submit {
    width: 100%;
    border: none;
    border-radius: var(--radius-md);
    padding: 15px 18px;
    font-family: var(--font-body);
    font-size: 15.5px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #fff;
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    min-height: 50px;
    transition: transform .12s ease, opacity .12s ease;
    box-shadow: 0 10px 24px -12px rgba(15, 92, 76, 0.55);
  }
  .btn-submit:active { transform: scale(0.98); }
  .btn-submit:focus-visible { outline: 3px solid rgba(15, 92, 76, 0.35); outline-offset: 2px; }
  .btn-submit:disabled { opacity: 0.75; cursor: progress; }

  .spinner {
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: #fff;
    display: none;
    animation: spin .7s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  .alert {
    display: none;
    align-items: flex-start;
    gap: 10px;
    margin-top: 18px;
    padding: 13px 14px;
    border-radius: var(--radius-md);
    background: rgba(180, 105, 14, 0.08);
    border: 1px solid rgba(180, 105, 14, 0.3);
    color: var(--color-pending);
    font-size: 13.5px;
    line-height: 1.5;
  }

  .result {
    display: none;
    margin-top: 8px;
    opacity: 0;
    transform: translateY(6px);
    transition: opacity .35s ease, transform .35s ease;
  }
  .result.is-visible { opacity: 1; transform: none; }

  .perforation {
    position: relative;
    height: 1px;
    margin: 26px -32px 22px;
    border-top: 2px dashed var(--color-line);
  }
  .perforation::before, .perforation::after {
    content: "";
    position: absolute; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--color-bg);
  }
  .perforation::before { left: -11px; }
  .perforation::after { right: -11px; }
  @media (max-width: 480px) { .perforation { margin: 22px -22px 18px; } }

  .stamp-row { display: flex; justify-content: center; margin-bottom: 22px; }
  .stamp {
    width: 126px; height: 126px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    text-align: center;
    font-weight: 800;
    font-size: 14.5px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    line-height: 1.3;
    transform: rotate(-8deg);
    border-style: double;
    border-width: 6px;
    animation: stamp-in .45s cubic-bezier(.2, .8, .3, 1.2);
  }
  .stamp--lunas { color: var(--color-success); border-color: var(--color-success); background: rgba(47, 158, 99, 0.06); }
  .stamp--pending { color: var(--color-pending); border-color: var(--color-pending); background: rgba(180, 105, 14, 0.06); }
  @keyframes stamp-in {
    from { opacity: 0; transform: scale(1.4) rotate(-8deg); }
    to { opacity: 1; transform: scale(1) rotate(-8deg); }
  }

  .detail-list { margin: 0; }
  .detail-row { display: flex; justify-content: space-between; gap: 16px; padding: 11px 0; border-bottom: 1px solid var(--color-line); }
  .detail-row:last-child { border-bottom: none; }
  .detail-row dt { font-size: 12.5px; color: rgba(31, 42, 36, 0.55); font-weight: 700; margin: 0; }
  .detail-row dd { margin: 0; font-size: 13.5px; text-align: right; font-weight: 600; }
  .detail-row--strong dd { color: var(--color-primary); font-size: 15px; }
  .detail-row--muted dt, .detail-row--muted dd { color: rgba(31, 42, 36, 0.45); font-weight: 500; font-size: 12px; }
  .mono { font-family: var(--font-mono); font-variant-numeric: tabular-nums; letter-spacing: 0.01em; }

  .footnote { text-align: center; margin-top: 20px; font-size: 12px; color: rgba(31, 42, 36, 0.4); }

  @media (prefers-reduced-motion: reduce) {
    .stamp { animation: none; }
    .result { transition: none; }
  }
</style>
</head>
<body>
  <main class="page">
    <div class="card">
      <div class="brand">
        <span class="brand__mark" aria-hidden="true">AN</span>
        <span class="brand__eyebrow">An-Namiroh Travelindo</span>
      </div>

      <h1 class="title">Cek Status Pembayaran</h1>
      <p class="subtitle">Masukkan nomor Virtual Account (VA) untuk melihat status pembayaran paket Anda.</p>

      <form id="vaForm" novalidate>
        <label for="va_number" class="field-label">Nomor Virtual Account</label>
        <input
          type="text"
          id="va_number"
          name="va_number"
          class="field-input"
          placeholder="Contoh: 198100049200040"
          inputmode="numeric"
          autocomplete="off"
          autocapitalize="off"
          spellcheck="false"
        >
        <button type="submit" id="btnSubmit" class="btn-submit">
          <span class="btn-text">Cek Pembayaran</span>
          <span class="spinner" aria-hidden="true"></span>
        </button>
      </form>

      <div id="alertBox" class="alert" role="alert" aria-live="assertive"></div>

      <div id="resultBox" class="result" aria-live="polite">
        <div class="perforation" aria-hidden="true"></div>

        <div class="stamp-row">
          <span id="resStatus" class="stamp"></span>
        </div>

        <dl class="detail-list">
          <div class="detail-row"><dt>No. Invoice</dt><dd id="resInv" class="mono"></dd></div>
          <div class="detail-row"><dt>Nomor VA</dt><dd id="resVa" class="mono"></dd></div>
          <div class="detail-row"><dt>Paket</dt><dd id="resPaket"></dd></div>
          <div class="detail-row"><dt>Bank</dt><dd id="resBank"></dd></div>
          <div class="detail-row"><dt>Atas Nama</dt><dd id="resNama"></dd></div>
          <div class="detail-row"><dt>Total Tagihan</dt><dd id="resTotal" class="mono"></dd></div>
          <div class="detail-row"><dt>Sudah Dibayar</dt><dd id="resBayar" class="mono"></dd></div>
          <div class="detail-row detail-row--strong"><dt>Sisa Tagihan</dt><dd id="resSisa" class="mono"></dd></div>
          <div class="detail-row detail-row--muted"><dt>Diperbarui</dt><dd id="resUpdated"></dd></div>
        </dl>
      </div>
    </div>

    <p class="footnote">© {{ date('Y') }} An-Namiroh Travelindo</p>
  </main>
@push('scripts')
  <script>
  (function () {
    var form = document.getElementById('vaForm');
    var input = document.getElementById('va_number');
    var btn = document.getElementById('btnSubmit');
    var btnText = btn.querySelector('.btn-text');
    var spinner = btn.querySelector('.spinner');
    var alertBox = document.getElementById('alertBox');
    var resultBox = document.getElementById('resultBox');
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';

    // Param wajib yang dikirim ke server: va_number (isian user) dan key (statis).
    var STATIC_KEY = 'namiroh123';
    // Sesuaikan URL ini dengan route API pengecekan VA Anda.
    var ENDPOINT_URL = '/get-va-jamaah';

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
      var d = new Date(String(value).replace(' ', 'T'));
      if (isNaN(d.getTime())) return value;
      try {
        return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(d) + ' WIB';
      } catch (e) {
        return value;
      }
    }

    function showAlert(message) {
      alertBox.textContent = message;
      alertBox.style.display = 'flex';
      resultBox.style.display = 'none';
      resultBox.classList.remove('is-visible');
    }

    function hideAlert() {
      alertBox.style.display = 'none';
      alertBox.textContent = '';
    }

    function setLoading(isLoading) {
      btn.disabled = isLoading;
      btn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
      btnText.textContent = isLoading ? 'Memeriksa...' : 'Cek Pembayaran';
      spinner.style.display = isLoading ? 'inline-block' : 'none';
    }

    function renderResult(data) {
      data = data || {};
      var total = Number(data.total_nominal) || 0;
      var bayar = Number(data.payment) || 0;
      var sisa = Math.max(total - bayar, 0);
      var lunas = total > 0 && bayar >= total;

      var statusEl = document.getElementById('resStatus');
      statusEl.textContent = lunas ? 'LUNAS' : 'BELUM LUNAS';
      statusEl.className = 'stamp ' + (lunas ? 'stamp--lunas' : 'stamp--pending');

      document.getElementById('resInv').textContent = data.inv_number || '-';
      document.getElementById('resVa').textContent = data.va_number || '-';
      document.getElementById('resPaket').textContent = (data.paket_name && data.paket_name !== '--') ? data.paket_name : '-';
      document.getElementById('resBank').textContent = data.bank_name || '-';
      document.getElementById('resNama').textContent = data.name_contact || '-';
      document.getElementById('resTotal').textContent = formatRupiah(total);
      document.getElementById('resBayar').textContent = formatRupiah(bayar);
      document.getElementById('resSisa').textContent = formatRupiah(sisa);
      document.getElementById('resUpdated').textContent = formatTanggal(data.updated_at);

      resultBox.style.display = 'block';
      requestAnimationFrame(function () { resultBox.classList.add('is-visible'); });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      hideAlert();

      var vaNumber = input.value.trim();
      if (!vaNumber) {
        showAlert('Nomor VA belum diisi. Masukkan nomor VA yang tertera pada rekening Anda.');
        input.focus();
        return;
      }

      setLoading(true);
      resultBox.style.display = 'none';
      resultBox.classList.remove('is-visible');

      fetch(ENDPOINT_URL, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ va_number: vaNumber, key: STATIC_KEY })
      })
        .then(function (response) {
        console.log('res',response)
          return response.json().catch(function () { return null; }).then(function (json) {
            return { ok: response.ok, json: json };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.json || result.json.status !== 'success') {
            var message = (result.json && result.json.message) ? result.json.message : 'Nomor VA tidak ditemukan. Periksa kembali nomor yang dimasukkan.';
            showAlert(message);
            return;
          }
          renderResult(result.json.data);
        })
        .catch(function () {
          showAlert('Terjadi kesalahan jaringan. Coba lagi beberapa saat lagi.');
        })
        .finally(function () {
          setLoading(false);
        });
    });
  })();
  </script>
  @endpush
</body>
</html>