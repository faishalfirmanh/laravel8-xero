<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY: command ini TIDAK menghapus / mengubah data apa pun.
 *
 * Tujuan:
 *  - Cari row duplicate di tabel tracking_categories (berdasarkan name_parent_category)
 *  - Baca lines_category (JSON) -> ambil semua item_uuid_category
 *  - Hitung pemakaian tiap UUID di tabel relasi (d_bills, item_detail_invoices)
 *  - Tentukan row mana yang AMAN dihapus
 *
 * Kriteria "AMAN DIHAPUS" (status SUBSET):
 *   Semua UUID di row tersebut juga ada di row lain pada grup yang sama.
 *   Karena relasi memakai UUID (bukan id row), menghapus row itu tidak
 *   membuat relasi apa pun menjadi yatim.
 *
 * Row yang tidak dipakai tapi punya UUID yang tidak ada di row lain TIDAK
 * dijadikan kandidat otomatis (bisa jadi itu item baru dari Xero yang
 * belum sempat dipakai), jadi diberi status "cek manual".
 *
 * Jalankan:
 *   php artisan tracking:check-duplicates
 *   php artisan tracking:check-duplicates --detail
 *
 * Kompatibel PHP 7.4 / Laravel 8.
 */
class CheckDuplicateTrackingCategories extends Command
{
    protected $signature = 'tracking:check-duplicates {--detail : Tampilkan detail per item UUID}';

    protected $description = 'Cek (read-only) row duplicate tracking_categories mana yang aman dihapus';

    /** Nama tabel master */
    private $masterTable = 'tracking_categories';

    /**
     * Relasi pemakaian UUID.
     * Key = name_parent_category dalam huruf kecil.
     * Value = daftar [tabel, kolom] yang menyimpan item_uuid_category.
     */
    private $references = [
        'nama paket' => [
            ['d_bills', 'paket_tracking_uuid'],
            ['item_detail_invoices', 'paket_tracking_uuid'],
        ],
        'divisi' => [
            ['d_bills', 'divisi_travel_tracking_uuid'],
            ['item_detail_invoices', 'divisi_travel_tracking_uuid'],
        ],
    ];

    /** Tabel yang discan untuk menampilkan kolom kandidat relasi */
    private $scanTables = ['d_bills', 'item_detail_invoices'];

    public function handle()
    {
        $this->info('=== CEK DUPLICATE ' . $this->masterTable . ' (READ-ONLY, tidak ada data yang dihapus) ===');

        if (!Schema::hasTable($this->masterTable)) {
            $this->error('Tabel ' . $this->masterTable . ' tidak ditemukan.');
            return 1;
        }

        if (!$this->validateReferences()) {
            return 1;
        }

        $this->showCandidateColumns();

        $rows = DB::table($this->masterTable)->orderBy('id')->get();

        // Kelompokkan berdasarkan nama (tanpa peduli huruf besar/kecil & spasi)
        $groups = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string) $row->name_parent_category));
            $groups[$key][] = $row;
        }

        $detail = (bool) $this->option('detail');
        $deleteCandidates = [];
        $needManual = [];

        $labels = [
            'INVALID' => 'JSON TIDAK VALID (cek manual)',
            'EMPTY'   => 'lines_category KOSONG (cek manual)',
            'NOREF'   => 'RELASI BELUM DIISI (cek manual)',
            'UNUSED'  => 'TIDAK DIPAKAI, tapi punya UUID yg tidak ada di row lain (cek manual)',
            'SHARED'  => 'UUID yg dipakai ada juga di row lain, tapi row ini punya UUID unik (cek manual)',
            'USED'    => 'DIPAKAI - JANGAN HAPUS',
        ];

        foreach ($groups as $key => $groupRows) {
            $this->line('');

            if (count($groupRows) < 2) {
                $this->line('[' . $key . '] hanya 1 baris (id ' . $groupRows[0]->id . ') -> tidak duplicate.');
                continue;
            }

            $this->warn('[' . $key . '] ditemukan ' . count($groupRows) . ' baris duplicate');

            $refs = isset($this->references[$key]) ? $this->references[$key] : [];

            // 1) Parse lines_category tiap row
            $parsed = [];
            $uuidRows = []; // uuid => [id row => true]
            foreach ($groupRows as $r) {
                $items = $this->parseItems($r->lines_category);
                if ($items === null) {
                    $parsed[$r->id] = ['valid' => false, 'items' => []];
                    continue;
                }
                $map = [];
                foreach ($items as $it) {
                    $u = strtolower($it['uuid']);
                    $map[$u] = $it['name'];
                    $uuidRows[$u][$r->id] = true;
                }
                $parsed[$r->id] = ['valid' => true, 'items' => $map];
            }

            // 2) Hitung pemakaian UUID di tabel relasi
            $usage = $this->buildUsage(array_keys($uuidRows), $refs);

            // 3) Tentukan status tiap row
            $verdicts = [];
            foreach ($groupRows as $r) {
                $p = $parsed[$r->id];
                $total = count($p['items']);
                $used = 0;
                $usedUnique = 0;
                $unique = 0;
                $refCount = 0;

                foreach ($p['items'] as $u => $name) {
                    $isUnique = (count($uuidRows[$u]) === 1);
                    if ($isUnique) {
                        $unique++;
                    }
                    if (isset($usage[$u])) {
                        $used++;
                        $refCount += $usage[$u]['total'];
                        if ($isUnique) {
                            $usedUnique++;
                        }
                    }
                }

                // Cari row lain yang memuat SEMUA UUID row ini.
                // Jika dua row isinya identik, row dengan id lebih kecil dipertahankan.
                $dominator = null;
                if ($p['valid'] && $total > 0) {
                    foreach ($groupRows as $k) {
                        if ((int) $k->id === (int) $r->id) {
                            continue;
                        }
                        $kp = $parsed[$k->id];
                        if (!$kp['valid']) {
                            continue;
                        }
                        $kTotal = count($kp['items']);
                        if (count(array_diff_key($p['items'], $kp['items'])) !== 0) {
                            continue; // ada UUID di row ini yang tidak ada di row K
                        }
                        if ($kTotal > $total || ($kTotal === $total && (int) $k->id < (int) $r->id)) {
                            if ($dominator === null || $kTotal > count($parsed[$dominator]['items'])) {
                                $dominator = $k->id;
                            }
                        }
                    }
                }

                if (!$p['valid']) {
                    $code = 'INVALID';
                } elseif ($total === 0) {
                    $code = 'EMPTY';
                } elseif ($dominator !== null) {
                    $code = 'SUBSET';
                } elseif (empty($refs)) {
                    $code = 'NOREF';
                } elseif ($used === 0) {
                    $code = 'UNUSED';
                } elseif ($usedUnique === 0) {
                    $code = 'SHARED';
                } else {
                    $code = 'USED';
                }

                $verdicts[$r->id] = [
                    'code'       => $code,
                    'total'      => $total,
                    'used'       => $used,
                    'usedUnique' => $usedUnique,
                    'unique'     => $unique,
                    'refCount'   => $refCount,
                    'dominator'  => $dominator,
                ];
            }

            // 4) Tabel ringkasan
            $tableRows = [];
            foreach ($groupRows as $r) {
                $v = $verdicts[$r->id];

                if ($v['code'] === 'SUBSET') {
                    $status = 'SUBSET: semua UUID ada di row id ' . $v['dominator'] . ' -> KANDIDAT HAPUS';
                    $deleteCandidates[] = (int) $r->id;
                } else {
                    $status = $labels[$v['code']];
                    if ($v['code'] !== 'USED') {
                        $needManual[] = (int) $r->id;
                    }
                }

                $tableRows[] = [
                    $r->id,
                    $r->name_parent_category,
                    isset($r->created_at) ? $r->created_at : '-',
                    isset($r->updated_at) ? $r->updated_at : '-',
                    $v['total'],
                    $v['used'],
                    $v['unique'],
                    $v['usedUnique'],
                    $v['refCount'],
                    $status,
                ];
            }

            $this->table(
                ['id', 'name', 'created_at', 'updated_at', 'jml item', 'item dipakai', 'UUID unik di row ini', 'unik & dipakai', 'jml baris relasi', 'status'],
                $tableRows
            );

            // 5) Info UUID yang sama persis di beberapa row
            $sharedUuids = 0;
            foreach ($uuidRows as $u => $ids) {
                if (count($ids) > 1) {
                    $sharedUuids++;
                }
            }
            if ($sharedUuids > 0) {
                $this->comment($sharedUuids . ' UUID muncul di lebih dari 1 row pada grup ini.');
            }

            // 6) Detail per item (opsional)
            if ($detail) {
                foreach ($groupRows as $r) {
                    $this->line('');
                    $this->line('Detail item row id ' . $r->id . ':');
                    $itemRows = [];
                    foreach ($parsed[$r->id]['items'] as $u => $name) {
                        $itemRows[] = [
                            $u,
                            mb_strimwidth((string) $name, 0, 45, '...'),
                            isset($usage[$u]) ? $usage[$u]['total'] : 0,
                            count($uuidRows[$u]) > 1 ? 'ya (id ' . implode(',', array_keys($uuidRows[$u])) . ')' : 'tidak',
                        ];
                    }
                    $this->table(['uuid', 'nama item', 'dipakai (baris)', 'ada di row lain'], $itemRows);
                }
            }
        }

        // Ringkasan akhir
        $this->line('');
        $this->info('=== RINGKASAN ===');
        if (!empty($deleteCandidates)) {
            $this->info('Kandidat id yang aman dihapus: ' . implode(', ', $deleteCandidates));
            $this->line('SQL (jalankan manual setelah backup): DELETE FROM ' . $this->masterTable . ' WHERE id IN (' . implode(', ', $deleteCandidates) . ');');
        } else {
            $this->line('Tidak ada kandidat hapus otomatis.');
        }
        if (!empty($needManual)) {
            $this->warn('Perlu keputusan manual (id): ' . implode(', ', array_unique($needManual)));
        }
        $this->comment('Backup tabel ' . $this->masterTable . ' dulu sebelum menghapus apa pun.');

        return 0;
    }

    /**
     * Parse kolom lines_category -> list [uuid, name]. Return null jika JSON tidak valid.
     */
    private function parseItems($json)
    {
        $data = json_decode((string) $json, true);

        // Antisipasi JSON yang ter-encode dua kali
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data)) {
            return null;
        }

        // Jika ternyata object tunggal, bukan list
        if (isset($data['item_uuid_category'])) {
            $data = [$data];
        }

        $items = [];
        foreach ($data as $line) {
            if (!is_array($line) || empty($line['item_uuid_category'])) {
                continue;
            }
            $items[] = [
                'uuid' => (string) $line['item_uuid_category'],
                'name' => isset($line['item_name_category']) ? $line['item_name_category'] : '',
            ];
        }

        return $items;
    }

    /**
     * Hitung pemakaian tiap UUID di semua tabel relasi.
     * Return: [uuid(lowercase) => ['total' => n, 'detail' => ['tabel.kolom' => n]]]
     */
    private function buildUsage(array $uuids, array $refs)
    {
        $usage = [];

        if (empty($refs) || empty($uuids)) {
            return $usage;
        }

        foreach ($refs as $ref) {
            $table = $ref[0];
            $col = $ref[1];

            foreach (array_chunk($uuids, 500) as $chunk) {
                $res = DB::table($table)
                    ->select($col . ' as uuid_val', DB::raw('COUNT(*) as total'))
                    ->whereIn($col, $chunk)
                    ->groupBy($col)
                    ->get();

                foreach ($res as $x) {
                    $u = strtolower((string) $x->uuid_val);
                    if (!isset($usage[$u])) {
                        $usage[$u] = ['total' => 0, 'detail' => []];
                    }
                    $usage[$u]['total'] += (int) $x->total;
                    $usage[$u]['detail'][$table . '.' . $col] = (int) $x->total;
                }
            }
        }

        return $usage;
    }

    /**
     * Pastikan semua tabel/kolom relasi ada. Kalau ada yang hilang, STOP
     * (supaya tidak salah menyimpulkan "tidak dipakai").
     */
    private function validateReferences()
    {
        $ok = true;

        foreach ($this->references as $name => $refs) {
            foreach ($refs as $ref) {
                if (!Schema::hasTable($ref[0])) {
                    $this->error('Tabel relasi tidak ditemukan: ' . $ref[0] . ' (untuk ' . $name . ')');
                    $ok = false;
                } elseif (!Schema::hasColumn($ref[0], $ref[1])) {
                    $this->error('Kolom relasi tidak ditemukan: ' . $ref[0] . '.' . $ref[1] . ' (untuk ' . $name . ')');
                    $ok = false;
                }
            }
        }

        return $ok;
    }

    /**
     * Tampilkan kolom yang namanya mengandung tracking/divisi/paket/category
     * untuk membantu memastikan kolom relasi.
     */
    private function showCandidateColumns()
    {
        $this->line('');
        $this->line('Kolom kandidat relasi (nama mengandung tracking/divisi/paket/category):');

        foreach ($this->scanTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->line('  - ' . $table . ': (tabel tidak ada)');
                continue;
            }

            $matches = [];
            foreach (Schema::getColumnListing($table) as $col) {
                if (preg_match('/tracking|divisi|paket|category/i', $col)) {
                    $matches[] = $col;
                }
            }

            $this->line('  - ' . $table . ': ' . (empty($matches) ? '(tidak ada)' : implode(', ', $matches)));
        }
    }
}
