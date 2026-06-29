<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Repository\Expenses\PODBillRepository;
use App\Http\Repository\Transaction\TransCoaRepo;
use App\Models\Expenses\Purchase\Bill\DBill;
use Illuminate\Http\Request;

use App\Http\Repository\MasterData\CoaRepo;


use Validator;
use App\Traits\ApiResponse;



class CoaController extends Controller
{

    use ApiResponse;
    protected $repo, $repo_trans_all, $repo_d_bill;

    public function __construct(
        CoaRepo $repo,
        TransCoaRepo $transCoaRepo,
        PODBillRepository $repo_d_bill
    ) {
        $this->repo = $repo;
        $this->repo_trans_all = $transCoaRepo;
        $this->repo_d_bill = $repo_d_bill;
    }

    function generateRandom4Digit()
    {
        return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'account_type' => 'required|string|regex:/^(current_asset|fixed_asset|revenue|inventory|non_current_asset|prepayment|equity|description|direct_cost|expense|overhead|current_liability|liability|non_current_liability|other_income|sales)$/i',
            'account_type' => [
                'required',
                'string',
                'regex:/^(CURRENT|FIXED|EQUITY|EXPENSE|DIRECTCOSTS|CURRLIAB|OTHERINCOME|REVENUE)$/i'
            ],
            // 'code' => [
            //     'required',
            //     'string',
            //      Rule::unique('coas', 'code')->ignore($request->id)
            // ],
            'name' => 'required|string',
            'desc' => 'nullable|string'

        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        if ($request->id == NULL) {
            $request->merge(['code' => self::generateRandom4Digit()]);
        }

        $request['created_by'] = $request->user_login->id;
        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($saved);
    }



    public function getAllPaginateSelect2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'type' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        //dd($request->menu);
        $where = $request->type ? ['account_type' => $request->type] : [];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, 10, $request->page, 'name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, 10, $request->page, 'name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        //$data['menu']= $request->menu;
        return $this->autoResponse($data);
        //return $this->success($data);
    }

    public function getAllPaginate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'kolom_name' => 'required|string',
            'limit' => 'required|integer',
            'type' => 'nullable|string|in:EXPENSE,REVENUE,ASSET,ALL,LIABILITIES,EQUITY'
        ]);


        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        //dd($request->menu);
        $where = self::chekWhere($request->type);
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'code', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'code', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }

        return $this->autoResponse($data);

    }

    public function chekWhere($requestType)
    {
        // Jika ALL, kembalikan array kosong (agar ->where([]) tidak memfilter apapun)
        if ($requestType == 'ALL' || empty($requestType)) {
            return [];
        }

        // Kembalikan Closure berisi Query Builder
        return function ($query) use ($requestType) {
            if ($requestType == 'ASSET') {
                $query->whereIn('account_type', ['CURRENT', 'FIXED', 'BANK']);
            } else if ($requestType == 'LIABILITIES') {
                $query->where('account_type', 'CURRLIAB');
            } else if ($requestType == 'EQUITY') {
                $query->where('account_type', 'EQUITY');
            } else if ($requestType == 'EXPENSE') {
                // Catatan: Saya ubah 'EQUITY' menjadi 'EXPENSE' karena sepertinya di script asli Anda ada typo
                $query->whereIn('account_type', ['EXPENSE', 'DIRECTCOSTS']);
            } else if ($requestType == 'REVENUE') {
                $query->whereIn('account_type', ['OTHERINCOME', 'REVENUE']);
            }
        };
    }

    public function getListTransByCoaId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_coa' => 'required|exists:coas,id',
            'page' => 'required|integer',
            'limit' => 'required|integer',
            'keyword' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $where = ['uuid_coa' => $request->code_coa];
        $relations = ['d_bill', 'd_bill.getParent', 'd_bank', 'd_bank.getParent', 'd_invoice', 'd_invoice.getParent'];

        // DEFINISIKAN KOLOM PENCARIAN (TABEL UTAMA + RELASI)
        $search_columns = [
            // 1. Kolom di Tabel Utama
            'nominal',
            'date_transaction' => 'date',

            // 2. Kolom di Tabel Relasi (Format: 'NamaRelasi' => ['kolom1', 'kolom2'])
            'd_bill' => ['desc'],
            'd_bill.getParent.getContactFrom' => ['full_name'],

            'd_bank' => ['desc'],
            'd_bank.getParent.getContactFrom' => ['full_name'],

            'd_invoice' => ['desc'],
            'd_invoice.getParent' => ['contact_name'],
        ];

        // LOGIKA PENARIKAN DATA
        if ($request->keyword) {
            $data = $this->repo_trans_all->searchDataMultiColumn(
                $where,
                $request->limit, // Menggunakan limit dari request, bukan manual 10
                $search_columns,
                $request->keyword,
                $relations
            );
        } else {
            $data = $this->repo_trans_all->getAllDataWithDefault(
                $where,
                $request->limit,
                $request->page,
                'date_transaction',
                'ASC',
                $relations
            );
        }

        return $this->autoResponse($data);
    }



    public function delete(Request $request)
    {
        $id = $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:coas,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $cekDbill = $this->repo_d_bill->whereData(['account_id_coa' => $request->id])->first();
        if ($cekDbill) {
            return $this->error("coa sedang di gunakan pada bills", 407, "failed deleted");
        }

        $blog = $this->repo->find($id);

        if ($blog) {
            $data = $this->repo->delete($id);
            return $this->autoResponse($data);
        }

        return $this->error('hotel not found', 404);
    }


    public function detail(Request $request)
    {
        $data = $this->repo->find($request->id);
        return $this->autoResponse($data);
    }

    //
}
