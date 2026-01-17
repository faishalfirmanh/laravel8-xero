<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
// Import Controller/Helper Anda untuk sync
use App\Http\Controllers\Xero\XeroSyncInvoicePaidController;

class ProcessXeroWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $eventData;

    public function __construct($eventData)
    {
        $this->eventData = $eventData;
    }

    public function handle()
    {
        // $this->eventData berisi array event dari Xero
        // Contoh: [['resourceId' => 'xxx', 'eventType' => 'UPDATE', ...]]

        foreach ($this->eventData as $event) {
            Log::info('Job Processing: xero to annamiroh' . json_encode($event));

            // Cek apakah ini event INVOICE
            if ($event['eventCategory'] === 'INVOICE') {
                $invoiceId = $event['resourceId'];
                // PANGGIL LOGIKA SYNC ANDA DI SINI
                // Contoh memanggil method yang sudah kita buat sebelumnya:
                try {
                    $xeroCtrl = new XeroSyncInvoicePaidController();
                    // Buat function khusus syncById($id) di controller tsb
                    // Jangan pakai getInvoicePaidArrival yang loop semua data!
                    $xeroCtrl->syncSingleInvoice($invoiceId);
                    Log::info("Sukses Sync Invoice ID: $invoiceId");
                } catch (\Exception $e) {
                    Log::error("Gagal Sync Invoice ID $invoiceId: " . $e->getMessage());
                    // Optional: throw $e; agar job me-retry otomatis
                }
            }
        }
    }
}
