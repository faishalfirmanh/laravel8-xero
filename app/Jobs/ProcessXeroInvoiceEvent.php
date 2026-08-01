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
use App\Models\Transaction\VaTransUser;

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

            $this->syncVaTransFromLineItems($invoice, $invoiceNumber);

            Log::info("Invoice {$invoiceNumber} berhasil diupdate ke {$fileName}");
        } catch (\Throwable $e) {
            Log::error('Gagal proses webhook invoice Xero: ' . $e->getMessage());
            $this->fail($e);
        }
    }


    protected function syncVaTransFromLineItems(array $invoice, string $invoiceNumber): void
    {
        $lineItems = $invoice['LineItems'] ?? [];
        $totalNominal = $invoice['Total'] ?? 0;
        $contactName = $invoice['Contact']['Name'] ?? null;

        foreach ($lineItems as $item) {
            $description = $item['Description'] ?? '';

            $vaInfo = $this->extractVaInfo($description);

            if (!$vaInfo) {
                continue; // bukan baris VA, lewati
            }

            $vaNumber = $vaInfo['va_number'];
            $bankName = $vaInfo['bank_name'];
            $paketName = $this->extractPaketName($item);
            $payment = $item['LineAmount'] ?? 0;

            $existing = VaTransUser::where('va_number', $vaNumber)->first();

            if ($existing) {
                // Sudah ada -> tinggal update payment & total_nominal
                Log::info('sukuses update to db ' . $invoiceNumber);
                $existing->update([
                    'payment' => $payment,
                    'total_nominal' => $totalNominal,
                ]);
            } else {
                // Belum ada -> buat record baru lengkap
                Log::info('sukuses create to db ' . $invoiceNumber);
                VaTransUser::create([
                    'inv_number' => $invoiceNumber,
                    'va_number' => $vaNumber,
                    'paket_name' => $paketName,
                    'bank_name' => $bankName,
                    'name_contact' => $contactName,
                    'payment' => $payment,
                    'total_nominal' => $totalNominal,
                ]);
            }
        }
    }


    protected function extractVaInfo(string $description): ?array
    {
        $upper = strtoupper($description);

        // 1) Cari keyword VIRTUAL ACCOUNT / VA (word boundary), ambil sisa teks setelahnya
        if (!preg_match('/\b(?:VIRTUAL\s*ACCOUNT|VA)\b\s*[:\-]?\s*(.+)$/', $upper, $matches)) {
            return null;
        }

        $remainder = trim($matches[1]);

        // 2) Angka di ujung string = nomor VA, sisanya = nama bank
        if (!preg_match('/^([A-Z\s]+?)\s+(\d{4,})\s*$/', $remainder, $parts)) {
            return null;
        }

        return [
            'bank_name' => trim($parts[1]),
            'va_number' => trim($parts[2]),
        ];
    }

    protected function extractPaketName(array $item): ?string
    {
        foreach ($item['Tracking'] ?? [] as $tracking) {
            if (strtolower($tracking['Name'] ?? '') === 'nama paket') {
                return $tracking['Option'] ?? null;
            }
        }

        return null;
    }
}