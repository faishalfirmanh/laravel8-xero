<?php
namespace App\Http\Controllers\Transaction\Revenue;
use App\Http\Controllers\Controller;
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
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceXeroLocalController extends Controller {

   private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';
    protected $repo, $repo_detail, $service_global, $repo_jamaah;
    use ConfigRefreshXero;
    use ApiResponse;


    public function __construct( InvoiceXeroLocalRepo $repo, HotelDetailInvoicesRepository $repo_detail, GlobalService $service_global, DataJamaahXeroRepository $repo_jamaah)
    {
        $this->repo = $repo;
       $this->repo_detail = $repo_detail;
       $this->service_global = $service_global;
       $this->repo_jamaah = $repo_jamaah;
    }

    public function getListInvoice(Request $request) { }

    function filterPaymentString($string) {
        $keyword = "-man";
        $position = strpos(strtolower($string), $keyword);
        if ($position !== false && $position > 0) {
            return substr($string, $position);
        }
        return $string;
    }

    public function updateInvoiceDate(Request $request)
    {
        $validator = Validator::make($request->all(),
         [ 'invoice_uuid' => 'required|string',
         'issue_date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        $user_name_xero = $this->getUserNameXeroFromToken($tokenData["access_token"]);
        //dd($user_name_xero);

        $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Invoices/' . $request->invoice_uuid);

            if ($response->failed()) {
                return response()->json(['status' => 'error', 'message' => 'Gagal koneksi ke Xero'], 500);
            }

            $available_min_req = (int) $response->header('X-MinLimit-Remaining');
            $available_day_req = (int) $response->header('X-DayLimit-Remaining');
            //$this->serviceGlobal->requestCalculationXero($available_min_req, $available_day_req);

            $invoiceData = $response->json()['Invoices'][0];

            $cekPayment = PaymentParams::where('invoice_id',$request->invoice_uuid)->first();
            if($cekPayment != NULL){
                PaymentParams::where('invoice_id',$request->invoice_uuid)->delete();
            }

            if(isset($invoiceData['Payments'])){
                foreach($invoiceData['Payments'] as $pay)
                {
                    $cek_pay = self::cekPaymentAda($pay['PaymentID']);
                    //dd(self::parseXeroDate($cek_pay['Date']));
                    if($cek_pay != NULL){
                       PaymentParams::create([
                        'invoice_id'=>$request->invoice_uuid,
                        'account_code'=>$cek_pay['Account']['Code'],
                        'date'=> self::parseXeroDate($cek_pay['Date']),
                        'amount'=>$cek_pay['Amount'],
                        'reference'=> self::filterPaymentString($cek_pay['Reference']),
                        'bank_account_id'=>$cek_pay['Account']['AccountID'],
                      ]);
                    }
                }

                foreach($invoiceData['Payments'] as $pay_2)
                {
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

            if($updateResponse->successful()){
                $is_update_issu_date += 1;
            }

           $tot_payemnt = 0;
           $payment_local_byInv = PaymentParams::where('invoice_id',$request->invoice_uuid)->get();
           if(count($payment_local_byInv) > 0){
            foreach($payment_local_byInv as $key => $value){
                $accoun_code_bank =  $value->account_code;
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
                    "Date"      => $tnggl_bayar,
                    "Amount"    => $value_amount,
                    "Reference" => $reff_nya ?? "Payment via API"
                ];

                $response_repayment = Http::withHeaders($this->getHeaders())
                 ->post($this->xeroBaseUrl . '/Payments', $form_payment);
                if ($response_repayment->failed()) {
                    Log::error("Gagal Restore Payment ke Invoice: InvoiceXerlocalController line 136 " . $response_repayment->body());
                        return response()->json([
                        'status' => 'error',
                        'message' => 'Gagal Update: payment baru ssat bayar ulang',
                        'body'=>$response_repayment->body()
                    ], 400);
                } else{
                    $tot_payemnt++;
                }
            }
           }

        PaymentParams::where('invoice_id',$request->invoice_uuid)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'berhasil ubah invoice date',
            'isue_date'=>$is_update_issu_date,
            'total_repayment' =>$tot_payemnt
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
        if(self::cekPaymentAda($paymentId)){
            $response = Http::withHeaders($this->getHeaders())
            ->post($this->xeroBaseUrl . "/Payments/$paymentId", ["Status" => "DELETED"]);
            if ($response->failed() && $response->status() != 404) {
               //  throw new \Exception("Gagal Void Payment: " . $response->body());
                Log::info('gagal void payment saat update issue date InvoiceXeroLocalController line : 187');
            }
        }
    }

 public function cekPaymentAda($paymentId){
        $responsePayment = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Payments/' . $paymentId);
        $res = $responsePayment->json('Payments.0');
        if($res != null){
            return $res;
        }else{
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
