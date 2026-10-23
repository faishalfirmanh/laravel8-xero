<?php

namespace App\Http\Controllers\Xero;
use App\Http\Repository\MasterData\CoaRepo;
use App\Jobs\SyncXeroPaketJob;
use App\Models\Xero\XeroRequestUsedLimit;
use App\Services\XeroService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\ConfigRefreshXero;
use App\Models\InvoicesAllFromXero;
use Validator;
use App\Traits\ApiResponse;
use App\Models\MasterData\ItemDetailInvoices;
use App\Models\ItemsPaketAllFromXero;
use Illuminate\Support\Str;
use App\Services\GlobalService;
use App\Services\XeroRateLimitService;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\TryCatch;

use App\Jobs\ExportXeroInvoiceAttachmentsJob;

class XeroDownloadInvoiceController extends Controller
{
    use ApiResponse;
    protected $rateLimiter;
    use ConfigRefreshXero;
    protected $global, $coas;

    public function __construct(XeroRateLimitService $rateLimiter, GlobalService $global, CoaRepo $coas)
    {
        $this->rateLimiter = $rateLimiter;
        $this->global = $global;
        $this->coas = $coas;
    }
    public function startExport(Request $request)
    {
        $tenantId = $request->input('tenant_id', config('services.xero.tenant_id'));

        $id = DB::table('xero_attachment_exports')->insertGetId([
            'tenant_id' => $tenantId,
            'status' => 'pending',
            'requested_by' => optional($request->user_login)->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ExportXeroInvoiceAttachmentsJob::dispatch($id, $tenantId);

        Log::info('[XeroExport] permintaan dibuat', ['export_id' => $id]);

        return response()->json(['status' => true, 'export_id' => $id]);
    }


    public function testXeroExport(XeroService $xero)
    {
        set_time_limit(0); // sementara, untuk pengujian lokal

        //$tenantId = config('services.xero.tenant_id');
        $accessToken = $xero->getValidToken2()["access_token"];
        $tenantId = $xero->getTenantIdV2($accessToken);

        if (empty($tenantId)) {
            return response()->json(['message' => 'Xero Tenant ID belum tersedia'], 500);
        }

        $id = DB::table('xero_attachment_exports')->insertGetId([
            'tenant_id' => $tenantId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ExportXeroInvoiceAttachmentsJob($id, $tenantId))->handle($xero);

        $row = DB::table('xero_attachment_exports')->find($id);
        $quota = XeroRequestUsedLimit::where('tracking_date', now()->toDateString())->first();

        return response()->json([
            'export_id' => $id,
            'status' => $row->status,
            'total_invoice' => $row->total_invoice,
            'total_file' => $row->total_file,
            'error' => $row->error,
            'file' => $row->file_path ? storage_path('app/' . $row->file_path) : null,
            'quota' => $quota ? [
                'used_min' => $quota->total_request_used_min,
                'used_day' => $quota->total_request_used_day,
                'available_min' => $quota->available_request_min,
                'available_day' => $quota->available_request_day,
            ] : null,
        ]);
    }

    // GET /xero/invoice-attachments/export/{id}
    public function exportStatus($id)
    {
        $row = DB::table('xero_attachment_exports')->find($id);
        if (!$row) {
            return response()->json(['status' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        return response()->json([
            'status' => true,
            'export_status' => $row->status,
            'total_invoice' => $row->total_invoice,
            'total_file' => $row->total_file,
            'error' => $row->error,
            'download_url' => $row->status === 'done' ? route('xero.export.download', $id) : null,
        ]);
    }

    // GET /xero/invoice-attachments/export/{id}/download
    public function exportDownload($id)
    {
        $row = DB::table('xero_attachment_exports')->find($id);
        if (!$row || $row->status !== 'done' || !Storage::exists($row->file_path)) {
            abort(404);
        }

        return response()->download(Storage::path($row->file_path), 'invoice_attachments.zip');
    }



}
