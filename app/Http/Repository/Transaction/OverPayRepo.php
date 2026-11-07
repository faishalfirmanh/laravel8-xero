<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;
use App\Models\InvoicesAllFromXero;
use App\Models\Transaction\Overpayment;
use Illuminate\Support\Facades\DB;


class OverPayRepo extends BaseRepository
{
    public function __construct(Overpayment $model)
    {
        $this->model = $model;
    }


    public function searchMultiColumn(
        array $where = [],
        int $perPage = 10,
        int $page = 1,
        string $keyword = '',
        array $columns = [],
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $modelWith = []
    ) {
        $query = $this->model->with($modelWith)->where($where);

        $keyword = trim($keyword);
        if ($keyword !== '' && !empty($columns)) {
            // escape wildcard LIKE yang diketik user (% dan _)
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($keyword));
            $safe = '%' . $escaped . '%';

            $grammar = $this->model->getConnection()->getQueryGrammar();
            $table = $this->model->getTable();

            $query->where(function ($q) use ($columns, $safe, $grammar, $table) {
                foreach ($columns as $col) {
                    // nama kolom hanya boleh huruf/angka/underscore (+ titik untuk relasi)
                    if (!preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/', $col)) {
                        continue;
                    }

                    if (strpos($col, '.') !== false) {
                        // kolom milik relasi: "getInv.invoice_number" atau "relA.relB.kolom"
                        $pos = strrpos($col, '.');
                        $relation = substr($col, 0, $pos);
                        $relCol = substr($col, $pos + 1);

                        $q->orWhereHas($relation, function ($rel) use ($relCol, $safe) {
                            $m = $rel->getModel();
                            $wrapped = $m->getConnection()->getQueryGrammar()
                                ->wrap($m->getTable() . '.' . $relCol);
                            $rel->whereRaw("LOWER(CAST({$wrapped} AS CHAR)) LIKE ?", [$safe]);
                        });
                    } else {
                        // kolom milik tabel ini (di-CAST supaya aman untuk decimal/text)
                        $wrapped = $grammar->wrap($table . '.' . $col);
                        $q->orWhereRaw("LOWER(CAST({$wrapped} AS CHAR)) LIKE ?", [$safe]);
                    }
                }
            });
        }

        if (!empty($dateFrom)) {
            $query->whereDate('issue_date', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('issue_date', '<=', $dateTo);
        }

        return $query->orderBy('id', 'DESC')
            ->paginate($perPage, ['*'], 'page', $page);
    }
    // ------------------------------------------------------------------
    // TIDAK BERUBAH (masih dipakai storeParent dan storePayment di InvXeroController)
    // ------------------------------------------------------------------
    public function getRemainingBalance(InvoicesAllFromXero $invoice): string//sisa saldo
    {
        $gross = bcsub($invoice->invoice_amount, $invoice->invoice_total, 4);
        if (bccomp($gross, '0', 4) < 0) {
            $gross = '0.0000';
        }

        $overpaymentIds = DB::table('overpayments')
            ->where('invoice_id', $invoice->id)
            ->pluck('id');

        $creditUsed = DB::table('transaction_nominal_bank_accounts')
            ->whereIn('overpay_id', $overpaymentIds)
            ->sum('nominal_spend');

        $refunds = DB::table('fee_overpays')
            ->whereIn('overpay_id', $overpaymentIds)
            ->sum('nominal');

        $remaining = bcsub(bcsub($gross, (string) $creditUsed, 4), (string) $refunds, 4);

        return bccomp($remaining, '0', 4) > 0 ? $remaining : '0.0000';
    }

    // ------------------------------------------------------------------
    // BARU
    // ------------------------------------------------------------------

    /** Kurs invoice ke IDR. IDR = 1. */
    public function invoiceRate($inv): float
    {
        if (strtoupper((string) $inv->code_curr) === 'IDR') {
            return 1.0;
        }

        $rate = (float) $inv->nominal_currency;

        return $rate > 0 ? $rate : 1.0;
    }

    /** Kelebihan bayar invoice dalam IDR: (invoice_amount - invoice_total) x kurs. */
    private function invoiceExcessIdr($inv): float
    {
        return max(0.0, (float) $inv->invoice_amount - (float) $inv->invoice_total) * $this->invoiceRate($inv);
    }

    /**
     * Jumlah overpay (IDR) yang sudah keluar dari satu baris overpayments:
     * credit yang dipakai membayar invoice lain + pengembalian ke jamaah (fee_overpays).
     *
     * $exceptFeeId: abaikan satu fee (dipakai saat mengedit fee yang sama).
     */
    public function committedFor(int $overpayId, ?int $exceptFeeId = null): float
    {
        // whereNull('id_fee_overpay'): baris bank milik fee tidak ikut terhitung dua kali
        $creditUsed = (float) DB::table('transaction_nominal_bank_accounts')
            ->where('overpay_id', $overpayId)
            ->whereNull('id_fee_overpay')
            ->sum(DB::raw(
                'CASE WHEN total_base_spend > 0 THEN total_base_spend '
                . 'ELSE nominal_spend * COALESCE(nominal_currency, 1) END'
            ));

        $refunds = (float) DB::table('fee_overpays')
            ->where('overpay_id', $overpayId)
            ->when($exceptFeeId, function ($q) use ($exceptFeeId) {
                return $q->where('id', '!=', $exceptFeeId);
            })
            ->sum('nominal');

        return round($creditUsed + $refunds, 4);
    }

    /**
     * Sisa overpay dalam IDR, dihitung dari sumbernya. Hanya membaca, tidak menulis.
     * Overpay milik bill (invoice_id kosong) mengembalikan 0.
     */
    public function balanceFor(int $overpayId, ?int $exceptFeeId = null): float
    {
        $over = DB::table('overpayments')->where('id', $overpayId)->first();
        if (!$over || empty($over->invoice_id)) {
            return 0.0;
        }

        $inv = DB::table('invoices_all_from_xeros')->where('id', $over->invoice_id)->first();
        if (!$inv) {
            return 0.0;
        }

        return round(
            max(0.0, $this->invoiceExcessIdr($inv) - $this->committedFor($overpayId, $exceptFeeId)),
            4
        );
    }

    /**
     * Hitung ulang lalu simpan ke overpayments.nominal_overpayment (mata uang invoice).
     * Panggil SETELAH payment/fee tersimpan, di dalam transaksi DB yang sama.
     * Overpay milik bill (tanpa invoice_id) tidak disentuh.
     *
     * Return: nilai yang disimpan (mata uang invoice).
     */
    public function syncBalance(int $overpayId): float
    {
        $over = DB::table('overpayments')->where('id', $overpayId)->first();
        if (!$over) {
            return 0.0;
        }
        if (empty($over->invoice_id)) {
            return (float) $over->nominal_overpayment;
        }

        $inv = DB::table('invoices_all_from_xeros')->where('id', $over->invoice_id)->first();
        if (!$inv) {
            return (float) $over->nominal_overpayment;
        }

        $stored = round($this->balanceFor($overpayId) / $this->invoiceRate($inv), 4);

        DB::table('overpayments')->where('id', $overpayId)->update([
            'nominal_overpayment' => $stored,
            'updated_at' => now(),
        ]);

        return $stored;
    }

    /**
     * Untuk invoice ini: buat baris overpayments bila ada kelebihan bayar dan barisnya belum ada,
     * lalu hitung ulang saldonya.
     *
     * - $invoice boleh model/object invoice atau id (data invoice selalu dibaca ulang dari DB).
     * - Memakai DB::table()->insertGetId supaya kegagalan insert langsung menjadi exception
     *   (tidak ditelan CreateOrUpdate).
     * - Tidak melempar exception kalau kelebihan bayar menyusut (invoice bisa berubah total):
     *   saldo cukup dihitung ulang dan minimal 0.
     *
     * Return: id baris overpayments, atau null bila invoice tidak punya overpayment.
     */
    public function syncForInvoice($invoice, ?int $transBankId = null, ?int $bankId = null): ?int
    {
        $invoiceId = is_numeric($invoice) ? (int) $invoice : (int) data_get($invoice, 'id');
        if ($invoiceId <= 0) {
            return null;
        }

        $inv = DB::table('invoices_all_from_xeros')->where('id', $invoiceId)->first();
        if (!$inv) {
            return null;
        }

        $row = DB::table('overpayments')
            ->where('invoice_id', $invoiceId)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (!$row) {
            if ($this->invoiceExcessIdr($inv) <= 0) {
                return null;
            }

            $id = (int) DB::table('overpayments')->insertGetId([
                'nominal_overpayment' => 0,
                'invoice_id' => $invoiceId,
                'trans_bank_id' => $transBankId,
                'bank_id' => $bankId,
                'jamaah_contact_id' => $inv->contact_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $id = (int) $row->id;
        }

        $this->syncBalance($id);

        return $id;
    }
}