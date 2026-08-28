<?php

namespace App\Services;

use App\Models\FinancialBudget;
use App\Models\FinancialBudgetLine;
use App\Models\FixedAssetDepreciation;
use App\Models\OperatingExpense;
use App\Models\Salary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FinancialBudgetService
{
    /**
     * Planning accounts used by the budget screen. These are not cash
     * accounts. 6211/6221 are system-chart codes while 6321/6411 are legacy
     * or tenant-chart aliases still present in existing budgets.
     *
     * @return array<int, array{code: string, name: string, actual_basis: string}>
     */
    public function budgetAccountOptions(): array
    {
        return [
            ['code' => '6321', 'name' => 'Chi phí nguyên liệu trực tiếp', 'actual_basis' => 'Giá vốn nguyên liệu đã xuất dùng/tiêu hao, không phải tiền mua nhập kho.'],
            ['code' => '6211', 'name' => 'Giá vốn nguyên liệu (mã hệ thống)', 'actual_basis' => 'Giá vốn nguyên liệu đã xuất dùng/tiêu hao, không phải tiền mua nhập kho.'],
            ['code' => '6411', 'name' => 'Lương nhân viên', 'actual_basis' => 'Lương ở trạng thái đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '6221', 'name' => 'Chi phí nhân sự (mã hệ thống)', 'actual_basis' => 'Lương ở trạng thái đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '6424', 'name' => 'Thuê mặt bằng', 'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '6427', 'name' => 'Điện, nước và tiện ích', 'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '6428', 'name' => 'Vận chuyển', 'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '6429', 'name' => 'Quảng cáo, marketing', 'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '6431', 'name' => 'Khấu hao tài sản cố định', 'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '6435', 'name' => 'Sửa chữa thiết bị', 'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '6271', 'name' => 'Chi phí vận hành (mã hệ thống)', 'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '6272', 'name' => 'Chi phí khấu hao (mã hệ thống)', 'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '6351', 'name' => 'Phí ngân hàng/cổng thanh toán', 'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.'],
            ['code' => '8111', 'name' => 'Chi phí khác', 'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.'],
        ];
    }

    public function actualForLine(FinancialBudgetLine $line): float
    {
        $month = CarbonImmutable::parse($line->period_month);
        $start = $month->startOfMonth()->toDateString();
        $end = $month->endOfMonth()->toDateString();
        $branchId = $line->budget?->branch_id;

        $accountCode = match ($line->account_code) {
            '6321' => '6211',
            '6411' => '6221',
            '6431' => '6272',
            '6424', '6427', '6428', '6429', '6435' => '6271',
            default => $line->account_code,
        };

        return match ($accountCode) {
            '6221' => (float) Salary::withoutGlobalScopes()
                ->where('restaurant_id', $line->restaurant_id)
                ->whereIn('status', ['approved', 'paid'])
                ->whereBetween('pay_period_end', [$start, $end])
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('net_salary'),
            '6211' => (float) DB::table('inventory_transactions')
                ->where('restaurant_id', $line->restaurant_id)
                ->where('type', 'usage')
                ->where('direction', 'out')
                ->whereBetween('occurred_at', [$month->startOfMonth(), $month->endOfMonth()])
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_cost'),
            '6272' => (float) FixedAssetDepreciation::withoutGlobalScopes()
                ->where('restaurant_id', $line->restaurant_id)
                ->whereBetween('period_month', [$start, $end])
                ->whereHas('asset', fn ($query) => $query->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId)))
                ->sum('amount'),
            default => (float) OperatingExpense::withoutGlobalScopes()
                ->where('restaurant_id', $line->restaurant_id)
                ->where('financial_account_code', $accountCode)
                ->whereIn('status', ['approved', 'paid'])
                ->whereBetween('expense_date', [$start, $end])
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->when($line->category_id !== null, fn ($query) => $query->where('category_id', $line->category_id))
                ->sum('amount'),
        };
    }

    public function serialize(FinancialBudget $budget): array
    {
        $budget->loadMissing(['branch:id,name', 'lines.category:id,name']);

        return [
            'id' => $budget->id,
            'name' => $budget->name,
            'branch_id' => $budget->branch_id,
            'branch_name' => $budget->branch?->name,
            'period_start' => $budget->period_start->format('Y-m-d'),
            'period_end' => $budget->period_end->format('Y-m-d'),
            'status' => $budget->status,
            'total_amount' => (float) $budget->total_amount,
            'lines' => $budget->lines->map(function (FinancialBudgetLine $line): array {
                $actual = $this->actualForLine($line);
                $account = collect($this->budgetAccountOptions())->firstWhere('code', $line->account_code);

                return [
                    'id' => $line->id,
                    'period_month' => $line->period_month->format('Y-m'),
                    'account_code' => $line->account_code,
                    'account_name' => $account['name'] ?? 'Khoản mục khác',
                    'actual_basis' => $account['actual_basis'] ?? 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.',
                    'category_name' => $line->category?->name,
                    'budget_amount' => (float) $line->budget_amount,
                    'actual_amount' => $actual,
                    'variance_amount' => round((float) $line->budget_amount - $actual, 2),
                ];
            })->values()->all(),
        ];
    }
}
