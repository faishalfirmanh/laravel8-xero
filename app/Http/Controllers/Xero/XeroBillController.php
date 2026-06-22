<?php

namespace App\Http\Controllers\Xero;

use App\ConfigRefreshXero;
use App\Http\Controllers\Controller;
use App\Jobs\SyncBillJob;
use App\Services\GlobalService;
use App\Services\XeroRateLimitService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Validator;

class XeroBillController extends Controller
{
    use ApiResponse;
    protected $rateLimiter;
    use ConfigRefreshXero;
    protected $global;

    public function __construct(XeroRateLimitService $rateLimiter, GlobalService $global)
    {
        $this->rateLimiter = $rateLimiter;
        $this->global = $global;
    }

    public function getBills(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_sync' => 'required|numeric|between:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        if ($request->is_sync == 1) {
            SyncBillJob::dispatch($tokenData);
        }
    }
}
