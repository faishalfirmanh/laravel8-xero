<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * TUJUAN: Prevent duplikat bills & payments di masa depan dengan:
     * 1. Unique index di bills_uuid_xero
     * 2. Unique index di payment_uuid
     * 3. Foreign key constraints untuk data integrity
     * 4. Check constraints untuk numeric validation
     * 
     * ✅ FIXED: Sekarang check existence sebelum create (prevent duplicate constraint error)
     */
    public function up(): void
    {
        $db = config('database.default');

        // ============================================================
        // 1. P_BILLS TABLE CONSTRAINTS
        // ============================================================

        Schema::table('p_bills', function (Blueprint $table) {

            // ✅ Unique index pada bills_uuid_xero
            if (!$this->indexExists('p_bills', 'unique_bills_uuid_xero')) {
                $table->unique('bills_uuid_xero')
                    ->name('unique_bills_uuid_xero')
                    ->nullable();
            } else {
                // Log kalau sudah ada
                echo "[Migration] Index unique_bills_uuid_xero sudah ada di p_bills, skip.\n";
            }

            // ✅ Index pada status & date
            if (!$this->indexExists('p_bills', 'idx_status_date')) {
                $table->index(['status', 'date_req'])
                    ->name('idx_status_date');
            }

            // ✅ Index pada uuid_from
            if (!$this->indexExists('p_bills', 'idx_uuid_from')) {
                $table->index('uuid_from')
                    ->name('idx_uuid_from');
            }
        });

        // ============================================================
        // 2. D_BILLS TABLE CONSTRAINTS
        // ============================================================

        Schema::table('d_bills', function (Blueprint $table) {

            // ✅ Unique index pada uuid_detail
            if (!$this->indexExists('d_bills', 'unique_uuid_detail')) {
                $table->unique('uuid_detail')
                    ->name('unique_uuid_detail');
            } else {
                echo "[Migration] Index unique_uuid_detail sudah ada di d_bills, skip.\n";
            }

            // ✅ Foreign key ke p_bills
            if (!$this->foreignKeyExists('d_bills', 'fk_d_bills_p_bills')) {
                $table->foreign('bills_parent_id')
                    ->references('id')
                    ->on('p_bills')
                    ->onDelete('cascade')
                    ->name('fk_d_bills_p_bills');
            } else {
                echo "[Migration] Foreign key fk_d_bills_p_bills sudah ada di d_bills, skip.\n";
            }

            // ✅ Index untuk join queries
            if (!$this->indexExists('d_bills', 'idx_parent_id')) {
                $table->index('bills_parent_id')
                    ->name('idx_parent_id');
            }
        });

        // ✅ Check constraint: qty >= 0 (dengan detection)
        if (!$this->checkConstraintExists('d_bills', 'check_qty_nonneg')) {
            DB::statement('
                ALTER TABLE d_bills 
                ADD CONSTRAINT check_qty_nonneg CHECK (qty >= 0)
            ');
        } else {
            echo "[Migration] Check constraint check_qty_nonneg sudah ada di d_bills, skip.\n";
        }

        // ============================================================
        // 3. transaction_nominal_bank_accounts TABLE CONSTRAINTS
        // ============================================================

        Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {

            // ✅ Unique index pada payment_uuid
            if (!$this->indexExists('transaction_nominal_bank_accountss', 'unique_payment_uuid')) {
                $table->unique('payment_uuid')
                    ->name('unique_payment_uuid')
                    ->nullable();
            } else {
                echo "[Migration] Index unique_payment_uuid sudah ada di transaction_nominal_bank_accounts, skip.\n";
            }

            // ✅ Foreign key ke p_bills
            if (!$this->foreignKeyExists('transaction_nominal_bank_accounts', 'fk_tnba_p_bills')) {
                $table->foreign('id_parent_bill')
                    ->references('id')
                    ->on('p_bills')
                    ->onDelete('set null')
                    ->name('fk_tnba_p_bills');
            } else {
                echo "[Migration] Foreign key fk_tnba_p_bills sudah ada di transaction_nominal_bank_accounts, skip.\n";
            }

            // ✅ Index untuk query
            if (!$this->indexExists('transaction_nominal_bank_accounts', 'idx_date_transaction')) {
                $table->index('date_transaction')
                    ->name('idx_date_transaction');
            }
        });

        // ✅ Check constraint: nominal_spend >= 0
        if (!$this->checkConstraintExists('transaction_nominal_bank_accounts', 'check_nominal_spend_nonneg')) {
            DB::statement('
                ALTER TABLE transaction_nominal_bank_accounts 
                ADD CONSTRAINT check_nominal_spend_nonneg CHECK (nominal_spend >= 0)
            ');
        } else {
            echo "[Migration] Check constraint check_nominal_spend_nonneg sudah ada di transaction_nominal_bank_accounts, skip.\n";
        }

        // ============================================================
        // 4. TRANSACTION_ALL_COA TABLE CONSTRAINTS
        // ============================================================

        Schema::table('transaction_all_coas', function (Blueprint $table) {

            // ✅ Unique index pada uuid_detail
            if (!$this->indexExists('transaction_all_coas', 'unique_uuid_detail_coa')) {
                $table->unique('uuid_detail')
                    ->name('unique_uuid_detail_coa');
            } else {
                echo "[Migration] Index unique_uuid_detail_coa sudah ada di transaction_all_coas, skip.\n";
            }

            // ✅ Index untuk coa query
            if (!$this->indexExists('transaction_all_coas', 'idx_coa')) {
                $table->index('uuid_coa')
                    ->name('idx_coa');
            }
        });

        // ✅ Check constraint: nominal >= 0
        if (!$this->checkConstraintExists('transaction_all_coas', 'check_nominal_coa_nonneg')) {
            DB::statement('
                ALTER TABLE transaction_all_coas 
                ADD CONSTRAINT check_nominal_coa_nonneg CHECK (nominal >= 0)
            ');
        } else {
            echo "[Migration] Check constraint check_nominal_coa_nonneg sudah ada di transaction_all_coas, skip.\n";
        }
    }

    public function down(): void
    {
        // Rollback: Drop constraints & indexes
        Schema::table('transaction_all_coas', function (Blueprint $table) {
            if ($this->indexExists('transaction_all_coas', 'unique_uuid_detail_coa')) {
                $table->dropUnique('unique_uuid_detail_coa');
            }
            if ($this->indexExists('transaction_all_coas', 'idx_coa')) {
                $table->dropIndex('idx_coa');
            }
        });

        if ($this->checkConstraintExists('transaction_all_coas', 'check_nominal_coa_nonneg')) {
            DB::statement('ALTER TABLE transaction_all_coas DROP CONSTRAINT check_nominal_coa_nonneg');
        }

        Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
            if ($this->indexExists('transaction_nominal_bank_accounts', 'unique_payment_uuid')) {
                $table->dropUnique('unique_payment_uuid');
            }
            if ($this->foreignKeyExists('transaction_nominal_bank_accounts', 'fk_tnba_p_bills')) {
                $table->dropForeign('fk_tnba_p_bills');
            }
            if ($this->indexExists('transaction_nominal_bank_accounts', 'idx_date_transaction')) {
                $table->dropIndex('idx_date_transaction');
            }
        });

        if ($this->checkConstraintExists('transaction_nominal_bank_accounts', 'check_nominal_spend_nonneg')) {
            DB::statement('ALTER TABLE transaction_nominal_bank_accounts DROP CONSTRAINT check_nominal_spend_nonneg');
        }

        Schema::table('d_bills', function (Blueprint $table) {
            if ($this->indexExists('d_bills', 'unique_uuid_detail')) {
                $table->dropUnique('unique_uuid_detail');
            }
            if ($this->foreignKeyExists('d_bills', 'fk_d_bills_p_bills')) {
                $table->dropForeign('fk_d_bills_p_bills');
            }
            if ($this->indexExists('d_bills', 'idx_parent_id')) {
                $table->dropIndex('idx_parent_id');
            }
        });

        if ($this->checkConstraintExists('d_bills', 'check_qty_nonneg')) {
            DB::statement('ALTER TABLE d_bills DROP CONSTRAINT check_qty_nonneg');
        }

        Schema::table('p_bills', function (Blueprint $table) {
            if ($this->indexExists('p_bills', 'unique_bills_uuid_xero')) {
                $table->dropUnique('unique_bills_uuid_xero');
            }
            if ($this->indexExists('p_bills', 'idx_status_date')) {
                $table->dropIndex('idx_status_date');
            }
            if ($this->indexExists('p_bills', 'idx_uuid_from')) {
                $table->dropIndex('idx_uuid_from');
            }
        });
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    /**
     * Check apakah index sudah ada (MySQL)
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = DB::select("
                SELECT INDEX_NAME 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_NAME = ? AND INDEX_NAME = ?
            ", [$table, $index]);

            return !empty($indexes);
        } catch (\Exception $e) {
            echo "[Migration Warning] Could not check index: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Check apakah foreign key sudah ada (MySQL)
     */
    private function foreignKeyExists(string $table, string $fkName): bool
    {
        try {
            $fks = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = ? 
                  AND CONSTRAINT_NAME = ? 
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$table, $fkName]);

            return !empty($fks);
        } catch (\Exception $e) {
            echo "[Migration Warning] Could not check foreign key: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Check apakah CHECK constraint sudah ada (MySQL 8.0+)
     * ✅ FIXED: Method baru untuk check constraint detection
     */
    private function checkConstraintExists(string $table, string $constraintName): bool
    {
        try {
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE TABLE_NAME = ?
                  AND CONSTRAINT_NAME = ?
                  AND CONSTRAINT_TYPE = 'CHECK'
            ", [$table, $constraintName]);

            return !empty($constraints);
        } catch (\Exception $e) {
            echo "[Migration Warning] Could not check constraint: " . $e->getMessage() . "\n";
            return false;
        }
    }
};