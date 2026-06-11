<?php

namespace App\Http\Controllers\SupplierPortal;

use App\Http\Controllers\Controller;
use App\Models\SupplierCatalogItem;
use App\Models\SupplierPriceHistory;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $supplier = $request->user()->supplier;
        $search   = $request->string('search');
        $status   = $request->string('status');

        $items = SupplierCatalogItem::where('supplier_id', $supplier->id)
            ->with(['unit:id,name,symbol', 'ingredient:id,name'])
            ->when($search->isNotEmpty(), fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($status->isNotEmpty(), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->through(fn ($item) => [
                'id'             => $item->id,
                'name'           => $item->name,
                'sku'            => $item->sku,
                'packaging_spec' => $item->packaging_spec,
                'description'    => $item->description,
                'current_price'  => (float) $item->current_price,
                'status'         => $item->status,
                'unit'           => $item->unit ? ['id' => $item->unit->id, 'name' => $item->unit->name, 'symbol' => $item->unit->symbol] : null,
                'ingredient'     => $item->ingredient ? ['id' => $item->ingredient->id, 'name' => $item->ingredient->name] : null,
                'created_at'     => $item->created_at->toDateString(),
            ]);

        $units = Unit::select('id', 'name', 'symbol')->orderBy('name')->get();

        return Inertia::render('supplier-portal/Catalog', [
            'items'    => $items,
            'units'    => $units,
            'filters'  => ['search' => $search->value(), 'status' => $status->value()],
            'supplier' => ['id' => $supplier->id, 'name' => $supplier->name],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = $request->user()->supplier;

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'sku'            => ['nullable', 'string', 'max:100'],
            'unit_id'        => ['required', 'exists:units,id'],
            'packaging_spec' => ['nullable', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'current_price'  => ['required', 'numeric', 'min:0'],
        ]);

        SupplierCatalogItem::create([
            ...$validated,
            'supplier_id'   => $supplier->id,
            'restaurant_id' => $request->user()->restaurant_id,
            'status'        => 'active',
        ]);

        return back()->with('success', 'Đã thêm mặt hàng vào danh mục.');
    }

    public function update(Request $request, SupplierCatalogItem $item): RedirectResponse
    {
        $this->authorizeItem($item, $request);

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'sku'            => ['nullable', 'string', 'max:100'],
            'unit_id'        => ['required', 'exists:units,id'],
            'packaging_spec' => ['nullable', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:1000'],
        ]);

        $item->update($validated);

        return back()->with('success', 'Đã cập nhật thông tin mặt hàng.');
    }

    public function destroy(Request $request, SupplierCatalogItem $item): RedirectResponse
    {
        $this->authorizeItem($item, $request);
        $item->delete();

        return back()->with('success', 'Đã xóa mặt hàng khỏi danh mục.');
    }

    public function toggleStatus(Request $request, SupplierCatalogItem $item): RedirectResponse
    {
        $this->authorizeItem($item, $request);

        $item->update([
            'status' => $item->status === 'active' ? 'inactive' : 'active',
        ]);

        $label = $item->status === 'active' ? 'kích hoạt' : 'tạm ngưng';

        return back()->with('success', "Đã {$label} mặt hàng.");
    }

    public function updatePrice(Request $request, SupplierCatalogItem $item): RedirectResponse
    {
        $this->authorizeItem($item, $request);

        $validated = $request->validate([
            'new_price'    => ['required', 'numeric', 'min:0'],
            'note'         => ['nullable', 'string', 'max:500'],
            'effective_at' => ['nullable', 'date'],
        ]);

        $oldPrice = (float) $item->current_price;
        $newPrice = (float) $validated['new_price'];

        if ($oldPrice === $newPrice) {
            return back()->with('error', 'Giá mới phải khác giá hiện tại.');
        }

        SupplierPriceHistory::create([
            'restaurant_id'          => $request->user()->restaurant_id,
            'supplier_id'            => $item->supplier_id,
            'supplier_catalog_item_id' => $item->id,
            'changed_by'             => $request->user()->id,
            'old_price'              => $oldPrice,
            'new_price'              => $newPrice,
            'note'                   => $validated['note'] ?? null,
            'effective_at'           => $validated['effective_at'] ?? now(),
        ]);

        $item->update(['current_price' => $newPrice]);

        return back()->with('success', 'Đã cập nhật giá và lưu lịch sử biến động.');
    }

    private function authorizeItem(SupplierCatalogItem $item, Request $request): void
    {
        $supplier = $request->user()->supplier;
        abort_unless($item->supplier_id === $supplier->id, 403);
    }
}
