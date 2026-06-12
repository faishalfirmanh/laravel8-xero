<?php

namespace App\Jobs;

use App\ConfigRefreshXero;
use App\Models\ItemsPaketAllFromXero;
use App\Models\MasterData\Coa;
use App\Services\GlobalService; // sesuaikan namespace-mu
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncXeroPaketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ConfigRefreshXero;

    // Retry jika gagal, timeout per eksekusi job
    public int $tries = 3;
    public int $timeout = 600; // 10 menit cukup untuk ribuan item

    private array $tokenData;

    public function __construct(array $tokenData)
    {
        $this->tokenData = $tokenData;
    }

    public function handle(GlobalService $global): void
    {
        $accessToken = $this->tokenData['access_token'];
        $tenantId = $this->getTenantId($accessToken);

        $page = 1;
        $totalSynced = 0;
        $perPage = 100; // Xero default max per page
        $minReqThreshold = 5;  // berhenti jika sisa request menit tinggal segini

        Log::info("[SyncXeroPaketJob] Mulai sync, page pertama...");

        do {
            // --- Fetch satu halaman ---
            $response = $this->fetchPage($accessToken, $tenantId, $page);
            if (!$response || !$response->successful()) {
                Log::error("[SyncXeroPaketJob] Gagal fetch halaman $page, status: " . (optional($response)->status() ?? 'null'));
                break;
            }

            // --- Rate limit check ---
            $minRem = (int) $response->header('X-MinLimit-Remaining');
            $dayRem = (int) $response->header('X-DayLimit-Remaining');
            $global->requestCalculationXero($minRem, $dayRem);

            Log::info("[SyncXeroPaketJob] Page $page | MinRem: $minRem | DayRem: $dayRem");

            if ($minRem <= $minReqThreshold) {
                Log::warning("[SyncXeroPaketJob] Rate limit hampir habis ($minRem tersisa/menit). Berhenti di page $page. Akan dilanjut nanti.");
                // Re-dispatch job untuk melanjutkan dari halaman ini (opsional, tergantung kebutuhan)
                // self::dispatch($this->tokenData)->delay(now()->addMinute());
                break;
            }

            // --- Proses items ---
            $items = $response->json('Items') ?? [];
            $batchItems = [];

            foreach ($items as $value) {
                if (!isset($value['Name']) || trim($value['Name']) === '') {
                    continue;
                }

                if (!self::cekFormatStringPaket($value['Name'])) {
                    continue;
                }

                $batchItems[] = [
                    'uuid_proudct_and_service' => $value['ItemID'],
                    'code' => $value['Code'] ?? null,
                    'nama_paket' => $value['Name'],
                    'purchase_AccountCode' => data_get($value, 'PurchaseDetails.AccountCode', '-'),
                    'sales_AccountCode' => data_get($value, 'SalesDetails.AccountCode', '-'),
                    'total_hari' => self::getTotalHari($value['Name']) ?? 0,
                    'jenis_item' => $global->cekJenisPaketBasePagar($value['Name']),
                    'price_purchase' => data_get($value, 'PurchaseDetails.UnitPrice', 0),
                    'price_sales' => data_get($value, 'SalesDetails.UnitPrice', 0),
                    'desc_salles' => $value['Description'] ?? '',
                    'desc' => $value['PurchaseDescription'] ?? '',
                    'created_at' => now(), // diperlukan upsert jika kolom timestamps manual
                    'updated_at' => now(),
                    'tax_rate_salles' => 0,
                    'tax_rate_purchase' => 0,
                    'account_id_salles' => self::cariAccounIdByCode(data_get($value, 'SalesDetails.AccountCode', null)),
                    'account_id_purchase' => self::cariAccounIdByCode(data_get($value, 'PurchaseDetails.AccountCode', null)),
                ];
            }

            // --- Upsert batch per halaman (lebih aman memori) ---
            if (!empty($batchItems)) {
                // ✅ Perbaikan: semua kolom diupdate saat upsert, bukan hanya updated_at
                ItemsPaketAllFromXero::upsert($batchItems, ['uuid_proudct_and_service'], [
                    'code',
                    'nama_paket',
                    'purchase_AccountCode',
                    'sales_AccountCode',
                    'total_hari',
                    'jenis_item',
                    'price_purchase',
                    'price_sales',
                    'desc_salles',
                    'desc',
                    'created_at',
                    'updated_at',
                    'tax_rate_salles',
                    'tax_rate_purchase',
                    'account_id_salles',
                    'account_id_purchase'
                ]);

                $totalSynced += count($batchItems);
            }

            Log::info("[SyncXeroPaketJob] Page $page selesai. Tersimpan halaman ini: " . count($batchItems));

            // --- Cek apakah masih ada halaman berikutnya ---
            // Xero: jika items yang dikembalikan < perPage, berarti halaman terakhir
            $hasNextPage = count($items) === $perPage;
            $page++;

            // Throttle ringan agar tidak blast API (opsional, Xero allow 60 req/min)
            if ($hasNextPage) {
                usleep(200_000); // 200ms jeda antar halaman
            }

        } while ($hasNextPage);

        $endTime = Carbon::now()->format('d-m-Y H.i');
        Log::info("[SyncXeroPaketJob] Selesai sync paket $endTime. Total tersimpan: $totalSynced");
    }

    public function cariAccounIdByCode($code)
    {
        $cek = Coa::query()->where('code', $code)->first();
        if ($cek) {
            return $cek->id;
        } else {
            return null;
        }
    }

    private function fetchPage(string $accessToken, string $tenantId, int $page)
    {
        try {
            return Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])
                ->timeout(20)
                ->get('https://api.xero.com/api.xro/2.0/Items', [
                    'order' => 'Code ASC',
                    'page' => $page,
                ]);
        } catch (\Exception $e) {
            Log::error("[SyncXeroPaketJob] Exception fetch page $page: " . $e->getMessage());
            return null;
        }
    }

    // Pindahkan atau reuse dari controller/service-mu
    private static function cekFormatStringPaket(string $name): bool
    {
        // sesuaikan logic aslimu
        return true; // placeholder
    }

    private static function getTotalHari(string $name): ?int
    {
        // sesuaikan logic aslimu
        return null; // placeholder
    }


}