<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendVaNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private string $phone,
        private string $invoiceNumber,
        private string $vaNumber,
        private ?string $bankName,
        private ?string $paketName,
        private float $totPayment,
        private float $totNominal,
    ) {
    }

    public function handle(): void
    {
        $sisa = max($this->totNominal - $this->totPayment, 0);

        $message = "Assalamualaikum, berikut update pembayaran Anda:\n\n"
            . "No. Invoice: {$this->invoiceNumber}\n"
            . "Paket: " . ($this->paketName ?: '-') . "\n"
            . "VA {$this->bankName}: {$this->vaNumber}\n"
            . "Total Tagihan: Rp" . number_format($this->totNominal, 0, ',', '.') . "\n"
            . "Sudah Dibayar: Rp" . number_format($this->totPayment, 0, ',', '.') . "\n"
            . "Sisa: Rp" . number_format($sisa, 0, ',', '.');

        $response = Http::withHeaders([
            'Authorization' => env('FONNTE_TOKEN'),
        ])->post('https://api.fonnte.com/send', [
                    'target' => $this->phone,
                    'message' => $message,
                ]);

        Log::info("Notifikasi VA terkirim untuk invoice {$this->invoiceNumber}", [
            'target' => $this->phone,
            'status' => $response->status(),
        ]);
    }
}