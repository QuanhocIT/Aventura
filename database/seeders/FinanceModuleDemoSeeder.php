<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinanceModuleDemoSeeder extends Seeder
{
    private int $restaurantId = 19;
    private int $ownerUserId  = 23;
    private int $branchMain   = 9;
    private int $branchQ3     = 11;
    private int $branchWH     = 12;

    public function run(): void
    {
        $this->command->info('🏦 Seeding Finance Module for Test Enterprise (restaurant_id=19)...');
        $this->seedFinancialAccounts();
        $this->seedAccountingPeriods();
        $this->seedJournalEntries();
        $this->seedFinancialBudgets();
        $this->seedFixedAssets();
        $this->seedBankAccounts();
        $this->seedDebtSettlementHistory();
        $this->seedSalaryPayments();
        $this->command->info('✅ Finance Module seeding completed!');
    }

    // ── 1. CHART OF ACCOUNTS ──────────────────────────────────────────────────
    private function seedFinancialAccounts(): void
    {
        $this->command->info('  → Tài khoản kế toán...');
        $now = now();
        $rid = $this->restaurantId;
        // Chỉ thêm những codes chưa tồn tại
        $existingCodes = DB::table('financial_accounts')->where('restaurant_id', $rid)->pluck('code')->toArray();
        $accounts = [
            ['1310', 'Phải thu khách hàng',              'asset',    'debit'],
            ['1530', 'Hàng tồn kho – NVL',               'asset',    'debit'],
            ['3388', 'Phải trả khác',                    'liability','credit'],
            ['4212', 'Lợi nhuận chưa phân phối',        'equity',   'credit'],
            ['5113', 'Doanh thu giao hàng online',       'revenue',  'credit'],
            ['6321', 'Chi phí NVL trực tiếp',            'expense',  'debit'],
            ['6411', 'Lương nhân viên',                  'expense',  'debit'],
            ['6424', 'Chi phí thuê mặt bằng',           'expense',  'debit'],
            ['6427', 'Chi phí điện nước',                'expense',  'debit'],
            ['6428', 'Chi phí vận chuyển',               'expense',  'debit'],
            ['6429', 'Chi phí quảng cáo marketing',      'expense',  'debit'],
            ['6431', 'Chi phí khấu hao TSCĐ',           'expense',  'debit'],
            ['6435', 'Chi phí sửa chữa thiết bị',       'expense',  'debit'],
        ];
        $inserted = 0;
        foreach ($accounts as $a) {
            if (in_array($a[0], $existingCodes)) continue;
            DB::table('financial_accounts')->insert([
                'restaurant_id'  => $rid,
                'parent_id'      => null,
                'code'           => $a[0],
                'name'           => $a[1],
                'type'           => $a[2],
                'normal_balance' => $a[3],
                'is_system'      => true,
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $inserted++;
        }
        $total = DB::table('financial_accounts')->where('restaurant_id', $rid)->count();
        $this->command->info("    ✓ Thêm {$inserted} tài khoản mới, tổng {$total} tài khoản");
    }

    // ── 2. ACCOUNTING PERIODS ─────────────────────────────────────────────────
    private function seedAccountingPeriods(): void
    {
        $this->command->info('  → Kỳ kế toán...');
        if (DB::table('accounting_periods')->where('restaurant_id', $this->restaurantId)->exists()) {
            $this->command->warn('    Đã có dữ liệu, bỏ qua.');
            return;
        }
        $now = now();
        $rid = $this->restaurantId;
        $periods = [];
        for ($m = 4; $m <= 8; $m++) {
            $start  = Carbon::create(2026, $m, 1)->startOfMonth();
            $end    = $start->copy()->endOfMonth();
            $status = $m < 8 ? 'closed' : 'open';
            $periods[] = [
                'restaurant_id' => $rid,
                'period_start'  => $start->toDateString(),
                'period_end'    => $end->toDateString(),
                'status'        => $status,
                'closed_by'     => $status === 'closed' ? $this->ownerUserId : null,
                'closed_at'     => $status === 'closed' ? $end->addDays(2)->toDateTimeString() : null,
                'notes'         => $status === 'closed' ? "Đã đóng kỳ tháng {$m}/2026" : null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }
        DB::table('accounting_periods')->insert($periods);
        $this->command->info('    ✓ ' . count($periods) . ' kỳ kế toán (T4–T8/2026)');
    }

    // ── 3. JOURNAL ENTRIES ────────────────────────────────────────────────────
    private function seedJournalEntries(): void
    {
        $this->command->info('  → Bút toán nhật ký...');
        if (DB::table('financial_journal_entries')->where('restaurant_id', $this->restaurantId)->exists()) {
            $this->command->warn('    Đã có dữ liệu, bỏ qua.');
            return;
        }
        $rid      = $this->restaurantId;
        $periodId = DB::table('accounting_periods')
            ->where('restaurant_id', $rid)
            ->where('status', 'open')
            ->value('id');
        if (! $periodId) {
            $this->command->warn('    Không có kỳ open, bỏ qua.');
            return;
        }
        $accs = DB::table('financial_accounts')->where('restaurant_id', $rid)->pluck('id', 'code');
        $now  = now();

        $entries = [
            ['2026-08-01','JE-0801', 'Doanh thu F&B ngày 01/08',        $this->branchMain, [['1111','debit',32500000,'Thu tiền mặt bán hàng'],['5111','credit',32500000,'Doanh thu F&B']]],
            ['2026-08-03','JE-0803', 'Mua NVL từ NCC – thanh toán sau',  $this->branchMain, [['6321','debit',18200000,'Chi phí NVL'],['3311','credit',18200000,'Phải trả NCC Minh Hà']]],
            ['2026-08-05','JE-0805', 'Thuê mặt bằng tháng 8',            $this->branchMain, [['6424','debit',12000000,'Thuê mặt bằng T8'],['1121','credit',12000000,'CK Vietcombank']]],
            ['2026-08-07','JE-0807', 'Doanh thu GrabFood',               $this->branchQ3,   [['1310','debit',8750000,'Phải thu GrabFood'],['5113','credit',8750000,'DT giao hàng online']]],
            ['2026-08-10','JE-0810', 'Lương NV kỳ 1 T8',                 null,              [['6411','debit',45000000,'Lương kỳ 1 T8/2026'],['3341','credit',45000000,'Lương phải trả']]],
            ['2026-08-10','JE-0810B','Chi tiền lương từ ngân hàng',       null,              [['3341','debit',45000000,'Trả lương NV'],['1121','credit',45000000,'CK ngân hàng trả lương']]],
            ['2026-08-12','JE-0812', 'Điện nước tháng 8',                $this->branchMain, [['6427','debit',3850000,'Điện nước chi nhánh chính'],['1121','credit',3850000,'CK điện nước']]],
            ['2026-08-15','JE-0815', 'GrabFood chuyển khoản',            $this->branchQ3,   [['1121','debit',8750000,'GrabFood CK'],['1310','credit',8750000,'Tất toán phải thu GrabFood']]],
            ['2026-08-18','JE-0818', 'Thanh toán NCC Minh Hà',          $this->branchMain, [['3311','debit',18200000,'Tất toán phải trả Minh Hà'],['1121','credit',18200000,'CK ngân hàng NCC']]],
            ['2026-08-20','JE-0820', 'Facebook Ads tháng 8',             null,              [['6429','debit',5500000,'Facebook Ads T8'],['1121','credit',5500000,'CK Meta Ads']]],
            ['2026-08-22','JE-0822', 'Khấu hao TSCĐ tháng 8',           null,              [['6431','debit',4200000,'Khấu hao TSCĐ T8/2026'],['2141','credit',4200000,'Hao mòn lũy kế']]],
        ];

        $count = 0;
        foreach ($entries as $e) {
            $totalD = array_sum(array_column(array_filter($e[4], fn($l) => $l[1] === 'debit'), 2));
            $totalC = array_sum(array_column(array_filter($e[4], fn($l) => $l[1] === 'credit'), 2));
            $entryId = DB::table('financial_journal_entries')->insertGetId([
                'restaurant_id'        => $rid,
                'accounting_period_id' => $periodId,
                'branch_id'            => $e[3],
                'entry_number'         => $e[1],
                'entry_date'           => $e[0],
                'status'               => 'posted',
                'source_type'          => 'manual',
                'source_id'            => null,
                'idempotency_key'      => 'demo-' . $e[1],
                'currency'             => 'VND',
                'total_debit'          => $totalD,
                'total_credit'         => $totalC,
                'description'          => $e[2],
                'created_by'           => $this->ownerUserId,
                'posted_by'            => $this->ownerUserId,
                'posted_at'            => Carbon::parse($e[0])->addHours(9),
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
            foreach ($e[4] as $line) {
                DB::table('financial_journal_lines')->insert([
                    'journal_entry_id'     => $entryId,
                    'restaurant_id'        => $rid,
                    'financial_account_id' => $accs[$line[0]] ?? null,
                    'branch_id'            => $e[3],
                    'description'          => $line[3],
                    'debit'                => $line[1] === 'debit'  ? $line[2] : 0,
                    'credit'               => $line[1] === 'credit' ? $line[2] : 0,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
            }
            $count++;
        }
        $this->command->info("    ✓ {$count} bút toán nhật ký");
    }

    // ── 4. FINANCIAL BUDGETS ──────────────────────────────────────────────────
    private function seedFinancialBudgets(): void
    {
        $this->command->info('  → Ngân sách tài chính...');
        if (DB::table('financial_budgets')->where('restaurant_id', $this->restaurantId)->exists()) {
            $this->command->warn('    Đã có dữ liệu, bỏ qua.');
            return;
        }
        $rid = $this->restaurantId;
        $now = now();

        $b1 = DB::table('financial_budgets')->insertGetId([
            'restaurant_id' => $rid, 'branch_id' => null,
            'name'          => 'Ngân sách Q3/2026 – Toàn chuỗi',
            'period_start'  => '2026-07-01', 'period_end' => '2026-09-30',
            'status'        => 'approved', 'version' => 1, 'total_amount' => 450_000_000,
            'created_by'    => $this->ownerUserId, 'approved_by' => $this->ownerUserId,
            'approved_at'   => '2026-06-28 10:00:00',
            'notes'         => 'Ngân sách hoạt động toàn chuỗi Q3/2026',
            'created_at'    => $now, 'updated_at' => $now,
        ]);
        $lines = [
            ['6321',7,55_000_000], ['6321',8,58_000_000], ['6321',9,60_000_000],
            ['6411',7,85_000_000], ['6411',8,88_000_000], ['6411',9,90_000_000],
            ['6424',7,24_000_000], ['6424',8,24_000_000], ['6424',9,24_000_000],
            ['6427',7, 7_500_000], ['6427',8, 7_500_000], ['6427',9, 7_500_000],
            ['6429',7,10_000_000], ['6429',8,10_000_000], ['6429',9,10_000_000],
        ];
        foreach ($lines as $l) {
            DB::table('financial_budget_lines')->insert([
                'restaurant_id' => $rid, 'financial_budget_id' => $b1,
                'period_month'  => Carbon::create(2026, $l[1], 1)->toDateString(),
                'account_code'  => $l[0], 'category_id' => null, 'cost_center' => null,
                'budget_amount' => $l[2], 'notes' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $b2 = DB::table('financial_budgets')->insertGetId([
            'restaurant_id' => $rid, 'branch_id' => $this->branchMain,
            'name'          => 'Ngân sách Chi Nhánh Chính – T8/2026',
            'period_start'  => '2026-08-01', 'period_end' => '2026-08-31',
            'status'        => 'approved', 'version' => 1, 'total_amount' => 120_000_000,
            'created_by'    => $this->ownerUserId, 'approved_by' => $this->ownerUserId,
            'approved_at'   => '2026-07-29 09:00:00',
            'notes'         => 'Ngân sách tháng 8 chi nhánh chính',
            'created_at'    => $now, 'updated_at' => $now,
        ]);
        $lines2 = [
            ['6321',30_000_000], ['6411',50_000_000], ['6424',12_000_000], ['6427',4_000_000], ['6429',5_000_000],
        ];
        foreach ($lines2 as $l) {
            DB::table('financial_budget_lines')->insert([
                'restaurant_id' => $rid, 'financial_budget_id' => $b2,
                'period_month'  => '2026-08-01', 'account_code' => $l[0],
                'category_id'   => null, 'cost_center' => null,
                'budget_amount' => $l[1], 'notes' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->command->info('    ✓ 2 ngân sách + ' . (count($lines) + count($lines2)) . ' dòng ngân sách');
    }

    // ── 5. FIXED ASSETS ───────────────────────────────────────────────────────
    private function seedFixedAssets(): void
    {
        $this->command->info('  → Tài sản cố định...');
        if (DB::table('fixed_assets')->where('restaurant_id', $this->restaurantId)->exists()) {
            $this->command->warn('    Đã có dữ liệu, bỏ qua.');
            return;
        }
        $rid = $this->restaurantId;
        $now = now();
        $assets = [
            ['TSCD-001', 'Máy POS thanh toán (bộ 3)',       $this->branchMain, 45_000_000, '2025-06-01',  36, 'equipment'],
            ['TSCD-002', 'Tủ lạnh công nghiệp 800L',        $this->branchMain, 28_000_000, '2025-03-15',  60, 'equipment'],
            ['TSCD-003', 'Hệ thống camera CCTV 16 kênh',    $this->branchMain, 32_000_000, '2025-09-01',  60, 'equipment'],
            ['TSCD-004', 'Bộ bếp từ công nghiệp 6 họng',   $this->branchQ3,   55_000_000, '2026-01-10', 120, 'equipment'],
            ['TSCD-005', 'Xe đẩy hàng kho tổng (bộ 5)',     $this->branchWH,   12_000_000, '2026-04-01',  36, 'equipment'],
            ['TSCD-006', 'Máy tính văn phòng (bộ 4)',        null,              24_000_000, '2025-01-01',  36, 'equipment'],
            ['TSCD-007', 'Nội thất bàn ghế chi nhánh Q3',   $this->branchQ3,   68_000_000, '2024-10-01',  84, 'furniture'],
            ['TSCD-008', 'Máy điều hòa (6 cái)',             $this->branchMain, 42_000_000, '2025-05-01',  60, 'equipment'],
        ];
        foreach ($assets as $a) {
            $cost        = $a[3];
            $life        = $a[5];
            $monthlyDep  = round($cost / $life, 2);
            $acqDate     = Carbon::parse($a[4]);
            $monthsUsed  = min((int) $acqDate->diffInMonths(now()), $life);
            $accumulated = round($monthlyDep * $monthsUsed, 2);
            $bookValue   = max(0, $cost - $accumulated);

            $assetId = DB::table('fixed_assets')->insertGetId([
                'restaurant_id'            => $rid,
                'branch_id'                => $a[2],
                'asset_code'               => $a[0],
                'name'                     => $a[1],
                'category'                 => $a[6],
                'status'                   => 'active',
                'purchase_date'            => $a[4],
                'in_service_date'          => $a[4],
                'cost'                     => $cost,
                'residual_value'           => 0,
                'useful_life_months'       => $life,
                'depreciation_method'      => 'straight_line',
                'accumulated_depreciation' => $accumulated,
                'created_by'               => $this->ownerUserId,
                'created_at'               => $now,
                'updated_at'               => $now,
            ]);
            DB::table('fixed_asset_depreciations')->insert([
                'restaurant_id'  => $rid,
                'fixed_asset_id' => $assetId,
                'period_month'   => '2026-08-01',
                'amount'         => $monthlyDep,
                'journal_entry_id' => null,
                'created_by'     => $this->ownerUserId,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }
        $this->command->info('    ✓ ' . count($assets) . ' tài sản cố định');
    }

    // ── 6. BANK ACCOUNTS & STATEMENT LINES ───────────────────────────────────
    private function seedBankAccounts(): void
    {
        $this->command->info('  → Tài khoản ngân hàng & sao kê...');
        if (DB::table('financial_bank_accounts')->where('restaurant_id', $this->restaurantId)->exists()) {
            $this->command->warn('    Đã có dữ liệu, bỏ qua.');
            return;
        }
        $rid = $this->restaurantId;
        $now = now();

        $vcbId = DB::table('financial_bank_accounts')->insertGetId([
            'restaurant_id'          => $rid, 'branch_id' => null,
            'name'                   => 'Vietcombank – Tài khoản chính',
            'bank_name'              => 'Vietcombank',
            'account_number'         => '0451000123456',
            'account_holder'         => 'CONG TY TEST ENTERPRISE',
            'account_type'           => 'bank',
            'financial_account_code' => '1121',
            'opening_balance'        => 250_000_000,
            'opening_date'           => '2026-01-01',
            'is_active'              => true,
            'created_at'             => $now, 'updated_at' => $now,
        ]);
        DB::table('financial_bank_accounts')->insert([
            'restaurant_id'=>$rid,'branch_id'=>null,'name'=>'Techcombank – Thu phí online',
            'bank_name'=>'Techcombank','account_number'=>'19034521987654',
            'account_holder'=>'CONG TY TEST ENTERPRISE','account_type'=>'bank',
            'financial_account_code'=>'1121','opening_balance'=>80_000_000,
            'opening_date'=>'2026-03-15','is_active'=>true,'created_at'=>$now,'updated_at'=>$now,
        ]);
        DB::table('financial_bank_accounts')->insert([
            'restaurant_id'=>$rid,'branch_id'=>$this->branchMain,'name'=>'Quỹ tiền mặt chi nhánh chính',
            'bank_name'=>null,'account_number'=>null,'account_holder'=>null,'account_type'=>'cash',
            'financial_account_code'=>'1111','opening_balance'=>15_000_000,
            'opening_date'=>'2026-01-01','is_active'=>true,'created_at'=>$now,'updated_at'=>$now,
        ]);

        $statements = [
            ['2026-08-03', 'CK đến: CONG TY MINH HA – Thanh toán NVL',       0,          18_200_000, 'REF20260803001'],
            ['2026-08-05', 'CK thuê mặt bằng T8 – BEN THANH PROPERTY',       0,          12_000_000, 'REF20260805001'],
            ['2026-08-10', 'CK lương NV T8/2026',                              0,          45_000_000, 'REF20260810001'],
            ['2026-08-12', 'Thanh toán tiền điện EVN',                         0,           3_850_000, 'REF20260812001'],
            ['2026-08-15', 'Nhận tiền từ GRAB VIETNAM – DT GrabFood',         8_750_000,  0,          'REF20260815001'],
            ['2026-08-18', 'CK đến: CONG TY MINH HA – Kỳ 2',                 0,          18_200_000, 'REF20260818001'],
            ['2026-08-20', 'Thanh toán META ADS – Facebook Advertising',       0,           5_500_000, 'REF20260820001'],
            ['2026-08-22', 'Nhận thu hồi từ ShopeeFood',                      12_300_000, 0,          'REF20260822001'],
            ['2026-08-23', 'Nộp thuế VAT quý 2/2026',                         0,           8_200_000, 'REF20260823001'],
        ];
        $balance = 250_000_000;
        foreach ($statements as $idx => $s) {
            $balance += $s[2] - $s[3];
            $matched = ($idx < 6 && ($s[2] + $s[3]) > 0) ? 'matched' : 'unmatched';
            DB::table('bank_statement_lines')->insert([
                'restaurant_id'            => $rid,
                'financial_bank_account_id'=> $vcbId,
                'transaction_date'         => $s[0],
                'value_date'               => $s[0],
                'external_reference'       => $s[4],
                'description'              => $s[1],
                'amount_in'                => $s[2],
                'amount_out'               => $s[3],
                'balance'                  => $balance,
                'fee_amount'               => 0,
                'status'                   => $matched,
                'matched_type'             => null,
                'matched_id'               => null,
                'idempotency_key'          => 'demo-bsl-vcb-' . $idx . '-' . $s[0],
                'raw_payload'              => null,
                'imported_by'              => $this->ownerUserId,
                'imported_at'              => now(),
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
        }
        $this->command->info('    ✓ 3 tài khoản ngân hàng + ' . count($statements) . ' dòng sao kê VCB');
    }

    // ── 7. DEBT SETTLEMENT HISTORY ────────────────────────────────────────────
    private function seedDebtSettlementHistory(): void
    {
        $this->command->info('  → Lịch sử thanh toán công nợ...');
        $rid = $this->restaurantId;
        $now = now();

        $payables = DB::table('account_payables')
            ->where('restaurant_id', $rid)
            ->take(4)
            ->get(['id', 'supplier_id', 'amount']);
        if ($payables->isNotEmpty()) {
            foreach ($payables as $idx => $ap) {
                if (DB::table('account_payable_payments')->where('account_payable_id', $ap->id)->exists()) {
                    continue;
                }
                DB::table('account_payable_payments')->insert([
                    'restaurant_id'      => $rid,
                    'account_payable_id' => $ap->id,
                    'branch_id'          => $this->branchMain,
                    'amount'             => round($ap->amount * 0.5, 0),
                    'payment_method'     => $idx % 2 === 0 ? 'bank_transfer' : 'cash',
                    'payment_reference'  => 'PAY-AP-' . $ap->id . '-001',
                    'paid_at'            => now()->subDays(rand(3, 20)),
                    'created_by'         => $this->ownerUserId,
                    'notes'              => 'Thanh toán đợt 1 công nợ phải trả #' . $ap->id,
                    'idempotency_key'    => 'demo-app-' . $ap->id,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);
            }
            $this->command->info('    ✓ ' . $payables->count() . ' thanh toán phải trả');
        } else {
            $this->command->warn('    Không có account_payables, bỏ qua.');
        }

        $receivables = DB::table('account_receivables')
            ->where('restaurant_id', $rid)
            ->take(3)
            ->get(['id', 'customer_id', 'amount']);
        if ($receivables->isNotEmpty()) {
            foreach ($receivables as $ar) {
                if (DB::table('account_receivable_payments')->where('account_receivable_id', $ar->id)->exists()) {
                    continue;
                }
                DB::table('account_receivable_payments')->insert([
                    'restaurant_id'         => $rid,
                    'account_receivable_id' => $ar->id,
                    'branch_id'             => $this->branchMain,
                    'amount'                => $ar->amount,
                    'payment_method'        => 'bank_transfer',
                    'payment_reference'     => 'REC-AR-' . $ar->id . '-001',
                    'received_at'           => now()->subDays(rand(1, 15)),
                    'created_by'            => $this->ownerUserId,
                    'notes'                 => 'Thu hồi công nợ phải thu #' . $ar->id,
                    'idempotency_key'       => 'demo-arp-' . $ar->id,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ]);
            }
            $this->command->info('    ✓ ' . $receivables->count() . ' thu hồi phải thu');
        } else {
            $this->command->warn('    Không có account_receivables, bỏ qua.');
        }
    }

    // ── 8. SALARY PAYMENTS ────────────────────────────────────────────────────
    private function seedSalaryPayments(): void
    {
        $this->command->info('  → Lịch sử chi lương...');
        if (DB::table('salary_payments')->where('restaurant_id', $this->restaurantId)->exists()) {
            $this->command->warn('    Đã có dữ liệu, bỏ qua.');
            return;
        }
        $rid = $this->restaurantId;
        $now = now();

        $employees = DB::table('employees')
            ->where('restaurant_id', $rid)
            ->where('status', 'active')
            ->whereNotNull('base_salary')
            ->take(8)
            ->get(['id', 'full_name', 'base_salary', 'branch_id']);

        if ($employees->isEmpty()) {
            $this->command->warn('    Không có nhân viên active.');
            return;
        }

        $rows = [];
        foreach ($employees as $emp) {
            $rows[] = [
                'restaurant_id'     => $rid,
                'employee_id'       => $emp->id,
                'period_month'      => '2026-07-01',
                'gross_amount'      => $emp->base_salary,
                'deductions'        => round($emp->base_salary * 0.105, 0),
                'net_amount'        => round($emp->base_salary * 0.895, 0),
                'payment_method'    => 'bank_transfer',
                'payment_reference' => 'SAL-2026-07-' . $emp->id,
                'paid_at'           => '2026-07-31 10:00:00',
                'created_by'        => $this->ownerUserId,
                'idempotency_key'   => 'demo-sal-07-' . $emp->id,
                'notes'             => 'Lương tháng 7/2026 – ' . $emp->full_name,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
            $rows[] = [
                'restaurant_id'     => $rid,
                'employee_id'       => $emp->id,
                'period_month'      => '2026-08-01',
                'gross_amount'      => round($emp->base_salary / 2, 0),
                'deductions'        => 0,
                'net_amount'        => round($emp->base_salary / 2, 0),
                'payment_method'    => 'bank_transfer',
                'payment_reference' => 'SAL-2026-08-KY1-' . $emp->id,
                'paid_at'           => '2026-08-10 10:00:00',
                'created_by'        => $this->ownerUserId,
                'idempotency_key'   => 'demo-sal-08k1-' . $emp->id,
                'notes'             => 'Lương T8/2026 kỳ 1 – ' . $emp->full_name,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }
        DB::table('salary_payments')->insert($rows);
        $this->command->info('    ✓ ' . count($rows) . ' bản ghi chi lương (T7 + T8 kỳ 1)');
    }
}
