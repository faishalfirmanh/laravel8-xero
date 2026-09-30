<?php

namespace App\Http\Controllers\Toko\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Repository\MasterData\Toko\ProductUomRepo;
use App\Models\ItemsPaketAllFromXero;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class ProductUomController extends Controller
{
    use ApiResponse;

    protected $repo;

    public function __construct(ProductUomRepo $repo)
    {
        $this->repo = $repo;
    }

    public function getAll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'kolom_name' => 'required|string',
            'limit' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->autoResponse($validator->errors());
        }

        $query = $this->repo->model->with(['item', 'unit']);
        if ($request->keyword !== null) {
            $keyword = strtoupper($request->keyword);
            $query->whereHas('item', function ($itemQuery) use ($keyword) {
                $itemQuery->where('nama_paket', 'like', '%' . $keyword . '%')
                    ->orWhere('code', 'like', '%' . $keyword . '%');
            });
        }

        return $this->autoResponse($query->orderBy('id', 'DESC')->paginate($request->limit));
    }

    public function getById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:product_uoms,id',
        ]);

        if ($validator->fails()) {
            return $this->autoResponse($validator->errors());
        }

        return $this->autoResponse($this->repo->model->with(['item', 'unit'])->find($request->id));
    }

    public function store(Request $request)
    {
        $id = $request->id ?: null;
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:product_uoms,id',
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items_paket_all_from_xeros', 'id')->where(function ($query) {
                    $query->where('is_item_product', true);
                }),
            ],
            'unit_satuan_id' => 'required|integer|exists:units,id',
            'conversion_factor' => 'required|numeric|min:0.0001',
            'sell_price' => 'required|numeric|min:0',
        ]);

        $validator->after(function ($validator) use ($request, $id) {
            $exists = $this->repo->model
                ->where('item_id', $request->item_id)
                ->where('unit_satuan_id', $request->unit_satuan_id)
                ->when($id, function ($query) use ($id) {
                    $query->where('id', '!=', $id);
                })
                ->exists();

            if ($exists) {
                $validator->errors()->add('unit_satuan_id', 'Unit sudah terdaftar untuk produk ini.');
            }
        });

        if ($validator->fails()) {
            return $this->autoResponse($validator->errors());
        }

        $saved = $this->repo->CreateOrUpdate($request->only([
            'item_id',
            'unit_satuan_id',
            'conversion_factor',
            'sell_price',
        ]), $id);

        return $this->autoResponse($saved);
    }

    public function getProducts(Request $request)
    {
        $keyword = trim((string) $request->get('keyword', ''));

        $products = ItemsPaketAllFromXero::query()
            ->where('is_item_product', true)
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('nama_paket', 'like', '%' . $keyword . '%')
                        ->orWhere('code', 'like', '%' . $keyword . '%');
                });
            })
            ->orderBy('nama_paket')
            ->paginate(20, ['id', 'code', 'nama_paket'], 'page', $request->get('page', 1));

        return $this->autoResponse($products);
    }
}
