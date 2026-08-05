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

    protected string $phone;
    protected string $invoiceNumber;
    protected string $vaNumber;
    protected ?string $bankName;
    protected ?string $paketName;
    protected float $totPayment;
    protected float $totNominal;
    protected string $user;
    protected string $pass;

    public function __construct(
        string $phone,
        string $invoiceNumber,
        string $vaNumber,
        ?string $bankName,
        ?string $paketName,
        float $totPayment,
        float $totNominal,
        string $user = '',
        string $pass = ''
    ) {
        $this->phone = $phone;
        $this->invoiceNumber = $invoiceNumber;
        $this->vaNumber = $vaNumber;
        $this->bankName = $bankName;
        $this->paketName = $paketName;
        $this->totPayment = $totPayment;
        $this->totNominal = $totNominal;
        $this->user = $user;
        $this->pass = $pass;
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
            . "Sisa: Rp" . number_format($sisa, 0, ',', '.') . "\n"
            . "kunjungi url : https://an.alhidayah.id/ \n";

        if ($this->user !== '' && $this->pass !== '') {
            $message .= "username : {$this->user}\n" . "pass : {$this->pass}\n";
        }

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