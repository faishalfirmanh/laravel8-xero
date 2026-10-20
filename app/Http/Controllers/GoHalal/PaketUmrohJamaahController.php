<?php

namespace App\Http\Controllers\GoHalal;

use App\Http\Controllers\Controller;

use App\Models\GoHalal\PaketUmroh;
use App\Models\GoHalal\PaketUmrohJamaah;
use App\Models\MasterData\DataJamaahXero;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaketUmrohJamaahController extends Controller
{
    use ApiResponse;

    // GET /api/paket-umroh/jamaah-select2?keyword=
    public function jamaahSelect2(Request $request)
    {
        $keyword = trim((string) $request->input('keyword', ''));

        $results = DataJamaahXero::query()
            ->select('id', 'full_name', 'nik')
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($w) use ($keyword) {
                    $w->where('full_name', 'like', '%' . $keyword . '%')
                        ->orWhere('nik', 'like', '%' . $keyword . '%');
                });
            })
            ->orderBy('full_name')
            ->limit(20)
            ->get()
            ->map(fn($j) => [
                'id' => $j->id,
                'text' => $j->full_name . ' (' . $j->nik . ')',
            ]);

        return $this->autoResponse(['results' => $results]);
    }

    // GET /api/paket-umroh/{id}/jamaah
    public function index($id)
    {
        $paket = PaketUmroh::find($id);
        if (!$paket) {
            return $this->error('Paket umroh tidak ditemukan', 404);
        }

        $jamaah = PaketUmrohJamaah::with('jamaah:id,full_name,nik')
            ->where('paket_umroh_id', $id)
            ->orderBy('id')
            ->get();

        return $this->autoResponse([
            'paket' => [
                'id' => $paket->id,
                'nama_paket' => $paket->nama_paket,
                'max_slot' => (int) $paket->max_slot,
                'filled_slot' => (int) $paket->filled_slot,
                'available_slot' => (int) $paket->available_slot,
            ],
            'jamaah' => $jamaah,
        ]);
    }

    // POST /api/paket-umroh/{id}/jamaah
    public function store(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'data_jamaah_id' => 'required|integer|exists:data_jamaah_xeros,id',
            'jumlah_jamaah' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Kunci baris paket agar dua input bersamaan tidak melewati batas slot
            $paket = PaketUmroh::lockForUpdate()->find($id);
            if (!$paket) {
                throw new \RuntimeException('Paket umroh tidak ditemukan');
            }

            $sudahAda = PaketUmrohJamaah::where('paket_umroh_id', $paket->id)
                ->where('data_jamaah_id', $request->data_jamaah_id)
                ->exists();

            if ($sudahAda) {
                throw new \RuntimeException('Jamaah ini sudah terdaftar di paket ini');
            }

            $sisa = (int) $paket->max_slot - (int) $this->totalJamaah($paket->id);
            if ((int) $request->jumlah_jamaah > $sisa) {
                throw new \RuntimeException('Slot tidak cukup. Sisa slot: ' . max(0, $sisa));
            }

            PaketUmrohJamaah::create([
                'paket_umroh_id' => $paket->id,
                'data_jamaah_id' => $request->data_jamaah_id,
                'jumlah_jamaah' => $request->jumlah_jamaah,
                'created_by' => optional($request->user_login)->id,
            ]);

            $this->syncSlot($paket);

            DB::commit();

            return $this->success(['paket_id' => $paket->id], 'Jamaah berhasil ditambahkan', 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[PaketUmrohJamaah] store: ' . $e->getMessage());

            return $this->error($e->getMessage(), 422);
        }
    }

    // DELETE /api/paket-umroh/{id}/jamaah/{rowId}
    public function destroy($id, $rowId)
    {
        DB::beginTransaction();
        try {
            $paket = PaketUmroh::lockForUpdate()->find($id);
            if (!$paket) {
                throw new \RuntimeException('Paket umroh tidak ditemukan');
            }

            $row = PaketUmrohJamaah::where('paket_umroh_id', $id)->where('id', $rowId)->first();
            if (!$row) {
                throw new \RuntimeException('Data jamaah tidak ditemukan');
            }

            $row->delete();
            $this->syncSlot($paket);

            DB::commit();

            return $this->success(null, 'Jamaah berhasil dihapus');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[PaketUmrohJamaah] destroy: ' . $e->getMessage());

            return $this->error($e->getMessage(), 422);
        }
    }

    // ================================================================
    // HELPER
    // ================================================================

    private function totalJamaah($paketId): int
    {
        return (int) PaketUmrohJamaah::where('paket_umroh_id', $paketId)->sum('jumlah_jamaah');
    }

    /**
     * Hitung ulang filled_slot dan available_slot dari semua booking jamaah.
     */
    private function syncSlot(PaketUmroh $paket): void
    {
        $filled = $this->totalJamaah($paket->id);

        $paket->update([
            'filled_slot' => $filled,
            'available_slot' => max(0, (int) $paket->max_slot - $filled),
        ]);
    }
}