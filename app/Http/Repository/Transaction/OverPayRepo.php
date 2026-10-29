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

    public function getRemainingBalance(InvoicesAllFromXero $invoice): string
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

}
