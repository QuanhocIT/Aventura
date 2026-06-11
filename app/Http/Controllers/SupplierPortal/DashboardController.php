<?php

namespace App\Http\Controllers\SupplierPortal;

use App\Http\Controllers\Controller;
use App\Models\SupplierCatalogItem;
use App\Models\SupplierPriceHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user     = $request->user();
        $supplier = $user->supplier;

        $totalItems   = SupplierCatalogItem::where('supplier_id', $supplier->id)->count();
        $activeItems  = SupplierCatalogItem::where('supplier_id', $supplier->id)->where('status', 'active')->count();
        $recentPriceChanges = SupplierPriceHistory::where('supplier_id', $supplier->id)
            ->with('catalogItem:id,name')
            ->latest('effective_at')
            ->limit(10)
            ->get()
            ->map(fn ($h) => [
                'id'         => $h->id,
                'item_name'  => $h->catalogItem?->name,
                'old_price'  => (float) $h->old_price,
                'new_price'  => (float) $h->new_price,
                'note'       => $h->note,
                'changed_at' => $h->effective_at->toIso8601String(),
            ]);

        return Inertia::render('supplier-portal/Dashboard', [
            'supplier' => [
                'id'           => $supplier->id,
                'name'         => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'email'        => $supplier->email,
                'phone'        => $supplier->phone,
                'status'       => $supplier->status,
            ],
            'stats' => [
                'total_items'  => $totalItems,
                'active_items' => $activeItems,
                'recent_price_changes' => $recentPriceChanges,
            ],
        ]);
    }
}
