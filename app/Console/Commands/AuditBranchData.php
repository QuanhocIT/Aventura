<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditBranchData extends Command
{
    protected $signature = 'branches:audit
        {--restaurant= : Chỉ kiểm tra một restaurant_id}
        {--format=table : Định dạng terminal: table hoặc json}
        {--output= : Đường dẫn file JSON báo cáo; mặc định storage/app/branch-audits}
        {--fail-on-issues : Trả về mã lỗi nếu còn bản ghi cần xử lý thủ công}';

    protected $description = 'Kiểm tra dữ liệu thiếu hoặc sai branch_id mà không tự động cập nhật bản ghi';

    /** Rows in these tables must belong to one concrete branch. */
    private const BRANCH_REQUIRED_TABLES = [
        'approval_requests',
        'employees',
        'areas',
        'restaurant_tables',
        'orders',
        'payments',
        'ingredients',
        'inventories',
        'inventory_transactions',
        'inventory_reservations',
        'purchase_orders',
        'temporary_orders',
        'cash_registers',
        'cash_transactions',
        'schedule_assignments',
        'shift_closings',
        'leave_requests',
        'schedule_registrations',
        'shift_swaps',
        'salaries',
        'violation_reports',
        'checklist_completions',
        'operating_expenses',
        'recurring_expenses',
        'request_for_proposals',
        'orders_archive',
    ];

    /** NULL is an intentional chain-wide scope in these tables. */
    private const GLOBAL_SCOPE_ALLOWED_TABLES = [
        'product_categories',
        'products',
        'suppliers',
        'customers',
        'work_shifts',
        'restaurant_settings',
        'restaurant_revenue_summaries',
        'analytics_rollups',
    ];

    /** User rows that must be assigned when their role is branch-scoped. */
    private const BRANCH_SCOPED_USER_ROLES = [
        'manager',
        'cashier',
        'waiter',
        'kitchen',
        'inventory_staff',
        'staff',
    ];

    public function handle(): int
    {
        $restaurantId = $this->option('restaurant') !== null
            ? (int) $this->option('restaurant')
            : null;

        $report = $this->buildReport($restaurantId);
        $outputPath = $this->writeReport($report);

        if (strtolower((string) $this->option('format')) === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTable($report);
        }

        $this->info("Đã ghi báo cáo audit: {$outputPath}");

        if ($report['summary']['manual_review_records'] > 0) {
            $this->warn('Có dữ liệu chưa xác định chi nhánh. Không có bản ghi nào bị tự động gán.');

            return $this->option('fail-on-issues') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Không phát hiện bản ghi cần xử lý thủ công.');

        return self::SUCCESS;
    }

    /**
     * Build a read-only report. This method deliberately contains no update,
     * delete, truncate, or cache mutation calls.
     *
     * @return array<string, mixed>
     */
    public function buildReport(?int $restaurantId = null): array
    {
        $tables = $this->databaseTables();
        $schemaGaps = [];
        $tableReports = [];
        $manualReviewRecords = 0;
        $globalScopeRecords = 0;

        $candidateTables = array_values(array_unique(array_merge(
            self::BRANCH_REQUIRED_TABLES,
            self::GLOBAL_SCOPE_ALLOWED_TABLES,
            $tables,
        )));

        foreach ($candidateTables as $table) {
            if (! in_array($table, $tables, true)) {
                if (in_array($table, self::BRANCH_REQUIRED_TABLES, true)) {
                    $schemaGaps[] = [
                        'table' => $table,
                        'issue' => 'missing_table',
                    ];
                }

                continue;
            }

            $columns = Schema::getColumnListing($table);
            if (! in_array('restaurant_id', $columns, true) || ! in_array('branch_id', $columns, true) || ! in_array('id', $columns, true)) {
                if (in_array($table, self::BRANCH_REQUIRED_TABLES, true)) {
                    $schemaGaps[] = [
                        'table' => $table,
                        'issue' => 'missing_required_column',
                        'missing_columns' => array_values(array_diff(['id', 'restaurant_id', 'branch_id'], $columns)),
                    ];
                }

                continue;
            }

            $policy = in_array($table, self::BRANCH_REQUIRED_TABLES, true)
                ? 'branch_required'
                : (in_array($table, self::GLOBAL_SCOPE_ALLOWED_TABLES, true)
                    ? 'global_scope_allowed'
                    : ($table === 'users' ? 'role_dependent' : 'manual_review_policy'));

            $query = DB::table($table);
            if ($restaurantId !== null) {
                $query->where('restaurant_id', $restaurantId);
            }

            $missingQuery = $table === 'users'
                ? $this->branchScopedUsersQuery($restaurantId)
                : (clone $query)->whereNull('branch_id');
            $missingCount = $table === 'users'
                ? (clone $missingQuery)->distinct()->count('source.id')
                : (clone $missingQuery)->count();
            $missingSamples = (clone $missingQuery)
                ->orderBy($table === 'users' ? 'source.id' : 'id')
                ->limit(25)
                ->get($table === 'users'
                    ? ['source.id', 'source.restaurant_id', 'source.branch_id']
                    : ['id', 'restaurant_id', 'branch_id'])
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'restaurant_id' => $row->restaurant_id !== null ? (int) $row->restaurant_id : null,
                    'branch_id' => null,
                ])
                ->all();

            $invalidCount = 0;
            $invalidSamples = [];
            if (Schema::hasTable('restaurant_branches')) {
                $invalidQuery = DB::table("{$table} as source")
                    ->leftJoin('restaurant_branches as branch', 'branch.id', '=', 'source.branch_id')
                    ->whereNotNull('source.branch_id')
                    ->where(function ($where): void {
                        $where->whereNull('branch.id')
                            ->orWhereColumn('branch.restaurant_id', '!=', 'source.restaurant_id');
                    });

                if ($restaurantId !== null) {
                    $invalidQuery->where('source.restaurant_id', $restaurantId);
                }

                $invalidCount = (clone $invalidQuery)->count('source.id');
                $invalidSamples = (clone $invalidQuery)
                    ->orderBy('source.id')
                    ->limit(25)
                    ->get([
                        'source.id',
                        'source.restaurant_id',
                        'source.branch_id',
                        'branch.restaurant_id as branch_restaurant_id',
                    ])
                    ->map(fn ($row): array => [
                        'id' => (int) $row->id,
                        'restaurant_id' => (int) $row->restaurant_id,
                        'branch_id' => (int) $row->branch_id,
                        'branch_restaurant_id' => $row->branch_restaurant_id !== null ? (int) $row->branch_restaurant_id : null,
                    ])
                    ->all();
            }

            $unresolvedCount = $missingCount + $invalidCount;
            if ($policy === 'global_scope_allowed') {
                $globalScopeRecords += $missingCount;
            }
            // An invalid cross-tenant reference is always an issue, even if
            // NULL is normally valid for a chain-wide record.
            $manualReviewRecords += $invalidCount;
            if ($policy !== 'global_scope_allowed') {
                $manualReviewRecords += $missingCount;
            }

            if ($missingCount > 0 || $invalidCount > 0 || $policy !== 'global_scope_allowed') {
                $tableReports[] = [
                    'table' => $table,
                    'policy' => $policy,
                    'missing_branch_id' => $missingCount,
                    'invalid_branch_reference' => $invalidCount,
                    'manual_review_required' => $invalidCount > 0 || ($policy !== 'global_scope_allowed' && $missingCount > 0),
                    'missing_samples' => $missingSamples,
                    'invalid_samples' => $invalidSamples,
                ];
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'restaurant_filter' => $restaurantId,
            'policy' => [
                'branch_required' => 'Bản ghi phải có branch_id xác định; NULL được đưa vào xử lý thủ công.',
                'global_scope_allowed' => 'NULL được phép vì bản ghi dùng chung toàn chuỗi; không coi là lỗi.',
                'manual_review_policy' => 'Bảng chưa có chính sách rõ ràng không được tự suy đoán; cần xác nhận thủ công.',
                'role_dependent' => 'User có role vận hành phải có branch_id; owner có thể để NULL ở phạm vi toàn chuỗi.',
                'mutation' => 'Audit chỉ đọc dữ liệu và không tự động cập nhật branch_id.',
            ],
            'schema_gaps' => $schemaGaps,
            'tables' => $tableReports,
            'summary' => [
                'tables_with_findings' => count($tableReports),
                'schema_gaps' => count($schemaGaps),
                'manual_review_records' => $manualReviewRecords,
                'global_scope_null_records' => $globalScopeRecords,
            ],
        ];
    }

    /** @return array<int, string> */
    private function databaseTables(): array
    {
        try {
            return collect(DB::connection()->getSchemaBuilder()->getTables())
                ->map(fn ($table): ?string => is_array($table) ? ($table['name'] ?? null) : ($table->name ?? null))
                ->filter()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function branchScopedUsersQuery(?int $restaurantId): mixed
    {
        $query = DB::table('users as source')
            ->join('model_has_roles as user_role', function ($join): void {
                $join->on('user_role.model_id', '=', 'source.id')
                    ->where('user_role.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->whereNull('source.branch_id')
            ->whereIn('roles.name', self::BRANCH_SCOPED_USER_ROLES)
            ->select(['source.id', 'source.restaurant_id', 'source.branch_id'])
            ->distinct();

        if ($restaurantId !== null) {
            $query->where('source.restaurant_id', $restaurantId);
        }

        return $query;
    }

    /** @param array<string, mixed> $report */
    private function writeReport(array $report): string
    {
        $requested = (string) ($this->option('output') ?? '');
        $path = $requested !== ''
            ? (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $requested) ? $requested : base_path($requested))
            : storage_path('app/branch-audits/branch-audit-'.now()->format('Ymd_His').'.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /** @param array<string, mixed> $report */
    private function renderTable(array $report): void
    {
        $rows = collect($report['tables'])
            ->map(fn (array $row): array => [
                $row['table'],
                $row['policy'],
                $row['missing_branch_id'],
                $row['invalid_branch_reference'],
                $row['manual_review_required'] ? 'CẦN XỬ LÝ' : '—',
            ])
            ->all();

        if ($rows !== []) {
            $this->table(['Bảng', 'Chính sách', 'Thiếu branch_id', 'Sai tham chiếu', 'Kết luận'], $rows);
        }

        if ($report['schema_gaps'] !== []) {
            $this->warn('Schema gaps: '.json_encode($report['schema_gaps'], JSON_UNESCAPED_UNICODE));
        }

        $this->line('Manual review records: '.$report['summary']['manual_review_records']);
        $this->line('Global-scope NULL records: '.$report['summary']['global_scope_null_records']);
    }
}
