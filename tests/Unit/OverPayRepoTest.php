<?php

namespace Tests\Unit;

use App\Http\Repository\Transaction\OverPayRepo;
use App\Models\InvoicesAllFromXero;
use App\Models\Transaction\Overpayment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OverPayRepoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        DB::schema()->create('overpayments', function ($table) {
            $table->increments('id');
            $table->decimal('nominal_overpayment', 19, 4)->default(0);
            $table->integer('invoice_id')->nullable();
        });
        DB::schema()->create('transaction_nominal_bank_accounts', function ($table) {
            $table->increments('id');
            $table->integer('overpay_id')->nullable();
            $table->decimal('nominal_spend', 19, 4)->default(0);
        });
        DB::schema()->create('fee_overpays', function ($table) {
            $table->increments('id');
            $table->integer('overpay_id');
            $table->decimal('nominal', 19, 4)->default(0);
        });
    }

    public function test_remaining_balance_subtracts_credit_and_refunds_from_gross(): void
    {
        $invoice = new InvoicesAllFromXero([
            'invoice_amount' => 1200,
            'invoice_total' => 1000,
        ]);
        $invoice->id = 10;
        $overpaymentId = DB::table('overpayments')->insertGetId([
            'invoice_id' => $invoice->id,
            'nominal_overpayment' => 200,
        ]);

        DB::table('transaction_nominal_bank_accounts')->insert([
            'overpay_id' => $overpaymentId,
            'nominal_spend' => 50,
        ]);
        DB::table('fee_overpays')->insert([
            'overpay_id' => $overpaymentId,
            'nominal' => 25,
        ]);

        $repo = new OverPayRepo(new Overpayment());

        $this->assertSame('125.0000', $repo->getRemainingBalance($invoice));
    }

    public function test_remaining_balance_prevents_refunding_used_overpayment_again(): void
    {
        $invoice = new InvoicesAllFromXero([
            'invoice_amount' => 1200,
            'invoice_total' => 1000,
        ]);
        $invoice->id = 10;
        $overpaymentId = DB::table('overpayments')->insertGetId([
            'invoice_id' => $invoice->id,
            'nominal_overpayment' => 200,
        ]);
        DB::table('fee_overpays')->insert([
            'overpay_id' => $overpaymentId,
            'nominal' => 200,
        ]);

        $repo = new OverPayRepo(new Overpayment());
        $this->assertSame('0.0000', $repo->getRemainingBalance($invoice));

        $invoice->invoice_amount = 1250;

        $this->assertSame('50.0000', $repo->getRemainingBalance($invoice));
    }
}
