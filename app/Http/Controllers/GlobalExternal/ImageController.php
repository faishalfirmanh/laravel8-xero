<?php

namespace App\Http\Controllers\GlobalExternal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use thiagoalessio\TesseractOCR\TesseractOCR;
class ImageController extends Controller
{

    public function extractText(Request $request)
    {
        // Validasi input: harus ada file gambar
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
        ]);

        // Simpan gambar sementara
        $imagePath = $request->file('image')->getRealPath();

        try {
            // Ekstrak teks menggunakan Tesseract
            $ocr = new TesseractOCR($imagePath);
            $ocr->lang('eng'); // Ganti 'eng' dengan 'ind' jika bahasa Indonesia, atau tambah multiple seperti 'eng+ind'
            $text = $ocr->run();

            $data = $this->parseKtpText($text);
            return response()->json([
                'success' => true,
                'extracted_text' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(), // Misalnya: Tesseract not found
            ], 500);
        }
    }


    private function parseKtpText($text)
{
    // Bersihkan teks: konversi ke uppercase agar regex lebih mudah
    $text = strtoupper($text);

    $results = [
        'nik' => null,
        'nama' => null,
        'tempat_tgl_lahir' => null,
        'jenis_kelamin' => null,
        'alamat' => null,
        'agama' => null,
        'pekerjaan' => null,
    ];

    // Regex untuk mengambil data (menyesuaikan karakter liar hasil OCR seperti '=' atau ':')
    preg_match('/NIK\s*[=:]?\s*(\d+)/', $text, $nik);
    preg_match('/NAMA[^\w]*\s*(.*)/', $text, $nama);
    preg_match('/TEMPAT\/TGL[^\w]*\s*(.*)/', $text, $ttl);
    preg_match('/JENIS KELAMIN\s*[=:]?\s*([A-Z\- ]+)/', $text, $jk);
    preg_match('/ALAMAT\s*[=:]?\s*(.*)/', $text, $alamat);
    preg_match('/AGAMA\s*[=:]?\s*(\w+)/', $text, $agama);
    preg_match('/PEKERJAAN\s*[=:]?\s*(.*)/', $text, $pekerjaan);

    $results['nik'] = $nik[1] ?? null;
    $results['nama'] = isset($nama[1]) ? trim($nama[1]) : null;
    $results['tempat_tgl_lahir'] = isset($ttl[1]) ? trim($ttl[1]) : null;
    $results['jenis_kelamin'] = isset($jk[1]) ? trim($jk[1]) : null;
    $results['alamat'] = isset($alamat[1]) ? trim($alamat[1]) : null;
    $results['agama'] = isset($agama[1]) ? trim($agama[1]) : null;
    $results['pekerjaan'] = isset($pekerjaan[1]) ? trim($pekerjaan[1]) : null;

    return $results;
}
}
