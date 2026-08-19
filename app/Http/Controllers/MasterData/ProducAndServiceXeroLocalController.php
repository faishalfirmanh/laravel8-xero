<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Repository\Expenses\PODBillRepository;
use App\Http\Repository\MasterData\Finance\ItemPaketAllXeroRepo;
use App\Http\Repository\MasterData\TrackingRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use App\Models\Expenses\Purchase\Bill\DBill;
use DB;
use Illuminate\Http\Request;

use App\Http\Repository\MasterData\CoaRepo;


use Str;
use Validator;
use App\Traits\ApiResponse;



class ProducAndServiceXeroLocalController extends Controller
{

    use ApiResponse;
    protected $repo, $repo_trans_all, $repo_d_bill, $repo_tracking;

    public function __construct(
        ItemPaketAllXeroRepo $repo,
        TransCoaRepo $transCoaRepo,
        PODBillRepository $repo_d_bill,
        TrackingRepo $repo_tracking
    ) {
        $this->repo = $repo;
        $this->repo_trans_all = $transCoaRepo;
        $this->repo_d_bill = $repo_d_bill;
        $this->repo_tracking = $repo_tracking;
    }

    function generateRandom4Digit()
    {
        return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'code' => 'required|string',
            'nama_paket' => 'required|string',
            'desc' => 'nullable|string',
            'desc_salles' => 'nullable|string',
            'account_id_purchase' => 'nullable|integer|exists:coas,id',
            'account_id_salles' => 'required|integer|exists:coas,id',
            'price_purchase' => 'nullable|integer',
            'price_sales' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors(), 500);
        }

        $request->merge([
            'uuid_product_and_service' => 'from_web',
            'purchase_AccountCode' => 'from_web',
            'sales_AccountCode' => 'from_web',
            'tax_rate_salles' => 0,
            'tax_rate_purchase' => 0,
            'price_purchase' => $request->price_purchase ?? 0
        ]);
        $request['uuid_proudct_and_service'] = 'from_web';

        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);

        if ($request->id == null) {
            $cari = $this->repo_tracking->whereData(['name_parent_category' => 'Nama Paket'])->first();

            if ($cari) {
                // ambil isi lines_category yang sudah ada (array), fallback [] kalau masih null
                $lines = $cari->lines_category ?? [];

                if ($request->id == null) {
                    // ===== CREATE: generate uuid baru, append line baru =====
                    $usedUuids = $this->getAllUsedUuids();
                    $newUuid = $this->generateUniqueUuid($usedUuids);

                    $lines[] = [
                        'id_parent' => $cari->id,
                        'item_name_category' => $request->nama_paket,
                        'item_uuid_category' => $newUuid,
                    ];

                    $cari->lines_category = $lines;
                    $cari->save();

                    // simpan uuid ini ke row paket yang baru dibuat, biar bisa dilacak balik saat diedit nanti
                    $saved->uuid_tracking_category = $newUuid;
                    $saved->save();

                } else {
                    $existingUuid = $saved->uuid_tracking_category ?? null;

                    if ($existingUuid) {
                        $updated = false;
                        foreach ($lines as &$line) {
                            if (($line['item_uuid_category'] ?? null) === $existingUuid) {
                                $line['item_name_category'] = $request->nama_paket;
                                $updated = true;
                                break;
                            }
                        }
                        unset($line);
                        if ($updated) {
                            $cari->lines_category = $lines;
                            $cari->save();
                        }
                    }
                }
            }
        }

        return $this->autoResponse($saved);
    }

    private function getAllUsedUuids(): array
    {
        $uuids = [];

        DB::table('tracking_categories')
            ->select('lines_category')
            ->whereNotNull('lines_category')
            ->orderBy('id')
            ->each(function ($row) use (&$uuids) {
                foreach (json_decode($row->lines_category, true) ?? [] as $line) {
                    if (!empty($line['item_uuid_category'])) {
                        $uuids[] = $line['item_uuid_category'];
                    }
                }
            });

        return array_unique($uuids);
    }
    private function generateUniqueRandom4Digit(array &$usedUuids): string
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $uuid = (string) Str::uuid();

            if (!in_array($uuid, $usedUuids, true)) {
                $usedUuids[] = $uuid;
                return $uuid;
            }
        }

        throw new \RuntimeException('Gagal menghasilkan item_uuid_category unik — coba lagi.');
    }


    public function getAllPaginateSelect2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',

        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        //dd($request->menu);
        $where = [];//$request->type ? ['account_type' => $request->type] : [];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, 10, $request->page, 'nama_paket', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, 10, $request->page, 'nama_paket', 'ASC');//getDataPaginate("name",10,$request->keyword);
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
            'type' => 'nullable|string|in:EXPENSE,REVENUE,ALL'
        ]);


        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        //dd($request->menu);
        $where = []; //$request->type == 'ALL' || $request->type == null ? [] : ['account_type' => $request->type];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'nama_paket', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'id', 'DESC');//getDataPaginate("name",10,$request->keyword);
        }

        return $this->autoResponse($data);

    }


    public function getListTransByCoaId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_coa' => 'required|exists:coas,id',
            'page' => 'required|integer',
            'limit' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $where = ['uuid_coa' => $request->code_coa];

        $data = $this->repo_trans_all->getAllDataWithDefault(
            $where,
            $request->limit,
            $request->page,
            'date_transaction',
            'DESC',
            ['d_bill', 'd_bill.getParent', 'd_bank', 'd_bank.getParent', 'd_invoice', 'd_invoice.getParent']
        );

        //$data['menu']= $request->menu;
        return $this->autoResponse($data);
        //return $this->success($data);
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
        $data = $this->repo->WhereDataWith(['getCoaSalles', 'getCoaPurchase'], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }

    //
}
