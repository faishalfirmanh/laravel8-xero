<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\MekariAuthService;

class SendVaNotifMekariJob implements ShouldQueue
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

    public function handle(MekariAuthService $mekari): void
    {
        $sisa = max($this->totNominal - $this->totPayment, 0);


        $bodyParams = [
            ['key' => '1', 'value' => 'invoice_number', 'value_text' => $this->invoiceNumber],
            ['key' => '2', 'value' => 'paket_name', 'value_text' => $this->paketName ?: '-'],
            ['key' => '3', 'value' => 'va_info', 'value_text' => trim(($this->bankName ?? '') . ' ' . $this->vaNumber)],
            ['key' => '4', 'value' => 'total_tagihan', 'value_text' => 'Rp' . number_format($this->totNominal, 0, ',', '.')],
            ['key' => '5', 'value' => 'sudah_dibayar', 'value_text' => 'Rp' . number_format($this->totPayment, 0, ',', '.')],
            ['key' => '6', 'value' => 'sisa', 'value_text' => 'Rp' . number_format($sisa, 0, ',', '.')],
        ];

        $payload = [
            'to_name' => $this->user ?: 'Jamaah',
            'to_number' => $this->phone,
            'message_template_id' => config('services.mekari.va_template_id'),
            'channel_integration_id' => config('services.mekari.channel_integration_id'),
            'language' => ['code' => 'id'],
            'parameters' => [
                'buttons' => [],
                'body' => $bodyParams,
            ],
        ];

        $response = $mekari->sendWhatsappBroadcastDirect($payload);

        if ($response->failed()) {
            Log::error("Gagal kirim notifikasi VA (Mekari) invoice {$this->invoiceNumber}: HTTP {$response->status()} - {$response->body()}");
            return;
        }

        Log::info("Notifikasi VA terkirim (Mekari) untuk invoice {$this->invoiceNumber}", [
            'target' => $this->phone,
            'status' => $response->status(),
        ]);
    }
}