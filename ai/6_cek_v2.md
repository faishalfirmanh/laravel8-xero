# TUGAS: Audit Ulang Keamanan (Pasca-Perbaikan) Laravel 8 / PHP 7.4 — READ-ONLY

## 1. PERAN
Kamu Penetration Tester & Security Engineer senior (10+ tahun), ahli Laravel, PHP internals, OWASP Top 10,
OWASP API Security Top 10, dan forensik webshell. Kerjakan sebagai white-box re-test.
Dua tujuan: **(A)** memverifikasi perbaikan S-01..S-09 benar, lengkap, dan tidak menimbulkan regresi;
**(B)** mencari potensi serangan lain yang belum tercakup audit pertama. Tulis laporan dalam Bahasa Indonesia.

## 2. KONTEKS TETAP (jangan dipersoalkan ulang)
- Hosting: shared hosting, **PHP 7.4** (patch PHP2Shell adalah wewenang hosting; cukup catat status, jangan diulang panjang).
- Keputusan pemilik sistem (bukan temuan): webhook Xero dan callback OAuth Xero memang publik; `login` publik;
  `register` ditutup total; token login 12 jam (720 menit); upload lama di `public/uploads` tidak dipindah;
  CORS default `*` sampai env `CORS_ALLOWED_ORIGINS` diisi (catat hanya sebagai risiko residual bergantung env).
- Dokumen input di dalam scope (baca dulu, ringkas): `security_fix/report_fix.txt` (daftar file yang diubah + klaim perbaikan)
  dan `security_fix/report_security.txt` bila ada. Jika tidak ada, lanjutkan dengan pola pencarian di bawah.

## 3. ATURAN KERAS
1. **Scope hanya** `/working/annamiro/laravel8-xero`. Dilarang membaca/menjelajah di luar path itu.
2. **READ-ONLY.** Dilarang mengubah, menghapus, memindah, atau membuat file di dalam scope.
3. **Perintah yang boleh** (hanya baca): `grep`/`rg`, `find`, `ls`, `stat`, `head`, `wc`, `php -l <file>`,
   `php artisan route:list` (jika gagal karena butuh DB/env, jangan diulang).
   **Dilarang**: composer, npm, git, migrate/db/tinker/queue/schedule/optimize/cache, menjalankan tes, curl/wget, dan perintah apa pun yang
   menulis ke disk/DB/jaringan.
4. **Satu-satunya file yang boleh ditulis**: `report_security_v2.txt` di direktori kerja saat ini (tempat OpenCode dijalankan, DI LUAR scope).
5. Jangan jalankan exploit/PoC. Bukti cukup statis (`path:baris` + 1 baris kutipan).
6. **Rahasia**: jangan cetak nilai `.env`/kunci/token/password. Tulis lokasi + 4 karakter pertama + `***`.
7. Perintah ditolak/butuh izin → lewati, catat di bagian "Tidak Terverifikasi".

## 4. EFISIENSI TOKEN
- Fase A: baca HANYA file yang tercantum di `report_fix.txt` (rentang baris di sekitar perubahan), jangan seluruh project.
- Fase B: mulai dari grep/find terarah; baca konteks hanya di sekitar hit.
- Abaikan `vendor/` (kecuali `composer.lock`), `node_modules/`, `storage/logs` besar (pakai `grep -c`/`head`), aset statis, `*.min.*`, `tests/`.
- Jangan baca ulang file yang sama; catat hasil tiap fase sebagai ringkasan 1 baris per butir lalu lanjut.
- Gabungkan temuan sejenis. Jangan salin blok kode panjang.

## 5. FASE A — VERIFIKASI PERBAIKAN
Beri status tiap butir: `TERVERIFIKASI` / `SEBAGIAN` / `REGRESI` / `BELUM DIPERBAIKI`, dengan bukti `path:baris`.

**A1. Sintaks PHP 7.4 (penting)** — `php -l` memakai PHP CLI mesin ini yang mungkin >7.4, sehingga sintaks PHP 8 lolos lint.
Maka grep di file yang diubah dan file baru: `\?->`, `\bmatch\s*\(`, `str_contains\(`, `str_starts_with\(`, `str_ends_with\(`,
`__construct\(\s*(public|private|protected)`, tipe union `\w+\|\w+\s+\$`, `: mixed`, `: static`, argumen bernama (`\w+: ` dalam pemanggilan), atribut `#\[`.
Satu hit = `REGRESI` (bisa fatal error di produksi PHP 7.4).

**A2. S-02/S-07 route** — Jalankan `php artisan route:list` (bila bisa) atau baca `routes/*.php`.
- Tidak ada route mutasi (POST/PUT/PATCH/DELETE) tanpa `auth:sanctum` kecuali: webhook Xero, callback OAuth Xero, `login`.
- `register` benar-benar hilang (cek juga `routes/web.php`, `Auth::routes`, dan method controller masih bisa terpanggil lewat route lain).
- Method `register` (jika masih ada) tidak menerbitkan token.
- `extract-text` ber-auth + throttle. `login` ber-throttle.
- **Webhook**: handler yang AKTIF di route memverifikasi signature: HMAC-SHA256 atas body mentah, `hash_equals`, balas 401 bila salah.
  Periksa **fail-closed**: jika kunci webhook kosong/tidak terkonfigurasi, request harus DITOLAK (bukan lolos). Periksa tidak ada pemrosesan payload sebelum verifikasi,
  dan tidak ada handler duplikat (`handleXeross`) yang tak terpakai tetapi bisa diarahkan route lain.
- Callback OAuth: ada validasi `state` (anti CSRF login); tidak membocorkan token di redirect/log.
- Route yang kini ber-`auth:sanctum` tetap memiliki `role.menu`/otorisasi yang sebelumnya ada (tidak ada yang tercopot saat dipindah).

**A3. S-04 token** — satu token per login; expiry 720 menit benar-benar terpasang (kode dan/atau `config/sanctum.php`);
nilai `env('SANCTUM_EXPIRATION', ...)` ber-cast `(int)`; logout hanya token aktif; reset/ganti password mencabut token lain;
bentuk JSON response login tidak berubah tanpa disengaja. Periksa juga: apakah ada tempat lain yang membuat token tanpa expiry (`createToken` di seluruh `app/`).

**A4. S-06 SQL raw** — semua `whereRaw|selectRaw|orderByRaw|groupByRaw|havingRaw|DB::raw|DB::select|DB::statement|DB::unprepared`
di `app/`, `routes/`, `database/` (hanya yang dipanggil runtime): tidak ada interpolasi/konkatenasi variabel tanpa binding/allowlist.
Evaluasi regex identifier: apakah cukup (anchor `^...$`, tidak mengizinkan spasi/kutip/titik koma), apakah identifier dikutip, apakah wildcard `%`/`_` pada keyword di-escape bila perlu.
Periksa juga `orderBy($request->...)`, `orderByDesc`, `->select($request->...)`, `->groupBy($request->...)`, `->pluck`/`->value` dengan nama kolom dari input.

**A5. S-08 mass assignment** — `$request->all()` pada `create/update/fill/CreateOrUpdate/insert/updateOrCreate` di SELURUH `app/`
(bukan hanya 3 controller): daftar yang tersisa. Bandingkan field validator dengan field yang sebelumnya dipakai
(**regresi fungsional**: apakah ada field sah yang sekarang hilang?). Periksa `$guarded = []` / `$fillable` model `User` (role/is_admin/level tidak boleh dapat diisi dari payload biasa)
dan bahwa perubahan role hanya lewat jalur yang berotorisasi.

**A6. S-05 `XssSanitization`** — null-safe; hanya string diproses; tipe lain utuh; field kredensial dikecualikan (`password`, `password_confirmation`, `current_password`, `token`, `signature`);
file upload tidak tersentuh; cara menulis balik ke request benar (`merge`/`replace`, bukan hanya salinan lokal yang dibuang); input JSON ikut diproses;
tidak ada efek samping pada rute webhook (body mentah untuk HMAC **tidak boleh** dimodifikasi sebelum verifikasi; jika middleware `xss` terpasang di rute webhook → `REGRESI`).

**A7. S-09 CORS & headers** —
- `config/cors.php`: parsing env benar. **Cek kasus nilai kosong**: jika `.env` berisi `CORS_ALLOWED_ORIGINS=` (seperti contoh di `.env.example`), `env()` mengembalikan string kosong
  (default `*` TIDAK dipakai) sehingga daftar origin menjadi kosong dan semua request lintas-origin ditolak. Pastikan kode menangani nilai kosong (mis. kembali ke `*` atau ke daftar yang aman) dan contoh di `.env.example` tidak memancing kondisi itu.
- `app/Http/Middleware/SecurityHeaders.php`: terdaftar global; memakai `$response->headers->set(...)` (kompatibel dengan `BinaryFileResponse`/`StreamedResponse`;
  `$response->header()` hanya ada di response Illuminate dan bisa fatal pada unduhan file); tidak menimpa header yang sudah ada; HSTS hanya bila HTTPS + production;
  tidak ada CSP yang merusak UI.
- `.env.example` tidak berisi nilai rahasia sungguhan.

**A8. S-03 upload (status SEBAGIAN)** — verifikasi `public/uploads/.htaccess` (sintaks valid Apache 2.2/2.4, tidak memakai `php_flag`/`php_value` yang bisa menyebabkan 500 di PHP-FPM).
Lalu nilai ulang risiko residual alur upload invoice/bill/OCR: apakah nama file masih dari klien, apakah ekstensi dari MIME server-side, apakah file masih tertulis ke `public/`,
apakah kepemilikan `bill_id`/`invoice_id` divalidasi. Tulis rekomendasi konkret untuk tahap berikutnya (tanpa mengubah apa pun).

**A9. S-01** — ringkas status: yang dilakukan sisi aplikasi, yang bergantung hosting. Maksimal 5 baris.

## 6. FASE B — POTENSI SERANGAN LAIN

**B1. Indikator kompromi / webshell (prioritas tinggi, terkait PHP2Shell)**
- File `.php`/`.phtml`/`.phar`/`.pht` yang tidak semestinya di: `public/` (selain `index.php`), `storage/` (seluruh subfolder), `bootstrap/cache/` (selain `packages.php`, `services.php`, `config.php`, `routes*.php`),
  `public/uploads`, `resources/`, `database/`, root project. File tanpa ekstensi/tersembunyi (`.something.php`, nama ganjil) di `public/` dan `storage/`.
- Pola berbahaya (grep di `app/ routes/ config/ public/ storage/ bootstrap/ resources/ database/`, abaikan `vendor/`):
  `eval\(`, `assert\(`, `create_function`, `base64_decode\(` dekat `eval|assert|include`, `gzinflate|gzuncompress|str_rot13` + eval,
  `preg_replace\(.*/e`, `\$_(GET|POST|REQUEST|COOKIE|FILES)\[[^]]*\]\s*\(`, `\$\$\w+`, `extract\(\s*\$_`, `call_user_func(_array)?\(\s*\$_`,
  `system\(|shell_exec\(|passthru\(|proc_open\(|popen\(|exec\(`, backtick, `file_put_contents\(.*\.php`, `move_uploaded_file`, `chr\(\d+\)\s*\.\s*chr`, string hex/base64 panjang (>200 karakter) di file PHP.
- **Pengaturan yang menanam persistensi**: `auto_prepend_file|auto_append_file` di `.htaccess`, `.user.ini`, `php.ini`; `AddHandler|SetHandler|AddType application/x-httpd-php` di folder upload/storage;
  `.htaccess`/`.user.ini` yang tak dikenali; `public/index.php` dan `bootstrap/app.php` dibandingkan dengan front controller Laravel standar (tidak ada `include/eval/base64` tambahan).
- File berubah baru-baru ini: `find app routes config public bootstrap resources database storage -type f -newermt "2026-10-20" -not -path "storage/logs/*" -not -path "storage/framework/*"`
  lalu kurangi dengan daftar file di `report_fix.txt`. Sisanya = perlu diperiksa (bukan otomatis berbahaya; bisa deploy normal). Laporkan path + `stat` mtime.
- Log aplikasi (`storage/logs/*.log`, `error_log` PHP di folder mana pun dalam scope): hitung dan ambil sampel terbatas (`grep -c`, `head -n 20`) untuk
  `Allowed memory size`, `out of memory`, `Maximum execution time`, `multipart`, `Segmentation`, `unserialize`, `base64_decode`, `eval()'d`, lonjakan error 4xx/5xx beruntun.
  (CVE-2026-109618 dipicu kondisi out-of-memory saat inisialisasi request; lonjakan pola ini di sekitar 20-26 Okt 2026 patut dicatat.) Jangan mencetak isi log yang mengandung rahasia.

**B2. Eksposur file & konfigurasi (khas shared hosting)**
- Apakah docroot kemungkinan = root project (ada `index.php`/`.htaccess` di root yang mengarahkan ke `public/`)? Jika ya, pastikan `.htaccess` menolak akses ke dotfile (`.env`, `.git`), `composer.json/lock`, `artisan`, `storage/`, `bootstrap/`, `vendor/`.
- File sensitif yang bisa terunduh/terkomit: `.env`, `.env.*` berisi nilai nyata, `*.sql|*.sql.gz|*.bak|*.old|*.zip|*.tar*|*.log` di `public/` atau root, `.git/`, `phpinfo`, `info.php`, `test*.php`, `adminer*.php`, `phpmyadmin`.
- `APP_DEBUG`/`APP_ENV` default di `config/app.php` dan `.env.example`; `facade/ignition` atau `spatie/laravel-ignition` di `composer.lock`
  (jika `facade/ignition` < 2.5.2 **dan** debug aktif → RCE CVE-2021-3129; laporkan sebagai `dugaan bergantung env` bila ragu); paket dev (debugbar, telescope, tinker) ter-require di produksi (`require` vs `require-dev`).
- Kebocoran di log/response: `Log::` yang mencetak `$request->all()`, password, token, header Authorization, payload Xero; `dd(`, `dump(`, `var_dump(`, `print_r(`, `phpinfo(` sisa; response error yang membuka stack trace/SQL;
  `app/Exceptions/Handler.php` apa yang dikembalikan untuk 500 saat debug off.

**B3. Integrasi Xero**
- Penyimpanan access/refresh token Xero dan client secret: plaintext di DB/config/kode? Apakah ada cast `encrypted`/enkripsi? Apakah ada di log/response?
- Alur OAuth: `state` dibuat acak dan divalidasi, `redirect_uri` tetap (bukan dari input), scope minimum.
- Webhook: replay (tidak ada idempotensi/pengecekan timestamp), pemrosesan sinkron berat (DoS), dan akses data tanpa validasi tenant.
- Pemanggilan HTTP keluar memakai URL/ID dari input (SSRF), timeout, verifikasi TLS tidak dimatikan (`verify => false`, `CURLOPT_SSL_VERIFYPEER`).
- Endpoint sinkronisasi/administratif Xero yang masih terjangkau role terlalu rendah.

**B4. Otorisasi (BOLA/BFLA) & logika bisnis keuangan**
- Cari akses objek via ID/UUID dari request (`find($request->`, `where('id', $request->`, `findOrFail($id)`, `uuid`) pada invoice, bill, payment, user, paket, jamaah, COA:
  adakah filter kepemilikan/tenant (user/travel/company)? Adakah Policy/Gate/`authorize()`? Daftar 10 endpoint paling berisiko (finansial/penghapusan/perubahan role) beserta ada/tidaknya pengecekan.
- Cara kerja `role.menu`: bagaimana menu/role ditentukan, bisa di-bypass lewat parameter/header/nama route? Apakah default-nya deny atau allow bila menu tidak terdaftar?
- Logika uang: jumlah negatif/nol/overflow/presisi float pada payment/invoice; status transisi tak valid (bayar invoice void, hapus invoice yang sudah dibayar); double-submit/race (tidak ada `DB::transaction`, `lockForUpdate`, idempotency key);
  endpoint destruktif tanpa soft-delete/audit log; tidak ada audit trail siapa-mengubah-apa.

**B5. Input, file, dan pustaka**
- Pembuatan PDF/laporan (dompdf/snappy/mpdf): opsi remote resource aktif (`isRemoteEnabled`, `enable_remote`) dengan HTML yang memuat data pengguna → SSRF/LFI; HTML tidak di-escape.
- Import/export Excel/CSV (maatwebsite/phpspreadsheet): **formula injection** (sel diawali `= + - @`), upload import tanpa batas ukuran/baris, XXE.
- Pemrosesan gambar (Intervention/GD/Imagick): path/URL dari input, dimensi gambar tanpa batas (DoS memori).
- Unduhan/baca file: `Storage::download|get|path`, `response()->file|download`, `file_get_contents|readfile|fopen|include|require` dengan unsur input (path traversal, `..`, wrapper `php://`/`phar://`/`file://`).
- `unserialize|Crypt::decrypt` dari input/cookie; `APP_KEY` di repo.
- Paginasi/query tanpa batas (`->get()` besar, `per_page` dari request tanpa maksimum), pencarian `LIKE` berat → DoS. Endpoint publik tanpa throttle.
- Session/web: `config/session.php` (`secure`, `http_only`, `same_site`), `VerifyCsrfToken::$except`, `TrustProxies` (`*`), reset password (token, throttle, enumerasi), kebijakan password (panjang minimum) pada validasi pembuatan user.
- Console/Job: command Artisan atau job yang menjalankan shell/URL dari data pengguna; `schedule` yang memproses input tak tepercaya.
- Dependensi: dari `composer.lock` sebut hanya paket yang kamu YAKIN usang/bermasalah (guzzle, league/flysystem, dompdf, symfony/*, laravel/framework 8.x EOL, facade/ignition). Selebihnya masuk "perlu `composer audit`".

## 7. FORMAT OUTPUT — tulis ke `report_security_v2.txt`
Teks polos, rapi.
```
LAPORAN AUDIT ULANG — laravel8-xero | Tanggal: <hari ini> | Mode: read-only | Scope: /working/annamiro/laravel8-xero

1. RINGKASAN EKSEKUTIF
   Risiko keseluruhan (Kritis/Tinggi/Sedang/Rendah) dibanding audit pertama (naik/turun/tetap) + 5 baris alasan
   Hitungan: fix TERVERIFIKASI / SEBAGIAN / REGRESI / BELUM; temuan baru per severity
   3 tindakan paling dulu

2. VERIFIKASI PERBAIKAN (A1..A9)
   Per butir: status | bukti path:baris | catatan 1-2 kalimat | perbaikan lanjutan bila perlu (maks. 6 baris kode)

3. REGRESI & RISIKO FUNGSIONAL
   Hal yang kemungkinan merusak klien/fitur setelah perbaikan + cara uji manual singkat

4. INDIKATOR KOMPROMI (B1)
   Temuan (path + alasan + tingkat keyakinan: kuat/lemah/tidak ada). Jika tidak ada: tulis eksplisit "tidak ditemukan pada pencarian statis" beserta batasannya.

5. TEMUAN BARU (N-01...) urut severity
   [ID] Judul — Severity (CVSS perkiraan) — CWE/OWASP — Status: TERKONFIRMASI / DUGAAN
   Lokasi | Bukti (1 baris) | Dampak | Perbaikan (langkah + kode maks. 8 baris) | Usaha

6. ROADMAP
   P0 (hari ini-3 hari) | P1 (minggu ini) | P2 (bulan ini) | P3 (jangka panjang), tiap butir merujuk ID

7. YANG SUDAH BAIK (singkat)

8. TIDAK TERVERIFIKASI & LANGKAH MANUAL OLEH ADMIN
   Hal di luar scope: versi/patch PHP & SAPI di hosting (tanya hosting), log akses web server (POST multipart tidak wajar sejak 20 Okt 2026),
   `composer audit`, isi `public/uploads` lama, pencabutan token tanpa expiry di DB, rotasi `APP_KEY`/password DB/kredensial & token Xero bila ada indikasi kompromi,
   checklist uji manual: login, logout, token kedaluwarsa, unduh/unggah file, webhook valid=200 & signature salah=401, alur OAuth Xero, CORS dari frontend resmi.
```
Aturan: setiap klaim wajib punya `path:baris`; tanpa bukti → bagian 8, jangan dikarang. Bedakan terkonfirmasi vs dugaan.
Jangan mengulang isi prompt. Jangan mengusulkan perubahan yang membutuhkan pindah hosting kecuali relevan.

## 8. SELESAI
Balas di terminal maksimal 10 baris: path laporan, hitungan status fix, jumlah temuan baru per severity, ada/tidaknya indikator kompromi, dan 3 tindakan prioritas.
Jangan menempelkan ulang isi laporan.