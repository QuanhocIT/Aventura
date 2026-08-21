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
            if ((int) $creator->restaurant_id !== $restaurantId) {
                throw new InvalidArgumentException('Tài khoản không thuộc nhà hàng của chuyến xe.');
            }

            $central = app(CentralWarehouseService::class)->getCentralWarehouse($restaurantId);
            if (! $central || (int) $fromBranchId !== (int) $central->id) {
                throw new InvalidArgumentException('Chuyến xe chỉ được tạo từ Kho Tổng đang hoạt động.');
            }

            $requestIds = array_values(array_unique(array_map('intval', $data['supply_request_ids'] ?? [])));
            if (count($requestIds) !== count($data['supply_request_ids'] ?? [])) {
                throw new InvalidArgumentException('Mỗi đơn cấp phát chỉ được chọn một lần.');
            }

            $code = $this->generateManifestCode($restaurantId);

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

            if (! empty($requestIds)) {
                $order = 1;
                foreach ($requestIds as $requestId) {
                    $supplyRequest = SupplyRequest::where('restaurant_id', $restaurantId)
                        ->where('from_branch_id', $central->id)
                        ->where('status', SupplyRequest::STATUS_DISPATCH_PENDING)
                        ->find($requestId);

                    if (! $supplyRequest) {
                        throw new InvalidArgumentException('Đơn cấp phát phải được soạn hàng và Trưởng kho duyệt xuất trước khi gom chuyến.');
                    }

                    if (DeliveryManifestItem::where('supply_request_id', $supplyRequest->id)
                        ->whereHas('manifest', fn ($query) => $query->whereIn('status', [DeliveryManifest::STATUS_DRAFT, DeliveryManifest::STATUS_PREPARING, DeliveryManifest::STATUS_DISPATCHED]))
                        ->exists()) {
                        throw new InvalidArgumentException('Đơn cấp phát đã thuộc một chuyến xe khác hoặc đã được xuất bến.');
                    }

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
        $this->assertManifestScope($manifest);
        $manifest->load(['items.supplyRequest.items.ingredient']);

        $summary = [];

        foreach ($manifest->items as $manifestItem) {
            $req = $manifestItem->supplyRequest;
            if (! $req) {
                throw new InvalidArgumentException('Chuyến xe có đơn cấp phát không còn tồn tại.');
            }
            if ((int) $req->restaurant_id !== (int) $manifest->restaurant_id
                || (int) $req->from_branch_id !== (int) $manifest->from_branch_id) {
                throw new InvalidArgumentException('Đơn cấp phát trong chuyến xe không cùng phạm vi Kho Tổng.');
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
        $this->assertManifestScope($manifest, $user);

        if (! in_array($manifest->status, [DeliveryManifest::STATUS_DRAFT, DeliveryManifest::STATUS_PREPARING], true)) {
            throw new InvalidArgumentException('Chuyến xe không còn ở trạng thái có thể xuất bến.');
        }

        return DB::transaction(function () use ($manifest, $user, $sealCode) {
            $centralService = app(CentralWarehouseService::class);
            $effectiveSeal  = $sealCode ?: $manifest->seal_code;

            $manifest->loadMissing('items.supplyRequest');

            if ($manifest->items->isEmpty()) {
                throw new InvalidArgumentException('Chuyến xe phải có ít nhất một đơn cấp phát hợp lệ.');
            }

            foreach ($manifest->items as $manifestItem) {
                $req = $manifestItem->supplyRequest;
                if (! $req) {
                    throw new InvalidArgumentException('Chuyến xe có đơn cấp phát không còn tồn tại.');
                }

                if ((int) $req->restaurant_id !== (int) $manifest->restaurant_id
                    || (int) $req->from_branch_id !== (int) $manifest->from_branch_id
                    || $req->status !== SupplyRequest::STATUS_DISPATCH_PENDING) {
                    throw new InvalidArgumentException('Chuyến xe chứa đơn cấp phát không hợp lệ hoặc đã được xử lý.');
                }

                $lockedRequest = SupplyRequest::where('restaurant_id', $manifest->restaurant_id)
                    ->where('from_branch_id', $manifest->from_branch_id)
                    ->lockForUpdate()
                    ->findOrFail($req->id);

                $centralService->dispatchSupplyRequest($lockedRequest, $user, $effectiveSeal);

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

    public function completeManifest(DeliveryManifest $manifest, User $user, ?string $notes = null): DeliveryManifest
    {
        $this->assertManifestScope($manifest, $user);

        return DB::transaction(function () use ($manifest, $user, $notes) {
            $locked = DeliveryManifest::whereKey($manifest->id)
                ->where('restaurant_id', $user->restaurant_id)
                ->with('items.supplyRequest')
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== DeliveryManifest::STATUS_DISPATCHED) {
                throw new InvalidArgumentException('Chỉ có chuyến xe đang giao mới được xác nhận hoàn tất.');
            }
            if ($locked->items->isEmpty()) {
                throw new InvalidArgumentException('Chuyến xe không có đơn để xác nhận.');
            }

            foreach ($locked->items as $item) {
                $request = $item->supplyRequest;
                if (! $request || (int) $request->to_branch_id !== (int) $user->assignedBranchId()) {
                    if (! $user->isOwner() && ! $user->isSuperAdmin() && ! $user->hasRole('warehouse_manager')) {
                        throw new InvalidArgumentException('Tài khoản chỉ được xác nhận chuyến xe giao tới chi nhánh được phân công.');
                    }
                }
                if (! $request || $request->status !== SupplyRequest::STATUS_COMPLETED) {
                    throw new InvalidArgumentException('Chỉ được đóng chuyến xe sau khi tất cả đơn đã được chi nhánh xác nhận nhận đủ.');
                }
                $item->update(['status' => 'delivered']);
            }

            $locked->update([
                'status' => DeliveryManifest::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => now(),
                'receipt_notes' => $notes,
            ]);

            return $locked->fresh(['items.supplyRequest.toBranch', 'completedBy']);
        });
    }

    public function syncFromSupplyRequest(SupplyRequest $request): void
    {
        $items = DeliveryManifestItem::where('supply_request_id', $request->id)
            ->with('deliveryManifest')
            ->get();

        foreach ($items as $item) {
            $item->update([
                'status' => $request->status === SupplyRequest::STATUS_COMPLETED
                    ? 'delivered'
                    : ($request->status === SupplyRequest::STATUS_DISPUTED ? 'disputed' : $item->status),
            ]);

            $manifest = $item->deliveryManifest;
            if (! $manifest || $manifest->status !== DeliveryManifest::STATUS_DISPATCHED) {
                continue;
            }

            $manifest->load('items.supplyRequest');
            if ($manifest->items->contains(fn ($manifestItem) => $manifestItem->supplyRequest?->status === SupplyRequest::STATUS_DISPUTED)) {
                $manifest->update(['status' => DeliveryManifest::STATUS_DISPUTED]);
            } elseif ($manifest->items->isNotEmpty() && $manifest->items->every(fn ($manifestItem) => $manifestItem->supplyRequest?->status === SupplyRequest::STATUS_COMPLETED)) {
                $manifest->update([
                    'status' => DeliveryManifest::STATUS_COMPLETED,
                    'completed_by' => $request->received_by,
                    'completed_at' => $request->received_at ?: now(),
                ]);
            }
        }
    }

    private function assertManifestScope(DeliveryManifest $manifest, ?User $actor = null): void
    {
        if ($actor && (int) $actor->restaurant_id !== (int) $manifest->restaurant_id) {
            throw new InvalidArgumentException('Tài khoản không thuộc nhà hàng của chuyến xe.');
        }

        $central = app(CentralWarehouseService::class)->getCentralWarehouse((int) $manifest->restaurant_id);
        if (! $central || (int) $manifest->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Chuyến xe không thuộc Kho Tổng đang hoạt động.');
        }
    }

    private function generateManifestCode(int $restaurantId): string
    {
        $prefix = 'MNF-'.$restaurantId.'-'.Carbon::now()->format('Ymd').'-';

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $prefix.str_pad(
                (string) (DeliveryManifest::where('restaurant_id', $restaurantId)->whereDate('created_at', Carbon::today())->count() + 1 + $attempt),
                4,
                '0',
                STR_PAD_LEFT,
            );

            if (! DeliveryManifest::where('restaurant_id', $restaurantId)->where('manifest_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $prefix.strtoupper(bin2hex(random_bytes(3)));
    }
}
