<?php

namespace App\Services;

use App\ConfigRefreshXero;


use App\Models\MasterData\Coa;
use App\Models\MasterData\DataJamaahXero;
use App\Models\Transaction\TransactionAllCoa;
use App\Models\Transaction\TransactionBankTransD;
use App\Models\Transaction\TransactionBankTransP;
use App\Models\Transaction\TransactionNominalBankAccount;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Log;

class XeroBankSyncService
{
    use ConfigRefreshXero, ApiResponse;

    private const PAGE_SIZE = 100;


    private const BASE_CURRENCY = 'IDR';

    private string $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';
    private ?string $tenantId = null;

    /**
     * Sinkronisasi BankTransactions (RECEIVE & SPEND) satu akun bank ke tabel lokal.
     */


    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel('xero_bank_sync')->{$level}('[XeroBankSync] ' . $message, $context);
    }

    private function currencyOf(array $t): array
    {
        $code = strtoupper($t['CurrencyCode'] ?? self::BASE_CURRENCY);

        if ($code === self::BASE_CURRENCY) {
            return [$code, 1.0];
        }

        $rate = (float) ($t['CurrencyRate'] ?? 0);
        if ($rate <= 0) {
            throw new \RuntimeException("CurrencyRate Xero kosong untuk {$code} (transaksi {$t['BankTransactionID']}).");
        }

        // Kurs Xero umumnya berbentuk "unit mata uang asing per 1 mata uang dasar" (SAR per IDR ≈ 0.0002).
        // Dengan dasar IDR, nilai 1 unit asing selalu > 1 IDR, jadi rate < 1 berarti harus dibalik.
        return [$code, round($rate < 1 ? 1 / $rate : $rate, 4)];
    }
    public function sync(string $accountId, ?string $from = null, ?string $to = null): array
    {
        $bankId = \App\Models\MasterData\BankXero::where('account_id', $accountId)->value('id'); // sekali saja
        if (!$bankId) {
            throw new \RuntimeException("BankXero dengan account_id {$accountId} belum ada.");
        }

        $where = 'BankAccount.AccountID==guid("' . $accountId . '") AND Status=="AUTHORISED"'
            . $this->dateFilter($from, $to);

        $page = 1;
        $created = 0;
        $this->log('info', 'Sync dimulai', ['account_id' => $accountId, 'from' => $from, 'to' => $to, 'bank_id' => $bankId]);

        do {
            $data = $this->get('BankTransactions', [
                'where' => $where,
                'order' => 'Date DESC',
                'page' => $page,
                'pageSize' => self::PAGE_SIZE,
                'unitdp' => 4,
            ]);

            $batch = $data['BankTransactions'] ?? [];
            $this->log('info', "Page {$page}: menerima " . count($batch) . ' transaksi dari Xero', ['account_id' => $accountId]);

            try {
                $created += $this->saveBatch($batch, $bankId);
            } catch (\Throwable $e) {
                $this->log('error', "Page {$page}: GAGAL simpan batch (transaksi di-rollback)", [
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
                throw $e;
            }
            $this->log('info', "Page {$page}: batch COMMIT", ['account_id' => $accountId, 'total_created' => $created]);

            $this->progress($accountId, ['status' => 'running', 'page' => $page, 'created' => $created]);
            $page++;
        } while (count($batch) === self::PAGE_SIZE);

        $this->log('info', 'Sync selesai', ['account_id' => $accountId, 'pages' => $page - 1, 'created' => $created]);
        return ['pages' => $page - 1, 'created' => $created];
    }

    /**
     * Simpan satu halaman (maks 100 transaksi) dengan query minimal.
     * Idempotent: aman dijalankan ulang (retry job / sync ulang).
     */
    private function saveBatch(array $batch, int $bankId): int
    {
        $rows = collect($batch)
            ->filter(fn($t) => in_array($t['Type'] ?? null, ['RECEIVE', 'SPEND'], true))
            ->values();

        if ($rows->isEmpty()) {
            return 0;
        }
        $this->log('info', 'saveBatch: ' . $rows->count() . ' transaksi RECEIVE/SPEND akan diproses (dari ' . count($batch) . ' total)');

        $txUuids = $rows->pluck('BankTransactionID')->all();
        $lines = $rows->flatMap(fn($t) => $t['LineItems'] ?? []);
        $lineUuids = $lines->pluck('LineItemID')->filter()->all();

        $coaMap = Coa::whereIn('code', $lines->pluck('AccountCode')->filter()->unique()->all())
            ->pluck('id', 'code')->all();

        // Cek COA di awal: semua kode yang hilang dilaporkan sekaligus, sebelum ada yang ditulis
        $missingCoa = $lines->map(fn($l) => $l['AccountCode'] ?? '(kosong)')
            ->unique()
            ->reject(fn($c) => isset($coaMap[$c]))
            ->values()
            ->all();

        if ($missingCoa) {
            throw new \RuntimeException('Kode akun (COA) belum ada di master: ' . implode(', ', $missingCoa));
        }

        $contactMap = DataJamaahXero::whereIn(
            'uuid_contact',
            $rows->pluck('Contact.ContactID')->filter()->unique()->all()
        )->pluck('id', 'uuid_contact')->all();

        $parentMap = TransactionBankTransP::whereIn('uuid_bank_trans', $txUuids)
            ->pluck('id', 'uuid_bank_trans')->all();

        $lineExists = TransactionBankTransD::whereIn('uuid_detail_trans_bank', $lineUuids)
            ->pluck('uuid_detail_trans_bank')->flip()->all();

        $allCoaExists = TransactionAllCoa::whereIn('uuid_detail', $lineUuids)
            ->pluck('uuid_detail')->flip()->all();

        $nominalExists = TransactionNominalBankAccount::whereIn('payment_uuid', $txUuids)
            ->pluck('payment_uuid')->flip()->all();

        return DB::transaction(function () use ($rows, $bankId, $contactMap, $coaMap, $parentMap, $lineExists, $allCoaExists, $nominalExists) {
            $created = 0;

            foreach ($rows as $t) {
                $uuid = $t['BankTransactionID'];
                $isSpend = $t['Type'] === 'SPEND';
                $total = $t['Total'] ?? 0;
                $contactId = $t['Contact']['ContactID'] ?? null;
                $date = $this->parseXeroDate($t['DateString'] ?? $t['Date'] ?? null);
                $reference = $t['Reference'] ?? null;

                [$currencyCode, $nominalCurr] = $this->currencyOf($t);

                $this->log('debug', 'Proses transaksi', [
                    'uuid' => $uuid,
                    'type' => $t['Type'],
                    'date' => $date,
                    'currency' => $currencyCode,
                    'rate' => $nominalCurr,
                    'total' => $total,
                ]);

                // Header
                if (isset($parentMap[$uuid])) {
                    $parentId = $parentMap[$uuid];
                    $this->log('debug', 'SKIP header (sudah ada)', ['id' => $parentId, 'uuid' => $uuid]);
                } else {
                    $parent = TransactionBankTransP::firstOrCreate(
                        ['uuid_bank_trans' => $uuid],
                        [
                            'uuid_to' => (string) ($contactId ? ($contactMap[$contactId] ?? $contactId) : ''),
                            'date_h' => $date,
                            'reference' => $reference,
                            'is_spend' => $isSpend ? 1 : 0,
                            'bank_id_xero' => $bankId,
                            'status' => 0,
                            'total' => $total,
                        ]
                    );
                    $parentId = $parent->id;
                    if ($parent->wasRecentlyCreated) {
                        $created++;
                        $this->log('debug', 'INSERT header TransactionBankTransP', ['id' => $parentId, 'uuid' => $uuid, 'total' => $total]);
                    }
                }

                // Detail
                foreach ($t['LineItems'] ?? [] as $l) {
                    $lineUuid = $l['LineItemID'] ?? null;
                    if (!$lineUuid) {
                        continue;
                    }

                    $coaId = $coaMap[$l['AccountCode']];   // dijamin ada oleh pre-check di atas
                    $lineAmount = $l['LineAmount'] ?? 0;

                    if (!isset($lineExists[$lineUuid])) {
                        $d = TransactionBankTransD::firstOrCreate(
                            ['uuid_detail_trans_bank' => $lineUuid],
                            [
                                'trans_bank_parent_id' => $parentId,
                                'desc' => $l['Description'] ?? '',
                                'qty' => $l['Quantity'] ?? 0,
                                'unit_price' => $l['UnitAmount'] ?? 0,
                                'account_id_coa' => $coaId,
                                'amount' => $lineAmount,
                            ]
                        );

                        if ($d->wasRecentlyCreated) {
                            $this->log('debug', 'INSERT detail TransactionBankTransD', ['id' => $d->id, 'line_uuid' => $lineUuid, 'coa' => $l['AccountCode'], 'amount' => $lineAmount]);
                        }
                    }

                    if (!isset($allCoaExists[$lineUuid])) {
                        $a = TransactionAllCoa::firstOrCreate(
                            ['uuid_detail' => $lineUuid],
                            [
                                'date_transaction' => $date,
                                'uuid_coa' => $coaId,
                                'reference' => $reference ?? '-',
                                'is_speend' => $isSpend ? 1 : 0,
                                'nominal' => $lineAmount,
                                'base_nominal' => round($lineAmount * $nominalCurr, 4),
                                'code_curr' => $currencyCode,
                                'nominal_currency' => $nominalCurr,
                            ]
                        );

                        if ($a->wasRecentlyCreated) {
                            $this->log('debug', 'INSERT TransactionAllCoa', ['id' => $a->id, 'line_uuid' => $lineUuid, 'nominal' => $lineAmount, 'base_nominal' => round($lineAmount * $nominalCurr, 4)]);
                        }
                    }
                }

                // Nominal per bank
                if (!isset($nominalExists[$uuid])) {
                    $n = TransactionNominalBankAccount::firstOrCreate(
                        ['payment_uuid' => $uuid],
                        [
                            'uuid_bank' => $bankId,
                            'id_parent_bank' => $parentId,
                            'date_transaction' => $date,
                            'reference_detail' => $reference,
                            'nominal_currency' => $nominalCurr,
                            ($isSpend ? 'nominal_spend' : 'nominal_receive') => $total,
                            ($isSpend ? 'total_base_spend' : 'total_base_receive') => round($total * $nominalCurr, 4),
                        ]
                    );

                    if ($n->wasRecentlyCreated) {
                        $this->log('debug', 'INSERT TransactionNominalBankAccount', ['id' => $n->id, 'payment_uuid' => $uuid, 'total' => $total, 'is_spend' => $isSpend]);
                    }
                }
            }

            return $created;
        });
    }

    public function get(string $endpoint, array $query = []): array
    {
        $accessToken = $this->getValidToken()['access_token'];
        $this->tenantId ??= $this->getTenantId($accessToken); // sekali per proses, bukan per request

        $attempts = 0;
        do {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $this->tenantId,
                'Accept' => 'application/json',
            ])->timeout(25)->get($this->xeroBaseUrl . '/' . $endpoint, $query);

            if ($response->status() === 429) {
                sleep((int) $response->header('Retry-After', 5));
                $attempts++;
                continue;
            }

            $response->throw();
            return $response->json();
        } while ($attempts < 3);

        throw new \RuntimeException('Xero rate limit terlampaui');
    }

    public function progress(string $accountId, array $state): void
    {
        Cache::put(
            "xero_bank_sync:{$accountId}",
            $state + ['updated_at' => now()->toDateTimeString()],
            now()->addDay()
        );
    }

    private function dateFilter(?string $from, ?string $to): string
    {
        $clause = '';

        if ($from) {
            [$y, $m, $d] = array_map('intval', explode('-', $from));
            $clause .= " AND Date>=DateTime($y,$m,$d)";
        }
        if ($to) {
            [$y, $m, $d] = array_map('intval', explode('-', $to));
            $clause .= " AND Date<=DateTime($y,$m,$d)";
        }

        return $clause;
    }

    private function parseXeroDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (preg_match('/\/Date\((\d+)/', $value, $m)) {
            return date('Y-m-d', (int) ($m[1] / 1000));
        }

        return substr($value, 0, 10);
    }
}