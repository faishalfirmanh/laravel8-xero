<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Expenses\Purchase\Bill\PBill;
use App\Models\InvoicesAllFromXero;
use App\Models\LogHistory;
use App\Models\MasterData\DataJamaahXero;
use App\Models\Revenue\Hotel\InvoicesHotel;
use App\Models\Transaction\TransactionBankTransP;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DataJamaahController extends Controller
{
    /**
     * Kolom di DataJamaahXero yang boleh dipakai sebagai kunci duplicate.
     * Sesuaikan dengan kolom yang benar-benar ada di tabel Anda.
     */
    private const ALLOWED_GROUP_BY = ['full_name', 'uuid_contact', 'id_jamaah_alhid', 'phone_number', 'nik'];

    public function index(Request $request): JsonResponse
    {
        $groupBy = $this->resolveGroupBy($request);
        if ($groupBy === null) {
            return response()->json([
                'status' => false,
                'message' => 'group_by tidak valid.',
            ], 422);
        }

        $analysis = $this->analyze($groupBy);

        return response()->json([
            'status' => true,
            'group_by' => $groupBy,
            'total_groups' => count($analysis['groups']),
            'total_deletable' => count($analysis['candidate_ids']),
            'candidate_ids' => $analysis['candidate_ids'],
            'data' => $analysis['groups'],
        ]);
    }



    public function getLogTransction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_inv' => 'required|boolean',
            'id_inv' => [
                'required_if:is_inv,1',
                'prohibited_if:is_inv,0',
                'array',
                'min:1',
            ],

            'id_inv.*' => [
                'required',
                'integer',
                'distinct',
                'exists:invoices_all_from_xeros,id',
            ],

            'id_bills' => [
                'required_if:is_inv,0',
                'prohibited_if:is_inv,1',
                'integer',
                'exists:bills,id',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($request->boolean('is_inv')) {

            $data = LogHistory::whereIn(
                'salles_inv_id',
                $request->id_inv
            )->get();

        } else {

            $data = LogHistory::where(
                'bills_id',
                $request->id_bills
            )->get();

        }

        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }


    /**
     * POST /api/master-data/jamaah-xero/duplicates/delete
     * Body: { "ids": [1,2,3], "group_by": "full_name", "dry_run": true }
     */
    public function destroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'group_by' => 'nullable|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer',
            'dry_run' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $groupBy = $this->resolveGroupBy($request);
        if ($groupBy === null) {
            return response()->json([
                'status' => false,
                'message' => 'group_by tidak valid.',
            ], 422);
        }

        $dryRun = $request->boolean('dry_run', true);
        $requested = array_values(array_unique(array_map('intval', $request->input('ids'))));

        // Hitung ulang di dalam transaction supaya data yang dicek sama dengan yang dihapus
        [$toDelete, $rejected] = DB::transaction(function () use ($groupBy, $requested, $dryRun) {
            $analysis = $this->analyze($groupBy);
            $allowed = array_map('intval', $analysis['candidate_ids']);
            $toDelete = array_values(array_intersect($requested, $allowed));
            $rejected = array_values(array_diff($requested, $allowed));

            if (!$dryRun && !empty($toDelete)) {
                DataJamaahXero::whereIn('id', $toDelete)->delete();
            }

            return [$toDelete, $rejected];
        });

        if (!$dryRun && !empty($toDelete)) {
            Log::info('Hapus duplicate DataJamaahXero', [
                'ids' => $toDelete,
                'user_id' => optional($request->user())->id,
            ]);
        }

        return response()->json([
            'status' => true,
            'dry_run' => $dryRun,
            'deleted' => $dryRun ? [] : $toDelete,
            'would_delete' => $dryRun ? $toDelete : [],
            'rejected' => $rejected,
            'message' => $dryRun
                ? 'Dry run. Belum ada data yang dihapus.'
                : count($toDelete) . ' data dihapus. ' . count($rejected) . ' id ditolak (masih dipakai atau bukan duplicate).',
        ]);
    }

    /**
     * Inti logika: cari grup duplicate, cek pemakaian, tentukan kandidat hapus.
     */
    private function analyze(string $groupBy): array
    {
        $refMap = $this->referenceMap();

        // Kolom yang perlu diambil dari data_jamaah_xeros
        $selectCols = ['id', $groupBy];
        foreach ($refMap as $ref) {
            $selectCols[] = $ref['source'];
        }
        $selectCols = array_values(array_unique($selectCols));

        $records = DataJamaahXero::query()->get($selectCols);

        // 1. Ambil nilai pemakaian dari tiap model referensi
        $usedSets = [];
        foreach ($refMap as $label => $ref) {
            $values = $records->pluck($ref['source'])
                ->filter(function ($v) {
                    return $v !== null && $v !== '';
                })
                ->unique()
                ->values()
                ->all();

            if (empty($values)) {
                $usedSets[$label] = [];
                continue;
            }

            $modelClass = $ref['model'];
            $found = $modelClass::query()
                ->whereIn($ref['column'], $values)
                ->distinct()
                ->pluck($ref['column'])
                ->map(function ($v) {
                    return (string) $v;
                })
                ->all();

            $usedSets[$label] = array_flip($found);
        }

        // 2. Tandai setiap record: dipakai di model mana saja
        $usageById = [];
        foreach ($records as $r) {
            $labels = [];
            foreach ($refMap as $label => $ref) {
                $value = (string) $r->{$ref['source']};
                if ($value !== '' && isset($usedSets[$label][$value])) {
                    $labels[] = $label;
                }
            }
            $usageById[(string) $r->id] = $labels;
        }

        // 3. Kelompokkan berdasarkan kunci (case-insensitive, trim), ambil yang > 1
        $groups = $records
            ->filter(function ($r) use ($groupBy) {
                return trim((string) $r->{$groupBy}) !== '';
            })
            ->groupBy(function ($r) use ($groupBy) {
                return strtoupper(trim((string) $r->{$groupBy}));
            })
            ->filter(function ($items) {
                return $items->count() > 1;
            });

        // 4. Tentukan kandidat hapus per grup
        $result = [];
        $candidateIds = [];

        foreach ($groups as $key => $items) {
            $items = $items->sortBy('id')->values();

            // Jika semua baris tidak terpakai, simpan satu (id terkecil) sebagai master
            $keepId = null;
            $anyUsed = $items->contains(function ($r) use ($usageById) {
                return !empty($usageById[(string) $r->id]);
            });
            if (!$anyUsed) {
                $keepId = (string) $items->first()->id;
            }

            $rows = $items->map(function ($r) use ($usageById, $keepId, $groupBy) {
                $labels = $usageById[(string) $r->id];
                $isKeep = $keepId !== null && (string) $r->id === $keepId;
                $deletable = empty($labels) && !$isKeep;

                return [
                    'id' => $r->id,
                    'uuid_contact' => $r->uuid_contact,
                    'id_jamaah_alhid' => $r->id_jamaah_alhid,
                    'value' => $r->{$groupBy},
                    'used_in' => $labels,
                    'can_delete' => $deletable,
                ];
            })->values();

            foreach ($rows as $row) {
                if ($row['can_delete']) {
                    $candidateIds[] = (int) $row['id'];
                }
            }

            $result[] = [
                'group_key' => $key,
                'total' => $items->count(),
                'rows' => $rows,
            ];
        }

        return [
            'groups' => $result,
            'candidate_ids' => $candidateIds,
        ];
    }

    /**
     * Daftar model yang mereferensi DataJamaahXero.
     * 'source' = kolom di data_jamaah_xeros
     * 'column' = kolom di model referensi
     *
     * PERLU DIVERIFIKASI: 'source' untuk tiap model. Saat ini semuanya diarahkan ke uuid_contact.
     * Jika InvoicesHotel atau TransactionBankTransP memakai id_jamaah_alhid, ubah source-nya.
     */
    private function referenceMap(): array
    {
        return [
            'PBill' => [
                'model' => PBill::class,
                'column' => 'uuid_from',
                'source' => 'uuid_contact',
            ],
            'InvoicesAllFromXero' => [
                'model' => InvoicesAllFromXero::class,
                'column' => 'contact_id',
                'source' => 'uuid_contact',
            ],
            'TransactionBankTransP' => [
                'model' => TransactionBankTransP::class,
                'column' => 'uuid_to',
                'source' => 'uuid_contact',
            ],
            'InvoicesHotel' => [
                'model' => InvoicesHotel::class,
                'column' => 'uuid_user_order',
                'source' => 'uuid_contact',
            ],
        ];
    }

    private function resolveGroupBy(Request $request): ?string
    {
        $groupBy = (string) $request->input('group_by', 'full_name');

        return in_array($groupBy, self::ALLOWED_GROUP_BY, true) ? $groupBy : null;
    }
}