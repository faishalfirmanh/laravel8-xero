<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\LogHistoryRepository;
use App\Traits\ApiResponse;
use Illuminate\Support\Carbon;
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
            'page' => 'required|integer',
            'limit' => 'required|integer',
            'kolom_name' => 'required|string',
            'keyword' => 'nullable|string',
            'date_start' => 'nullable|date|required_with:date_end',
            'date_end' => 'nullable|date|required_with:date_start|after_or_equal:date_start',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $where = [];

        if ($request->filled('date_start') && $request->filled('date_end')) {
            $where[] = ['created_at', '>=', Carbon::parse($request->date_start)->startOfDay()->toDateTimeString()];
            $where[] = ['created_at', '<', Carbon::parse($request->date_end)->addDay()->startOfDay()->toDateTimeString()];
        }

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
