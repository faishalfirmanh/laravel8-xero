<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('data_jamaah', function (Blueprint $table) {
            // id_jamaah (bigint 20, AI, Primary)
            $table->bigIncrements('id_jamaah');

            // Data Agen & Identitas
            $table->integer('agen');
            $table->string('no_ktp');
            $table->enum('title', ['MR', 'MRS', 'CHD'])->nullable();
            $table->integer('age')->nullable();
            $table->string('place')->nullable();

            // Dokumen
            $table->string('passport')->nullable();
            $table->date('issued')->nullable();
            $table->date('expired')->nullable();
            $table->string('office')->nullable();

            // Kelahiran
            $table->date('tgl_lahir')->nullable();
            $table->string('tempat_lahir');

            // Keberangkatan
            $table->date('estimasi_berangkat');
            $table->integer('imigrasi');
            $table->integer('leader')->default(1)->comment('1=haji, 2=umroh');
            $table->integer('id_status')->default(2);

            // Detail Jamaah
            $table->string('nama_jamaah', 100);
            $table->string('nama_tambahan');
            $table->text('alamat_jamaah');
            $table->string('nama_di_vaksin');

            // Vaksinasi 1
            // List enum disesuaikan dengan potongan yang terlihat (Sinovac, Bio Farma, dll)
            $opsiVaksin = ['Belum Vaksin', 'Sinovac', 'Bio Farma', 'AstraZeneca', 'Moderna', 'Pfizer', 'Janssen', 'Sinopharm'];
            $table->enum('jenis_vaksin', $opsiVaksin);
            $table->date('tgl_vaksin_1')->nullable();
            $table->date('tgl_vaksin_2')->nullable();

            // Kontak
            $table->string('no_tlp', 40);
            // hp_jamaah di gambar tipe varbinary, tapi di Laravel umum menggunakan string/binary.
            // Menggunakan string agar aman, ubah ke ->binary() jika ingin blob.
            $table->string('hp_jamaah')->nullable();

            // File Uploads (Path)
            $table->string('foto')->nullable();
            $table->string('kartukeluarga')->nullable();
            $table->string('ktp')->nullable(); // Kolom untuk file KTP (beda dengan no_ktp)
            $table->string('surat_nikah')->nullable();

            // Lainnya
            $table->text('keterangan')->nullable();
            $table->integer('transaksi')->nullable();

            // Vaksinasi Lanjutan
            $table->enum('jenis_vaksin_2', $opsiVaksin)->nullable();
            $table->enum('jenis_vaksin_3', $opsiVaksin)->nullable();
            $table->enum('jenis_vaksin_4', $opsiVaksin)->nullable();
            $table->date('tgl_vaksin_3')->nullable();
            $table->date('tgl_vaksin_4')->nullable();

            // System info
            $table->integer('user_id')->nullable();

            // created_at & updated_at
            // Menggunakan timestamps() laravel standar.
            // Jika ingin persis seperti gambar (updated_at tipe datetime), timestamps() sudah cukup mewakili.
            $table->timestamps();

            $table->boolean('is_agen')->nullable(); // tinyint(1) mapping ke boolean
            $table->integer('travel_agent')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_jamaah');
    }
};
