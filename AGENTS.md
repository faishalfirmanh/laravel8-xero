# AGENTS.md — laravel8-xero

Aplikasi akuntansi (clone Xero) untuk travel umroh. Laravel 8, PHP 7.4, Ubuntu.
File ini berisi aturan tetap. Konteks lengkap ada di `docs/ai/`.

## 0. Batas akses (prioritas tertinggi)
- Root kerja satu-satunya: `/working/annamiro/laravel8-xero`
- DILARANG baca/tulis/jalankan perintah di luar root. Jangan pakai `..` untuk keluar, jangan ikuti symlink keluar root.
- Jangan install paket global, jangan akses jaringan kecuali diminta eksplisit.
- Butuh akses di luar root: BERHENTI dan tanyakan.

## 1. Wajib dibaca sebelum bekerja
1. `docs/ai/PROJECT.md`   — tujuan & istilah
2. `docs/ai/FEATURES.md`  — daftar fitur & status
3. `docs/ai/DECISIONS.md` — keputusan final
4. `docs/ai/TESTING.md`   — cara tes yang aman
Sesuai area tugas: `ARCHITECTURE.md`, `DATABASE.md`, `INTEGRATIONS.md`.
Tugas yang menyentuh uang (invoice, bill, bank, overpayment, refund, kurs, summary bank): WAJIB baca `TRANSACTIONS.md` dan bagian Transaksi di `DECISIONS.md`.
Jika tugas bertentangan dengan `DECISIONS.md`: berhenti dan tanyakan.

## 2. Cara kerja
1. Jika ada file tugas di `docs/ai/tasks/`, itu spesifikasi utama.
2. Baca kode terkait dan contoh serupa, tiru gaya yang ada.
3. Tulis rencana singkat (file yang diubah) dan TUNGGU persetujuan sebelum mengedit.
4. Ubah seminimal mungkin. Jangan refactor di luar lingkup.
5. Verifikasi: `php -l` pada setiap file PHP yang diubah, tes yang aman (lihat TESTING.md).
6. Laporan akhir: daftar file berubah/dibuat, cara menguji, asumsi, hal yang belum selesai.
7. Perbarui status di `docs/ai/FEATURES.md` bila fitur berubah.
8. Ada ambiguitas atau risiko ke data keuangan: tanyakan dulu, jangan menebak.

## 3. Arsitektur singkat
`routes/api.php → Controller → Repo (extends BaseRepository, App\Http\Repository) → Model`; response lewat trait `ApiResponse` (detail: ARCHITECTURE.md).
Kode baru mengikuti pola ini. Fungsi lama yang menyimpang jangan direfactor tanpa diminta.

## 4. Aturan kode PHP 7.4 / Laravel 8
- JANGAN: constructor promotion, union types, `match`, `?->`, named arguments, attributes `#[...]`, `str_contains`/`str_starts_with`/`str_ends_with` (pakai `Illuminate\Support\Str`).
- Uang: `decimal`, jangan `float`. Operasi multi-tabel dibungkus `DB::transaction()`.
- `$fillable` eksplisit, hindari N+1 (`with()`), daftar wajib pagination.
- PSR-12. Identifier bahasa Inggris, komunikasi bahasa Indonesia.

## 5. Larangan
- Jangan baca/tampilkan/edit `.env` utama dan kredensial. Pengecualian: `.env.testing` BOLEH dibaca hanya untuk melihat `DB_CONNECTION` dan `DB_DATABASE` (apakah DB tes terpisah). Jangan tampilkan nilai lain (password, key, token) di jawaban atau laporan.
- Jangan edit `vendor/`, `storage/`, `bootstrap/cache/`, `composer.lock` tanpa izin.
- Jangan jalankan `migrate:fresh`, `migrate:refresh`, `db:wipe`, atau perintah yang menghapus data.
- Jangan ubah migration lama; buat migration baru.
- Jangan tambah package tanpa izin.
- Jangan jalankan tes yang menyentuh database utama.
- GIT: JANGAN menjalankan perintah git apa pun (status, diff, log, add, commit, checkout, stash, dll). Pemilik yang mengelola git dan commit; kamu cukup melaporkan file yang diubah.

## 6. Transaksi (uang)
- Perilaku bertanda ⚠ di `TRANSACTIONS.md` adalah temuan, bukan standar. Jangan ditiru dan jangan diperbaiki sebagai efek samping tugas lain; laporkan.
- Keputusan di `DECISIONS.md` bagian Transaksi bersifat final.
- Nama tabel/kolom ditulis persis seperti di database (termasuk yang tampak typo: `is_speend`, `uuid_proudct_and_service`); jangan dikoreksi.
- Semua posting dalam `DB::transaction()`; baris yang dibaca lalu diubah memakai `lockForUpdate()`; jangan `return` di tengah transaksi tanpa `rollBack()`.
- Konversi mata uang ke rupiah dibulatkan ke atas lewat satu helper (lihat TRANSACTIONS.md bagian 0). Summary bank dihitung dari `total_base_*` (rupiah).
- Jangan mengubah data yang sudah tersimpan (termasuk data lama non-IDR) dan jangan menjalankan perintah yang menulis ke database utama.
- Jangan menulis API key/kredensial ke kode; jangan memakai `env()` di luar file config.