<?php

namespace App\Http\Controllers\Transaction\Revenue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Repository\Revenue\HotelInvoicesRepository;
use App\Http\Repository\MasterData\DataJamaahXeroRepository;
use App\Http\Repository\Revenue\HotelDetailInvoicesRepository;
use App\Http\Repository\Revenue\HotelPaymentRepository;
use Validator;
use App\Traits\ApiResponse;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\Revenue\Hotel\InvoicesHotel;
use App\Models\Config\ConfigCurrency;
class RPaymentHotelApiController extends Controller
{
    //
    protected $repo, $repo_payment, $service_global, $repo_jamaah;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        HotelInvoicesRepository $repo,
        HotelPaymentRepository $repo_payment
    ) {
        $this->repo = $repo;
        $this->repo_payment = $repo_payment;
    }


    public function getAllByIdInv(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'=> 'required|exists:invoices_hotels,id'
        ]);
        //dd($request->id);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
       // $data = $this->repo_payment->whereData(['invoices_id'=> $request->id])->get();
        $data = $this->repo_payment->WhereDataWith(['getInvoice'],['invoices_id'=> $request->id])->get();
        return $this->autoResponse($data);
    }



    public function store(Request $request)
    {
        // 1. Validasi Input (Termasuk Array)
        // $request->invoices_id = $request->invoices_id_parent;
        $request['invoices_id']= $request->invoices_id_parent;
        $config_curency = ConfigCurrency::first();
        $request['payment_sar']= $request->payment_idr / $config_curency->nominal_rupiah_1_riyal;
        $validator = Validator::make($request->all(), [
            'invoices_id' => 'required|exists:invoices_hotels,id',
            // 'payment_sar' => 'required|numeric',
             //'payment_idr' => 'required|numeric',
            'payment_sar'   => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    $invoice = DB::table('invoices_hotels')
                                ->where('id', $request->invoices_id)
                                ->first();
                    if ($invoice) {
                        if ($value > $invoice->less_payment_sar) {
                            $fail("Nominal Payment SAR ($value) tidak boleh melebihi sisa tagihan ($invoice->less_payment_sar).");
                        }
                    }
                },
            ],
             'payment_idr'   => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    $invoice = DB::table('invoices_hotels')
                                ->where('id', $request->invoices_id)
                                ->first();
                    if ($invoice) {
                        if ($value > $invoice->less_payment_idr) {
                            $fail("Nominal Payment IDR ($value) tidak boleh melebihi sisa tagihan ($invoice->less_payment_idr).");
                        }
                    }
                },
            ],
            'desc'=> 'required|string',
            'date_transfer'=> 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $saved_details = $this->repo_payment->CreateOrUpdate($request->all(), null);
        $parent = $this->repo->whereData(['id' => $request->invoices_id])->first();

        $total_bayar_idr_sum = $this->repo_payment->sumDataWhereDinamis(['invoices_id'=>$request->invoices_id],'payment_idr');
        $total_bayar_sar_sum = $this->repo_payment->sumDataWhereDinamis(['invoices_id'=>$request->invoices_id],'payment_sar');
        //
        $final_sar = $total_bayar_sar_sum;
        $less_payment_sar = $parent->total_payment-$total_bayar_sar_sum;
        $final_idr =$total_bayar_idr_sum;
        $less_Payment_idr = $parent->total_payment_rupiah-$total_bayar_idr_sum;
        //
        $updated_data = [ 'final_payment_sar'=>$final_sar, 'less_payment_sar'=>$less_payment_sar,
        'final_payment_idr'=>$total_bayar_idr_sum, 'less_payment_idr'=>$less_Payment_idr];

        $update_sar_payment = $this->repo->CreateOrUpdate($updated_data,$request->invoices_id);
        return $this->autoResponse($saved_details);
    }

    public function updated_row(Request $request)
    {
        $request['invoices_id']= $request->invoices_id_parent;
        $config_curency = ConfigCurrency::first();
        $request['payment_sar']= $request->payment_idr / $config_curency->nominal_rupiah_1_riyal;

        $validator = Validator::make($request->all(), [
            'id'=> 'required|exists:payment_hotels,id',
            'invoices_id' => 'required|exists:invoices_hotels,id',
            // 'payment_sar' => 'required|numeric',
            // 'payment_idr' => 'required|numeric',
              'payment_sar'   => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    $invoice = DB::table('invoices_hotels')
                                ->where('id', $request->invoices_id)
                                ->first();
                    if ($invoice) {
                        if ($value > $invoice->less_payment_sar) {
                            $fail("Nominal Payment SAR ($value) tidak boleh melebihi sisa tagihan ($invoice->less_payment_sar).");
                        }
                    }
                },
            ],
             'payment_idr'   => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    $invoice = DB::table('invoices_hotels')
                                ->where('id', $request->invoices_id)
                                ->first();
                    if ($invoice) {
                        if ($value > $invoice->less_payment_idr) {
                            $fail("Nominal Payment IDR ($value) tidak boleh melebihi sisa tagihan ($invoice->less_payment_idr).");
                        }
                    }
                },
            ],
            'desc'=> 'required|string',
            'date_transfer'=> 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $saved_details = $this->repo_payment->CreateOrUpdate($request->all(), $request->id);
        $parent = $this->repo->whereData(['id' => $request->invoices_id])->first();
        //parent
         $total_bayar_idr_sum = $this->repo_payment->sumDataWhereDinamis(['invoices_id'=>$request->invoices_id],'payment_idr');
        $total_bayar_sar_sum = $this->repo_payment->sumDataWhereDinamis(['invoices_id'=>$request->invoices_id],'payment_sar');
        //
        $final_sar = $total_bayar_sar_sum;
        $less_payment_sar = $parent->total_payment-$total_bayar_sar_sum;
        $final_idr =$total_bayar_idr_sum;
        $less_Payment_idr = $parent->total_payment_rupiah-$total_bayar_idr_sum;
        //
        $updated_data = [ 'final_payment_sar'=>$final_sar, 'less_payment_sar'=>$less_payment_sar,
        'final_payment_idr'=>$total_bayar_idr_sum, 'less_payment_idr'=>$less_Payment_idr];

        $update_sar_payment = $this->repo->CreateOrUpdate($updated_data,$request->invoices_id);
        return $this->autoResponse($saved_details);
    }


     public function deleted_row(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'=> 'required|exists:payment_hotels,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $row_payment = $this->repo_payment->find($request->id);
        $parent = $this->repo->whereData(['id' => $row_payment->invoices_id])->first();
        //parent

        if ($row_payment) {

            $deleted = $this->repo_payment->delete($request->id);

            $total_bayar_idr_sum = $this->repo_payment->sumDataWhereDinamis(['invoices_id'=>$row_payment->invoices_id],'payment_idr');
            $total_bayar_sar_sum = $this->repo_payment->sumDataWhereDinamis(['invoices_id'=>$row_payment->invoices_id],'payment_sar');
            //
            $less_payment_sar = $parent->total_payment-$total_bayar_sar_sum;
            $less_Payment_idr = $parent->total_payment_rupiah-$total_bayar_idr_sum;
            //
            $updated_data = [ 'final_payment_sar'=>$total_bayar_sar_sum, 'less_payment_sar'=>$less_payment_sar,
            'final_payment_idr'=>$total_bayar_idr_sum, 'less_payment_idr'=>$less_Payment_idr];
             $update_sar_payment = $this->repo->CreateOrUpdate($updated_data, $row_payment->invoices_id);
            return $this->autoResponse($deleted);
        }
        return $this->autoResponse($saved_details);
    }

    public function by_id_row(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'=> 'required|exists:payment_hotels,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        $data = $this->repo_payment->whereData(['id' => $request->id])->first();
        return $this->autoResponse($data);
    }


}
