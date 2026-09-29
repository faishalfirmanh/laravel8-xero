<?php

namespace App\Http\Controllers\Xero;

use App\Http\Repository\MasterData\BankXeroRepo;

use App\Jobs\SyncXeroBankTransactionsJob;
use App\Services\XeroBankSyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Str;
use Validator;
class BankSyncTransController extends Controller
{

    protected $xero;
    public function __construct(XeroBankSyncService $xero)
    {
        $this->xero = $xero;
    }

    public function bankAccounts()
    {
        $data = $this->xero->get('Accounts', [
            'where' => 'Type=="BANK" AND Status=="ACTIVE"',
        ]);

        return response()->json(collect($data['Accounts'] ?? [])->map(fn($a) => [
            'account_id' => $a['AccountID'],
            'name' => $a['Name'],
            'code' => $a['Code'] ?? null,
            'bank_number' => $a['BankAccountNumber'] ?? null,
            'currency' => $a['CurrencyCode'] ?? null,
        ])->values());
    }

    /**
     * POST /xero/bank-accounts/{accountId}/sync?from=2026-01-01&to=2026-09-30
     */
    public function receivedTransactions(Request $request, string $accountId)
    {
        abort_unless(Str::isUuid($accountId), 422, 'accountId tidak valid');

        $v = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);

        SyncXeroBankTransactionsJob::dispatch($accountId, $v['from'] ?? null, $v['to'] ?? null);

        return response()->json([
            'message' => 'Sinkronisasi dijadwalkan',
            'account_id' => $accountId,
        ], 202);
    }

    /**
     * GET /xero/bank-accounts/{accountId}/sync-status
     */
    public function syncStatus(string $accountId)
    {
        return response()->json(Cache::get("xero_bank_sync:{$accountId}", ['status' => 'idle']));
    }
}
