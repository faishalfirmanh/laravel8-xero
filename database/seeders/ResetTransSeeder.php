<?php

namespace Database\Seeders;

use App\Models\Expenses\Purchase\Bill\DBill;
use App\Models\Expenses\Purchase\Bill\PBill;
use App\Models\InvoicesAllFromXero;
use App\Models\MasterData\ItemDetailInvoices;
use App\Models\Transaction\TransactionAllCoa;
use App\Models\Transaction\TransactionBankTransD;
use App\Models\Transaction\TransactionBankTransP;
use App\Models\Transaction\TransactionNominalBankAccount;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Seeder;

class ResetTransSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Schema::disableForeignKeyConstraints();

        // 2. Truncate tabel (menghapus data & reset primary key ke 1)
        TransactionNominalBankAccount::truncate();
        TransactionAllCoa::truncate();
        TransactionBankTransD::truncate();
        TransactionBankTransP::truncate();
        PBill::truncate();
        DBill::truncate();
        ItemDetailInvoices::truncate();
        InvoicesAllFromXero::truncate();

        // 3. Aktifkan kembali foreign key checks
        Schema::enableForeignKeyConstraints();

        $this->command->info('Semua data berhasil dihapus dan primary key telah di-reset ke 1!');
    }
}
