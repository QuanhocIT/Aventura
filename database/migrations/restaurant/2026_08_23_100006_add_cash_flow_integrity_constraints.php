<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung các khóa bất biến cho sổ quỹ tiền mặt.
 *
 * Các kiểm tra ở controller vẫn cần thiết để trả thông báo nghiệp vụ dễ hiểu,
 * nhưng các khóa này bảo vệ dữ liệu kể cả khi có request đồng thời hoặc một
 * client cố tình bỏ qua giao diện.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_transactions')) {
            Schema::table('cash_transactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('cash_transactions', 'voucher_code')) {
                    $table->string('voucher_code', 100)->nullable()->after('idempotency_key');
                }
            });

            // Những bản ghi cũ có thể dùng cùng một mã idempotency do trước đây
            // mã chứng từ được dùng trực tiếp làm khóa. Đổi tên các bản trùng
            // trước khi bật unique index, không xóa hay gộp giao dịch lịch sử.
            $duplicateKeys = DB::table('cash_transactions')
                ->select('restaurant_id', 'idempotency_key')
                ->whereNotNull('idempotency_key')
                ->groupBy('restaurant_id', 'idempotency_key')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicateKeys as $duplicate) {
                $rows = DB::table('cash_transactions')
                    ->where('restaurant_id', $duplicate->restaurant_id)
                    ->where('idempotency_key', $duplicate->idempotency_key)
                    ->orderBy('id')
                    ->get(['id']);

                foreach ($rows->skip(1) as $row) {
                    $legacyKey = substr((string) $duplicate->idempotency_key, 0, 155).':legacy:'.$row->id;
                    DB::table('cash_transactions')
                        ->where('id', $row->id)
                        ->update(['idempotency_key' => $legacyKey]);
                }
            }

            if (! $this->indexExists('cash_transactions', 'cash_tx_restaurant_idempotency_unique')) {
                Schema::table('cash_transactions', function (Blueprint $table): void {
                    $table->unique(
                        ['restaurant_id', 'idempotency_key', 'occurred_at'],
                        'cash_tx_restaurant_idempotency_unique',
                    );
                });
            }

            if (! $this->indexExists('cash_transactions', 'cash_tx_restaurant_voucher_unique')) {
                Schema::table('cash_transactions', function (Blueprint $table): void {
                    $table->unique(
                        ['restaurant_id', 'voucher_code', 'occurred_at'],
                        'cash_tx_restaurant_voucher_unique',
                    );
                });
            }
        }

        if (Schema::hasTable('cash_registers')) {
            Schema::table('cash_registers', function (Blueprint $table): void {
                if (! Schema::hasColumn('cash_registers', 'open_scope_key')) {
                    $table->string('open_scope_key', 100)->nullable()->after('status');
                }
            });

            // Chỉ backfill những chi nhánh hiện không có dữ liệu mở trùng nhau.
            // Nhóm trùng được để null để controller buộc người dùng xử lý thủ
            // công, thay vì tự ý chọn một két không xác định.
            $singleOpenRegisters = DB::table('cash_registers')
                ->select('restaurant_id', 'branch_id')
                ->where('status', 'open')
                ->whereNotNull('branch_id')
                ->whereNull('open_scope_key')
                ->groupBy('restaurant_id', 'branch_id')
                ->havingRaw('COUNT(*) = 1')
                ->get();

            foreach ($singleOpenRegisters as $scope) {
                DB::table('cash_registers')
                    ->where('restaurant_id', $scope->restaurant_id)
                    ->where('branch_id', $scope->branch_id)
                    ->where('status', 'open')
                    ->whereNull('open_scope_key')
                    ->update([
                        'open_scope_key' => $scope->restaurant_id.':'.$scope->branch_id,
                    ]);
            }

            if (! $this->indexExists('cash_registers', 'cash_registers_open_scope_unique')) {
                Schema::table('cash_registers', function (Blueprint $table): void {
                    $table->unique('open_scope_key', 'cash_registers_open_scope_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cash_transactions')) {
            Schema::table('cash_transactions', function (Blueprint $table): void {
                if ($this->indexExists('cash_transactions', 'cash_tx_restaurant_idempotency_unique')) {
                    $table->dropUnique('cash_tx_restaurant_idempotency_unique');
                }
                if ($this->indexExists('cash_transactions', 'cash_tx_restaurant_voucher_unique')) {
                    $table->dropUnique('cash_tx_restaurant_voucher_unique');
                }
                if (Schema::hasColumn('cash_transactions', 'voucher_code')) {
                    $table->dropColumn('voucher_code');
                }
            });
        }

        if (Schema::hasTable('cash_registers')) {
            Schema::table('cash_registers', function (Blueprint $table): void {
                if ($this->indexExists('cash_registers', 'cash_registers_open_scope_unique')) {
                    $table->dropUnique('cash_registers_open_scope_unique');
                }
                if (Schema::hasColumn('cash_registers', 'open_scope_key')) {
                    $table->dropColumn('open_scope_key');
                }
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
        } catch (Throwable) {
            return false;
        }
    }
};
