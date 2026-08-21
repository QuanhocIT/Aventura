<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryQuarantine;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnItem;
use App\Models\InventoryTransaction;
use App\Models\SupplierClaim;
use App\Models\StockTransferRequest;
use App\Models\User;
use App\Models\WarehouseShipmentEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WarehouseReverseLogisticsService
{
    public function recordShipmentEvent(
        int $restaurantId,
        string $shipmentType,
        int $shipmentId,
        string $eventType,
        User $actor,
        array $data = [],
        ?UploadedFile $evidence = null,
    ): WarehouseShipmentEvent {
        $evidencePath = $evidence?->store("warehouse/shipment-events/{$restaurantId}/".now()->format('Y/m'), 'local');

        return WarehouseShipmentEvent::create([
            'restaurant_id' => $restaurantId,
            'shipment_type' => $shipmentType,
            'shipment_id' => $shipmentId,
            'event_type' => $eventType,
            'branch_id' => $data['branch_id'] ?? null,
            'actor_id' => $actor->id,
            'vehicle_number' => $data['vehicle_number'] ?? null,
            'carrier_name' => $data['carrier_name'] ?? null,
            'seal_code' => $data['seal_code'] ?? null,
            'temperature_min_c' => $data['temperature_min_c'] ?? null,
            'temperature_max_c' => $data['temperature_max_c'] ?? null,
            'evidence_path' => $evidencePath,
            'notes' => $data['notes'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
    }

    public function createDestinationBatch(
        int $restaurantId,
        int $branchId,
        int $ingredientId,
        float $quantity,
        float $unitCost,
        User $actor,
        ?InventoryBatch $sourceBatch = null,
        bool $locked = false,
        ?string $lockReason = null,
        ?int $locationId = null,
    ): ?InventoryBatch {
        if ($quantity <= 0) {
            return null;
        }

        $batchCode = $sourceBatch?->batch_code ?: $sourceBatch?->batch_number;
        $batchCode = $batchCode ?: 'TRACE-'.Str::upper(Str::random(12));

        return InventoryBatch::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'location_id' => $locationId,
            'ingredient_id' => $ingredientId,
            'batch_code' => $batchCode,
            'batch_number' => substr($batchCode, 0, 50),
            'quantity_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'purchased_at' => $sourceBatch?->purchased_at?->toDateString() ?: now()->toDateString(),
            'expiry_date' => $sourceBatch?->expiry_date?->toDateString(),
            'supplier_id' => $sourceBatch?->supplier_id,
            'status' => $locked ? 'locked' : 'active',
            'lock_reason' => $locked ? ($lockReason ?: 'Hàng chờ xử lý chất lượng.') : null,
            'locked_by' => $locked ? $actor->id : null,
            'locked_at' => $locked ? now() : null,
        ]);
    }

    public function createQuarantine(
        int $restaurantId,
        int $branchId,
        int $ingredientId,
        float $quantity,
        string $condition,
        string $reason,
        User $actor,
        ?InventoryBatch $batch = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?int $sourceItemId = null,
        array $evidencePaths = [],
        ?string $notes = null,
    ): InventoryQuarantine {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Số lượng cách ly phải lớn hơn 0.');
        }

        return InventoryQuarantine::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'inventory_batch_id' => $batch?->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_item_id' => $sourceItemId,
            'quantity' => $quantity,
            'condition' => $condition,
            'status' => 'open',
            'reason' => $reason,
            'notes' => $notes,
            'evidence_paths' => $evidencePaths,
            'created_by' => $actor->id,
        ]);
    }

    public function createReturnFromQuarantine(
        InventoryQuarantine $quarantine,
        User $actor,
        array $data = [],
    ): InventoryReturn {
        return DB::transaction(function () use ($quarantine, $actor, $data): InventoryReturn {
            $lockedQuarantine = InventoryQuarantine::query()
                ->where('restaurant_id', $actor->restaurant_id)
                ->whereKey($quarantine->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedQuarantine->status, ['open', 'return_requested'], true)) {
                throw new InvalidArgumentException('Lô cách ly đã được xử lý, không thể lập phiếu hoàn trả mới.');
            }

            $quantityAlreadyReturned = (float) $lockedQuarantine->returnItems()
                ->whereHas('returnOrder', fn ($query) => $query->whereNotIn('status', ['rejected', 'destroyed']))
                ->sum('quantity');
            $remaining = max(0, (float) $lockedQuarantine->quantity - $quantityAlreadyReturned);
            $quantity = (float) ($data['quantity'] ?? $remaining);
            if ($quantity <= 0 || $quantity > $remaining + 0.0005) {
                throw new InvalidArgumentException('Số lượng hoàn trả vượt quá số lượng đang cách ly.');
            }

            $return = InventoryReturn::create([
                'restaurant_id' => $actor->restaurant_id,
                'return_code' => $this->returnCode($actor->restaurant_id),
                'source_type' => $lockedQuarantine->source_type,
                'source_id' => $lockedQuarantine->source_id,
                'from_branch_id' => $lockedQuarantine->branch_id,
                'to_branch_id' => $data['to_branch_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'status' => 'requested',
                'reason' => $data['reason'] ?? $lockedQuarantine->reason,
                'notes' => $data['notes'] ?? null,
                'evidence_paths' => $data['evidence_paths'] ?? [],
                'created_by' => $actor->id,
            ]);

            $return->items()->create([
                'ingredient_id' => $lockedQuarantine->ingredient_id,
                'inventory_batch_id' => $lockedQuarantine->inventory_batch_id,
                'quarantine_id' => $lockedQuarantine->id,
                'quantity' => $quantity,
                'unit_cost' => (float) ($lockedQuarantine->batch?->unit_cost ?? 0),
                'condition' => $lockedQuarantine->condition,
                'notes' => $data['notes'] ?? null,
            ]);

            $lockedQuarantine->update(['status' => 'return_requested']);

            return $return->load('items');
        });
    }

    public function approveReturn(InventoryReturn $returnOrder, User $actor): InventoryReturn
    {
        return DB::transaction(function () use ($returnOrder, $actor): InventoryReturn {
            $returnOrder = InventoryReturn::query()
                ->where('restaurant_id', $actor->restaurant_id)
                ->with('items')
                ->whereKey($returnOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($returnOrder->status !== 'requested') {
                throw new InvalidArgumentException('Phiếu hoàn trả không còn ở trạng thái chờ duyệt.');
            }

            foreach ($returnOrder->items as $item) {
                if (! $item->inventory_batch_id || $item->quarantine_id) {
                    continue;
                }

                $inventory = Inventory::query()
                    ->where('restaurant_id', $actor->restaurant_id)
                    ->where('branch_id', $returnOrder->from_branch_id)
                    ->where('ingredient_id', $item->ingredient_id)
                    ->lockForUpdate()
                    ->first();
                $batch = InventoryBatch::query()->whereKey($item->inventory_batch_id)->lockForUpdate()->first();
                if (! $inventory || ! $batch || (float) $inventory->quantity_on_hand + 0.0005 < (float) $item->quantity || (float) $batch->quantity_remaining + 0.0005 < (float) $item->quantity) {
                    throw new InvalidArgumentException('Tồn khả dụng hoặc tồn theo lô không đủ để hoàn trả.');
                }

                $before = (float) $inventory->quantity_on_hand;
                $quantity = (float) $item->quantity;
                $transaction = InventoryTransaction::createWithIdempotency([
                    'restaurant_id' => $actor->restaurant_id,
                    'branch_id' => $returnOrder->from_branch_id,
                    'ingredient_id' => $item->ingredient_id,
                    'inventory_id' => $inventory->id,
                    'performed_by' => $actor->id,
                    'type' => 'return',
                    'direction' => 'out',
                    'quantity' => $quantity,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => $quantity * (float) $item->unit_cost,
                    'source_type' => 'inventory_return',
                    'source_id' => $returnOrder->id,
                    'idempotency_key' => "return_out_{$returnOrder->id}_{$item->id}",
                    'reference_code' => $returnOrder->return_code,
                    'notes' => 'Xuất hàng hoàn trả theo phiếu '.$returnOrder->return_code,
                    'occurred_at' => now(),
                ]);
                $inventory->update(['quantity_on_hand' => $before - $quantity, 'theoretical_quantity' => max(0, (float) $inventory->theoretical_quantity - $quantity), 'updated_by' => $actor->id]);
                $batch->decrement('quantity_remaining', $quantity);
                if ((float) $batch->quantity_remaining <= 0) {
                    $batch->update(['status' => 'depleted']);
                }
                InventoryBatchAllocation::create([
                    'restaurant_id' => $actor->restaurant_id,
                    'branch_id' => $returnOrder->from_branch_id,
                    'inventory_batch_id' => $batch->id,
                    'inventory_transaction_id' => $transaction->id,
                    'direction' => 'out',
                    'quantity' => $quantity,
                    'unit_cost' => $item->unit_cost,
                ]);
            }

            $returnOrder->update(['status' => 'in_transit', 'approved_by' => $actor->id, 'approved_at' => now()]);

            return $returnOrder->fresh('items');
        });
    }

    public function completeReturn(InventoryReturn $returnOrder, User $actor, string $disposition, ?string $notes = null): InventoryReturn
    {
        return DB::transaction(function () use ($returnOrder, $actor, $disposition, $notes): InventoryReturn {
            $returnOrder = InventoryReturn::query()->where('restaurant_id', $actor->restaurant_id)->with(['items.quarantine', 'items.batch'])->whereKey($returnOrder->id)->lockForUpdate()->firstOrFail();
            if (! in_array($returnOrder->status, ['in_transit', 'requested'], true)) {
                throw new InvalidArgumentException('Phiếu hoàn trả không còn ở trạng thái có thể chốt.');
            }

            foreach ($returnOrder->items as $item) {
                $quarantine = $item->quarantine;
                if ($quarantine) {
                    $quarantine->update([
                        'status' => $disposition === 'destroyed' ? 'destroyed' : 'returned',
                        'disposition' => $disposition,
                        'disposition_reason' => $notes,
                        'resolved_by' => $actor->id,
                        'resolved_at' => now(),
                    ]);
                }
                if ($item->batch && $disposition !== 'central_quarantine') {
                    $item->batch->update(['quantity_remaining' => 0, 'status' => 'depleted']);
                }
                $item->update(['received_quantity' => $item->quantity, 'disposition' => $disposition, 'notes' => $notes ?: $item->notes]);
            }

            $returnOrder->update([
                'status' => $disposition === 'destroyed' ? 'destroyed' : 'received',
                'received_by' => $actor->id,
                'received_at' => now(),
                'resolution_notes' => $notes,
            ]);

            if ($returnOrder->source_type === 'stock_transfer' && $returnOrder->source_id) {
                StockTransferRequest::where('restaurant_id', $actor->restaurant_id)
                    ->whereKey($returnOrder->source_id)
                    ->update([
                        'status' => $disposition === 'destroyed' ? 'destroyed' : 'returned',
                        'disposition' => $disposition,
                        'disposition_notes' => $notes,
                        'disposition_by' => $actor->id,
                        'disposition_at' => now(),
                    ]);
            }

            return $returnOrder->fresh('items');
        });
    }

    public function createClaim(User $actor, array $data, array $evidencePaths = []): SupplierClaim
    {
        return SupplierClaim::create([
            'restaurant_id' => $actor->restaurant_id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'carrier_name' => $data['carrier_name'] ?? null,
            'status' => 'open',
            'reason' => $data['reason'],
            'loss_amount' => $data['loss_amount'] ?? 0,
            'requested_action' => $data['requested_action'] ?? 'replacement',
            'created_by' => $actor->id,
            'due_at' => $data['due_at'] ?? now()->addDays(3),
            'evidence_paths' => $evidencePaths,
        ]);
    }

    private function returnCode(int $restaurantId): string
    {
        return 'RTN-'.now()->format('Ymd').'-'.str_pad((string) (InventoryReturn::where('restaurant_id', $restaurantId)->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
