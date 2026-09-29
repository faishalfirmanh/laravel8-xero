<?php

namespace App\Http\Controllers\Xero;

use App\Http\Repository\MasterData\BankXeroRepo;
use App\Models\MasterData\BankXero;
use App\Models\MasterData\Coa;
use App\Models\MasterData\DataJamaahXero;
use App\Models\Transaction\TransactionBankTransD;
use App\Models\Transaction\TransactionBankTransP;
use App\Models\Transaction\TransactionNominalBankAccount;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Validator;
class BankController extends Controller
{

    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';

    use ConfigRefreshXero, ApiResponse;

    private $repo_bank_xero_local;
    public function __construct(BankXeroRepo $bankXeroRepo)
    {
        $this->repo_bank_xero_local = $bankXeroRepo;
    }

    private function getHeaders()
    {
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }
        //dd($tokenData);
        return [
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function getAllBank(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_sync' => 'nullable|numeric|in:1,0',
        ]);

        // Changed 500 to 422 for standard Validation Error response
        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        try {
            // 1. Ambil Token Valid
            $tokenData = $this->getValidToken();

            if (!$tokenData) {
                return response()->json(['status' => 'error', 'message' => 'Token Invalid/Expired'], 401);
            }

            // 2. Tembak API Xero Accounts
            $response = Http::withHeaders($this->getHeaders())->get('https://api.xero.com/api.xro/2.0/Accounts', [
                'where' => 'Type=="BANK"'
            ]);

            // 3. Cek Error dari Xero
            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal ambil data Bank',
                    'detail' => $response->json()
                ], $response->status());
            }

            // Fallback to empty array if 'Accounts' is missing

            $responseData = $response->json();
            $allAccounts = $responseData['Accounts'] ?? [];

            $savedCount = 0;

            // Use strict comparison or cast to boolean/int if relying on '1' vs 1

            if ($request->is_sync == 1) {
                foreach ($allAccounts as $index => $acc) {

                    // 2. BULLETPROOF CHECK: Use array_key_exists or isset BEFORE reading the key
                    if (!isset($acc['AccountID']) || !isset($acc['Name'])) {
                        // Log the bad data so you can see exactly what Xero sent that caused the crash
                        \Log::warning("Xero Sync Skipped Index {$index}: Missing AccountID or Name", ['raw_data' => $acc]);
                        continue;
                    }

                    // 3. SAFE ASSIGNMENT: Since we passed the isset() check above, 'account_id' is safe.
                    // We use ?? for all other fields just in case Xero leaves them out.
                    $param_save = [
                        'account_id' => $acc['AccountID'],
                        'code' => $acc['Code'] ?? '-',
                        'name' => $acc['Name'],
                        'status' => (isset($acc['Status']) && $acc['Status'] === 'ACTIVE') ? 1 : 0,
                        'created_by' => auth()->id() ?? 0,
                        'type' => $acc['Type'] ?? 'BANK',
                        'currency_code' => $acc['CurrencyCode'] ?? null,
                        'account_number' => $acc['BankAccountNumber'] ?? '-',
                    ];

                    $this->repo_bank_xero_local->firstCreate($param_save);
                    $savedCount++;
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Berhasil sinkronisasi data Bank dari Xero',
                    'total_fetched' => count($allAccounts),
                    'total_saved' => $savedCount,
                    'data' => $this->repo_bank_xero_local->getAllDataNoLimit(),
                ]);
            }

            // Return raw data if not syncing
            return response()->json([
                'status' => 'success',
                'total_banks' => count($allAccounts),
                'data' => $allAccounts
            ]);

        } catch (\Exception $e) {
            // Good practice: log the actual error in Laravel so you can debug it later
            \Log::error('Xero Sync Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server saat sinkronisasi Xero.',
                $e->getMessage()
                // Don't expose $e->getMessage() to the frontend in production to prevent leaking system paths/SQL errors
            ], 500);
        }
    }

    public function postBankOverPayment()
    {
        $payloadOverpayment = [
            "BankTransactions" => [
                [
                    "Type" => "RECEIVE-OVERPAYMENT",
                    "Contact" => ["ContactID" => '31bac9bb-d7f8-4dfe-ac10-afdfed2d195f'],
                    "BankAccount" => ["AccountID" => '23eda3ac-02dd-466e-9521-ba62feff6de7'],
                    "Date" => '2025-12-10',
                    "Reference" => "referensi",
                    "LineItems" => [
                        [
                            "Description" => "Overpayment pada Invoice " . " testing bbbbbb",
                            "UnitAmount" => 401122,
                            // AccountCode kosongkan agar Xero otomatis pakai Accounts Receivable / AP
                            // Atau isi dengan kode akun Liability (Hutang ke Customer) jika ada
                        ]
                    ]
                ]
            ]
        ];

        try {
            $resOver = Http::withHeaders($this->getHeaders())
                ->put($this->xeroBaseUrl . '/BankTransactions', $payloadOverpayment);
            // Cek Error
            if ($resOver->failed()) {
                Log::error("Gagal Restore Overpayment: " . $resOver->body());

                // PERBAIKAN 1: Return JSON error yang rapi agar terbaca di Postman/Frontend
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal kirim ke Xero',
                    'xero_response' => $resOver->json() // Tampilkan detail error Xero
                ], $resOver->status());
            }

            // Sukses
            Log::info("Sukses Restore Overpayment");

            return response()->json([
                'status' => 'success',
                'data' => $resOver->json()
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    private function xeroGet(string $endpoint, array $query = []): array
    {
        $tokenData = $this->getValidToken(); // sesuaikan dengan cara Anda menyimpan token
        $accessToken = $tokenData['access_token'];
        $tenantId = $this->getTenantId($accessToken);

        $attempts = 0;
        do {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
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

    /**
     * Daftar akun bank (untuk pilihan dropdown / mendapatkan AccountID).
     * GET /xero/bank-accounts
     */
    public function bankAccounts()
    {
        $data = $this->xeroGet('Accounts', [
            'where' => 'Type=="BANK" AND Status=="ACTIVE"',
        ]);

        $accounts = collect($data['Accounts'] ?? [])->map(fn($a) => [
            'account_id' => $a['AccountID'],
            'name' => $a['Name'],
            'code' => $a['Code'] ?? null,
            'bank_number' => $a['BankAccountNumber'] ?? null,
            'currency' => $a['CurrencyCode'] ?? null,
        ])->values();

        return response()->json($accounts);
    }

    /**
     * Semua transaksi uang masuk pada satu akun bank.
     * GET /xero/bank-accounts/{accountId}/received?from=2026-01-01&to=2026-09-30
     */
    public function receivedTransactions(Request $request, string $accountId)
    {
        $from = $request->query('from'); // Y-m-d, opsional
        $to = $request->query('to');   // Y-m-d, opsional

        $bankTransactions = $this->fetchBankTransactions($accountId, $from, $to);
        //$payments = $this->fetchInvoicePayments($accountId, $from, $to);

        $result = $bankTransactions
            //->concat($payments)
            ->sortByDesc('date')
            ->values();

        return response()->json([
            'account_id' => $accountId,
            'count' => $result->count(),
            'total' => $result->sum('total'),
            'data' => $result,
        ]);
    }

    /**
     * BankTransactions bertipe RECEIVE* (termasuk line items karena pakai paging).
     */
    private function fetchBankTransactions(string $accountId, ?string $from, ?string $to)
    {
        $where = 'BankAccount.AccountID==guid("' . $accountId . '")'
            //. ' AND (Type=="RECEIVE" OR Type=="RECEIVE-OVERPAYMENT" OR Type=="RECEIVE-PREPAYMENT")'
            . ' AND Status!="DELETED"';

        $where .= $this->dateFilter($from, $to);

        $items = collect();
        $page = 1;

        do {
            $data = $this->xeroGet('BankTransactions', [
                'where' => $where,
                'order' => 'Date DESC',
                'page' => $page,
                'pageSize' => 100,
                'unitdp' => 4,
            ]);

            $batch = $data['BankTransactions'] ?? [];

            foreach ($batch as $t) {
                $items->push([
                    'source' => 'bank_transaction',
                    'id' => $t['BankTransactionID'],
                    'type' => $t['Type'],
                    'status' => $t['Status'],
                    'date' => $this->parseXeroDate($t['DateString'] ?? $t['Date'] ?? null),
                    'reference' => $t['Reference'] ?? null,
                    'contact' => $t['Contact']['Name'] ?? null,
                    'contact_id' => $t['Contact']['ContactID'] ?? null,
                    'bank_account' => $t['BankAccount']['Name'] ?? null,
                    'currency' => $t['CurrencyCode'] ?? null,
                    'currency_rate' => $t['CurrencyRate'] ?? 1,
                    'sub_total' => $t['SubTotal'] ?? 0,
                    'total_tax' => $t['TotalTax'] ?? 0,
                    'total' => $t['Total'] ?? 0,
                    'is_reconciled' => $t['IsReconciled'] ?? false,
                    'invoice_number' => null,
                    'line_items' => collect($t['LineItems'] ?? [])->map(fn($l) => [
                        'uuid_details_line' => $l['LineItemID'] ?? null,
                        'description' => $l['Description'] ?? null,
                        'quantity' => $l['Quantity'] ?? null,
                        'unit_amount' => $l['UnitAmount'] ?? null,
                        'account_code' => $l['AccountCode'] ?? null,
                        'tax_type' => $l['TaxType'] ?? null,
                        'line_amount' => $l['LineAmount'] ?? null,
                        'tracking_kategory' => collect($l['Tracking'] ?? [])->map(fn($tr) => [
                            'category_id' => $tr['TrackingCategoryID'] ?? null,
                            'category_name' => $tr['Name'] ?? null,
                            'option_id' => $tr['TrackingOptionID'] ?? null,
                            'option_name' => $tr['Option'] ?? null,
                        ])->all(),
                    ])->all(),
                ]);
                // $cariContactk = $t['Contact']['ContactID'] ? DataJamaahXero::where(['uuid_contact' => $t['Contact']['ContactID']])->first()->id : NULL;
                // if ($t['Type'] == 'RECEIVE' || $t['Type' == 'SPEND']) {
                //     $cariBank = BankXero::where('account_id', $accountId)->value('id');
                //     $savePbank = TransactionBankTransP::firstOrCreate(
                //         ['uuid_bank_trans'],
                //         [
                //             'uuid_bank_trans' => $t['BankTransactionID'],
                //             'uuid_to' => $cariContactk,
                //             'date_h' => $this->parseXeroDate($t['DateString'] ?? $t['Date'] ?? null),
                //             'reference' => $t['Reference'] ?? null,
                //             'is_spend' => $t['Type'] == 'RECEIVE' ? 0 : 1,
                //             'bank_id_xero' => $cariBank,
                //             'status' => 0,
                //             'total' => $t['Total'] ?? 0,
                //         ]
                //     );
                //     TransactionBankTransD::firstOrCreate(['uuid_detail_trans_bank'], [
                //         'trans_bank_parent_id' => $savePbank->id,
                //         'desc' => $l['Description'],
                //         'qty' => $l['Quantity'] ?? null,
                //         'unit_price' => $l['UnitAmount'] ?? null,
                //         'account_id_code' => Coa::where('code', $l['AccountCode'])->value('id'),
                //         'amount' => $l['LineAmount'] ?? null,
                //         'uuid_detail_trans_bank' => $l['LineItemID'] ?? null,
                //     ]);

                //     $cekSpendOrReceive =  $t['Type' == 'SPEND'] ? 'nominal_spend'=>$t['Total'] : 'nominal_receive'=>$t['Total'];
                //     $cekSpenReceiveBase =  $t['Type' == 'SPEND'] ? 'total_base_spend'=>$t['Total'] : 'total_base_receive'=>$t['Total'];
                //     TransactionNominalBankAccount::firstOrCreate(['payment_uuid'],
                //     ['payment_uuid'=>,'uuid_bank'=>$cariBank,$cekSpenReceiveBase ,'id_parent_bank'=>$savePbank->id,
                // 'reference_detail'=>$t['Reference']]);
                // }

            }
            $page++;
        } while (count($batch) === 100);

        return $items;
    }


    /**
     * Pembayaran invoice (ACCREC) yang masuk ke akun bank tersebut.
     */
    private function fetchInvoicePayments(string $accountId, ?string $from, ?string $to)
    {
        $where = 'Account.AccountID==guid("' . $accountId . '")'
            . ' AND PaymentType=="ACCRECPAYMENT"'
            . ' AND Status=="AUTHORISED"';

        $where .= $this->dateFilter($from, $to);

        $items = collect();
        $page = 1;

        do {
            $data = $this->xeroGet('Payments', [
                'where' => $where,
                'order' => 'Date DESC',
                'page' => $page,
                'pageSize' => 100,
            ]);

            $batch = $data['Payments'] ?? [];

            foreach ($batch as $p) {
                $items->push([
                    'source' => 'invoice_payment',
                    'id' => $p['PaymentID'],
                    'type' => $p['PaymentType'],
                    'status' => $p['Status'],
                    'date' => $this->parseXeroDate($p['DateString'] ?? $p['Date'] ?? null),
                    'reference' => $p['Reference'] ?? null,
                    'contact' => $p['Invoice']['Contact']['Name'] ?? null,
                    'contact_id' => $p['Invoice']['Contact']['ContactID'] ?? null,
                    'bank_account' => $p['Account']['Name'] ?? null,
                    'currency' => $p['Invoice']['CurrencyCode'] ?? null,
                    'currency_rate' => $p['CurrencyRate'] ?? 1,
                    'sub_total' => $p['Amount'] ?? 0,
                    'total_tax' => 0,
                    'total' => $p['Amount'] ?? 0,
                    'is_reconciled' => $p['IsReconciled'] ?? false,
                    'invoice_id' => $p['Invoice']['InvoiceID'] ?? null,
                    'invoice_number' => $p['Invoice']['InvoiceNumber'] ?? null,
                    'line_items' => [],
                ]);
            }

            $page++;
        } while (count($batch) === 100);

        return $items;
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

        // Format lama Xero: /Date(1719792000000+0000)/
        if (preg_match('/\/Date\((\d+)/', $value, $m)) {
            return date('Y-m-d', (int) ($m[1] / 1000));
        }

        return substr($value, 0, 10);
    }
}
