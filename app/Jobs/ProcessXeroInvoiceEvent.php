<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\XeroService;

class ProcessXeroInvoiceEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $event;

    public function __construct(array $event)
    {
        $this->event = $event;
    }

    public function handle(XeroService $xero)
    {
        $invoiceId = $this->event['resourceId'] ?? null;
        $tenantId = $this->event['tenantId'] ?? null;

        if (!$invoiceId || !$tenantId) {
            return;
        }

        try {
            // Ambil detail invoice terbaru dari Xero API
            $invoice = $xero->getInvoice($tenantId, $invoiceId);

            $invoiceNumber = $invoice['InvoiceNumber'] ?? $invoiceId; // contoh: INV-1219
            $safeName = preg_replace('/[^A-Za-z0-9\-_]/', '', $invoiceNumber);
            $fileName = $safeName . '.json';

            Storage::disk('local')->put(
                'xero-invoices/' . $fileName,
                json_encode($invoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            Log::info("Invoice {$invoiceNumber} berhasil diupdate ke {$fileName}");
        } catch (\Throwable $e) {
            Log::error('Gagal proses webhook invoice Xero: ' . $e->getMessage());
            $this->fail($e);
        }
    }
}