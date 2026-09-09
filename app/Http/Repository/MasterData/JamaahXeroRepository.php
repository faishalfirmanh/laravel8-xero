<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\DataJamaahXero;


use DB;

class JamaahXeroRepository extends BaseRepository
{

    public $model;
    public function __construct(DataJamaahXero $model)
    {
        $this->model = $model;
    }

    public function firstCreate($req)
    {

        return $this->model->firstOrCreate(['uuid_contact' => $req['uuid_contact']], $req);
    }

    // Di ContactRepository / JamaahRepo


    public function batchUpsert(array $records): void
    {
        if (empty($records))
            return;

        // Chunk per 500 agar tidak overload query di DB
        foreach (array_chunk($records, 500) as $index => $chunk) {
            try {
                DB::table('data_jamaah_xeros')                           // ← sesuaikan nama tabel
                    ->upsert(
                        $chunk,
                        ['uuid_contact'],                     // ← unique key (conflict check)
                        ['full_name', 'phone_number', 'is_mitra_trevel', 'updated_at']
                    );
                \Log::info("[batchUpsert] Chunk {$index} berhasil: " . count($chunk) . " records");
            } catch (\Throwable $th) {
                \Log::error("[batchUpsert] Chunk {$index} gagal: " . $th->getMessage());
                \Log::error("[batchUpsert] Sample data: " . json_encode($chunk[0] ?? []));
                throw $th; // lempar ke job agar ter-catch di failed()
            }

        }
    }
}
