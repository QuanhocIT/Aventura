<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\OperatingExpense;
use App\Models\RecurringExpense;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resources.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        // 1. Fetch Categories (includes system-shared ones)
        $categories = ExpenseCategory::all();

        // 2. Build Expenses Query with Filters
        $query = OperatingExpense::with(['category', 'creator']);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($startDate && $endDate) {
            $query->whereBetween('expense_date', [$startDate, $endDate]);
        }

        $categoryId = $request->input('category_id');
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $expenses = $query->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // 3. Fetch Recurring Expenses
        $recurringExpenses = RecurringExpense::with('category')->latest()->get();

        // 4. Calculate Analytics (This Month vs Last Month)
        $now = now();
        $thisMonthStart = $now->copy()->startOfMonth()->toDateString();
        $thisMonthEnd = $now->copy()->endOfMonth()->toDateString();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth()->toDateString();

        // Total sum
        $totalThisMonth = (float) OperatingExpense::whereBetween('expense_date', [$thisMonthStart, $thisMonthEnd])->sum('amount');
        $totalLastMonth = (float) OperatingExpense::whereBetween('expense_date', [$lastMonthStart, $lastMonthEnd])->sum('amount');

        // MoM Delta
        $momDelta = 0.0;
        if ($totalLastMonth > 0) {
            $momDelta = round((($totalThisMonth - $totalLastMonth) / $totalLastMonth) * 100, 1);
        } elseif ($totalThisMonth > 0) {
            $momDelta = 100.0;
        }

        // Recurring share ratio
        $recurringTotalThisMonth = (float) OperatingExpense::whereBetween('expense_date', [$thisMonthStart, $thisMonthEnd])
            ->whereNotNull('recurring_expense_id')
            ->sum('amount');
        $recurringRatio = $totalThisMonth > 0 ? round(($recurringTotalThisMonth / $totalThisMonth) * 100, 1) : 0.0;

        // Six months MoM data
        $sixMonthsMom = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = $now->copy()->subMonths($i);
            $mStart = $monthDate->copy()->startOfMonth()->toDateString();
            $mEnd = $monthDate->copy()->endOfMonth()->toDateString();
            $mAmount = (float) OperatingExpense::whereBetween('expense_date', [$mStart, $mEnd])->sum('amount');
            $sixMonthsMom[] = [
                'month' => $monthDate->format('m/Y'),
                'label' => 'Tháng ' . $monthDate->format('m/Y'),
                'amount' => $mAmount,
            ];
        }

        // Category breakdown this month
        $rawBreakdown = OperatingExpense::whereBetween('expense_date', [$thisMonthStart, $thisMonthEnd])
            ->select('category_id', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('category_id')
            ->get();

        $categoryBreakdown = [];
        foreach ($rawBreakdown as $row) {
            $category = $categories->firstWhere('id', $row->category_id);
            $catName = $category ? $category->name : 'Chưa phân loại';
            $categoryBreakdown[] = [
                'id' => $row->category_id,
                'name' => $catName,
                'amount' => (float)$row->total_amount,
                'percentage' => $totalThisMonth > 0 ? round(($row->total_amount / $totalThisMonth) * 100, 1) : 0.0,
            ];
        }
        usort($categoryBreakdown, fn($a, $b) => $b['amount'] <=> $a['amount']);

        $analytics = [
            'total_this_month' => $totalThisMonth,
            'total_last_month' => $totalLastMonth,
            'mom_delta' => $momDelta,
            'recurring_ratio' => $recurringRatio,
            'six_months_mom' => $sixMonthsMom,
            'category_breakdown' => $categoryBreakdown,
        ];

        return Inertia::render('expenses/Index', [
            'expenses' => $expenses,
            'recurringExpenses' => $recurringExpenses,
            'categories' => $categories,
            'analytics' => $analytics,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'category_id' => $categoryId,
            ]
        ]);
    }

    /**
     * Store a manual operating expense.
     */
    public function store(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'category_id' => ['nullable', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'invoice' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
        ]);

        $invoicePath = null;
        if ($request->hasFile('invoice')) {
            $path = $request->file('invoice')->store('invoices', 'public');
            $invoicePath = '/storage/' . $path;
        }

        OperatingExpense::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'category_id' => $data['category_id'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'description' => $data['description'] ?? null,
            'invoice_path' => $invoicePath,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Đã thêm chi phí vận hành mới thành công.');
    }

    /**
     * Update an operating expense.
     */
    public function update(Request $request, OperatingExpense $expense)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($expense->restaurant_id !== $request->user()->restaurant_id, 403);

        $data = $request->validate([
            'category_id' => ['nullable', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'invoice' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
        ]);

        if ($request->hasFile('invoice')) {
            // Delete old invoice file if exists
            if ($expense->invoice_path && str_starts_with($expense->invoice_path, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $expense->invoice_path);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('invoice')->store('invoices', 'public');
            $data['invoice_path'] = '/storage/' . $path;
        }

        $expense->update($data);

        return back()->with('success', 'Đã cập nhật thông tin chi phí.');
    }

    /**
     * Delete an operating expense.
     */
    public function destroy(Request $request, OperatingExpense $expense)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($expense->restaurant_id !== $request->user()->restaurant_id, 403);

        if ($expense->invoice_path && str_starts_with($expense->invoice_path, '/storage/')) {
            $oldPath = str_replace('/storage/', '', $expense->invoice_path);
            Storage::disk('public')->delete($oldPath);
        }

        $expense->delete();

        return back()->with('success', 'Đã xóa khoản chi phí thành công.');
    }

    /**
     * Create a recurring expense configuration.
     */
    public function storeRecurring(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'category_id' => ['required', 'exists:expense_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'frequency' => ['required', 'in:weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        RecurringExpense::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'amount' => $data['amount'],
            'frequency' => $data['frequency'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => true,
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Đã tạo cấu hình chi phí định kỳ mới.');
    }

    /**
     * Update recurring expense configuration.
     */
    public function updateRecurring(Request $request, RecurringExpense $recurring)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($recurring->restaurant_id !== $request->user()->restaurant_id, 403);

        $data = $request->validate([
            'category_id' => ['sometimes', 'required', 'exists:expense_categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'frequency' => ['sometimes', 'required', 'in:weekly,monthly,quarterly,yearly'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $recurring->update($data);

        return back()->with('success', 'Đã cập nhật cấu hình chi phí định kỳ.');
    }

    /**
     * Delete recurring expense configuration.
     */
    public function destroyRecurring(Request $request, RecurringExpense $recurring)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($recurring->restaurant_id !== $request->user()->restaurant_id, 403);

        $recurring->delete();

        return back()->with('success', 'Đã xóa cấu hình chi phí định kỳ.');
    }

    /**
     * Add a custom expense category.
     */
    public function storeCategory(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        ExpenseCategory::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Đã thêm danh mục chi phí tùy chỉnh mới.');
    }

    /**
     * Delete a custom expense category.
     */
    public function destroyCategory(Request $request, ExpenseCategory $category)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        // Only allow deleting custom categories belonging to this restaurant
        abort_if($category->restaurant_id !== $request->user()->restaurant_id, 403);

        $category->delete();

        return back()->with('success', 'Đã xóa danh mục chi phí tùy chỉnh.');
    }
}
