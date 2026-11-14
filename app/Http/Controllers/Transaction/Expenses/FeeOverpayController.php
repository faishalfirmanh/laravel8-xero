<?php

namespace App\Http\Controllers\Transaction\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Repository\Expenses\PODBillRepository;
use App\Http\Repository\Expenses\POPBillRepository;
use App\Http\Repository\LogHistoryRepository;
use App\Http\Repository\MasterData\CoaRepo;
use App\Http\Repository\MasterData\Finance\InvoiceAllXeroRepo;
use App\Http\Repository\MasterData\Finance\ItemPaketAllXeroRepo;
use App\Http\Repository\Transaction\FeeOverpayRepo;
use App\Http\Repository\Transaction\OverPayRepo;
use App\Http\Repository\Transaction\SummaryBankRepo;
use App\Http\Repository\Transaction\TransBankRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use App\Models\MasterData\MasterCurrency;
use App\Models\Transaction\SummaryNominalBank;
use Illuminate\Http\Request;


use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\File;

use Intervention\Image\Facades\Image;
use Cache;

class FeeOverpayController extends Controller
{
    //
    protected $repo, $repo_overpay, $service_global, $repo_coa, $repo_trans_bank, $repo_trans_coa, $repo_invoice, $repo_master_item, $globa_service, $repo_summary_bank;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        FeeOverpayRepo $repo,
        OverPayRepo $repo_overpay,
        CoaRepo $repo_coa,
        TransBankRepo $repo_trans_bank,
        TransCoaRepo $repo_trans_coa,
        InvoiceAllXeroRepo $repo_invoice,
        ItemPaketAllXeroRepo $repo_master_item,
        GlobalService $globa_service,
        SummaryBankRepo $repo_summary_bank,
        GlobalService $service_global
    ) {
        $this->repo = $repo;
        $this->repo_overpay = $repo_overpay;
        $this->repo_coa = $repo_coa;
        $this->repo_trans_bank = $repo_trans_bank;
        $this->repo_trans_coa = $repo_trans_coa;
        $this->repo_invoice = $repo_invoice;
        $this->repo_master_item = $repo_master_item;
        $this->globa_service = $globa_service;
        $this->repo_summary_bank = $repo_summary_bank;
        $this->service_global = $service_global;
    }




    //used
    public function getOverpayByInvId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|integer|exists:invoices_all_from_xeros,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $data = $this->repo->whereData(['invoice_id' => $request->invoice_id])->get();
        return $this->autoResponse($data);
    }



    public function storeCreateFeeOverpay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'date_transaction' => 'required|date',
            'invoice_id' => 'required|integer|exists:invoices_all_from_xeros,id',
            'bank_id_origin' => 'required|integer|exists:bank_xeros,id',
            'nominal' => 'required|numeric|min:0.0001',
            'bank_tujuan_jamaah_rek' => 'required|string|max:255',
            'overpay_id' => 'required|integer|exists:overpayments,id',
            'jamaah_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            $nominal = round((float) $request->nominal, 4);

            // ---- 0. Lock & validasi overpayment ----
            $overpay = DB::table('overpayments')
                ->where('id', $request->overpay_id)
                ->lockForUpdate()
                ->first();

            if (!$overpay || (int) $overpay->invoice_id !== (int) $request->invoice_id) {
                throw new \Exception('Overpayment tidak ditemukan untuk invoice ini');
            }

            // ---- 0b. Ambil fee lama jika update ----
            $oldFee = null;
            if ($request->filled('id')) {
                $oldFee = DB::table('fee_overpays')
                    ->where('id', $request->id)
                    ->lockForUpdate()
                    ->first();

                if (!$oldFee) {
                    throw new \Exception('Data fee overpay tidak ditemukan');
                }
            }

            // Selisih nominal: create = nominal baru, update = nominal baru - nominal lama
            $oldNominal = $oldFee ? (float) $oldFee->nominal : 0.0;
            $delta = round($nominal - $oldNominal, 4);

            if ($delta > 0 && $delta > (float) $overpay->nominal_overpayment) {
                throw new \Exception('Nominal fee overpay lebih dari sisa overpayment');
            }

            // ---- Data pendukung ----
            $coaFee = $this->repo_coa->whereData(['code' => 4800])->first();
            if (!$coaFee) {
                throw new \Exception('COA diskon penjualan (4800) tidak ditemukan');
            }
            $coaId = $coaFee->id;

            $inv = $this->repo_invoice->whereData(['id' => $request->invoice_id])->first();
            if (!$inv) {
                throw new \Exception('Invoice tidak ditemukan');
            }

            // UUID dipakai bersama di semua tabel terkait
            $uuid = $oldFee
                ? $oldFee->uuid_details_feeoverpay
                : $this->globa_service->generateRandomCode();

            $baseNominal = round($nominal * (float) $inv->nominal_currency, 4);

            // ---- 1. fee_overpays ----
            $saveFee = $this->repo->CreateOrUpdate([
                'date_transaction' => $request->date_transaction,
                'invoice_id' => $request->invoice_id,
                'bank_id_origin' => $request->bank_id_origin,
                'nominal' => $nominal,
                'bank_tujuan_jamaah_rek' => $request->bank_tujuan_jamaah_rek,
                'reference' => $inv->reference,
                'account_id' => $coaId,
                'overpay_id' => $request->overpay_id,
                'jamaah_id' => $request->jamaah_id,
                'uuid_details_feeoverpay' => $uuid,
            ], $oldFee ? $oldFee->id : null);

            // ---- 2. Kurangi saldo overpayment ----
            DB::table('overpayments')
                ->where('id', $overpay->id)
                ->update([
                    'nominal_overpayment' => round((float) $overpay->nominal_overpayment - $delta, 4),
                    'updated_at' => now(),
                ]);

            // ---- 3. transaction_all_coas ----
            $existingCoa = $oldFee
                ? DB::table('transaction_all_coas')->where('uuid_detail', $uuid)->first()
                : null;

            $this->repo_trans_coa->CreateOrUpdate([
                'date_transaction' => $request->date_transaction,
                'uuid_coa' => $coaId,
                'reference' => $inv->reference,
                'is_speend' => 1,
                'nominal' => $nominal,
                'created_by' => Auth::id(),
                'uuid_detail' => $uuid,
                'code_curr' => $inv->code_curr,
                'nominal_currency' => $inv->nominal_currency,
                'base_nominal' => $baseNominal,
            ], $existingCoa ? $existingCoa->id : null);

            // ---- 4. transaction_nominal_bank_account ----
            $existingBank = DB::table('transaction_nominal_bank_accounts')
                ->where('id_fee_overpay', $saveFee->id)
                ->first();

            $this->repo_trans_bank->CreateOrUpdate([
                'payment_uuid' => $uuid,
                'uuid_bank' => $request->bank_id_origin,
                'account_transaction' => $coaId,
                'nominal_receive' => 0,
                'nominal_spend' => $nominal,
                'nominal_transfer' => 0,
                'created_by' => Auth::id(),
                'date_transaction' => $request->date_transaction,
                'reference_detail' => $inv->reference,
                'id_parent_invoice' => $request->invoice_id,
                // 'id_parent_bank' => $request->bank_id_origin,
                // 'overpay_id' => $request->overpay_id,
                'id_fee_overpay' => $saveFee->id,
                'nominal_currency' => $inv->nominal_currency,
                'total_base_spend' => $baseNominal,
                'total_base_receive' => 0,
            ], $existingBank ? $existingBank->id : null);

            // ---- 5. summary_nominal_banks: nominal_out & final_nominal ----
            $summary = DB::table('summary_nominal_banks')
                ->where('bank_id', $request->bank_id_origin)
                ->lockForUpdate()
                ->first();

            if (!$summary) {
                throw $this->error("saldo bank tidak tersedia ", 500);
            }

            $newOut = round((float) $summary->nominal_out + $delta, 4);
            DB::table('summary_nominal_banks')
                ->where('id', $summary->id)
                ->update([
                    'nominal_out' => $newOut,
                    'final_nominal' => round((float) $summary->final_nominal - $newOut, 4),
                    'updated_at' => now(),
                ]);

            // ---- 6. invoices_all_from_xeros: potongan fee agent ----
            // Asumsi: fee agent menambah less_nominal invoice. Sesuaikan jika logikanya berbeda.
            // $this->repo_invoice->CreateOrUpdate([
            //     'less_nominal' => round((float) $inv->less_nominal + $delta, 4),
            // ], $inv->id);

            DB::commit();
            return $this->autoResponse($saveFee);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * BaseRepository::CreateOrUpdate menelan exception dan mengembalikan string
     * ("error saved" / "no data id X"). Tanpa pengecekan ini transaksi tetap ter-commit
     * dengan data setengah jadi, jadi hasilnya wajib berupa object model.
     */
    private function assertSaved($result, string $label)
    {
        if (!is_object($result)) {
            throw new \Exception('Gagal menyimpan ' . $label . (is_string($result) ? ': ' . $result : ''));
        }

        return $result;
    }

    public function storeFeeOverpay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:fee_overpays,id',
            'date_transaction' => 'required|date',
            'invoice_id' => 'required|integer|exists:invoices_all_from_xeros,id',
            'bank_id_origin' => 'required|integer|exists:bank_xeros,id',
            'nominal' => 'required|numeric|min:0.0001',
            'bank_tujuan_jamaah_rek' => 'required|string|max:255',
            'overpay_id' => 'required|integer|exists:overpayments,id',
            'jamaah_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $invoiceId = (int) $request->invoice_id;
        $newOverpayId = (int) $request->overpay_id;
        $newBankId = (int) $request->bank_id_origin;

        DB::beginTransaction();
        try {
            // ---- 0. Kunci dulu, baru baca. Urutan: fee -> invoice -> overpayment ----
            $oldFee = null;
            if ($request->filled('id')) {
                $oldFee = DB::table('fee_overpays')
                    ->where('id', $request->id)
                    ->lockForUpdate()
                    ->first();

                if (!$oldFee) {
                    throw new \Exception('Data fee overpay tidak ditemukan');
                }
                if ((int) $oldFee->invoice_id !== $invoiceId) {
                    throw new \Exception('Fee overpay bukan milik invoice ini');
                }
            }

            DB::table('invoices_all_from_xeros')->where('id', $invoiceId)->lockForUpdate()->first();

            $oldOverpayId = $oldFee ? (int) $oldFee->overpay_id : null;
            $oldBankId = $oldFee ? (int) $oldFee->bank_id_origin : null;

            $lockIds = array_values(array_unique(array_filter([$newOverpayId, $oldOverpayId])));
            sort($lockIds);
            foreach ($lockIds as $lockId) {
                DB::table('overpayments')->where('id', $lockId)->lockForUpdate()->first();
            }

            $inv = $this->repo_invoice->whereData(['id' => $invoiceId])->first();
            if (!$inv) {
                throw new \Exception('Invoice tidak ditemukan');
            }

            $overpay = DB::table('overpayments')->where('id', $newOverpayId)->first();
            if (!$overpay || (int) $overpay->invoice_id !== $invoiceId) {
                throw new \Exception('Overpayment tidak ditemukan untuk invoice ini');
            }
            if (
                $oldFee && $oldOverpayId !== $newOverpayId
                && !DB::table('overpayments')->where('id', $oldOverpayId)->exists()
            ) {
                throw new \Exception('Overpayment lama tidak ditemukan');
            }

            // Angka yang diketik user = mata uang invoice ($nominalAsli). Untuk invoice IDR keduanya sama.
            // $nominal = nilai Rupiah (kurs invoice). fee_overpays.nominal dan total_base_* memakai Rupiah;
            // nominal_spend di bank dan nominal di COA memakai angka asli, sama seperti alur pembayaran.
            $nominalAsli = round((float) $request->nominal, 4);
            $nominal = round($nominalAsli * $this->repo_overpay->invoiceRate($inv), 4);

            // ---- 1. Cek sisa overpay dari SUMBERnya, bukan dari nominal_overpayment tersimpan ----
            // Fee yang sedang diedit dikecualikan supaya tidak terhitung dua kali.
            $available = $this->repo_overpay->balanceFor($newOverpayId, $oldFee ? (int) $oldFee->id : null);

            if (round($nominal - $available, 4) > 0) {
                throw new \Exception('Nominal fee overpay lebih dari sisa overpayment');
            }

            // ---- 2. Data pendukung ----
            $coaFee = $this->repo_coa->whereData(['code' => 4800])->first();
            if (!$coaFee) {
                throw new \Exception('COA diskon penjualan (4800) tidak ditemukan');
            }

            $coaId = $coaFee->id;
            $uuid = $oldFee ? $oldFee->uuid_details_feeoverpay : $this->globa_service->generateRandomCode();

            // ---- 3. fee_overpays ----
            $saveFee = $this->assertSaved($this->repo->CreateOrUpdate([
                'date_transaction' => $request->date_transaction,
                'invoice_id' => $invoiceId,
                'bank_id_origin' => $newBankId,
                'nominal' => $nominal,
                'bank_tujuan_jamaah_rek' => $request->bank_tujuan_jamaah_rek,
                'reference' => $inv->reference,
                'account_id' => $coaId,
                'overpay_id' => $newOverpayId,
                'jamaah_id' => $request->jamaah_id,
                'uuid_details_feeoverpay' => $uuid,
            ], $oldFee ? $oldFee->id : null), 'fee overpay');

            // ---- 4. transaction_all_coas ----
            $existingCoa = ($oldFee && !empty($uuid))
                ? DB::table('transaction_all_coas')->where('uuid_detail', $uuid)->first()
                : null;

            $this->assertSaved($this->repo_trans_coa->CreateOrUpdate([
                'date_transaction' => $request->date_transaction,
                'uuid_coa' => $coaId,
                'reference' => $inv->reference,
                'is_speend' => 1,
                'nominal' => $nominalAsli,
                'created_by' => Auth::id(),
                'uuid_detail' => $uuid,
                'code_curr' => $inv->code_curr,
                'nominal_currency' => $inv->nominal_currency,
                'base_nominal' => $nominal,
            ], $existingCoa ? $existingCoa->id : null), 'jurnal COA');

            // ---- 5. transaction_nominal_bank_accounts ----
            $existingBank = $oldFee
                ? DB::table('transaction_nominal_bank_accounts')
                    ->where('id_fee_overpay', $saveFee->id)
                    ->first()
                : null;

            $this->assertSaved($this->repo_trans_bank->CreateOrUpdate([
                'payment_uuid' => $uuid,
                'uuid_bank' => $newBankId,
                'account_transaction' => $coaId,
                'nominal_receive' => 0,
                'nominal_spend' => $nominalAsli,
                'nominal_transfer' => 0,
                'created_by' => Auth::id(),
                'date_transaction' => $request->date_transaction,
                'reference_detail' => $inv->reference,
                'id_parent_invoice' => $invoiceId,
                // 'id_parent_bank'   => ... (lihat catatan FK sebelumnya)
                // 'overpay_id'       => $newOverpayId,  // jangan diisi: committedFor() memakai id_fee_overpay IS NULL
                'id_fee_overpay' => $saveFee->id,
                'nominal_currency' => $inv->nominal_currency,
                'total_base_spend' => $nominal,
                'total_base_receive' => 0,
            ], $existingBank ? $existingBank->id : null), 'transaksi bank');

            // ---- 6. summary_nominal_banks: dihitung ulang dari transaksi (mata uang bank itu sendiri) ----
            $bankIds = array_values(array_unique(array_filter([$newBankId, $oldBankId])));
            sort($bankIds); // urutan tetap supaya tidak deadlock
            foreach ($bankIds as $bankId) {
                $this->repo_summary_bank->recalcFromLedger((int) $bankId);
            }

            // ---- 7. overpayments.nominal_overpayment: dihitung ulang dari sumber ----
            // Dipanggil SETELAH fee tersimpan, di transaksi yang sama.
            $this->repo_overpay->syncBalance($newOverpayId);
            if ($oldFee && $oldOverpayId !== $newOverpayId) {
                $this->repo_overpay->syncBalance($oldOverpayId);
            }

            $logMessage = Auth::user()->name
                . ($oldFee ? ' mengubah' : ' membuat')
                . ' pengembalian dana (fee overpay) invoice ' . $inv->invoice_number
                . ', nominal ' . number_format($nominalAsli, 2, ',', '.') . ' ' . $inv->code_curr
                . ' dari bank ID ' . $newBankId
                . ' ke rekening jamaah ' . $request->bank_tujuan_jamaah_rek;

            $this->service_global->saveLogHistory(
                Auth::id(),
                $logMessage,
                $request->userAgent(),
                $request->ip(),
                null,
                $saveFee->id
            );

            DB::commit();
            return $this->autoResponse($saveFee);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 400);
        }
    }

    public function deleteFeeOverpay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:fee_overpays,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Urutan kunci sama dengan storeFeeOverpay: fee -> invoice -> overpayment
            $fee = DB::table('fee_overpays')->where('id', $request->id)->lockForUpdate()->first();
            if (!$fee) {
                throw new \Exception('Data fee overpay tidak ditemukan');
            }

            if (!empty($fee->invoice_id)) {
                DB::table('invoices_all_from_xeros')->where('id', $fee->invoice_id)->lockForUpdate()->first();
            }

            $overpay = DB::table('overpayments')->where('id', $fee->overpay_id)->lockForUpdate()->first();
            if (!$overpay) {
                throw new \Exception('Overpayment tidak ditemukan');
            }

            // 1. Hapus data turunan, lalu fee-nya
            DB::table('transaction_nominal_bank_accounts')
                ->where('id_fee_overpay', $fee->id)
                ->delete();

            if (!empty($fee->uuid_details_feeoverpay)) {
                DB::table('transaction_all_coas')
                    ->where('uuid_detail', $fee->uuid_details_feeoverpay)
                    ->delete();
            }

            DB::table('fee_overpays')->where('id', $fee->id)->delete();

            // 2. Ringkasan bank dihitung ulang dari transaksi yang tersisa
            $this->repo_summary_bank->recalcFromLedger((int) $fee->bank_id_origin);

            // 3. Saldo overpayment dihitung ulang dari sumber (fee sudah terhapus)
            $this->repo_overpay->syncBalance((int) $fee->overpay_id);

            $logMessage = Auth::user()->name
                . ' menghapus pengembalian dana (fee overpay) ID ' . $fee->id
                . ', reference ' . ($fee->reference ?? '-')
                . ', nominal ' . number_format((float) $fee->nominal, 2, ',', '.')
                . ' dari bank ID ' . $fee->bank_id_origin
                . ' ke rekening jamaah ' . ($fee->bank_tujuan_jamaah_rek ?? '-');

            $this->globa_service->saveLogHistory(
                Auth::id(),
                $logMessage,
                $request->userAgent(),
                $request->ip(),
                null,
                $fee->id
            );
            DB::commit();
            return $this->success(null, 'Fee overpay berhasil dihapus');

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 400);
        }
    }

}