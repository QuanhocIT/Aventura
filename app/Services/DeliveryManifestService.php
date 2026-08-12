<?php

namespace App\Services;

use App\Models\DeliveryManifest;
use App\Models\DeliveryManifestItem;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeliveryManifestService
{
    /**
     * Create a new Delivery Manifest (Gom chuyến xe giao hàng).
     */
    public function createManifest(int $restaurantId, int $fromBranchId, array $data, User $creator): DeliveryManifest
    {
        return DB::transaction(function () use ($restaurantId, $fromBranchId, $data, $creator) {
            $code = 'MNF-'.Carbon::now()->format('Ymd').'-'.str_pad((string) (DeliveryManifest::where('restaurant_id', $restaurantId)->count() + 1), 4, '0', STR_PAD_LEFT);

            $manifest = DeliveryManifest::create([
                'restaurant_id'         => $restaurantId,
                'from_branch_id'         => $fromBranchId,
                'manifest_code'          => $code,
                'route_name'             => $data['route_name'] ?? 'Tuyến mặc định',
                'driver_name'            => $data['driver_name'] ?? null,
                'driver_phone'           => $data['driver_phone'] ?? null,
                'vehicle_number'         => $data['vehicle_number'] ?? null,
                'seal_code'              => $data['seal_code'] ?? null,
                'status'                 => DeliveryManifest::STATUS_DRAFT,
                'scheduled_dispatch_at'  => ! empty($data['scheduled_dispatch_at']) ? Carbon::parse($data['scheduled_dispatch_at']) : now()->addHours(2),
                'created_by'             => $creator->id,
                'notes'                  => $data['notes'] ?? null,
            ]);

            if (! empty($data['supply_request_ids'])) {
                $order = 1;
                foreach ($data['supply_request_ids'] as $requestId) {
                    $supplyRequest = SupplyRequest::where('restaurant_id', $restaurantId)->findOrFail($requestId);

                    DeliveryManifestItem::create([
                        'delivery_manifest_id' => $manifest->id,
                        'supply_request_id'    => $supplyRequest->id,
                        'sequence_order'       => $order++,
                        'status'               => 'pending',
                    ]);
                }
            }

            return $manifest->load(['items.supplyRequest.toBranch', 'creator']);
        });
    }

    /**
     * Get Master Packing List (Bảng kê gom hàng xuất kho tổng hợp).
     */
    public function getMasterPackingList(DeliveryManifest $manifest): array
    {
        $manifest->load(['items.supplyRequest.items.ingredient']);

        $summary = [];

        foreach ($manifest->items as $manifestItem) {
            $req = $manifestItem->supplyRequest;
            if (! $req) {
                continue;
            }

            foreach ($req->items as $item) {
                $ingId = $item->ingredient_id;
                $qty   = (float) ($item->approved_quantity ?? $item->requested_quantity);

                if (! isset($summary[$ingId])) {
                    $summary[$ingId] = [
                        'ingredient_id'   => $ingId,
                        'ingredient_name' => $item->ingredient?->name ?? "NL #{$ingId}",
                        'unit_symbol'     => $item->unit_symbol ?? 'kg',
                        'total_quantity'  => 0.0,
                        'branches'        => [],
                    ];
                }

                $summary[$ingId]['total_quantity'] += $qty;
                $summary[$ingId]['branches'][] = [
                    'branch_id'    => $req->to_branch_id,
                    'branch_name'  => $req->toBranch?->name ?? "Chi nhánh #{$req->to_branch_id}",
                    'request_code' => $req->request_code,
                    'quantity'     => $qty,
                ];
            }
        }

        return array_values($summary);
    }

    /**
     * Dispatch entire manifest (Xuất toàn bộ chuyến xe giao hàng).
     */
    public function dispatchManifest(DeliveryManifest $manifest, User $user, ?string $sealCode = null): DeliveryManifest
    {
        if (in_array($manifest->status, [DeliveryManifest::STATUS_DISPATCHED, DeliveryManifest::STATUS_COMPLETED])) {
            throw new InvalidArgumentException('Chuyến xe này đã được xuất bến.');
        }

        return DB::transaction(function () use ($manifest, $user, $sealCode) {
            $centralService = app(CentralWarehouseService::class);
            $effectiveSeal  = $sealCode ?: $manifest->seal_code;

            foreach ($manifest->items as $manifestItem) {
                $req = $manifestItem->supplyRequest;
                if (! $req) {
                    continue;
                }

                if (in_array($req->status, [SupplyRequest::STATUS_APPROVED, SupplyRequest::STATUS_PREPARING, SupplyRequest::STATUS_DISPATCH_PENDING])) {
                    $centralService->dispatchSupplyRequest($req, $user, $effectiveSeal);
                }

                $manifestItem->update(['status' => 'loaded']);
            }

            $manifest->update([
                'status'        => DeliveryManifest::STATUS_DISPATCHED,
                'seal_code'     => $effectiveSeal,
                'dispatched_by' => $user->id,
                'dispatched_at' => now(),
            ]);

            return $manifest->fresh(['items.supplyRequest', 'dispatchedBy']);
        });
    }
}
