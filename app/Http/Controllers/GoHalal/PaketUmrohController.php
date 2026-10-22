<?php

namespace App\Http\Controllers\GoHalal;

use App\Http\Controllers\Controller;


use App\Models\GoHalal\PaketUmroh;
use App\Models\MasterData\Hotel;
use App\Models\MasterData\TravelName;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class PaketUmrohController extends Controller
{
    use ApiResponse;

    const TIPE = ['Umroh', 'Haji', 'Umroh Plus'];
    const UPLOAD_DIR = 'uploads/paket-umroh';
    // Ganti dengan nama kolom nama hotel di tabel 'hotels'
    const HOTEL_NAME_COLUMN = 'name';

    private array $newFiles = [];
    private array $oldFiles = [];

    // ================================================================
    // VIEW
    // ================================================================

    public function view()
    {
        return view('admin.master.paket_umroh');
    }

    // ================================================================
    // API
    // ================================================================

    // GET /api/paket-umroh/options
    public function options()
    {
        $hotels = Hotel::orderBy('name')
            ->get(['id', 'name', 'type_location_hotel'])
            ->map(function ($h) {
                return [
                    'id' => $h->id,
                    'name' => $h->name,
                    'lokasi' => (int) $h->type_location_hotel === 1 ? 'Makkah' : 'Madinah',
                ];
            });

        return $this->autoResponse([
            'tipe' => self::TIPE,
            'travel' => TravelName::orderBy('name')->get(['id', 'name']),
            'hotels' => $hotels,
        ]);
    }

    // GET /api/paket-umroh?tipe=&travel=&keyword=&per_page=
    public function index(Request $request)
    {
        $query = PaketUmroh::with(['travel:id,name', 'hotels'])
            ->when($request->filled('tipe'), fn($q) => $q->where('tipe', $request->tipe))
            ->when($request->filled('travel'), fn($q) => $q->where('travel_id', $request->travel))
            ->when($request->filled('keyword'), fn($q) => $q->where('nama_paket', 'like', '%' . $request->keyword . '%'))
            ->orderByDesc('tgl_berangkat');

        return $this->autoResponse($query->paginate((int) $request->input('per_page', 10)));
    }

    // GET /api/paket-umroh/{id}
    public function show($id)
    {
        $paket = PaketUmroh::with(['travel:id,name', 'hotels'])->find($id);

        if (!$paket) {
            return $this->error('Paket umroh tidak ditemukan', 404);
        }

        return $this->autoResponse($paket);
    }

    // POST /api/paket-umroh
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules($request));
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }
        // if ($msg = $this->slotError($request)) {
        //     return $this->error($msg, 422);
        // }

        $this->newFiles = [];
        $this->oldFiles = [];

        DB::beginTransaction();
        try {
            $data = $this->payload($request);
            $data['created_by'] = $request->user_login->id;
            $data['maskapai'] = strtoupper($request->maskapai);
            $data['keberangkatan '] = strtoupper($request->keberangkatan);
            $data['landing'] = strtoupper($request->landing);
            $data['random_uuid_paket'] = $this->generateUniqueRandomUuid();
            $paket = PaketUmroh::create($data);
            $paket->hotels()->createMany($this->hotelRows($request));

            DB::commit();

            return $this->success($this->fresh($paket), 'Paket umroh berhasil dibuat', 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->deleteFiles($this->newFiles);
            Log::error('[PaketUmroh] store: ' . $e->getMessage());

            return $this->error($e->getMessage(), 422);
        }
    }

    // POST|PUT /api/paket-umroh/{id}
    public function update(Request $request, $id)
    {
        $paket = PaketUmroh::find($id);
        if (!$paket) {
            return $this->error('Paket umroh tidak ditemukan', 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request));
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }
        //input bebas
        // if ($msg = $this->slotError($request)) {
        //     return $this->error($msg, 422);
        // }

        $this->newFiles = [];
        $this->oldFiles = [];

        DB::beginTransaction();
        try {
            $data['maskapai'] = strtoupper($request->maskapai);
            $data['keberangkatan '] = strtoupper($request->keberangkatan);
            $data['landing'] = strtoupper($request->landing);
            $data = $this->payload($request, $paket);

            $paket->update($data);
            $paket->hotels()->delete();
            $paket->hotels()->createMany($this->hotelRows($request));

            DB::commit();

            // File lama dihapus setelah transaksi sukses
            $this->deleteFiles($this->oldFiles);

            return $this->success($this->fresh($paket), 'Paket umroh berhasil diupdate');

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->deleteFiles($this->newFiles);
            Log::error('[PaketUmroh] update: ' . $e->getMessage());

            return $this->error($e->getMessage(), 422);
        }
    }

    // DELETE /api/paket-umroh/{id}
    public function destroy($id)
    {
        $paket = PaketUmroh::find($id);
        if (!$paket) {
            return $this->error('Paket umroh tidak ditemukan', 404);
        }

        $files = [$paket->imgpaketumroh, $paket->itinerary];

        DB::beginTransaction();
        try {
            $paket->hotels()->delete();
            $paket->jamaahs()->delete();
            $paket->delete();

            DB::commit();
            $this->deleteFiles($files);

            return $this->success(null, 'Paket umroh berhasil dihapus');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[PaketUmroh] destroy: ' . $e->getMessage());

            return $this->error($e->getMessage(), 422);
        }
    }

    // ================================================================
    // HELPER
    // ================================================================


    private function rules(Request $request): array
    {
        return [
            'tipe' => 'required|in:' . implode(',', self::TIPE),
            'travel' => 'required|integer|exists:travel_names,id',
            'nama_paket' => 'required|string|max:255',
            'tgl_berangkat' => 'required|date',
            // 'price' => 'required|integer',
            'jumlah_hari' => 'required|integer|min:1',
            'maskapai' => 'nullable|string|max:100',
            'keberangkatan' => 'required|string|max:100',
            'landing' => 'required|string|max:100',
            'kodeflight' => 'nullable|string|max:50',
            'hrginfant' => 'nullable|numeric',
            'hrgquad' => 'required|numeric|min:0',
            'hrgtriple' => 'required|numeric|min:0',
            'hrgdouble' => 'required|numeric|min:0',
            'hrgperlengkapan' => 'nullable|numeric|min:0',
            'is_posting' => 'required|boolean',
            'is_internal' => 'required|boolean',
            'fasilitas_tambahan' => 'nullable|string',
            // Jika file dikirim -> validasi file, jika URL -> validasi URL
            'imgpaketumroh' => $request->hasFile('imgpaketumroh')
                ? 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240'   // input maks 10 MB, dikompres ke 300 KB
                : 'nullable|url',
            'itinerary' => $request->hasFile('itinerary')
                ? 'nullable|file|mimes:pdf|max:5120'
                : 'nullable|url',

            'max_slot' => 'required|integer|min:1',
            'available_slot' => 'required|integer|min:0',
            'filled_slot' => 'required|integer|min:0',

            'hotels' => 'nullable|array',
            'hotels.*.idhotelmitra' => 'required|integer|exists:hotels,id',
            'hotels.*.night' => 'required|integer|min:1',
        ];
    }

    /**
     * Generate UUID unik untuk paket. Dicek ke database agar tidak pernah sama.
     */
    private function generateUniqueRandomUuid(): string
    {
        do {
            $uuid = (string) Str::uuid();
        } while (PaketUmroh::where('random_uuid_paket', $uuid)->exists());

        return $uuid;
    }
    private function slotError(Request $request): ?string
    {
        $sum = (int) $request->available_slot + (int) $request->filled_slot;

        return $sum === (int) $request->max_slot
            ? null
            : 'available_slot + filled_slot harus sama dengan max_slot';
    }

    private function payload(Request $request, ?PaketUmroh $existing = null): array
    {
        $data = [
            'tipe' => $request->tipe,
            'travel_id' => $request->travel,
            'nama_paket' => $request->nama_paket,
            'tgl_berangkat' => $request->tgl_berangkat,
            'jumlah_hari' => $request->jumlah_hari,
            'maskapai' => $request->maskapai,
            'keberangkatan' => $request->keberangkatan,
            'landing' => $request->landing,
            'kodeflight' => $request->kodeflight,
            'hrginfant' => $request->hrginfant ?? 0,
            'hrgquad' => $request->hrgquad ?? 0,
            'hrgtriple' => $request->hrgtriple ?? 0,
            'hrgdouble' => $request->hrgdouble ?? 0,
            'hrgperlengkapan' => $request->hrgperlengkapan ?? 0,
            'max_slot' => $request->max_slot,
            'available_slot' => $request->available_slot,
            'filled_slot' => $request->filled_slot,
            'fasilitas_tambahan' => $request->fasilitas_tambahan,
            'is_posting' => $request->is_posting ? true : false,
            'is_internal' => $request->is_internal ? true : false
        ];

        if ($request->hasFile('imgpaketumroh')) {
            $data['imgpaketumroh'] = $this->storeImage($request->file('imgpaketumroh'));
            $this->newFiles[] = $data['imgpaketumroh'];
            if ($existing) {
                $this->oldFiles[] = $existing->imgpaketumroh;
            }
        } elseif ($request->filled('imgpaketumroh')) {
            $data['imgpaketumroh'] = $request->imgpaketumroh;
        }

        if ($request->hasFile('itinerary')) {
            $data['itinerary'] = $this->storeItinerary($request->file('itinerary'));
            $this->newFiles[] = $data['itinerary'];
            if ($existing) {
                $this->oldFiles[] = $existing->itinerary;
            }
        } elseif ($request->filled('itinerary')) {
            $data['itinerary'] = $request->itinerary;
        }

        return $data;
    }

    private function hotelRows(Request $request): array
    {
        $items = collect((array) $request->input('hotels', []));

        $ids = $items->pluck('idhotelmitra')
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $master = Hotel::whereIn('id', $ids)->get()->keyBy('id');

        $rows = [];

        foreach ($items as $h) {
            $m = $master->get((int) $h['idhotelmitra']);

            if (!$m) {
                continue; // sudah dijamin oleh validasi exists
            }

            $rows[] = [
                'hotel_id' => $m->id,
                'kota' => (int) $m->type_location_hotel === 1 ? 'Makkah' : 'Madinah',
                'nama_hotel' => $m->name,
                'alamat' => $m->address,
                'jarak' => $m->distance,
                'bintang' => $m->star,
                'night' => (int) ($h['night'] ?? 0),
            ];
        }

        return $rows;
    }

    private function normalizeKota($kota): string
    {
        return in_array((string) $kota, ['1', 'Makkah'], true) ? 'Makkah' : 'Madinah';
    }

    private function storeImage(UploadedFile $file): string
    {
        $dir = public_path(self::UPLOAD_DIR);
        $this->ensureDir($dir);

        $maxBytes = 300 * 1024; // 300 KB

        $img = Image::make($file->getRealPath())->orientate();

        if ($img->width() > 1200) {
            $img->resize(1200, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        // 1. Turunkan kualitas WebP sampai <= 300 KB
        $quality = 90;
        $encoded = (string) $img->encode('webp', $quality);

        while (strlen($encoded) > $maxBytes && $quality > 10) {
            $quality -= 10;
            $encoded = (string) $img->encode('webp', $quality);
        }

        // 2. Jika masih besar, perkecil dimensi secara bertahap
        $guard = 0;
        while (strlen($encoded) > $maxBytes && $img->width() > 300 && $guard < 10) {
            $img->resize((int) ($img->width() * 0.85), null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $encoded = (string) $img->encode('webp', 60);
            $guard++;
        }

        if (strlen($encoded) > $maxBytes) {
            throw new \RuntimeException('Gambar tidak bisa dikompres hingga maksimal 300 KB.');
        }

        $name = Str::random(24) . '.webp';
        file_put_contents($dir . '/' . $name, $encoded);

        return url(self::UPLOAD_DIR . '/' . $name);
    }

    private function storeItinerary(UploadedFile $file): string
    {
        $dir = public_path(self::UPLOAD_DIR . '/itinerary');
        $this->ensureDir($dir);

        $name = Str::random(24) . '.pdf';
        $file->move($dir, $name);

        return url(self::UPLOAD_DIR . '/itinerary/' . $name);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // Hanya menghapus file yang berada di folder upload lokal
    private function deleteFiles(array $urls): void
    {
        $base = rtrim(url('/'), '/') . '/';

        foreach (array_filter($urls) as $url) {
            $path = str_replace($base, public_path() . '/', $url);

            if (strpos($path, public_path()) === 0 && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function fresh(PaketUmroh $paket): PaketUmroh
    {
        return $paket->fresh(['travel:id,name', 'hotels']);
    }
}