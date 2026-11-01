<?php
// Pakai: php sync_xero_log.php TENANT_ID           (dry run)
//        php sync_xero_log.php TENANT_ID --apply   (benar-benar update)

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$t     = $argv[1] ?? null;
$apply = in_array('--apply', $argv, true);
if (!$t) { exit("Pakai: php sync_xero_log.php TENANT_ID [--apply]\n"); }

$base = Illuminate\Support\Facades\Storage::path("xero_attachments/$t");
if (!is_dir($base)) { exit("Folder tidak ada: $base\n"); }

// 1. Hitung file di disk per nomor invoice (dari nama file)
$have = [];
$totalFiles = 0;
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $f) {
    if ($f->isFile() && $f->getSize() > 0 &&
        preg_match('/^(.+)_[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\.[^.]+$/i', $f->getFilename(), $m)) {
        $have[$m[1]] = ($have[$m[1]] ?? 0) + 1;
        $totalFiles++;
    }
}

// 2. Bandingkan dengan log 'done'
$rows = Illuminate\Support\Facades\DB::table('xero_attachment_invoice_logs')
    ->where('tenant_id', $t)->where('status', 'done')->where('total_file', '>', 0)
    ->get();

$reset = [];
$sample = [];
foreach ($rows as $r) {
    $clean = preg_replace('/[^A-Za-z0-9\-_]+/', '_', $r->invoice_number);
    $onDisk = $have[$clean] ?? 0;
    if ($onDisk < $r->total_file) {
        $reset[] = $r->invoice_id;
        if (count($sample) < 15) {
            $sample[] = "{$r->invoice_number}: log={$r->total_file}, disk={$onDisk}";
        }
    }
}

echo "File valid di disk   : $totalFiles\n";
echo "Invoice 'done' di log: " . count($rows) . "\n";
echo "Perlu direset        : " . count($reset) . "\n";
foreach ($sample as $s) echo "  - $s\n";

if ($apply && $reset) {
    foreach (array_chunk($reset, 500) as $chunk) {
        Illuminate\Support\Facades\DB::table('xero_attachment_invoice_logs')
            ->where('tenant_id', $t)->whereIn('invoice_id', $chunk)
            ->update(['status' => 'pending', 'updated_at' => now()]);
    }
    echo "Selesai: " . count($reset) . " invoice direset ke pending.\n";
} elseif (!$apply) {
    echo "(dry run, tidak ada yang diubah. Tambahkan --apply untuk update.)\n";
}
