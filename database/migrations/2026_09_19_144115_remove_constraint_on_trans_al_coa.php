<?php
// database/migrations/YYYY_MM_DD_drop_check_nominal_coa_nonneg.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Drop check_nominal_coa_nonneg dari transaction_all_coas.
     *
     * Constraint CHECK (nominal >= 0) salah diterapkan di sini karena
     * invoice/credit note dari Xero bisa punya line amount negatif —
     * ini entri akuntansi yang valid (adjustment, reversal, credit note).
     *
     * Constraint di d_bills (qty >= 0) dan
     * transaction_nominal_bank_accounts (nominal_spend >= 0) tetap
     * dipertahankan karena memang tidak boleh negatif secara semantik.
     */
    public function up(): void
    {
        if ($this->constraintExists('transaction_all_coas', 'check_nominal_coa_nonneg')) {
            DB::statement('
                ALTER TABLE transaction_all_coas
                DROP CONSTRAINT check_nominal_coa_nonneg
            ');
        }
    }

    public function down(): void
    {
        if (!$this->constraintExists('transaction_all_coas', 'check_nominal_coa_nonneg')) {
            DB::statement('
                ALTER TABLE transaction_all_coas
                ADD CONSTRAINT check_nominal_coa_nonneg CHECK (nominal >= 0)
            ');
        }
    }

    private function constraintExists(string $table, string $name): bool
    {
        try {
            return !empty(DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE TABLE_NAME = ?
                  AND CONSTRAINT_NAME = ?
                  AND CONSTRAINT_TYPE = 'CHECK'
            ", [$table, $name]));
        } catch (\Exception $e) {
            return false;
        }
    }
};