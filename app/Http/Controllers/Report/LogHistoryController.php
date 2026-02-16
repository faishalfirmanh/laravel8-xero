<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\LogHistoryRepository;
use App\Traits\ApiResponse;
use Validator;

class LogHistoryController extends Controller
{
    use ApiResponse;

    protected $repo;

    public function __construct(LogHistoryRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index()
    {
        return view('admin.report.log_history');
    }

    public function getData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page'       => 'required|integer',
            'limit'      => 'required|integer',
            'kolom_name' => 'required|string',
            'keyword'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $where = [];

        if ($request->keyword != null) {
            $data = $this->repo->searchData(
                $where,
                $request->limit,
                $request->page,
                $request->kolom_name,
                strtoupper($request->keyword)
            );
        } else {
            $data = $this->repo->getAllDataWithDefault(
                $where,
                $request->limit,
                $request->page,
                'id',
                'desc'
            );
        }

        return $this->autoResponse($data);
    }
}
