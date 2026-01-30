<?php

namespace App\Http\Controllers\Transaction\Expenses;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Repository\Expenses\DPackageExpensesRepository;
use App\Http\Repository\Expenses\PackageExpensesRepository;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\Revenue\Hotel\InvoicesHotel;
use App\Models\Config\ConfigCurrency;
use Barryvdh\DomPDF\Facade\Pdf;


class ExpensesPackageApiController extends Controller
{
    //
    protected $repo, $repo_detail, $service_global;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        PackageExpensesRepository $repo,
        DPackageExpensesRepository $repo_detail,
        GlobalService $service_global
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->service_global = $service_global;
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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'name_paket', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'name_paket', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'=>'nullable|integer',
            'uuid_paket_item' => 'required|string',
            'code_paket' => 'required|string',
            'name_paket' => 'required|string',
            'nominal_purchase' => 'required|numeric',
            'nominal_sales' => 'required|numeric',
            'nominal_profit' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        $saved_i = $this->repo->CreateOrUpdate($request->all(), $request->id);
       // $saved_details = $this->repo_detail->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($saved_i);
    }


    public function deleteInvoiceReveueHotel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:invoices_hotels,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd($request->id);
        $detail = DetailInvoicesHotel::where('invoice_id', $request->id)->delete();
        $data = InvoicesHotel::where('id', $request->id)->delete();
        if ($detail) {
            return response()->json(['msg' => 'success'], 200);
        } else {
            return response()->json(['msg' => 'error'], 500);
        }

    }

    public function printInvoice(Request $request,$id)
    {
        $invoice = InvoicesHotel::with(['details','payments'])->find($id);
        //dd(Auth::guard('sanctum')->user());

        $data = [
            'invoice' => $invoice,
            'title' => 'Invoice #' . $invoice->no_invoice_hotel,
            'date' => date('d-m-Y'),
           // 'cetak_by'=>
        ];
        $pdf = Pdf::loadView('pdf.invoice_hotel_print', $data);
        $pdf->setPaper('A4', 'portrait');
        //return $pdf->download('Invoice-'.$invoice->no_invoice_hotel.'.pdf');
        return $pdf->stream('Invoice-'.$invoice->no_invoice_hotel.'.pdf');//tampil
    }

    public function getTotalAmount(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'date_start' => 'required|date',
            'date_end'=>  'required|date',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        $data_sar = $this->repo->sumWhereDateRange(
            'total_payment',
            [],
            [
                'date_start'=>$request->date_start,
                'date_end'=>$request->date_end,
            ],
            'date_transaction');
        $data_rp = $this->repo->sumWhereDateRange(
            'total_payment_rupiah',
            [],
            [
                'date_start'=>$request->date_start,
                'date_end'=>$request->date_end,
            ],
            'date_transaction');

        //UANG DITERIMA
         $data_rp_paid = $this->repo->sumWhereDateRange(
            'final_payment_idr',
            [],
            [
                'date_start'=>$request->date_start,
                'date_end'=>$request->date_end,
            ],
            'date_transaction');

        $data_rp_remain = $this->repo->sumWhereDateRange(
            'less_payment_idr',
            [],
            [
                'date_start'=>$request->date_start,
                'date_end'=>$request->date_end,
            ],
            'date_transaction');
        $final = [
            'tanggal_awal'=>$request->date_start,
            'tanggal_akhir'=>$request->date_end,
            'sar'=>$data_sar,
            'rupiah'=> $data_rp,
            'payment_idr'=>$data_rp_paid,
            'remaining_idr'=>$data_rp_remain
        ];
        return $this->autoResponse($final);
    }


    public function getInvoiceReveueHotel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:invoices_hotels,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        $data = $this->repo->WhereDataWith(['details'], ['id' => $request->id])->first();
        return $this->autoResponse($data);

    }






}
