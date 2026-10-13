<?php

namespace App\Http\Controllers\Xero;

use App\Http\Controllers\Controller;
use App\Http\Repository\MasterData\DataJamaahXeroRepository;
use App\Http\Repository\MasterData\Finance\InvoiceAllXeroRepo;
use App\Http\Repository\MasterData\Finance\ItemPaketAllXeroRepo;
use App\Http\Repository\Revenue\InvoiceDXeroLocalRepo;
use App\Http\Repository\Revenue\InvoiceXeroLocalRepo;
use App\Jobs\ProcessXeroInvoiceEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessXeroWebhook;

class WebhookController extends Controller
{
    //

    protected $repo_jamaah_alhid, $repo_jamaah_xero, $repo_item, $repo_p_invoice, $repo_d_invoice, $repo_trans_coa, $repo_trans_bank, $repo_bank, $repo_jamaah;
    public function __construct(
        ItemPaketAllXeroRepo $repo_item,
        InvoiceXeroLocalRepo $repo_p_invoice,
        InvoiceDXeroLocalRepo $repo_trans_coa,
        DataJamaahXeroRepository $repo_jamaah_xero

    ) {
        $this->repo_item = $repo_item;
        $this->repo_p_invoice = $repo_p_invoice;
        $this->repo_trans_coa = $repo_trans_coa;
        $this->repo_jamaah_xero = $repo_jamaah_xero;
    }


   public function handleXero(Request $request)
{
    Log::info('[XeroWebhook] request masuk');

    $rawPayload = $request->getContent();
    $signature  = $request->header('x-xero-signature');
    $webhookKey = config('services.xero.webhook_key');

    if (!$signature || !$webhookKey) {
        Log::warning('[XeroWebhook] signature atau webhook key kosong');
        return response('', 401);
    }

    $computed = base64_encode(hash_hmac('sha256', $rawPayload, $webhookKey, true));

    if (!hash_equals($computed, $signature)) {
        Log::warning('[XeroWebhook] signature tidak valid');
        return response('', 401);
    }

    $data   = json_decode($rawPayload, true) ?? [];
    $events = $data['events'] ?? [];

    // Satu event per resourceId, event terakhir yang menang
    $invoiceEvents = [];
    foreach ($events as $event) {
        if (($event['eventCategory'] ?? null) !== 'INVOICE') {
            continue;
        }
        $resourceId = $event['resourceId'] ?? null;
        if ($resourceId) {
            $invoiceEvents[$resourceId] = $event;
        }
    }

    Log::info('[XeroWebhook] ' . count($events) . ' event, ' . count($invoiceEvents) . ' invoice unik.');

    foreach ($invoiceEvents as $event) {
        ProcessXeroInvoiceEvent::dispatch($event);
    }

    return response('', 200);
}

    public function handleXeross(Request $request)
    {
        // 1. Ambil Signature dari Header
        $xeroSignature = $request->header('x-xero-signature');

        // 2. Ambil Body Request MENTAH (Raw Content)
        // Penting: Jangan gunakan $request->all(), hash harus dari raw body
        $payload = $request->getContent();

        // 3. Validasi Keamanan (HMAC-SHA256)
        $webhookKey = env('WEB_HOOK_XERO');

        // Rumus validasi Xero
        $computedSignature = base64_encode(
            hash_hmac('sha256', $payload, $webhookKey, true)
        );

        // Bandingkan Signature Xero vs Hitungan Kita
        if (!hash_equals($xeroSignature, $computedSignature)) {
            // Jika tidak sama, berarti request palsu/hacker
            Log::warning('Xero Webhook Signature Invalid!');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 4. Jika Valid, Proses Event
        $data = json_decode($payload, true);

        // Cek apakah ada events
        if (isset($data['events']) && count($data['events']) > 0) {
            // Dispatch ke Queue (Background Process)
            ProcessXeroWebhook::dispatch($data['events']);
            Log::info('Webhook Xero diterima dan dimasukkan antrian.');
        }

        // 5. Response 200 OK (WAJIB < 5 Detik)
        // Jika tidak return 200, Xero akan menganggap gagal dan mengirim ulang
        return response()->json([], 200);
    }
}
