# TUGAS: Perbaikan Keamanan Laravel 8 (PHP 7.4) berdasarkan report_security.txt

## 1. PERAN
Kamu Security Engineer + Senior Laravel Developer (10+ tahun). Tugasmu memperbaiki temuan
S-01 s/d S-09 dari audit sebelumnya dengan perubahan **minimal, aman, dan tidak merusak klien yang ada**.
Tulis semua komentar kode singkat dan laporan dalam Bahasa Indonesia.

## 2. KONTEKS TETAP (jangan ditanyakan ulang)
- Runtime: shared hosting, **PHP 7.4** (hosting belum patch PHP2Shell; itu urusan hosting, bukan kode).
  Maka kode WAJIB kompatibel PHP 7.4: dilarang `match`, `readonly`, constructor promotion, union types,
  `?->`, named arguments, enum, `str_contains/str_starts_with/str_ends_with`, `mixed`, atribut `#[...]`.
- Framework: Laravel 8.83.x + Sanctum. Jangan upgrade/downgrade paket. Jangan ubah `composer.json` / `composer.lock`.
- Keputusan pemilik sistem:
  1. Semua route Xero masuk `auth:sanctum`, **kecuali webhook Xero**.
  2. `/api/extract-text` (OCR) wajib login.
  3. `register` ditutup total (akun dibuat admin).
  4. Token login berlaku **12 jam** (720 menit).
  5. Upload BARU disimpan privat + diunduh lewat route berotorisasi. File LAMA di `public/uploads` jangan dipindah/dihapus.
  6. Key JSON response upload tidak boleh berubah (hanya nilai URL boleh berubah).
  7. Domain frontend untuk CORS belum diberikan, jadi CORS dibuat berbasis env (lihat S-09).

## 3. ATURAN KERAS
1. **Akses tulis HANYA di** `/working/annamiro/laravel8-xero`. Dilarang membaca/menulis di luar path itu.
2. **Dilarang menyentuh**: `.env`, `.env.*` (kecuali menambah baris contoh di `.env.example`), `vendor/`, `node_modules/`,
   `storage/logs`, `storage/framework`, `bootstrap/cache`, `composer.json`, `composer.lock`, file di `public/uploads` yang sudah ada,
   migrasi yang sudah ada.
3. **Dilarang menyentuh database**. Tidak boleh: `migrate*`, `db:*`, `tinker`, `seed`, `queue:*`, `schedule:*`,
   `sanctum:prune-expired`, `key:generate`, `storage:link`, `vendor:publish`, `config:cache`, `route:cache`, `optimize`.
   Jangan membuat migrasi baru. Jika perbaikan butuh perubahan skema, catat di laporan sebagai tindakan manual.
4. **Perintah yang boleh**: `php -l <file>`, `php artisan route:list` (jika gagal karena butuh DB/env, jangan diulang; cukup `php -l`).
   `php artisan test`/phpunit: **jangan dijalankan** kecuali kamu sudah membuktikan lewat grep bahwa tes tersebut sama sekali
   tidak memakai `RefreshDatabase`, `DatabaseTransactions`, factory, atau Eloquent. Jangan membuat tes yang menyentuh DB.
5. Jangan jalankan exploit/PoC dan jangan akses jaringan.
6. **Rahasia**: jangan cetak nilai kunci/token/password ke laporan atau terminal; samarkan.
7. **Perubahan minimal**: edit hanya baris yang perlu. Tanpa reformat, tanpa rename, tanpa merapikan kode lain.
   Pertahankan bentuk JSON response, status code, dan nama field, kecuali yang disebut eksplisit di bawah.
8. Jika ragu atau butuh keputusan bisnis yang tidak ada di dokumen ini: **jangan menebak**. Lewati, tandai `DITUNDA`
   di laporan beserta alasannya.
9. Setiap selesai mengedit file PHP: jalankan `php -l` pada file itu. Jika error, perbaiki segera sebelum lanjut.

## 4. EFISIENSI TOKEN
- Cari dengan grep terarah, baca hanya rentang baris yang relevan, jangan baca ulang file yang sama.
- Nomor baris dari laporan audit bisa bergeser: cari ulang polanya, jangan percaya buta.
- Abaikan `vendor/`, `node_modules/`, `storage/`, aset statis, `*.min.*`.
- Satu pola yang sama di banyak tempat = satu pass dengan daftar lokasi, bukan analisis berulang.
- Jangan menempelkan kode panjang di terminal. Ringkas.

## 5. INPUT
Baca `security_fix/report_security.txt` di root project (disalin manusia ke sana) untuk lokasi temuan.
Jika file itu tidak ada, temukan lokasi sendiri dengan pola pada tiap fase. Jangan berhenti hanya karena file itu tidak ada.

## 6. FASE PERBAIKAN (kerjakan berurutan; satu fase selesai → catat → lanjut)

### Fase 1 — S-02 + S-07: route tanpa auth & register
Berkas: `routes/api.php`, `app/Http/Controllers/AuthController.php`.
1. Petakan semua route di grup tanpa `auth:sanctum` (rujukan laporan: sekitar baris 106, 114-118, 127-227, 571-595).
2. Pindahkan route Xero (invoice, payment, bank, sinkronisasi, create/update/delete) ke grup `auth:sanctum`.
   Pertahankan middleware lain yang sudah ada pada route tersebut (mis. `xss`, `role.menu`); ikuti pola grup bersaudara yang sudah memakai `auth:sanctum`.
3. **Tetap publik (jangan dipindah)**, tetapi catat di laporan:
   - webhook Xero (handler `handleXero`),
   - `login`,
   - callback/redirect OAuth Xero bila ada (browser dialihkan oleh Xero, tidak membawa Bearer token; lindungi dengan validasi `state`, jangan dengan Sanctum).
4. `/api/extract-text` → masuk `auth:sanctum` + `throttle:30,1`.
5. Route destruktif/mutasi yang masih ragu apakah dipakai sistem eksternal: **jangan dibiarkan terbuka diam-diam**. Pindahkan ke `auth:sanctum` dan catat sebagai "berpotensi memutus klien eksternal" di laporan.
6. **Webhook**: pastikan handler yang AKTIF di route memverifikasi signature. Laporan menemukan validasi hanya ada di `handleXeross`
   (WebhookController ±83-105) sementara route memakai `handleXero`.
   - Verifikasi: HMAC-SHA256 dari body mentah (`$request->getContent()`) dengan kunci webhook, dibandingkan ke header `x-xero-signature`
     memakai `hash_equals`, base64.
   - Signature tidak valid/kosong → balas **401** tanpa memproses payload.
   - Pakai nama config/env kunci yang sudah dipakai `handleXeross`; jika belum ada, tambah `XERO_WEBHOOK_KEY` di `config/services.php` (via `env()`) dan contoh baris kosong di `.env.example`.
7. **Register**: hapus route `/register`. Hapus kode penerbitan token di method `register` (atau hapus method-nya jika tidak direferensikan di tempat lain; cek dengan grep).
   Pembuatan akun tetap lewat endpoint admin yang sudah ada di grup `auth:sanctum` (UserController).
8. Tambahkan `throttle:10,1` pada route `login`.
9. Jalankan `php artisan route:list` bila bisa, lalu bandingkan: tidak boleh ada route Xero mutasi tanpa auth selain webhook/OAuth callback.

### Fase 2 — S-04: token Sanctum
Berkas: `app/Http/Controllers/AuthController.php`, `config/sanctum.php`.
1. Login membuat satu token saja (hapus token ganda; laporan: ±baris 35-43, 102-109). Token yang dikembalikan harus token ber-expiry **12 jam**.
   Cek versi Sanctum di `composer.lock`. Gunakan cara expiry yang sama dengan yang sudah ada di kode (token 5 jam di baris awal) atau
   `config/sanctum.php` → `'expiration' => (int) env('SANCTUM_EXPIRATION', 720)`. Bila keduanya didukung, pakai keduanya.
2. Jangan ubah bentuk JSON response login (nama field `token`/dll tetap). Jika ada field yang menampilkan expiry, sesuaikan nilainya ke 12 jam.
3. Logout: hapus **token aktif saja** (`$request->user()->currentAccessToken()->delete()`), bukan semua token.
4. Ganti/reset password (jika ada di kode): hapus semua token user tersebut.
5. Tambahkan `SANCTUM_EXPIRATION=720` sebagai contoh di `.env.example`.
6. Catat sebagai tindakan manual: token lama tanpa expiry masih ada di DB dan tetap valid. Admin harus mencabutnya
   (setelah backup DB), mis. menghapus baris `personal_access_tokens` yang `expires_at IS NULL`. **Jangan jalankan sendiri.**

### Fase 3 — S-03: upload
Berkas: `ImageController.php`, `BillXeroController.php` (±236-303), `InvXeroController.php` (±1618-1684), route upload di `routes/api.php`.
1. Upload baru: simpan di disk privat Laravel (`Storage::disk('local')`, di luar webroot), di folder terstruktur, nama file acak `Str::uuid()` + ekstensi dari allowlist.
   Ekstensi/MIME ditentukan **server-side** (`$file->getMimeType()` / `guessExtension()`), bukan dari nama file klien.
   Allowlist: `pdf, jpg, jpeg, png`. Perketat validator dengan `mimes:` + `max:` yang sudah ada.
2. Tambah route unduhan di grup `auth:sanctum` yang menyajikan file dengan `response()->file()`/`Storage::download()`.
   Cegah path traversal (hanya identifier/uuid, tidak menerima path mentah).
3. Key JSON response upload tidak berubah; nilai URL menunjuk ke route unduhan baru.
4. **Kepemilikan**: upload bill menerima `bill_id`/file ID tanpa bukti kepemilikan. Telusuri relasi model (mis. `user_id`, travel/tenant/company).
   Jika ada kolom/relasi kepemilikan yang jelas → terapkan pengecekan (404/403 bila bukan miliknya).
   Jika model tidak punya konsep kepemilikan → jangan mengarang; catat `DITUNDA` + usulan.
5. File lama di `public/uploads`: **jangan dipindah**. Buat/ubah `public/uploads/.htaccess` agar menolak eksekusi skrip. Isi (kompatibel Apache 2.2/2.4, tanpa `php_flag`):
   ```
   <FilesMatch "\.(php|phtml|phar|pht|phps|php[0-9]|cgi|pl|py|sh)$">
     <IfModule mod_authz_core.c>
       Require all denied
     </IfModule>
     <IfModule !mod_authz_core.c>
       Order deny,allow
       Deny from all
     </IfModule>
   </FilesMatch>
   ```
   Jika folder `public/uploads` tidak ada di repo, buat folder + file itu. Catat bahwa ini hanya bekerja bila hosting mengizinkan `.htaccess`.
6. Catat sebagai tindakan manual: audit isi `public/uploads` lama dari sisi server untuk file `.php`/mencurigakan.

### Fase 4 — S-06: SQL raw
Berkas: `app/Http/Repository/BaseRepository.php` (±139-142), `app/Http/Repository/MasterData/RoleUserRepository.php` (±43-47).
1. Ganti interpolasi dengan binding: `whereRaw('LOWER(' . $col . ') LIKE ?', ['%' . mb_strtolower($keyword) . '%'])`.
2. `$col` adalah identifier, tidak bisa di-bind: validasi dengan allowlist. Telusuri pemanggil untuk melihat asal nilainya.
   - Jika berasal dari request → batasi ke allowlist kolom yang sah (mis. dari `$fillable` model + `id`), atau minimal regex `^[A-Za-z0-9_.]+$`, lalu kutip identifier.
   - Jika berasal dari kode internal konstan → cukup regex defensif.
3. Jalankan grep penuh `whereRaw|selectRaw|orderByRaw|groupByRaw|havingRaw|DB::raw|DB::select|DB::statement|DB::unprepared` di `app/` dan `routes/`.
   Perbaiki yang menyisipkan variabel (dengan pola yang sama). Yang sudah memakai binding biarkan.

### Fase 5 — S-08: mass assignment
Berkas: `UserController.php` (±131-150), `CoaController.php` (±43-69), `TravelController.php` (±38-75), `BaseRepository::CreateOrUpdate`.
1. Ganti `$request->all()` pada pemanggilan persistensi dengan `$validator->validated()` / `$request->only([...])`.
2. **Awas merusak fitur**: validator sekarang mungkin hanya mencakup sebagian field. Sebelum membatasi, tentukan field sah dari `$fillable` model +
   field yang dipakai form/endpoint terkait, tambahkan ke aturan validasi (`sometimes|nullable|<tipe>`), baru batasi.
3. Untuk model `User`: `role`/`is_admin`/level akses tidak boleh bisa diubah dari payload biasa. Jika penetapan role memang bagian endpoint admin,
   pastikan hanya lewat jalur yang sudah ber-`role.menu`/otorisasi; bila tidak jelas → `DITUNDA`.
4. Periksa `$guarded = []` pada model; ganti dengan `$fillable` hanya bila daftar fieldnya jelas dari kode. Jika tidak jelas → catat, jangan ubah.

### Fase 6 — S-05: middleware XssSanitization
Berkas: `app/Http/Middleware/XssSanitization.php` (±24-41), `app/Http/Kernel.php`.
Keputusan realistis: **jangan dihapus** (klien/data lama mungkin bergantung) dan **jangan dipasang ke lebih banyak route** (bisa merusak data). Perbaiki saja:
1. Null-safe: jangan mengakses `$user->id`/relasi bila user null; jika tidak ada user, lewati logika menu/user tanpa error, lanjutkan `$next($request)`.
2. Hanya proses nilai bertipe `string`; biarkan int/float/bool/null/array-bukan-string apa adanya (tidak mengubah tipe).
3. Jangan sanitasi field kredensial: `password`, `password_confirmation`, `current_password`, `token`, `signature`.
4. Jangan menyentuh file upload.
5. Tambahkan komentar singkat: middleware ini lapisan tambahan, BUKAN pengganti validasi dan escaping output.
6. Grep view Blade (`resources/views`) untuk `{!!` yang memuat data pengguna; ganti ke `{{ }}` bila jelas aman secara fungsional. Jika bukan Blade/tidak ada → lewati.

### Fase 7 — S-09: CORS + security headers
1. `config/cors.php`: `allowed_origins` dibaca dari env:
   `array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*')))))`.
   Default `*` agar **perilaku sekarang tidak berubah** sampai pemilik mengisi env. `allowed_methods` → `['GET','POST','PUT','PATCH','DELETE','OPTIONS']`.
   `allowed_headers` biarkan apa adanya (klien mungkin memakai header khusus). `supports_credentials` tetap `false`.
   Tambahkan contoh `CORS_ALLOWED_ORIGINS=` dengan komentar di `.env.example`.
2. Buat middleware `SecurityHeaders` (dan daftarkan sebagai global di `Kernel.php`) yang menambahkan:
   `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`,
   `Permissions-Policy: camera=(), microphone=(), geolocation=()`. HSTS hanya bila `$request->secure()` dan `app()->environment('production')`.
   **Jangan menambah CSP** (bisa merusak UI/Blade).
   Jangan menimpa header yang sudah diset oleh respons (mis. unduhan file).

### Fase 8 — S-01: PHP2Shell (hanya sisi aplikasi)
Tidak ada perubahan kode yang menutup kerentanan runtime. **Jangan** mengklaim sudah diperbaiki. Lakukan hanya:
1. Pastikan semua endpoint upload sudah berada di balik `auth:sanctum` (Fase 1 & 3) dan punya `max:` ukuran wajar.
2. Di laporan, tulis tindakan manual untuk pemilik: minta penyedia hosting memastikan binary PHP web sudah ber-patch advisory resmi PHP2Shell
   (backport PHP 7.4 atau pindah versi), restart PHP-FPM/Apache; pasang WAF/batas multipart bila hosting menyediakan; lakukan audit upload lama.
   Jangan memodifikasi `RLIMIT_*`, php.ini, atau `.user.ini`.

## 7. LAPORAN: tulis ke `security_fix/report_fix.txt` (buat foldernya di root project)
Teks polos. Struktur:
```
LAPORAN PERBAIKAN — laravel8-xero | Tanggal: <hari ini>

1. RINGKASAN: jumlah temuan SELESAI / SEBAGIAN / DITUNDA, dan daftar file yang diubah (path saja)
2. PER TEMUAN (S-01..S-09):
   Status | File:baris yang diubah | Perubahan (1-3 kalimat) | Dampak perilaku/risiko memutus klien | Hasil php -l / route:list
3. DITUNDA & ALASAN: keputusan yang butuh pemilik sistem
4. TINDAKAN MANUAL SETELAH DEPLOY (urut):
   - Backup file + DB sebelum deploy
   - Isi .env server: CORS_ALLOWED_ORIGINS, SANCTUM_EXPIRATION=720, kunci webhook Xero (bila baru)
   - Cabut token lama tanpa expiry (setelah backup DB)
   - Bersihkan cache di server (config:clear, route:clear, cache:clear) bila aplikasi memakai cache
   - Uji: login, logout, unduh file upload baru, webhook Xero (intent to receive harus 200 saat valid, 401 saat signature salah), alur OAuth Xero
   - Audit public/uploads lama
   - Minta hosting patch PHP2Shell
5. YANG TIDAK DIUBAH & MASIH BERISIKO (singkat)
```
Jangan menempelkan ulang kode di laporan; cukup `path:baris` dan deskripsi singkat.
`security_fix/` bukan bagian dari `public/`; jangan taruh di dalam `public/`.

## 8. SELESAI
Balas di terminal maksimal 10 baris: jumlah SELESAI/SEBAGIAN/DITUNDA, path laporan, dan 3 tindakan manual terpenting.