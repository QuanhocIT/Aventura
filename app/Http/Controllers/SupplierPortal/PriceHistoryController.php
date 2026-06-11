<?php

namespace App\Http\Controllers\SupplierPortal;

use App\Http\Controllers\Controller;
use App\Models\SupplierCatalogItem;
use App\Models\SupplierPriceHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriceHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $supplier = $request->user()->supplier;
        $itemId   = $request->integer('item_id');
        $from     = $request->string('from');
        $to       = $request->string('to');

        $histories = SupplierPriceHistory::where('supplier_id', $supplier->id)
            ->with(['catalogItem:id,name,unit_id', 'catalogItem.unit:id,symbol'])
            ->when($itemId, fn ($q) => $q->where('supplier_catalog_item_id', $itemId))
            ->when($from->isNotEmpty(), fn ($q) => $q->whereDate('effective_at', '>=', $from))
            ->when($to->isNotEmpty(),   fn ($q) => $q->whereDate('effective_at', '<=', $to))
            ->latest('effective_at')
            ->paginate(30)
            ->through(fn ($h) => [
                'id'         => $h->id,
                'item_name'  => $h->catalogItem?->name,
                'unit'       => $h->catalogItem?->unit?->symbol,
                'old_price'  => (float) $h->old_price,
                'new_price'  => (float) $h->new_price,
                'change_pct' => $h->old_price > 0
                    ? round((($h->new_price - $h->old_price) / $h->old_price) * 100, 2)
                    : null,
                'note'        => $h->note,
                'effective_at' => $h->effective_at->toIso8601String(),
            ]);

        $items = SupplierCatalogItem::where('supplier_id', $supplier->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('supplier-portal/PriceHistory', [
            'histories' => $histories,
            'items'     => $items,
            'filters'   => [
                'item_id' => $itemId ?: null,
                'from'    => $from->value(),
                'to'      => $to->value(),
            ],
            'supplier'  => ['id' => $supplier->id, 'name' => $supplier->name],
        ]);
    }
}
