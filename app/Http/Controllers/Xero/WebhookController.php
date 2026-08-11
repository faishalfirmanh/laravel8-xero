<?php

namespace App\Http\Controllers\Xero;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessXeroInvoiceEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessXeroWebhook;

class WebhookController extends Controller
{
    //


    public function handleXero(Request $request)
    {
        Log::info('xero webhook awal mulai berjalan WebhookController');
        $rawPayload = $request->getContent();
        $signature = $request->header('x-xero-signature');
        $webhookKey = config('services.xero.webhook_key');

        $computed = base64_encode(hash_hmac('sha256', $rawPayload, $webhookKey, true));

        // hash_equals untuk mencegah timing attack
        if (!$signature || !hash_equals($computed, $signature)) {
            Log::warning('Xero webhook: signature tidak valid');
            return response('', 401);
        }

        $data = json_decode($rawPayload, true) ?? [];

        $events = $data['events'] ?? [];
        $invoiceEvents = [];
        foreach ($events as $event) {
            if (($event['eventCategory'] ?? null) === 'INVOICE') {
                $resourceId = $event['resourceId'] ?? null;
                if ($resourceId) {
                    $invoiceEvents[$resourceId] = $event; // event terakhir untuk resourceId yang sama akan menimpa yang sebelumnya
                }
            }
        }
        Log::info('Xero webhook: ' . count($events) . ' event masuk, ' . count($invoiceEvents) . ' invoice unik akan diproses.');
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
