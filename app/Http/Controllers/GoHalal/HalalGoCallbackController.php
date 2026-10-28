<?php

namespace App\Http\Controllers\GoHalal;

use App\Http\Controllers\Controller;
use App\Models\DataJamaah;
use App\Models\GoHalal\PaketUmroh;
use App\Models\GoHalal\PaketUmrohJamaah;
use App\Models\MasterData\DataJamaahXero;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Callback dari Halal Go: dipanggil saat ada booking baru / booking berubah di sisi Halal Go.
 *
 * Seat hanya dihitung dari dua model: PaketUmroh (max/available/filled) dan
 * PaketUmrohJamaah (satu baris per booking Halal Go, dikenali dari kode_booking).
 *
 * Status:
 *  201 sukses (baru maupun update booking yang sama)
 *  400 validasi gagal / parameter kurang
 *  401 Authorization Bearer hilang/tidak valid (middleware VerifyHalalGoCallback)
 *  404 paket (kode_paket) tidak ditemukan
 */
class HalalGoCallbackController extends Controller
{
    use ApiResponse;

    // POST /api/mitra/halalgo/callback-booking
    public function booking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_paket' => 'required|string|max:100',
            'kode_booking' => 'required|string|max:100',
            'total_seat' => 'required|integer|min:1',
            'tanggal_booked' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            Log::warning('[HalalGoCallback] validasi gagal', [
                'error' => $validator->errors()->first(),
                'body' => $request->all(),
            ]);

            return $this->error($validator->errors()->first(), 400);
        }

        $kodePaket = (string) $request->kode_paket;
        $kodeBooking = (string) $request->kode_booking;
        $totalSeat = (int) $request->total_seat;

        // kode_paket Halal Go disimpan di kolom random_uuid_paket
        $found = PaketUmroh::where('random_uuid_paket', $kodePaket)->first();
        if (!$found) {
            Log::warning('[HalalGoCallback] paket tidak ditemukan', ['kode_paket' => $kodePaket]);

            return $this->error('Paket tidak ditemukan', 404);
        }

        DB::beginTransaction();
        try {
            // Booking yang sama bisa saja dikirim ulang / pindah paket -> kunci semua paket terkait
            // dengan urutan id konsisten (sama seperti pindah()) supaya tidak deadlock.
            $existing = PaketUmrohJamaah::where('kode_booking', $kodeBooking)->first();

            $ids = array_values(array_unique(array_filter([
                (int) $found->id,
                (int) optional($existing)->paket_umroh_id,
            ])));
            sort($ids);

            $pakets = PaketUmroh::whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $paket = $pakets->get((int) $found->id);

            $booking = PaketUmrohJamaah::where('kode_booking', $kodeBooking)
                ->lockForUpdate()
                ->first();

            $oldPaketId = $booking ? (int) $booking->paket_umroh_id : null;

            $attrs = [
                'paket_umroh_id' => $paket->id,
                'jumlah_jamaah' => $totalSeat,
                'tanggal_booked' => $request->tanggal_booked,
                'source' => 'halalgo',
            ];

            $jamaah_for_go = DataJamaah::firstOrCreate(
                ['no_ktp' => 111223, 'nama_jamaah' => 'jamaah_from_gohalalgo'],
                ['no_ktp' => 111223, 'nama_jamaah' => 'jamaah_from_gohalalgo']
            );
            if ($booking) {
                $booking->update($attrs);
                $created = false;
            } else {
                PaketUmrohJamaah::create($attrs + [
                    'kode_booking' => $kodeBooking,
                    'created_by' => null,
                    'id_jamaah_alhid' => $jamaah_for_go->id_jamaah
                ]);
                $created = true;
            }

            $paket->syncSlot();

            // Booking pindah paket di sisi Halal Go -> paket lama ikut dihitung ulang
            if ($oldPaketId && $oldPaketId !== (int) $paket->id && $pakets->has($oldPaketId)) {
                $pakets->get($oldPaketId)->syncSlot();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[HalalGoCallback] booking: ' . $e->getMessage(), [
                'kode_paket' => $kodePaket,
                'kode_booking' => $kodeBooking,
            ]);

            return $this->error('Terjadi kesalahan saat menyimpan booking', 500);
        }

        // Booking dari Halal Go sudah final di sisi mereka, jadi tetap diterima walau melebihi slot.
        if ($paket->available_slot < 0) {
            Log::warning('[HalalGoCallback] overbook', [
                'paket_id' => $paket->id,
                'kode_paket' => $kodePaket,
                'max_slot' => $paket->max_slot,
                'filled_slot' => $paket->filled_slot,
            ]);
        }

        Log::info('[HalalGoCallback] booking diterima', [
            'kode_paket' => $kodePaket,
            'kode_booking' => $kodeBooking,
            'total_seat' => $totalSeat,
            'created' => $created,
            'filled_slot' => $paket->filled_slot,
            'available_slot' => $paket->available_slot,
        ]);

        return $this->success([
            'kode_paket' => $kodePaket,
            'kode_booking' => $kodeBooking,
            'total_seat' => $totalSeat,
            'created' => $created,
            'max_slot' => (int) $paket->max_slot,
            'filled_slot' => (int) $paket->filled_slot,
            'available_slot' => (int) $paket->available_slot,
        ], $created ? 'Booking berhasil disimpan' : 'Booking berhasil diperbarui', 201);
    }
}