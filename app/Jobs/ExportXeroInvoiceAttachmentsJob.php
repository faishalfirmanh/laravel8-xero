<?php

namespace App\Jobs;

use App\Services\XeroService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Carbon\Carbon;

class ExportXeroInvoiceAttachmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 1800;

    private int $exportId;
    private string $tenantId;

    public function __construct(int $exportId, string $tenantId)
    {
        $this->exportId = $exportId;
        $this->tenantId = $tenantId;
    }

    public function handle(XeroService $xero): void
    {
        $this->update(['status' => 'processing']);

        $from = $this->resumeFrom();
        $relPath = 'tmp/xero_attachments/export_' . $this->exportId . '.zip';
        Storage::makeDirectory('tmp/xero_attachments');

        Log::info('[XeroExport] mulai', [
            'export_id' => $this->exportId,
            'resume_from' => $from ? $from->toDateString() : null,
        ]);

        $zip = new ZipArchive();
        if ($zip->open(Storage::path($relPath), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat ZIP');
        }

        $totalInv = 0;
        $totalFile = 0;
        $paused = false;
        $page = 1;

        try {
            do {
                $invoices = $xero->getAllInvoices($this->tenantId, $page, $from);

                foreach ($invoices as $inv) {
                    $invoiceId = $inv['InvoiceID'];
                    $number = $inv['InvoiceNumber'] ?? 'NOINV';
                    $invDate = Carbon::parse($inv['DateString'] ?? $inv['Date'] ?? now())->toDateString();

                    if ($this->isDone($invoiceId)) {
                        continue;
                    }

                    if ($this->quotaLow($xero)) {
                        $paused = true;
                        break 2;
                    }

                    try {
                        $attachments = $xero->getInvoiceAttachments($this->tenantId, $invoiceId);
                    } catch (\Throwable $e) {
                        // Gagal ambil daftar lampiran: catat sebagai failed agar dicoba lagi
                        $this->saveInvoiceLog($invoiceId, $number, $invDate, 'failed', 0, $e->getMessage());
                        Log::warning('[XeroExport] gagal ambil lampiran', [
                            'export_id' => $this->exportId,
                            'invoice_no' => $number,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }

                    // Tidak ada lampiran: lewati, tanpa folder dan tanpa log
                    if (empty($attachments)) {
                        continue;
                    }

                    $folder = preg_replace('/[^A-Za-z0-9\-_]+/', '_', $number) . '/';
                    $zip->addEmptyDir(rtrim($folder, '/'));
                    $totalInv++;

                    $fileCount = 0;
                    $status = 'done';
                    $error = null;

                    Log::info('[XeroExport] invoice dengan lampiran', [
                        'export_id' => $this->exportId,
                        'invoice_no' => $number,
                        'attachments' => count($attachments),
                    ]);

                    foreach ($attachments as $att) {
                        try {
                            $ext = pathinfo($att['FileName'], PATHINFO_EXTENSION) ?: 'pdf';
                            $fileName = $number . '_' . $att['AttachmentID'] . '.' . $ext;

                            $content = $xero->downloadAttachment($this->tenantId, $invoiceId, $att['FileName']);
                            $zip->addFromString($folder . $fileName, $content);

                            $fileCount++;
                            $totalFile++;

                            Log::info('[XeroExport] file didownload', [
                                'export_id' => $this->exportId,
                                'invoice_no' => $number,
                                'file' => $fileName,
                                'size' => strlen($content),
                            ]);
                        } catch (\Throwable $e) {
                            $status = 'failed';
                            $error = $e->getMessage();
                            Log::warning('[XeroExport] gagal download file', [
                                'export_id' => $this->exportId,
                                'invoice_no' => $number,
                                'error' => $error,
                            ]);
                            break;
                        }
                    }

                    $this->saveInvoiceLog($invoiceId, $number, $invDate, $status, $fileCount, $error);

                    $this->update(['total_invoice' => $totalInv, 'total_file' => $totalFile]);
                }

                $page++;
            } while (count($invoices) === 100);

        } catch (\Throwable $e) {
            $this->update(['status' => 'failed', 'error' => $e->getMessage()]);
            Log::error('[XeroExport] gagal', ['export_id' => $this->exportId, 'error' => $e->getMessage()]);
            return;
        } finally {
            $zip->close();
        }

        $this->update([
            'status' => $paused ? 'paused' : 'done',
            'file_path' => $relPath,
            'total_invoice' => $totalInv,
            'total_file' => $totalFile,
        ]);

        Log::info('[XeroExport] ' . ($paused ? 'pause karena kuota' : 'selesai'), [
            'export_id' => $this->exportId,
            'total_invoice' => $totalInv,
            'total_file' => $totalFile,
        ]);
    }

    /**
     * Titik lanjut: tanggal invoice gagal paling awal, atau tanggal terakhir yang berhasil.
     */
    private function resumeFrom(): ?Carbon
    {
        $base = DB::table('xero_attachment_invoice_logs')->where('tenant_id', $this->tenantId);

        $failedMin = (clone $base)->where('status', 'failed')->min('invoice_date');
        $doneMax = (clone $base)->where('status', 'done')->max('invoice_date');

        $date = $failedMin ?? $doneMax;

        return $date ? Carbon::parse($date) : null;
    }

    private function isDone(string $invoiceId): bool
    {
        return DB::table('xero_attachment_invoice_logs')
            ->where('tenant_id', $this->tenantId)
            ->where('invoice_id', $invoiceId)
            ->where('status', 'done')
            ->exists();
    }

    private function saveInvoiceLog(string $invoiceId, string $number, string $date, string $status, int $fileCount, ?string $error): void
    {
        DB::table('xero_attachment_invoice_logs')->updateOrInsert(
            ['tenant_id' => $this->tenantId, 'invoice_id' => $invoiceId],
            [
                'invoice_number' => $number,
                'invoice_date' => $date,
                'status' => $status,
                'total_file' => $fileCount,
                'export_id' => $this->exportId,
                'error' => $error,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * true = berhenti (pause). Jika limit per menit hampir habis, tunggu 60 detik lalu lanjut.
     */
    private function quotaLow(XeroService $xero): bool
    {
        if ($xero->dayRemaining !== null && $xero->dayRemaining < 100) {
            Log::warning('[XeroExport] kuota harian hampir habis, pause', [
                'export_id' => $this->exportId,
                'day_remaining' => $xero->dayRemaining,
            ]);
            return true;
        }

        if ($xero->minRemaining !== null && $xero->minRemaining < 5) {
            Log::info('[XeroExport] kuota per menit hampir habis, tunggu 60 detik', [
                'export_id' => $this->exportId,
                'min_remaining' => $xero->minRemaining,
            ]);
            sleep(60);
        }

        return false;
    }

    public function failed(\Throwable $e): void
    {
        $this->update(['status' => 'failed', 'error' => $e->getMessage()]);
        Log::error('[XeroExport] gagal', ['export_id' => $this->exportId, 'error' => $e->getMessage()]);
    }

    private function update(array $data): void
    {
        DB::table('xero_attachment_exports')->where('id', $this->exportId)->update($data + ['updated_at' => now()]);
    }

    private function fail(\Throwable $e): void
    {
        $this->update(['status' => 'failed', 'error' => $e->getMessage()]);
    }
}