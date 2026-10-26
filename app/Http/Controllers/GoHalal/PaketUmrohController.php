<?php

namespace App\Http\Controllers\GoHalal;

use App\Http\Controllers\Controller;


use App\Models\GoHalal\PaketUmroh;
use App\Models\ItemsPaketAllFromXero;
use App\Models\MasterData\Coa;
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
use App\Services\HalalGoService;

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

    // POST /api/paket-umroh/{id}/itenari   (name: update-file-itenary)
    public function updateItenari(Request $request, $id)
    {
        $paket = PaketUmroh::find($id);
        if (!$paket) {
            return $this->error('Paket umroh tidak ditemukan', 404);
        }

        $validator = Validator::make($request->all(), [
            'itinerary' => 'required|file|mimes:pdf|max:5120',
        ], [
            'itinerary.required' => 'File itinerary (PDF) wajib dipilih',
            'itinerary.uploaded' => 'File gagal diunggah, cek batas ukuran upload server',
            'itinerary.mimes' => 'File itinerary harus berformat PDF',
            'itinerary.max' => 'Ukuran file itinerary maksimal 5 MB',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $file = $request->file('itinerary');
        $service = app(HalalGoService::class);

        // 1) Halal Go lebih dulu: kalau gagal, data lokal belum disentuh
        $halal = null;
        if ($service->hasKodePaket($paket)) {
            $halal = $service->uploadItinerary($paket->random_uuid_paket, $file->getRealPath());

            if (!$halal['ok']) {
                return $this->error($this->halalErrorMessage($halal) . ' (itinerary belum diubah)', 422);
            }
        }

        // 2) Simpan lokal + update kolom itinerary
        $this->newFiles = [];
        $oldUrl = $paket->itinerary;

        try {
            $url = $this->storeItinerary($file);
            $this->newFiles[] = $url;

            $paket->update(['itinerary' => $url]);
        } catch (\Throwable $e) {
            $this->deleteFiles($this->newFiles);
            Log::error('[PaketUmroh] updateItenari: ' . $e->getMessage(), [
                'paket_id' => $paket->id,
                'sudah_terkirim_ke_halal_go' => $halal !== null,
            ]);

            return $this->error(
                ($halal ? 'Itinerary sudah terkirim ke Halal Go, tetapi gagal disimpan lokal: ' : '') . $e->getMessage(),
                422
            );
        }

        // 3) File lama dihapus setelah semuanya berhasil
        $this->deleteFiles([$oldUrl]);

        return $this->success(
            ['paket' => $this->fresh($paket), 'halal_go' => $halal],
            $halal ? 'Itinerary berhasil diperbarui dan dikirim ke Halal Go' : 'Itinerary berhasil diperbarui'
        );
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules($request));
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $this->newFiles = [];
        $this->oldFiles = [];

        DB::beginTransaction();
        try {
            $data = $this->payload($request);
            $data['created_by'] = $request->user_login->id;
            $data['maskapai'] = strtoupper($request->maskapai);
            $data['keberangkatan'] = strtoupper($request->keberangkatan);
            $data['landing'] = strtoupper($request->landing);
            $data['random_uuid_paket'] = $this->generateUniqueRandomUuid();
            $data['is_posting'] = $request->boolean('is_posting') ? 1 : 0;
            $data['is_internal'] = $request->boolean('is_internal') ? 1 : 0;

            if ($request->id == null) {
                $this->createProductItem($data);
            }

            $paket = PaketUmroh::create($data);
            $paket->hotels()->createMany($this->hotelRows($request));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->deleteFiles($this->newFiles);
            Log::error('[PaketUmroh] store: ' . $e->getMessage());

            return $this->error($e->getMessage(), 422);
        }

        // Kirim ke Halal Go hanya jika diposting dan bukan internal.
        // Dilakukan setelah commit, sehingga kegagalan API tidak membatalkan data lokal.
        $halal = null;
        if ($request->boolean('is_posting') && !$request->boolean('is_internal')) {
            $halal = $this->sendToHalalGo($paket);
            if (!$halal['ok']) {
                return $this->error($this->halalErrorMessage($halal), 422);
            }
        }

        return $this->success(
            ['paket' => $this->fresh($paket), 'halal_go' => $halal],
            'Paket umroh berhasil dibuat',
            201
        );
    }

    private function halalErrorMessage(array $halal): string
    {
        $detail = !empty($halal['errors'])
            ? implode('; ', $halal['errors'])
            : ($halal['message'] ?? 'Respon tidak dikenali');

        return 'Gagal menyimpan ke Halal Go: ' . $detail;
    }

    private function sendToHalalGo(PaketUmroh $paket): array
    {
        $service = app(HalalGoService::class);
        $paket = $this->fresh($paket);

        $res = $service->createPackage($service->buildPayload($paket));

        if (!$res['ok']) {
            return $res;
        }

        $kode = $res['kode_paket'] ?? null;
        if (!$kode) {
            Log::error('[PaketUmroh] Halal Go sukses tapi kode_paket kosong', ['paket_id' => $paket->id]);
            return $res;
        }

        // Ganti UUID lokal dengan kode_paket Halal Go.
        // ItemsPaketAllFromXero ikut diganti karena ditautkan lewat nilai yang sama.
        try {
            DB::transaction(function () use ($paket, $kode) {
                ItemsPaketAllFromXero::where('uuid_proudct_and_service', $paket->random_uuid_paket)
                    ->update(['uuid_proudct_and_service' => $kode]);

                $paket->update(['random_uuid_paket' => $kode]);
            });
        } catch (\Throwable $e) {
            // Paket sudah ada di Halal Go: kode HARUS tercatat supaya tidak terkirim dobel
            Log::critical('[PaketUmroh] kode_paket Halal Go gagal disimpan lokal', [
                'paket_id' => $paket->id,
                'kode_paket' => $kode,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'http_status' => $res['http_status'],
                'message' => "Paket sudah terkirim ke Halal Go (kode {$kode}) tetapi gagal disimpan lokal: " . $e->getMessage(),
                'errors' => [],
            ];
        }

        return $res;
    }
    // POST /api/paket-umroh
    public function storeLocal(Request $request)
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
            $data['keberangkatan'] = strtoupper($request->keberangkatan);
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

        $this->newFiles = [];
        $this->oldFiles = [];

        DB::beginTransaction();
        try {
            $data = $this->payload($request, $paket);
            $data['maskapai'] = strtoupper($request->maskapai);
            $data['keberangkatan'] = strtoupper($request->keberangkatan);
            $data['landing'] = strtoupper($request->landing);

            $paket->update($data);
            $paket->hotels()->delete();
            $paket->hotels()->createMany($this->hotelRows($request));

            DB::commit();

            // File lama dihapus setelah transaksi sukses
            $this->deleteFiles($this->oldFiles);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->deleteFiles($this->newFiles);
            Log::error('[PaketUmroh] update: ' . $e->getMessage());

            return $this->error($e->getMessage(), 422);
        }

        // Sinkron ke Halal Go setelah commit: kegagalan API tidak membatalkan data lokal
        $halal = null;
        if ($request->boolean('is_posting') && !$request->boolean('is_internal')) {
            $halal = $this->syncToHalalGo($paket, $request->hasFile('itinerary'));
            if (!$halal['ok']) {
                return $this->error($this->halalErrorMessage($halal), 422);
            }
        }

        return $this->success(
            ['paket' => $this->fresh($paket), 'halal_go' => $halal],
            'Paket umroh berhasil diupdate'
        );
    }

    private function syncToHalalGo(PaketUmroh $paket, bool $itineraryChanged): array
    {
        $service = app(HalalGoService::class);
        $paket = $this->fresh($paket);

        // Belum pernah terkirim (masih UUID lokal, mis. baru di-posting) -> create
        if (!$service->hasKodePaket($paket)) {
            return $this->sendToHalalGo($paket);
        }

        $res = $service->updatePackage($paket->random_uuid_paket, $service->buildPayload($paket));

        if ($res['ok'] && $itineraryChanged) {
            $res = $this->pushItinerary($paket, $res);
        }

        return $res;
    }

    private function pushItinerary(PaketUmroh $paket, array $res): array
    {
        $path = $this->localPath($paket->itinerary);
        if (!$path) {
            return $res; // itinerary berupa URL luar / file lokal tidak ada
        }

        $up = app(HalalGoService::class)->uploadItinerary($paket->random_uuid_paket, $path);

        if (!$up['ok']) {
            return [
                'ok' => false,
                'http_status' => $up['http_status'],
                'message' => 'Paket terupdate, tetapi upload itinerary gagal: ' . ($up['message'] ?? ''),
                'errors' => $up['errors'] ?? [],
            ] + $res;
        }

        $res['itinerary'] = $up;
        return $res;
    }

    // URL upload lokal -> path file di server (null bila bukan file lokal)
    private function localPath(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $base = rtrim(url('/'), '/') . '/';
        $path = str_replace($base, public_path() . '/', $url);

        return (strpos($path, public_path()) === 0 && is_file($path)) ? $path : null;
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
        } while (
            PaketUmroh::where('random_uuid_paket', $uuid)->exists()
            || ItemsPaketAllFromXero::where('uuid_proudct_and_service', $uuid)->exists()
        );

        return $uuid;
    }
    private function slotError(Request $request): ?string
    {
        $sum = (int) $request->available_slot + (int) $request->filled_slot;

        return $sum === (int) $request->max_slot
            ? null
            : 'available_slot + filled_slot harus sama dengan max_slot';
    }


    public function createProductItem($data)
    {
        $cariCoa = Coa::where('code', 4600)->first();

        if (!$cariCoa) {
            throw new \Exception('COA dengan kode 4600 tidak ditemukan.');
        }

        return ItemsPaketAllFromXero::firstOrCreate(
            [
                'uuid_proudct_and_service' => $data['random_uuid_paket'],
            ],
            [
                'code' => Str::random(5),
                'nama_paket' => $data['nama_paket'],
                'sales_AccountCode' => 4600,
                'purchase_AccountCode' => 'from cs',
                'total_hari' => $data['jumlah_hari'],
                'jenis_item' => 1,
                'price_sales' => $data['hrgquad'],
                'account_id_salles' => $cariCoa->id,
                'is_active' => 1,
                'departure_date' => !empty($data['tgl_berangkat'])
                    ? $data['tgl_berangkat']
                    : null,
                'desc_salles' => $data['nama_paket'],
                'tax_rate_salles' => 0,
                'price_purchase' => 0,
                'tax_rate_purchase' => 0,
                'is_item_product' => 0
            ]
        );
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