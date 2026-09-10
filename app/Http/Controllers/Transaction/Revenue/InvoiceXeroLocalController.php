<?php
namespace App\Http\Controllers\Transaction\Revenue;
use App\Http\Controllers\Controller;
use App\Http\Repository\Transaction\VaRepo;
use App\Jobs\SyncXeroInvoiceJob;
use App\Jobs\SyncXeroInvoiceJobV2;
use App\Models\InvoicesAllFromXero;
use App\Models\SyncJobStatus;
use Illuminate\Http\Request;
use App\Http\Repository\Revenue\InvoiceXeroLocalRepo;
use App\Http\Repository\MasterData\DataJamaahXeroRepository;
use App\Http\Repository\Revenue\HotelDetailInvoicesRepository;
use Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Str;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\PaymentParams;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;

use Barryvdh\DomPDF\Facade\Pdf;
class InvoiceXeroLocalController extends Controller
{

    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';
    protected $repo, $repo_detail, $service_global, $repo_jamaah, $repo_va;
    use ConfigRefreshXero;
    use ApiResponse;


    public function __construct(
        InvoiceXeroLocalRepo $repo,
        VaRepo $repo_va,
        HotelDetailInvoicesRepository $repo_detail,
        GlobalService $service_global,
        DataJamaahXeroRepository $repo_jamaah
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->service_global = $service_global;
        $this->repo_jamaah = $repo_jamaah;
        $this->repo_va = $repo_va;
    }

    public function getSyncStatus(string $jobId)
    {
        $job = SyncJobStatus::where('job_id', $jobId)->first();

        if (!$job) {
            return response()->json(['message' => 'Job tidak ditemukan.'], 404);
        }

        $durasi = null;
        if ($job->started_at && $job->finished_at) {
            $durasi = $job->started_at->diffForHumans($job->finished_at, true);
        }

        return response()->json([
            'job_id' => $job->job_id,
            'status' => $job->status,           // pending/running/success/failed
            'current_page' => $job->current_page,
            'total_pages' => $job->total_pages,
            'total_synced' => $job->total_synced,
            'error_message' => $job->error_message,
            'started_at' => $job->started_at->format('d-m-Y H:i:s'),
            'finished_at' => $job->finished_at ? $job->finished_at->format('d-m-Y H:i:s') : null,
            'durasi' => $durasi,
        ]);
    }

    public function getVa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'va_number' => 'required|string',
            'key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if (!Str::contains($request->key, 'namiroh123')) {
            return $this->error('Key tidak valid.', 422);
        }

        $data = $this->repo_va->whereData(['va_number' => $request->va_number])->first();
        return $this->autoResponse($data);
    }


    public function getListInvoice(Request $request)
    {
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'is_sync' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        if ($request->is_sync == 1) {
            $jobId = (string) Str::uuid();
            SyncXeroInvoiceJob::dispatch($tokenData, $jobId);

            return response()->json([
                'status' => 'queued',
                'job_id' => $jobId,
                'message' => 'Sync invoice dimulai. Gunakan job_id untuk cek status.',
            ]);
        } else {
            $accessToken = $tokenData['access_token'];
            $tenantId = $this->getTenantId($accessToken);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])->timeout(25)->get('https://api.xero.com/api.xro/2.0/Invoices', [
                        'Statuses' => 'DRAFT,SUBMITTED,AUTHORISED,PAID',
                        'Type' => 'ACCREC',
                        'order' => 'Date DESC',
                        'page' => 1,
                        'unitdp' => 4,
                        'pageSize' => 200
                    ]);

            //dd($response['Invoices'][0]['CurrencyCode']);
            return $response;
        }
        // SyncJobStatus::where('job_type', 'SyncXeroInvoiceJob')
        //     ->where('status', 'running')
        //     ->where('started_at', '<', now()->subMinutes(15))
        //     ->update([
        //         'status' => 'failed',
        //         'error_message' => 'Auto-reset: job timeout tidak selesai dalam 15 menit.',
        //         'finished_at' => now(),
        //     ]);

        // // Cegah double sync
        // $isRunning = SyncJobStatus::where('job_type', 'SyncXeroInvoiceJob')
        //     ->where('status', 'running')
        //     ->exists();

        // if ($isRunning) {
        //     return response()->json([
        //         'status' => 'already_running',
        //         'message' => 'Sync invoice sedang berjalan, tunggu hingga selesai.',
        //     ], 409);
        // }



        // SyncJobStatus::create([
        //     'job_id' => $jobId,
        //     'job_type' => 'SyncXeroInvoiceJob',
        //     'status' => 'pending',
        // ]);

        // SyncXeroInvoiceJobV2::dispatch($tokenData, $jobId);

    }
    // ── Helper bersama: logo base64 ──────────────────────────────────────────

    private function getLogoBase64($url_path): string
    {
        $path = public_path($url_path);

        if (!file_exists($path)) {
            return '';
        }

        $mime = mime_content_type($path);
        $base64 = base64_encode(file_get_contents($path));

        return 'data:' . $mime . ';base64,' . $base64;
    }

    // ── Generate QR lokal (base64) — dipakai bersama preview & print ──
    private function generateQrBase64(string $url, string $uniqueKey, $path_image): string
    {
        $webpLogoPath = public_path($path_image);

        if (!file_exists($webpLogoPath) || !extension_loaded('imagick')) {
            // Fallback: QR tanpa logo kalau imagick/logo tidak tersedia
            $qrCode = QrCode::format('png')
                ->size(400)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($url);

            return 'data:image/png;base64,' . base64_encode($qrCode);
        }

        $tempLogoPath = storage_path('app/temp_qr_logo_' . $uniqueKey . '.png');

        $logo = new \Imagick($webpLogoPath);
        $logo->setImagePage(0, 0, 0, 0);
        $logo->trimImage(0);
        $logo->setImageBackgroundColor(new \ImagickPixel('white'));
        $logo->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);

        $logoWidth = $logo->getImageWidth();
        $logoHeight = $logo->getImageHeight();
        $logoSize = max($logoWidth, $logoHeight);

        $canvas = new \Imagick();
        $canvas->newImage($logoSize, $logoSize, new \ImagickPixel('white'));
        $canvas->setImageFormat('png');

        $x = (int) (($logoSize - $logoWidth) / 2);
        $y = (int) (($logoSize - $logoHeight) / 2);

        $canvas->compositeImage($logo, \Imagick::COMPOSITE_OVER, $x, $y);
        $canvas->writeImage($tempLogoPath);

        $logo->clear();
        $logo->destroy();
        $canvas->clear();
        $canvas->destroy();

        $qrCode = QrCode::format('png')
            ->size(400)
            ->margin(2)
            ->errorCorrection('H')
            ->merge($tempLogoPath, 0.30, true)
            ->generate($url);

        if (file_exists($tempLogoPath)) {
            @unlink($tempLogoPath);
        }

        return 'data:image/png;base64,' . base64_encode($qrCode);
    }

    // ── Preview → tampil di iframe modal, bukan PDF ──────────────────────────
    public function previewInvoice(Request $request, $id)
    {
        $invoice = InvoicesAllFromXero::with([
            'getDetailById',
            'getPayment',
            'getDetailById.getItems',
            'getOverPay',
            'getTravel'
        ])->where('invoice_uuid', $id)->first();

        abort_if(!$invoice, 404);

        $path_img_dinamis = $invoice->getTravel ? $invoice->getTravel->location_path_image ? $invoice->getTravel->location_path_image : 'assets/img/nam_min.webp' : 'assets/img/nam_min.webp';

        // $urlTujuan = route('salles_invoice_preview', ['id' => $invoice->invoice_uuid]);
        $urlTujuan = "https://inv.alhidayah.id/" . $invoice->invoice_uuid;

        $data = [
            'invoice' => $invoice,
            'title' => 'Invoice #' . $invoice->invoice_number,
            'date' => date('d-m-Y'),
            'qrCode' => $this->generateQrBase64($urlTujuan, $invoice->invoice_uuid . '_preview', $path_img_dinamis),
            'logoBase64' => $this->getLogoBase64($path_img_dinamis),
        ];

        return view('admin.transaksi.sales.modal_inv', $data);
    }

    // ── Print → PDF stream (tidak berubah banyak) ────────────────────────────


    public function InvExternal(Request $request, $uuid_inv)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'key' => 'required|string|in:namiroh123',
            ]
        );
        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $invoice = InvoicesAllFromXero::with([
            'getDetailById',
            'getPayment',
            'getDetailById.getItems',
            'getOverPay'
        ])
            ->where('invoice_uuid', $uuid_inv)
            ->first();

        return $this->autoResponse($invoice);
    }


    public function printInvoice(Request $request, $id)
    {
        $invoice = InvoicesAllFromXero::with([
            'getDetailById',
            'getPayment',
            'getDetailById.getItems',
            'getOverPay',
            'getTravel'
        ])
            ->where('invoice_uuid', $id)
            ->first();

        if (!$invoice) {
            abort(404, 'Invoice tidak ditemukan.');
        }

        $urlTujuan = "https://inv.alhidayah.id/" . $id;
        // route('salles_invoice_print', [
        //     'id' => $invoice->invoice_uuid
        // ]);

        $img_path = $invoice->getTravel ? $invoice->getTravel->location_path_image : 'assets/img/nam_min.webp';

        $webpLogoPath = public_path($img_path);

        if (!file_exists($webpLogoPath)) {
            abort(500, 'File logo tidak ditemukan.');
        }

        $tempLogoPath = storage_path(
            'app/temp_qr_logo_' . $invoice->invoice_uuid . '.png'
        );


        if (!extension_loaded('imagick')) {
            abort(500, 'Imagick extension belum aktif.');
        }

        $logo = new \Imagick($webpLogoPath);


        $logo->setImagePage(0, 0, 0, 0);

        $logo->trimImage(0);


        $logo->setImageBackgroundColor(new \ImagickPixel('white'));
        /*
        |--------------------------------------------------------------------------
        | Hilangkan alpha untuk PNG hasil akhir
        |--------------------------------------------------------------------------
        */
        $logo->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        /*
        |--------------------------------------------------------------------------
        | Buat logo menjadi persegi
        |--------------------------------------------------------------------------
        */
        $logoWidth = $logo->getImageWidth();
        $logoHeight = $logo->getImageHeight();

        $logoSize = max($logoWidth, $logoHeight);

        $canvas = new \Imagick();

        $canvas->newImage(
            $logoSize,
            $logoSize,
            new \ImagickPixel('white')
        );

        $canvas->setImageFormat('png');

        /*
        |--------------------------------------------------------------------------
        | Center logo
        |--------------------------------------------------------------------------
        */
        $x = (int) (($logoSize - $logoWidth) / 2);
        $y = (int) (($logoSize - $logoHeight) / 2);

        $canvas->compositeImage(
            $logo,
            \Imagick::COMPOSITE_OVER,
            $x,
            $y
        );

        $canvas->writeImage($tempLogoPath);

        $logo->clear();
        $logo->destroy();

        $canvas->clear();
        $canvas->destroy();


        $qrCode = QrCode::format('png')
            ->size(400)
            ->margin(2)
            ->errorCorrection('H')
            ->merge(
                $tempLogoPath,
                0.30,
                true
            )
            ->generate($urlTujuan);

        /*
        |--------------------------------------------------------------------------
        | Convert QR menjadi Base64
        |--------------------------------------------------------------------------
        */
        $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($qrCode);

        /*
        |--------------------------------------------------------------------------
        | Hapus temporary logo
        |--------------------------------------------------------------------------
        */
        if (file_exists($tempLogoPath)) {
            @unlink($tempLogoPath);
        }

        /*
        |--------------------------------------------------------------------------
        | Data PDF
        |--------------------------------------------------------------------------
        */
        $data = [
            'invoice' => $invoice,
            'title' => 'Invoice #' . $invoice->invoice_number,
            'date' => date('d-m-Y'),
            'qrCode' => $qrCodeBase64,
        ];

        /*
        |--------------------------------------------------------------------------
        | Generate PDF
        |--------------------------------------------------------------------------
        */
        $pdf = Pdf::loadView(
            'pdf.salles_invoice_print',
            $data
        );

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream(
            'Invoice-' . $invoice->invoice_number . '.pdf'
        );
    }


    function filterPaymentString($string)
    {
        $keyword = "-man";
        $position = strpos(strtolower($string), $keyword);
        if ($position !== false && $position > 0) {
            return substr($string, $position);
        }
        return $string;
    }

    public function updateInvoiceDate(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'invoice_uuid' => 'required|string',
                'issue_date' => 'required|date',
            ]
        );
        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        //$user_name_xero = $this->getUserNameXeroFromToken($tokenData["access_token"]);
        //dd($user_name_xero);

        $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Invoices/' . $request->invoice_uuid);

        if ($response->failed()) {
            return response()->json(['status' => 'error', 'message' => 'Gagal koneksi ke Xero'], 500);
        }

        $available_min_req = (int) $response->header('X-MinLimit-Remaining');
        $available_day_req = (int) $response->header('X-DayLimit-Remaining');
        //$this->serviceGlobal->requestCalculationXero($available_min_req, $available_day_req);

        $invoiceData = $response->json()['Invoices'][0];

        $cekPayment = PaymentParams::where('invoice_id', $request->invoice_uuid)->first();
        if ($cekPayment != NULL) {
            PaymentParams::where('invoice_id', $request->invoice_uuid)->delete();
        }

        if (isset($invoiceData['Payments'])) {
            foreach ($invoiceData['Payments'] as $pay) {
                $cek_pay = self::cekPaymentAda($pay['PaymentID']);
                //dd(self::parseXeroDate($cek_pay['Date']));
                if ($cek_pay != NULL) {
                    PaymentParams::create([
                        'invoice_id' => $request->invoice_uuid,
                        'account_code' => $cek_pay['Account']['Code'],
                        'date' => self::parseXeroDate($cek_pay['Date']),
                        'amount' => $cek_pay['Amount'],
                        'reference' => self::filterPaymentString($cek_pay['Reference']),
                        'bank_account_id' => $cek_pay['Account']['AccountID'],
                    ]);
                }
            }

            foreach ($invoiceData['Payments'] as $pay_2) {
                self::voidPaymentInXero($pay_2['PaymentID']);
            }
        }

        //update date :
        //
        $is_update_issu_date = 0;
        $payload = [
            'InvoiceID' => $request->invoice_uuid,
            'Date' => $request->issue_date
        ];

        $updateResponse = Http::withHeaders($this->getHeaders())
            ->post($this->xeroBaseUrl . '/Invoices/' . $request->invoice_uuid, $payload);

        if ($updateResponse->successful()) {
            $is_update_issu_date += 1;
        }

        $tot_payemnt = 0;
        $payment_local_byInv = PaymentParams::where('invoice_id', $request->invoice_uuid)->get();
        if (count($payment_local_byInv) > 0) {
            foreach ($payment_local_byInv as $key => $value) {
                $accoun_code_bank = $value->account_code;
                $value_amount = $value->amount;
                $reff_nya = $value->reference;
                $tnggl_bayar = $value->date;

                $form_payment = [
                    "Invoice" => [
                        "InvoiceID" => $request->invoice_uuid
                    ],
                    "Account" => [
                        "Code" => $accoun_code_bank
                    ],
                    "Date" => $tnggl_bayar,
                    "Amount" => $value_amount,
                    "Reference" => $reff_nya ?? "Payment via API"
                ];

                $response_repayment = Http::withHeaders($this->getHeaders())
                    ->post($this->xeroBaseUrl . '/Payments', $form_payment);
                if ($response_repayment->failed()) {
                    Log::error("Gagal Restore Payment ke Invoice: InvoiceXerlocalController line 136 " . $response_repayment->body());
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Gagal Update: payment baru ssat bayar ulang',
                        'body' => $response_repayment->body()
                    ], 400);
                } else {
                    $tot_payemnt++;
                }
            }
        }

        PaymentParams::where('invoice_id', $request->invoice_uuid)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'berhasil ubah invoice date',
            'isue_date' => $is_update_issu_date,
            'total_repayment' => $tot_payemnt
        ], 200);
        // echo  . "<br>".$tot_payemnt ."<br>";
        //dd($invoiceData['Payments']);
    }

    public function parseXeroDate($xeroDate)
    {
        if (preg_match('/\/Date\((-?\d+)([+-]\d+)?\)\//', $xeroDate, $matches)) {
            $timestamp = (int) $matches[1] / 1000;
            return date('Y-m-d', $timestamp);
        }
        return date('Y-m-d');

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

    private function voidPaymentInXero($paymentId)
    {
        if (self::cekPaymentAda($paymentId)) {
            $response = Http::withHeaders($this->getHeaders())
                ->post($this->xeroBaseUrl . "/Payments/$paymentId", ["Status" => "DELETED"]);
            if ($response->failed() && $response->status() != 404) {
                //  throw new \Exception("Gagal Void Payment: " . $response->body());
                Log::info('gagal void payment saat update issue date InvoiceXeroLocalController line : 187');
            }
        }
    }

    public function hapusLock(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        DB::table('record_locks')
            ->where('lockable_type', 'invoice')
            ->where('lockable_id', $request->id)
            ->where('locked_by', $request->user_login->id)
            ->delete();
        return response()->json(['success' => true], 200);
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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'nama_pemesan', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'nama_pemesan', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }




}
