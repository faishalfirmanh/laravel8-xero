<?php

namespace App\Http\Controllers\Transaction\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Repository\Expenses\PODBillRepository;
use App\Http\Repository\Expenses\POPBillRepository;
use App\Http\Repository\LogHistoryRepository;
use App\Http\Repository\MasterData\CoaRepo;
use App\Http\Repository\Transaction\TransBankRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use Illuminate\Http\Request;

use App\Http\Repository\Expenses\DPackageExpensesRepository;
use App\Http\Repository\MasterData\Finance\ItemPaketAllXeroRepo;
use App\Http\Repository\MasterData\Finance\InvoiceAllXeroRepo;
use App\Http\Repository\Expenses\DInvPackageExpensesRepository;
use App\Http\Repository\Expenses\PackageExpensesRepository;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\File;

use Intervention\Image\Facades\Image;
use Cache;

class BillXeroController extends Controller
{
    //
    protected $repo, $repo_detail, $service_global, $repo_all_trans, $repo_trans_bill, $repo_coa, $repo_log;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        POPBillRepository $repo,
        PODBillRepository $repo_detail,
        GlobalService $service_global,
        TransCoaRepo $repo_all_trans,
        TransBankRepo $repo_trans_bill,
        CoaRepo $repo_coa,
        LogHistoryRepository $repo_log
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->service_global = $service_global;
        $this->repo_all_trans = $repo_all_trans;
        $this->repo_trans_bill = $repo_trans_bill;
        $this->repo_coa = $repo_coa;
        $this->repo_log = $repo_log;
    }


    public function getLogWeb(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipe' => 'required|string|in:bill,inv',
            'id' => 'required|integer',//id parent inv / bills
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $cek_tipe = $request->tipe == 'bill' ? ['bills_id' => $request->id] : ['salles_inv_id ' => $request->id];
        $getData = $this->repo_log->whereData($cek_tipe)->get();
        return $this->autoResponse($getData);

    }

    //used
    public function getAllPaginate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'kolom_name' => 'required|string',
            'limit' => 'required|integer',
            'status' => 'required|integer|between:0,3'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $where = $request->status != 3 ? ['status' => $request->status] : [];


        $relations = ['getContactFrom', 'getDetail'];

        // DEFINISIKAN KOLOM PENCARIAN (TABEL UTAMA + RELASI)
        $search_columns = [
            // 1. Kolom di Tabel Utama
            'reference',
            'date_req' => 'date',
            'due_date' => 'date',
            // 2. Kolom di Tabel Relasi (Format: 'NamaRelasi' => ['kolom1', 'kolom2'])
            'getContactFrom' => ['full_name'],
            'subtotal',
            'total',
            'nominal_due'
        ];

        if ($request->keyword) {
            $data = $this->repo->searchDataMultiColumn(
                $where,
                $request->limit, // Menggunakan limit dari request, bukan manual 10
                $search_columns,
                $request->keyword,
                $relations
            );
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'id', 'DESC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function getImageDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bill_id' => 'required|exists:p_bills,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 400);
        }

        try {
            $billId = $request->input('bill_id');
            $directory = public_path('uploads/images/purchase_bill');

            // Jika folder belum ada, berarti belum ada gambar sama sekali
            if (!File::exists($directory)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ], 200);
            }
            $pattern = $directory . '/*_' . $billId . '.webp';
            $matchedFiles = glob($pattern);

            $images = [];

            if ($matchedFiles) {
                foreach ($matchedFiles as $file) {
                    $filename = basename($file);

                    $images[] = [
                        'name' => $filename,
                        'size' => filesize($file), // Dropzone butuh ukuran file (bytes)
                        'url' => url('uploads/images/purchase_bill/' . $filename) // Full path URL
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $images
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal mengambil data gambar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 400);
        }

        try {
            $filename = $request->input('file_name');

            // Keamanan tambahan: Cegah directory traversal attack (misal nama file '../gambar.webp')
            if (preg_match('/\.\./', $filename)) {
                return response()->json(['error' => 'Nama file tidak valid.'], 400);
            }

            $filePath = public_path('uploads/images/purchase_bill/' . $filename);

            // Cek apakah file fisik ada, lalu hapus
            if (File::exists($filePath)) {
                File::delete($filePath);

                return response()->json([
                    'success' => true,
                    'message' => 'Gambar berhasil dihapus dari server.'
                ], 200);
            }

            return response()->json(['error' => 'Gambar tidak ditemukan di server.'], 404);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan server: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadMultiple(Request $request)
    {
        // 1. Validasi Input Gambar (Pastikan 'file' divalidasi sebagai array)
        $validator = Validator::make($request->all(), [
            'file' => 'required|array',
            'file.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            'bill_id' => 'required|integer|exists:p_bills,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 400);
        }


        try {
            $files = $request->file('file'); // Ini sekarang adalah ARRAY dari file
            $invoiceId = $request->input('bill_id');

            // 2. Siapkan Path
            $destinationPath = public_path('uploads/images/purchase_bill');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $uploadedFilesData = [];

            // 3. LOOPING UNTUK SETIAP FILE GAMBAR
            foreach ($files as $index => $file) {

                $img = Image::make($file->getRealPath());

                // Resize jika resolusi terlalu besar
                if ($img->width() > 1200) {
                    $img->resize(1200, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }

                $quality = 90;
                $targetSize = 90 * 1024; // 90 KB

                // Encode awal
                $encodedData = $img->encode('webp', $quality);

                // Looping kompresi untuk target 90KB
                while (strlen($encodedData) > $targetSize && $quality > 10) {
                    $quality -= 10;
                    $encodedData = $img->encode('webp', $quality);
                }

                // Resolusi darurat jika masih > 90KB
                if (strlen($encodedData) > $targetSize) {
                    $img->resize($img->width() * 0.7, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $encodedData = $img->encode('webp', 40);
                }

                $invNumber = $this->repo->whereData(['id' => $invoiceId])->first();

                // Penamaan file (Gunakan uniqid agar nama tidak bentrok di dalam loop)
                $filename = $invNumber->reference . '_' . uniqid() . '_' . $invoiceId . '.webp';

                // Simpan File
                file_put_contents($destinationPath . '/' . $filename, $encodedData);

                // Hitung ukuran akhir
                $finalSizeKb = round(filesize($destinationPath . '/' . $filename) / 1024, 2);

                // Simpan data file yang berhasil diproses ke array
                $uploadedFilesData[] = [
                    'file_name' => $filename,
                    'file_url' => url('uploads/images/purchase_bill/' . $filename),
                    'final_size' => $finalSizeKb . ' KB'
                ];
            }

            // Kembalikan Response Berisi Array Data Gambar
            return response()->json([
                'success' => true,
                'message' => 'Semua gambar berhasil diupload.',
                'data' => $uploadedFilesData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal memproses gambar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'uuid_bank' => 'required|integer|exists:bank_xeros,id',
            'nominal_spend' => 'required|integer|min:1',
            'reference_detail' => 'required|string',
            'date_transaction' => 'required|date',
            'id_parent_bill' => 'required|integer|exists:p_bills,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        DB::beginTransaction();
        try {
            $findData = $this->repo->whereData(['id' => $request->id_parent_bill])
                ->lockForUpdate()
                ->first();

            if (!$findData) {
                DB::rollBack();
                return $this->error('Tagihan tidak ditemukan', 404);
            }

            $sisaTagihan = $findData->nominal_due; // asumsi: nominal_due = sisa belum dibayar, cek lagi ke skema DB kamu

            if ($request->nominal_spend > $findData->total) {
                DB::rollBack();
                return $this->error("Nominal melebihi total tagihan (total: {$findData->total})", 400);
            }

            if ($request->nominal_spend > $sisaTagihan) {
                DB::rollBack();
                return $this->error("Nominal melebihi sisa tagihan yang belum dibayar (sisa: {$sisaTagihan})", 400);
            }

            $request->merge([
                'created_by' => $request->user_login->id,
                'nominal_transfer' => 0,
                'nominal_receive' => 0,
            ]);

            $rate = (float) $findData->nominal_currency;
            $nominalSpendBase = ceil($request->nominal_spend * $rate);

            $nominal_paid_final = $findData->nominal_paid + $request->nominal_spend;
            $nominal_due_final = $findData->total - $nominal_paid_final;

            $nominal_paid_base_final = $findData->nominal_paid_base + $nominalSpendBase;
            $nominal_due_base_final = $findData->total_base - $nominal_paid_base_final;

            $cek_status = $nominal_due_final <= 0 ? 2 : 1;


            $param_bill_save = [
                'nominal_paid' => $nominal_paid_final,
                'nominal_due' => $nominal_due_final,
                'nominal_paid_base' => $nominal_paid_base_final,
                'nominal_due_base' => $nominal_due_base_final,
                'status' => $cek_status,
            ];

            $this->repo->CreateOrUpdate($param_bill_save, $request->id_parent_bill);
            $request->merge([
                'nominal_currency' => $rate,
                'total_base_spend' => $nominalSpendBase
            ]);
            $saveP = $this->repo_trans_bill->CreateOrUpdate($request->all(), null);

            $logMessage = $request->user_login->name . ' membuat pembayaran bills ' . $saveP->name_contact .
                " sebesar " . $request->nominal_spend . " pada bank " . $saveP->name_bank;

            $this->service_global->saveLogHistory(
                $request->user_login->id,
                $logMessage,
                $request->userAgent(),
                $request->ip(),
                null,
                $request->id_parent_bill
            );

            DB::commit();
            return $this->autoResponse($saveP);
        } catch (\Throwable $th) {
            DB::rollBack();
            \Log::error('storePayment error: ' . $th->getMessage());
            return $this->error('Gagal memproses pembayaran', 400);
        }
    }


    private function getRates()
    {
        // Cache selama 60 menit agar hemat kuota API dan loading cepat
        return Cache::remember('currency_rates', 60 * 60, function () {
            $apiKey = 'f759b7cefeb24896bc934f6a01c498a1';

            $response = Http::get("https://api.currencyfreaks.com/v2.0/rates/latest", [
                'apikey' => $apiKey,
                'symbols' => 'IDR,SAR,USD'
            ]);

            if ($response->successful()) {
                return $response->json()['rates'];
            }

            return null; // Handle jika error
        });
    }


    private function getRateToIdr(string $currencyCode): float
    {
        $currencyCode = strtoupper($currencyCode);

        if ($currencyCode === 'IDR') {
            return 1.0;
        }

        $rates = $this->getRates();
        if (!$rates || empty($rates['IDR']) || empty($rates[$currencyCode])) {
            throw new \RuntimeException("Gagal mengambil rate untuk currency: {$currencyCode}");
        }

        return floatval($rates['IDR']) / floatval($rates[$currencyCode]); // 1 unit currency = X IDR
    }
    public function idrToSar($nominal)
    {
        // Validasi input

        $amountRp = $nominal;
        $rates = $this->getRates();

        if (!$rates)
            return response()->json(['error' => 'Gagal ambil rate'], 500);

        $rateIDR = floatval($rates['IDR']);
        $rateSAR = floatval($rates['SAR']);

        $result = ($amountRp / $rateIDR) * $rateSAR;

        return round($result, 2);
    }

    public function sarToIdr($amount)
    {
        $amountSar = $amount;
        $rates = $this->getRates();

        if (!$rates)
            return response()->json(['error' => 'Gagal ambil rate'], 500);

        $rateIDR = floatval($rates['IDR']);
        $rateSAR = floatval($rates['SAR']);
        $result = ($amountSar / $rateSAR) * $rateIDR;
        return round($result, 2);
    }

    //belm suppor mutly currency
    public function storeParent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'uuid_from' => 'required|string',
            'date_req' => 'required|date',
            'due_date' => 'required|date',
            'reference' => 'required|string',
            'currency' => 'required|string',
            'account_id' => 'required|array|min:1',
            'action_save' => 'required|integer|between:0,2',

            'item_code' => 'nullable|array',
            'desc' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'unit_price' => 'required|array|min:1',
            'paket_tracking_uuid' => 'nullable|array',
            'divisi_travel_tracking_uuid' => 'nullable|array',
            'id_detail' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $isUpdate = !empty($request->id);
        $oldParent = $isUpdate ? $this->repo->whereData(['id' => $request->id])->first() : null;

        $currencyChanged = $isUpdate && $oldParent && $oldParent->currency !== $request->currency;

        $cek_nominal_currency = ($isUpdate && $oldParent && !$currencyChanged)
            ? $oldParent->nominal_currency
            : $this->getRateToIdr($request->currency);

        // Gunakan merge agar field ini terbaca dengan baik saat request->except() atau validasi lanjutan
        $request->merge([
            'status' => $request->action_save, // 0->draft, 1/2->approve
            'reference' => strtolower($request->reference),
            'nominal_currency' => $cek_nominal_currency,
            'created_by' => $request->user_login->id
        ]);


        DB::beginTransaction();
        try {

            $oldDetails = $isUpdate
                ? $this->repo_detail->whereData(['bills_parent_id' => $request->id])->get()->keyBy('id')
                : collect();
            //untuk log
            $coaIdsInvolved = collect($oldDetails)->pluck('account_id_coa')
                ->merge($request->account_id)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $coaNames = !empty($coaIdsInvolved)
                ? $this->repo_coa->wherenDataIn('id', $coaIdsInvolved)->pluck('name', 'id')->toArray()
                : [];
            // 1. Save Parent
            $saveP = $this->repo->CreateOrUpdate(
                $request->except(['item_code', 'account_id', 'desc', 'qty', 'unit_price', 'tax_rate', 'nama_paket', 'divisi', 'id_detail', 'action_save']),
                $request->id
            );
            $deleted_array = [];
            // 2. Hapus Detail yang Dibuang (Lakukan DI LUAR LOOP)
            // Pastikan kita hanya mengecek jika ini adalah proses Update (id tidak null)
            if ($saveP->id) {
                $allDetailIds = $this->repo_detail->whereData(['bills_parent_id' => $saveP->id])->pluck('id')->toArray();

                // Hindari error jika $request->id_detail kosong/null
                $providedDetailIds = $request->id_detail ? array_filter($request->id_detail) : [];
                $deleted_array = array_diff($allDetailIds, $providedDetailIds);

                if (!empty($deleted_array)) {
                    // Asumsi wherenDataIn adalah fungsi custom repository Anda (mirip whereIn eloquent)
                    $deletedUuids = $this->repo_detail->wherenDataIn('id', $deleted_array)->pluck('uuid_detail')->toArray();
                    // B. Hapus data di tabel all_trans berdasarkan uuid_detail tersebut
                    if (!empty($deletedUuids)) {
                        // Asumsi repo_all_trans juga memiliki fungsi wherenDataIn
                        $this->repo_all_trans->wherenDataIn('uuid_detail', $deletedUuids)->delete();
                    }
                    $this->repo_detail->wherenDataIn('id', $deleted_array)->delete();
                }
            }


            $detailChangeLogs = [];

            // 3. Save Details (Create / Update)
            foreach ($request->account_id as $key => $accountId) {
                $detailId = $request->id_detail[$key] ?? null;
                $amountForeign = ($request->qty[$key] ?? 0) * ($request->unit_price[$key] ?? 0);


                $detailData = [
                    'bills_parent_id' => $saveP->id,
                    'account_id_coa' => $accountId,
                    'item_code' => $request->item_code[$key] ?? null,
                    'desc' => $request->desc[$key] ?? null,
                    'qty' => $request->qty[$key] ?? 0,
                    'unit_price' => $request->unit_price[$key] ?? 0,
                    'amount' => $amountForeign,                              // tetap simpan versi mata uang asli
                    'total_base' => ceil($amountForeign * $cek_nominal_currency),  // BARU — dipakai utk GL
                    'paket_tracking_uuid' => $request->paket_tracking_uuid[$key] ?? null,
                    'divisi_travel_tracking_uuid' => $request->divisi_travel_tracking_uuid[$key] ?? null,
                ];

                // FIX: Hanya generate UUID_DETAIL jika ini adalah baris baru (bukan edit)
                if (empty($detailId)) {
                    $detailData['uuid_detail'] = $this->service_global->generateUniqueString();
                }


                //baru untuk catat log
                if (!empty($detailId) && $oldDetails->has($detailId)) {
                    $diffText = $this->diffBillDetailRow($oldDetails->get($detailId), $detailData, $coaNames);
                    if ($diffText !== '') {
                        $detailChangeLogs[] = "Item '{$detailData['desc']}' diubah ({$diffText})";
                    }
                } elseif (empty($detailId)) {
                    $detailChangeLogs[] = "Item '{$detailData['desc']}' ditambahkan (Qty: {$detailData['qty']}, Harga: {$detailData['unit_price']})";
                }

                // Create atau Update Detail
                $save_d = $this->repo_detail->CreateOrUpdate($detailData, $detailId);

                // 4. Manajemen Transaksi (Jika approve / action_save != 0)
                if ($request->action_save != 0) {

                    $cek_create_trans = $this->repo_all_trans->whereData([
                        'reference' => $request->reference, // Sudah di-strtolower via merge
                        'uuid_coa' => $accountId,
                        'uuid_detail' => $save_d->uuid_detail
                    ])->first();

                    if ($cek_create_trans) {
                        // FIX: Jika transaksi sudah ada, update nominal menggunakan data terbaru dari $save_d
                        $cek_create_trans->is_speend = true;
                        $cek_create_trans->nominal = $save_d->amount; //$save_d->total_base;// $save_d->amount;
                        $cek_create_trans->code_curr = $request->currency;
                        $cek_create_trans->nominal_currency = $cek_nominal_currency;
                        $cek_create_trans->base_nominal = $save_d->total_base;
                        $cek_create_trans->save();
                    } else {
                        // FIX: uuid_detail harus disamakan dengan punya tabel detail ($save_d->uuid_detail), bukan di-generate ulang
                        $data_trans_create = [
                            'date_transaction' => $request->date_req,
                            'uuid_coa' => $accountId,
                            'reference' => $request->reference,
                            'is_speend' => true,
                            'nominal' => $save_d->amount,//amount,//abs((int) $save_d->amount),//auto positif
                            'created_by' => $request->user_login->id, // Pastikan user_login dilampirkan via middleware
                            'uuid_detail' => $save_d->uuid_detail,
                            'code_curr' => $request->currency,
                            'nominal_currency' => $cek_nominal_currency,
                            'base_nominal' => $save_d->total_base
                        ];
                        $this->repo_all_trans->CreateOrUpdate($data_trans_create, null);
                    }
                }
            }

            //untuk log
            foreach ($deleted_array as $delId) {
                $old = $oldDetails->get($delId);
                if ($old) {
                    $detailChangeLogs[] = "Item '{$old->desc}' dihapus";
                }
            }

            // 5. Update Total Keseluruhan Parent
            $sumD = $this->repo_detail->sumDataWhereDinamis(['bills_parent_id' => $saveP->id], 'amount');
            $sumDBase = $this->repo_detail->sumDataWhereDinamis(['bills_parent_id' => $saveP->id], 'total_base'); // BARU


            $currentPaid = ($isUpdate && $oldParent) ? (float) $oldParent->nominal_paid : 0;
            $currentPaidBase = ($isUpdate && $oldParent) ? (float) $oldParent->nominal_paid_base : 0; // BARU


            $newDue = $sumD - $currentPaid;
            $newDueBase = $sumDBase - $currentPaidBase;

            if ($newDueBase <= 0) {
                $status = 2;
                $newDue = 0;
                $newDueBase = 0;
            } elseif ($currentPaidBase > 0) {
                $status = 1;
            } else {
                $status = $request->action_save;
            }

            $this->repo->CreateOrUpdate(['total' => $sumD, 'total_base' => $sumDBase, 'nominal_due' => $newDue, 'nominal_due_base' => $newDueBase, 'status' => $status], $saveP->id);

            // ================== SUSUN PESAN LOG BERISI DETAIL PERUBAHAN ==================
            $parentChangeText = $isUpdate ? $this->diffBillParentRow($oldParent, $request, $saveP) : '';

            $summaryParts = [];
            if ($parentChangeText !== '') {
                $summaryParts[] = $parentChangeText;
            }
            if (!empty($detailChangeLogs)) {
                $summaryParts[] = implode('; ', $detailChangeLogs);
            }

            $actionLabel = $isUpdate ? 'mengubah' : 'membuat';
            $logMessage = $request->user_login->name . ' ' . $actionLabel . ' transaksi bills ' . $saveP->name_contact;
            $logMessage .= !empty($summaryParts)
                ? '. Detail: ' . implode('. ', $summaryParts)
                : ($isUpdate ? '. Tidak ada perubahan data.' : '.');

            $this->service_global->saveLogHistory(
                $request->user_login->id,
                $logMessage,
                $request->userAgent(),
                $request->ip(),
                null,
                $saveP->id
            );

            DB::commit();
            return $this->autoResponse($saveP);

        } catch (\Throwable $th) {
            DB::rollBack();
            // Memunculkan pesan error dengan lengkap sangat membantu saat debugging di network tab inspect element
            return $this->error($th->getMessage() . ' at line ' . $th->getLine(), 500);
        }
    }

    private function diffBillParentRow($oldParent, Request $request, $saveP): string
    {
        if (!$oldParent) {
            return '';
        }

        $fieldLabels = [
            'uuid_from' => 'Kontak/Vendor',
            'date_req' => 'Tgl Bill',
            'due_date' => 'Jatuh Tempo',
            'reference' => 'Referensi',
            'currency' => 'Mata Uang',
            'status' => 'Status',
        ];
        $dateFields = ['date_req', 'due_date'];

        $newValues = [
            'uuid_from' => $request->uuid_from,
            'date_req' => $request->date_req,
            'due_date' => $request->due_date,
            'reference' => $request->reference,
            'currency' => $request->currency,
            'status' => $request->status == 1 ? 'approved' : 'draft',
        ];

        $changes = [];
        foreach ($fieldLabels as $field => $label) {
            $oldVal = $oldParent->{$field} ?? null;
            $newVal = $newValues[$field] ?? null;

            if ($field === 'status') {
                $oldCompare = (string) $oldVal;
                $newCompare = (string) $newVal;
                $oldDisplay = $this->billStatusLabel($oldVal);
                $newDisplay = $this->billStatusLabel($newVal);
            } elseif (in_array($field, $dateFields, true)) {
                $oldCompare = $oldVal ? Carbon::parse($oldVal)->format('Y-m-d') : null;
                $newCompare = $newVal ? Carbon::parse($newVal)->format('Y-m-d') : null;
                $oldDisplay = $oldCompare;
                $newDisplay = $newCompare;
            } else {
                $oldCompare = (string) $oldVal;
                $newCompare = (string) $newVal;
                $oldDisplay = $oldCompare;
                $newDisplay = $newCompare;
            }

            if ($oldCompare !== $newCompare) {
                $changes[] = "{$label}: '{$oldDisplay}' → '{$newDisplay}'";
            }
        }
        return implode('; ', $changes);
    }

    private function billStatusLabel($status): string
    {
        $labels = [
            0 => 'Draft',
            1 => 'Awaiting Payment',
            2 => 'Paid',
        ];
        $key = is_numeric($status) ? (int) $status : null;
        return ($key !== null && isset($labels[$key])) ? $labels[$key] : (string) $status;
    }

    private function normalizeNumber($val): string
    {
        if ($val === null || $val === '') {
            return '0';
        }
        $formatted = number_format((float) $val, 4, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');
        return $trimmed === '' ? '0' : $trimmed;
    }

    private function diffBillDetailRow($oldDetail, array $newDetailData, array $coaNames = []): string
    {
        $numericFields = ['qty', 'unit_price'];

        $fieldLabels = [
            'desc' => 'Deskripsi',
            'qty' => 'Qty',
            'unit_price' => 'Harga',
            'account_id_coa' => 'Akun',
            'item_code' => 'Kode Item',
        ];

        $changes = [];
        foreach ($fieldLabels as $field => $label) {
            $oldRaw = $oldDetail->{$field} ?? null;
            $newRaw = $newDetailData[$field] ?? null;

            if (in_array($field, $numericFields, true)) {
                // Bandingkan versi ternormalisasi -> '31600000.0000' vs '31600000' dianggap SAMA
                $oldCompare = $this->normalizeNumber($oldRaw);
                $newCompare = $this->normalizeNumber($newRaw);
                $oldDisplay = $oldCompare;
                $newDisplay = $newCompare;
            } elseif ($field === 'account_id_coa') {
                $oldCompare = (string) $oldRaw;
                $newCompare = (string) $newRaw;
                $oldDisplay = $oldRaw ? ($coaNames[$oldRaw] ?? "ID {$oldRaw}") : '-';
                $newDisplay = $newRaw ? ($coaNames[$newRaw] ?? "ID {$newRaw}") : '-';
            } else {
                $oldCompare = (string) $oldRaw;
                $newCompare = (string) $newRaw;
                $oldDisplay = $oldCompare;
                $newDisplay = $newCompare;
            }

            if ($oldCompare !== $newCompare) {
                $changes[] = "{$label}: '{$oldDisplay}' → '{$newDisplay}'";
            }
        }

        return implode(', ', $changes);
    }

    //used
    public function detailBill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:p_bills,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd(222);
        $data = $this->repo->WhereDataWith(['getDetail', 'getHistoryBills', 'getContactFrom', 'getPayment'], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }





}
