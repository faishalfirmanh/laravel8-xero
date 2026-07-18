<?php
namespace App\Http\Controllers\Transaction\Sales;

use App\Http\Controllers\Controller;
use App\Http\Repository\Revenue\InvoiceDXeroLocalRepo;
use App\Http\Repository\Transaction\TransBankRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use Illuminate\Http\Request;
use App\Http\Repository\Revenue\InvoiceXeroLocalRepo;
use App\Http\Repository\MasterData\DataJamaahXeroRepository;
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
use Illuminate\Support\Facades\File;
use Barryvdh\DomPDF\Facade\Pdf;
use Intervention\Image\Facades\Image;

class InvXeroController extends Controller
{

    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';
    protected $repo, $repo_detail, $service_global, $repo_jamaah, $repo_all_trans, $repo_trans_bank;
    use ConfigRefreshXero;
    use ApiResponse;


    public function __construct(
        InvoiceXeroLocalRepo $repo,
        InvoiceDXeroLocalRepo $repo_detail,
        TransCoaRepo $repo_all_trans,
        TransBankRepo $repo_trans_bank,
        GlobalService $service_global,
        DataJamaahXeroRepository $repo_jamaah
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->repo_all_trans = $repo_all_trans;
        $this->service_global = $service_global;
        $this->repo_jamaah = $repo_jamaah;
        $this->repo_trans_bank = $repo_trans_bank;
    }

    public function getListInvoice(Request $request)
    {


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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'contact_name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'id', 'DESC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function storeParent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'contact_id' => 'required|integer|exists:data_jamaah_xeros,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
            'reference' => 'required|string',
            'action_save' => 'required|integer|between:0,2',

            'item_id' => 'required|array|min:1',
            'desc' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'unit_price' => 'required|array|min:1',
            'coa_id' => 'required|array|min:1',
            'paket_tracking_uuid' => 'nullable|array',
            'divisi_travel_tracking_uuid' => 'nullable|array',
            'id_detail' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $request->merge([
            'status' => $request->action_save == 0 ? 'DRAFT' : 'AUTHORISED', // 0->draft, 1/2->approve, harus di perbaiki
            'reference' => strtolower($request->reference)
        ]);

        $isUpdate = !empty($request->id);

        DB::beginTransaction();
        try {
            // ================== SNAPSHOT "SEBELUM" — WAJIB diambil sebelum ada mutasi apapun ==================
            $oldParent = $isUpdate ? $this->repo->whereData(['id' => $request->id])->first() : null;
            $oldDetails = $isUpdate
                ? $this->repo_detail->whereData(['parent_inv_id' => $request->id])->get()->keyBy('id')
                : collect();

            // 1. Save Parent
            $get_contact = $this->repo_jamaah->whereData(['id' => $request->contact_id])->first();

            $mergeData = [
                'contact_name' => $get_contact->full_name,
                'uuid_contact' => 'from_local'
            ];
            if (empty($request->id)) {
                $mergeData['invoice_number'] = $this->service_global->generateNewInvoiceNumber();
                $mergeData['invoice_uuid'] = $this->service_global->generateUniqueRandomStringInvoice();
            }
            $request->merge($mergeData);

            $saveP = $this->repo->CreateOrUpdate(
                $request->except(['coa_id', 'desc', 'qty', 'unit_price', 'nama_paket', 'divisi', 'id_detail', 'action_save', 'invoice_nuber']),
                $request->id
            );

            // 2. Hapus Detail yang Dibuang
            $deleted_array = [];
            if ($saveP->id) {
                $allDetailIds = $this->repo_detail->whereData(['parent_inv_id' => $saveP->id])->pluck('id')->toArray();
                $providedDetailIds = $request->id_detail ? array_filter($request->id_detail) : [];
                $deleted_array = array_diff($allDetailIds, $providedDetailIds);

                if (!empty($deleted_array)) {
                    $deletedUuids = $this->repo_detail->wherenDataIn('id', $deleted_array)->pluck('uuid_detail_inv')->toArray();
                    if (!empty($deletedUuids)) {
                        $this->repo_all_trans->wherenDataIn('uuid_detail', $deletedUuids)->delete();
                    }
                    $this->repo_detail->wherenDataIn('id', $deleted_array)->delete();
                }
            }

            // 3. Save Details (Create / Update) + catat perubahan tiap baris utk log
            $detailChangeLogs = [];

            foreach ($request->coa_id as $key => $accountId) {
                $detailId = $request->id_detail[$key] ?? null;

                $detailData = [
                    'invoice_number' => $saveP->invoice_number,
                    'uuid_invoices' => 'from_local',
                    'uuid_item' => 'from_local',
                    'coa_id' => $accountId,
                    'desc' => $request->desc[$key] ?? null,
                    'qty' => $request->qty[$key] ?? 0,
                    'unit_price' => $request->unit_price[$key] ?? 0,
                    'total_amount_each_row' => ($request->qty[$key] ?? 0) * ($request->unit_price[$key] ?? 0),
                    'paket_tracking_uuid' => $request->paket_tracking_uuid[$key] ?? null,
                    'divisi_travel_tracking_uuid' => $request->divisi_travel_tracking_uuid[$key] ?? null,
                    'parent_inv_id' => $saveP->id,
                    'item_id' => $request->item_id[$key] ?? null
                ];

                if (empty($detailId)) {
                    $detailData['uuid_detail_inv'] = $this->service_global->generateUniqueString();
                }

                // --- Bandingkan SEBELUM disimpan, terhadap snapshot lama ---
                if (!empty($detailId) && $oldDetails->has($detailId)) {
                    $diffText = $this->diffDetailRow($oldDetails->get($detailId), $detailData);
                    if ($diffText !== '') {
                        $detailChangeLogs[] = "Item '{$detailData['desc']}' diubah ({$diffText})";
                    }
                } elseif (empty($detailId)) {
                    $detailChangeLogs[] = "Item '{$detailData['desc']}' ditambahkan (Qty: {$detailData['qty']}, Harga: {$detailData['unit_price']})";
                }

                $save_d = $this->repo_detail->CreateOrUpdate($detailData, $detailId);

                // 4. Manajemen Transaksi
                if ($request->action_save != 0) {
                    $cek_create_trans = $this->repo_all_trans->whereData([
                        'reference' => $request->reference,
                        'uuid_coa' => $accountId,
                        'uuid_detail' => $save_d->uuid_detail_inv
                    ])->first();

                    if ($cek_create_trans) {
                        $cek_create_trans->is_speend = false;
                        $cek_create_trans->nominal = $save_d->total_amount_each_row ?? 0;
                        $cek_create_trans->save();
                    } else {
                        $data_trans_create = [
                            'date_transaction' => $request->issue_date,
                            'uuid_coa' => $accountId,
                            'reference' => $request->reference,
                            'is_speend' => false,
                            'nominal' => $save_d->total_amount_each_row,
                            'created_by' => $request->user_login->id,
                            'uuid_detail' => $save_d->uuid_detail_inv
                        ];
                        $this->repo_all_trans->CreateOrUpdate($data_trans_create, null);
                    }
                }
            }

            // Item yang dihapus, catat juga (nama diambil dari snapshot lama)
            foreach ($deleted_array as $delId) {
                $old = $oldDetails->get($delId);
                if ($old) {
                    $detailChangeLogs[] = "Item '{$old->desc}' dihapus";
                }
            }

            // 5. Update Total Keseluruhan Parent
            $sumD = $this->repo_detail->sumDataWhereDinamis(['parent_inv_id' => $saveP->id], 'total_amount_each_row');
            $this->repo->CreateOrUpdate(['invoice_total' => $sumD, 'less_nominal' => $sumD], $saveP->id);

            // ================== SUSUN PESAN LOG BERISI DETAIL PERUBAHAN ==================
            $parentChangeText = $isUpdate ? $this->diffParentRow($oldParent, $request, $saveP) : '';

            $summaryParts = [];
            if ($parentChangeText !== '') {
                $summaryParts[] = $parentChangeText;
            }
            if (!empty($detailChangeLogs)) {
                $summaryParts[] = implode('; ', $detailChangeLogs);
            }

            $actionLabel = $isUpdate ? 'mengubah' : 'membuat';
            $logMessage = $request->user_login->name . ' ' . $actionLabel . ' transaksi invoice ' . $saveP->contact_name;
            $logMessage .= !empty($summaryParts)
                ? '. Detail: ' . implode('. ', $summaryParts)
                : ($isUpdate ? '. Tidak ada perubahan data.' : '.');

            $this->service_global->saveLogHistory(
                $request->user_login->id,
                $logMessage,
                $request->userAgent(),
                $request->ip(),
                $saveP->id
            );

            DB::commit();
            return $this->autoResponse($saveP);

        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error($th->getMessage() . ' at line ' . $th->getLine(), 500);
        }
    }

    /**
     * Bandingkan field parent invoice (lama vs baru). Tanggal dinormalisasi ke 'Y-m-d'
     * dulu, biar '2026-01-05' vs '2026-01-05 00:00:00' tidak dianggap beda.
     */
    private function diffParentRow($oldParent, Request $request, $saveP): string
    {
        if (!$oldParent) {
            return '';
        }

        $fieldLabels = [
            'contact_name' => 'Kontak',
            'issue_date' => 'Tgl Invoice',
            'due_date' => 'Jatuh Tempo',
            'reference' => 'Referensi',
            'status' => 'Status',
        ];
        $dateFields = ['issue_date', 'due_date'];

        $newValues = [
            'contact_name' => $saveP->contact_name,
            'issue_date' => $request->issue_date,
            'due_date' => $request->due_date,
            'reference' => $request->reference,
            'status' => $request->status,
        ];

        $changes = [];
        foreach ($fieldLabels as $field => $label) {
            $oldVal = $oldParent->{$field} ?? null;
            $newVal = $newValues[$field] ?? null;

            if (in_array($field, $dateFields, true)) {
                $oldVal = $oldVal ? Carbon::parse($oldVal)->format('Y-m-d') : null;
                $newVal = $newVal ? Carbon::parse($newVal)->format('Y-m-d') : null;
            } else {
                $oldVal = (string) $oldVal;
                $newVal = (string) $newVal;
            }

            if ($oldVal !== $newVal) {
                $changes[] = "{$label}: '{$oldVal}' → '{$newVal}'";
            }
        }

        return implode('; ', $changes);
    }

    /**
     * Bandingkan field 1 baris detail invoice (lama vs baru).
     */
    private function diffDetailRow($oldDetail, array $newDetailData): string
    {
        $fieldLabels = [
            'desc' => 'Deskripsi',
            'qty' => 'Qty',
            'unit_price' => 'Harga',
            'coa_id' => 'Akun',
            'item_id' => 'Item',
        ];

        $changes = [];
        foreach ($fieldLabels as $field => $label) {
            $oldVal = (string) ($oldDetail->{$field} ?? '');
            $newVal = (string) ($newDetailData[$field] ?? '');
            if ($oldVal !== $newVal) {
                $changes[] = "{$label}: '{$oldVal}' → '{$newVal}'";
            }
        }

        return implode(', ', $changes);
    }
    public function getImageDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:invoices_all_from_xeros,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 400);
        }

        try {
            $invoiceId = $request->input('invoice_id');
            $directory = public_path('uploads/images/invoices');

            // Jika folder belum ada, berarti belum ada gambar sama sekali
            if (!File::exists($directory)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ], 200);
            }
            $pattern = $directory . '/*_' . $invoiceId . '.webp';
            $matchedFiles = glob($pattern);

            $images = [];

            if ($matchedFiles) {
                foreach ($matchedFiles as $file) {
                    $filename = basename($file);

                    $images[] = [
                        'name' => $filename,
                        'size' => filesize($file), // Dropzone butuh ukuran file (bytes)
                        'url' => url('uploads/images/invoices/' . $filename) // Full path URL
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

            $filePath = public_path('uploads/images/invoices/' . $filename);

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
            'invoice_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 400);
        }

        try {
            $files = $request->file('file'); // Ini sekarang adalah ARRAY dari file
            $invoiceId = $request->input('invoice_id');

            // 2. Siapkan Path
            $destinationPath = public_path('uploads/images/invoices');
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
                $filename = $invNumber->invoice_number . '_' . uniqid() . '_' . $invoiceId . '.webp';

                // Simpan File
                file_put_contents($destinationPath . '/' . $filename, $encodedData);

                // Hitung ukuran akhir
                $finalSizeKb = round(filesize($destinationPath . '/' . $filename) / 1024, 2);

                // Simpan data file yang berhasil diproses ke array
                $uploadedFilesData[] = [
                    'file_name' => $filename,
                    'file_url' => url('uploads/images/invoices/' . $filename),
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

    public function detailInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:invoices_all_from_xeros,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd(222);
        $data = $this->repo->WhereDataWith([
            'getDetailById',
            'getDetailById.getCoa',
            'getDetailById.getItem',
            'getPayment'
            // 'getDetailById.trackingCategoryPaket'
        ], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }

    public function storePayment(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'uuid_bank' => 'required|integer|exists:bank_xeros,id',
            'nominal_receive' => 'required|integer',
            'reference_detail' => 'required|string',
            'date_transaction' => 'required|date',
            'parent_inv_id' => 'required|integer|exists:invoices_all_from_xeros,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 500);
        }

        $cekData = $this->repo->whereData(['id' => $request->parent_inv_id])->first();


        $sisaTagihan = $cekData->less_nominal;

        if ($request->nominal_receive > $cekData->invoice_total) {
            return $this->error("Nominal melebihi total tagihan (due: {$cekData->invoice_total})", 400);
        }

        if ($request->nominal_receive > $sisaTagihan) {
            return $this->error("Nominal melebihi sisa tagihan yang belum dibayar (sisa: {$sisaTagihan})", 400);
        }

        $request->merge([
            'created_by' => $request->user_login->id,
            'nominal_transfer' => 0,
            'nominal_spend' => 0
        ]);

        DB::beginTransaction();
        try {
            $nominal_paid_final = $cekData->invoice_amount + $request->nominal_receive;
            //$nominal_due_final = $cekData->invoice_amount - $nominal_paid_final;//kekurangan bayar
            $final_less = $cekData->less_nominal < 1 ? $cekData->invoice_total - $request->nominal_receive : $cekData->less_nominal - $request->nominal_receive;

            $param_inv_save = ['invoice_amount' => $nominal_paid_final, 'less_nominal' => $final_less];
            $invP = $this->repo->CreateOrUpdate($param_inv_save, $request->parent_inv_id);
            $request->merge([
                'id_parent_invoice' => $request->parent_inv_id
            ]);
            $saveP = $this->repo_trans_bank->CreateOrUpdate($request->all(), null);

            if ($invP->invoice_amount == $invP->invoice_total && $invP->less_nominal == 0) {
                $this->repo->CreateOrUpdate(['status' => 'PAID'], $request->parent_inv_id);
            }
            DB::commit();
            return $this->autoResponse($saveP);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error($th->getMessage(), 400);
        }


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



    public function cekPaymentAda($paymentId)
    {
        $responsePayment = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Payments/' . $paymentId);
        $res = $responsePayment->json('Payments.0');
        if ($res != null) {
            return $res;
        } else {
            return NULL;
        }
    }







}
