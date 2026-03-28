<?php
namespace App\Http\Controllers\MasterData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\Revenue\InvoiceXeroLocalRepo;
use App\Http\Repository\MasterData\TrackingRepo;
use App\Http\Repository\MasterData\BankXeroRepo;

use App\Http\Repository\Transaction\SpendMoneyRepo;
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

class BankXeroController extends Controller {

   private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';
   // protected $repo, $repo_detail, $service_global, $repo_jamaah;
    use ConfigRefreshXero;
    use ApiResponse;

    protected $repo_trans, $repo_tracking;


    public function __construct(BankXeroRepo $repo, SpendMoneyRepo $repo_trans, TrackingRepo $repo_tracking)
    {
        $this->repo = $repo;
        $this->repo_trans = $repo_trans;
        $this->repo_tracking = $repo_tracking;
    }

public function updateTrans(Request $request, $id)
{
    $request->merge(['id' => $id]); // supaya CreateOrUpdate tahu ID-nya
    return $this->storeTrans($request);  // reuse validator + logic
}



    public function getTracking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_parent_category' => 'required|string|in:divisi,paket',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $cekSudahAdaData = $this->repo_tracking->whereData(['name_parent_category'=>strtolower($request->name_parent_category)])->first();

        if($cekSudahAdaData == NULL){
             return response()->json([
                'status'  => 'error',
                'message' => 'data dengan tracking_name '. $request->name_parent_category   ." tidak ada",
                'errors'  => $validator->errors()
            ], 404);
        }

        $data = $this->repo_tracking->whereData(['name_parent_category'=>strtolower($request->name_parent_category )])->first();
        return $this->autoResponse($data);
    }

    public function storeTrans(Request $request)
    {

        $idnya = $request['id'] ?? null;
       // unset($request['id']);

       // dd($request->id);


        $validator = Validator::make($request->all(), [
            'contact_id'          => 'required|integer|exists:data_jamaah_xeros,id',//dari tabel jamaah_xero atau data jamaah
            'to'                  => 'required|string|max:255',
            'bank_id'             =>'required|numeric|exists:bank_xeros,id',
            'tax_type'            => 'required|numeric|between:1,3',//1=no tax, 2, tax inclusive, 3, tax exclusive
            'type_trans'          => 'required|numeric|between:1,2',//1 spend money, 2 receive money
            'date'                => 'required|date_format:Y-m-d',
            'reference'           => 'nullable|string|max:255',
            'currency_code'       => 'required|string|in:IDR',
            'lines'               => 'required|array|min:1',
            'lines.*.item'        => 'nullable|string|exists:items_paket_all_from_xeros,id',
            'lines.*.description' => 'required|string',
            'lines.*.qty'         => 'required|numeric|min:0',
            'lines.*.unit_price'  => 'required|numeric|min:0',//ambil dari get item
            'lines.*.account'     =>  'nullable|string|exists:coas,id',
            'lines.*.tax_rate'    => 'nullable|string',
            'lines.*.tracking_paket'     =>  ['nullable', 'string', new \App\Rules\DivisiExistsInTracking()],//'nullable|string',     // Nama Pa... (Partner / Pemasok)
            //'lines.*.divisi'      => 'nullable|string',//tracking kategory
            'lines.*.divisi' => ['nullable', 'string', new \App\Rules\DivisiExistsInTracking()],
            //'lines.*.amount_idr'  => 'required|numeric|min:0',//set otomatis
            'subtotal'            => 'required|numeric|min:0',
            'tax'                 => 'required|numeric|min:0',
            'total'               => 'required|numeric|min:0',
            'status'              => 'nullable|numeric|between:1,5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validasi Gagal',
                'errors'  => $validator->errors()
            ], 422);
        }


    $data = $request->all();
    $lines = $data['lines'] ?? [];
    $subtotal = 0;
    foreach ($lines as $key => $line) {
        // Hitung amount_idr = qty * unit_price
        $amountIdr = (float)($line['qty'] ?? 0) * (float)($line['unit_price'] ?? 0);

        $lines[$key]['amount_idr'] = $amountIdr;   // override nilai

        $subtotal += $amountIdr;
    }


    $data['lines'] = $lines;

    $tax   = (float)($data['tax'] ?? 0);
    $total = $subtotal + $tax;

    $data['subtotal'] = $subtotal;
    $data['tax']      = $tax;
    $data['total']    = $total;


        $saved = $this->repo_trans->createFromXeroForm($data,$request->id);

        return $this->autoResponse($saved);
    }

    // Bonus: method detail & destroy (sama seperti BankXeroController kamu)
    public function detailTrans(Request $request)
    {
        $data = $this->repo_trans->find($request->id);
        return $this->autoResponse($data);
    }

    public function destroyTrans(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:spend_money_xeros,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $data = $this->repo_trans->delete($request->id);
        return $this->autoResponse($data);
    }


       public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => [ 'required', 'string' ],
            'code' => 'required|string',
            'name'        => 'required|string',
            'status'      => 'required|numeric|between:1,5',
            'type'      => 'required|string',
            'currency_code'      => 'required|string',
            'account_number'      => 'required|string'
        ]);

        if ($validator->fails()) {
            // return $validator->errors();
            //
            //
             return response()->json([
                'status'  => 'error',
                'message' => 'Validasi Gagal',
                'errors'  => $validator->errors()
            ], 422);

        }

        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);

        return $this->autoResponse($saved);
    }

    public function destroy(Request $request)
    {
         $id = $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:bank_xeros,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $bank = $this->repo->find($id);

        if ($bank) {
            $data = $this->repo->delete($id);
            return $this->autoResponse($data);
        }

        return $this->error('hotel not found', 404);

    }

    public function detail(Request $request)
    {
        $bank = $this->repo->find($request->id);
        return $this->autoResponse($bank);
    }



}
