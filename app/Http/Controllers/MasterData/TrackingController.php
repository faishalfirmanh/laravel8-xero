<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Repository\MasterData\TrackingRepo;

use App\Http\Repository\Transaction\SpendMoneyRepo;
use App\Http\Repository\Revenue\HotelDetailInvoicesRepository;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\PaymentParams;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\Revenue\Hotel\InvoicesHotel;
use App\Models\Config\ConfigCurrency;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;


class TrackingController extends Controller
{

    use ApiResponse;
    protected $repo;

    public function __construct(TrackingRepo $repo)
    {
        $this->repo = $repo;

    }

    private array $uuidReferenceGuards = [
        [
            'table' => 'd_bills',
            'column' => 'paket_tracking_uuid',
            'label' => 'Tagihan',
        ],
        [
            'table' => 'item_detail_invoices',
            'column' => 'paket_tracking_uuid',
            'label' => 'Detail Invoice',
        ],
        // tambah tabel baru di sini ↓
        // ['table' => 'tabel_lain', 'column' => 'paket_tracking_uuid', 'label' => 'Nama Modul'],
    ];


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_parent_category' => [
                'required',
                'string',
                Rule::unique('tracking_categories', 'name_parent_category')
                    ->ignore($request->id),
            ],
            'lines_category' => 'required|array|min:1',
            'lines_category.*.item_name_category' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi Gagal',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $isUpdate = !empty($request->id);
        $parentId = $isUpdate
            ? (int) $request->id
            : $this->repo->getLastIdPlusOne();

        // ── NEW: guard deleted items ──────────────────────────────────────────────
        // Only runs on update. Compares existing JSON vs incoming payload,
        // then checks every guard table for references to removed UUIDs.
        if ($isUpdate) {
            $blockingErrors = $this->getRemovedUuidsInUse(
                $request->id,
                $request->input('lines_category', [])
            );

            if (!empty($blockingErrors)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Beberapa paket tidak dapat dihapus karena masih digunakan di transaksi lain.',
                    'errors' => $blockingErrors,
                ], 422);
            }
        }

        // ── Pre-load all in-use UUIDs for uniqueness guarantee ────────────────────
        $usedUuids = $this->getAllUsedUuids();
        $linesCategory = $request->input('lines_category', []);

        foreach ($linesCategory as &$line) {
            $line['id_parent'] = $parentId;

            // Only generate UUID for NEW items (no item_uuid_category yet).
            // Existing items keep their UUID unchanged.
            if (empty($line['item_uuid_category'])) {
                $line['item_uuid_category'] = $this->generateUniqueRandom4Digit($usedUuids);
            }
        }
        unset($line);

        $request->merge([
            'name_parent_category' => strtolower($request->name_parent_category),
            'lines_category' => $linesCategory,
        ]);

        $request['created_by'] = $request->user_login->id;
        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($saved);
    }

    private function getRemovedUuidsInUse($existingId, array $incomingLines): array
    {
        // ── 1. Load current JSON from DB ──────────────────────────────────────────
        $current = DB::table('tracking_categories')
            ->select('lines_category')
            ->where('id', $existingId)
            ->first();

        if (!$current) {
            return [];
        }

        $existingLines = json_decode($current->lines_category, true) ?? [];

        // ── 2. Diff: which UUIDs disappeared from the payload? ────────────────────
        $existingUuids = array_values(array_filter(
            array_column($existingLines, 'item_uuid_category')
        ));
        $incomingUuids = array_values(array_filter(
            array_column($incomingLines, 'item_uuid_category')
        ));

        $removedUuids = array_values(array_diff($existingUuids, $incomingUuids));

        if (empty($removedUuids)) {
            return [];
        }

        // UUID → item_name_category for human-readable error messages
        $uuidToName = array_column($existingLines, 'item_name_category', 'item_uuid_category');

        // ── 3. One query per guard table ──────────────────────────────────────────
        $errors = []; // keyed by uuid while building, values() at the end

        foreach ($this->uuidReferenceGuards as $guard) {
            $found = DB::table($guard['table'])
                ->whereIn($guard['column'], $removedUuids)
                ->distinct()
                ->pluck($guard['column'])
                ->toArray();

            foreach ($found as $uuid) {
                // Initialize the error bucket for this UUID if it doesn't exist yet
                $errors[$uuid] ??= [
                    'uuid' => $uuid,
                    'name' => $uuidToName[$uuid] ?? '-',
                    'used_in' => [],
                ];
                $errors[$uuid]['used_in'][] = $guard['label'];
            }
        }

        // ── 4. Attach human-readable messages and re-index ────────────────────────
        return array_values(
            array_map(function (array $item): array {
                $item['message'] = sprintf(
                    'Paket "%s" tidak dapat dihapus, masih digunakan di: %s.',
                    $item['name'],
                    implode(', ', $item['used_in'])
                );
                return $item;
            }, $errors)
        );
    }



    private function getAllUsedUuids(): array
    {
        $uuids = [];

        DB::table('tracking_categories')
            ->select('lines_category')
            ->whereNotNull('lines_category')
            ->orderBy('id')
            ->each(function ($row) use (&$uuids) {
                foreach (json_decode($row->lines_category, true) ?? [] as $line) {
                    if (!empty($line['item_uuid_category'])) {
                        $uuids[] = $line['item_uuid_category'];
                    }
                }
            });

        return array_unique($uuids);
    }



    private function generateUniqueRandom4Digit(array &$usedUuids): string
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $uuid = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            if (!in_array($uuid, $usedUuids, true)) {
                $usedUuids[] = $uuid;
                return $uuid;
            }
        }

        throw new \RuntimeException('Gagal menghasilkan item_uuid_category unik — slot hampir penuh.');
    }



    public function getAllPaginate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'kolom_name' => 'required|string',
            'limit' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $where = [];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'name_parent_category', strtoupper($request->keyword));
        } else {
            $data =
                $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'name_parent_category', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }



    public function delete(Request $request)
    {
        $id = $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:tracking_categories,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $blog = $this->repo->find($id);

        if ($blog) {
            $data = $this->repo->delete($id);
            return $this->autoResponse($data);
        }

        return $this->error('hotel not found', 404);
    }


    public function detail(Request $request)
    {
        $data = $this->repo->find($request->id);
        return $this->autoResponse($data);
    }

    public function detailItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_parent_category' => 'required|string|in:nama paket,divisi',
            'id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 402);
        }
        $data = $this->repo->whereData(['name_parent_category' => $request->name_parent_category])->first();
        $aaa = is_string($data->lines_category) ? json_decode($data->lines_category, true) : $data->lines_category;

        $index = array_search($request->id, array_column($aaa, 'item_uuid_category'));

        $final_result = $index !== false ? $aaa[$index] : null;

        return $this->autoResponse($final_result);
    }

    public function trackByParent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_parent_category' => 'required|string',
            'keyword' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 402);
        }



        $data = $this->repo
            ->whereData(['name_parent_category' => $request->name_parent_category])
            ->first();

        if (!$data) {
            return $this->error('Data tidak ditemukan.', 404);
        }

        // Decode lines_category: handle JSON string maupun sudah array
        $linesCategory = is_string($data->lines_category)
            ? json_decode($data->lines_category, true)
            : (array) $data->lines_category;

        $linesCategory = $linesCategory ?? [];

        // Filter keyword pada field item_name_category (case-insensitive)
        $keyword = trim((string) $request->keyword);
        if ($keyword !== '') {
            $keywordUpper = strtoupper($keyword);
            $linesCategory = array_values(
                array_filter($linesCategory, function (array $item) use ($keywordUpper) {
                    $itemName = strtoupper($item['item_name_category'] ?? '');
                    return strpos($itemName, $keywordUpper) !== false;
                })
            );
        }

        $data->lines_category = $linesCategory;

        return $this->autoResponse($data);
    }

    //
}
