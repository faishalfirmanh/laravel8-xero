<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Repository\Alhid\JamaahAlhidRepo;
use App\Models\Alhidayah\DataJamaahAlhidayah;
use Validator;
use Illuminate\Http\Request;

use App\Traits\ApiResponse;

class JamaahAlhidController extends Controller//from xero when create transaction invoice hotel
{
    //

    protected $repo;
    use ApiResponse;
    public function __construct(JamaahAlhidRepo $repo)
    {
        $this->repo = $repo;
    }


    public function getById(Request $request, $idjamaah)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|in:namiroh123',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $data = $this->repo->whereData(['id_jamaah' => $idjamaah])->first();
        return $this->autoResponse($data);
    }

}
