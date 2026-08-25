<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryQuarantine;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnItem;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\Supplier;
use App\Models\SupplierClaim;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Models\WarehouseReceivingVoucher;
use App\Models\WarehouseShipmentEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

        if ((int) $actor->restaurant_id !== $restaurantId) {
            throw new InvalidArgumentException('Người thực hiện không thuộc nhà hàng của dữ liệu cách ly.');
        }

        $this->assertBranchBelongsToRestaurant($branchId, $restaurantId);
        $this->assertIngredientBelongsToRestaurant($ingredientId, $restaurantId);

        if ($batch) {
            $this->assertBatchBelongsToRestaurant($batch, $restaurantId, $branchId, $ingredientId);
        }

        // Receiving/transfer requests can be retried by the browser or a job.
        // Do not create a second quarantine record for the same source item.
        if ($sourceType !== null && $sourceId !== null) {
            $existing = InventoryQuarantine::query()
                ->where('restaurant_id', $restaurantId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where(function ($query) use ($sourceItemId): void {
                    $query->where('source_item_id', $sourceItemId);
                    if ($sourceItemId === null) {
                        $query->orWhereNull('source_item_id');
                    }
                })
                ->whereNotIn('status', ['returned', 'destroyed'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ((int) $existing->ingredient_id !== $ingredientId || abs((float) $existing->quantity - $quantity) > 0.0005) {
                    throw new InvalidArgumentException('Nguồn hàng đã có hồ sơ cách ly với số lượng hoặc nguyên liệu khác.');
                }

                return $existing;
            }
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

            $this->assertQuarantineIntegrity($lockedQuarantine, $actor->restaurant_id);

            $pendingReturnExists = $lockedQuarantine->returnItems()
                ->whereHas('returnOrder', fn ($query) => $query->whereIn('status', ['requested', 'in_transit']))
                ->exists();
            if ($pendingReturnExists) {
                throw new InvalidArgumentException('Lô này đang có phiếu hoàn trả chờ duyệt hoặc đang vận chuyển.');
            }

            $quantityAlreadyReturned = (float) $lockedQuarantine->returnItems()
                ->whereHas('returnOrder', fn ($query) => $query->whereIn('status', ['received', 'destroyed']))
                ->sum('quantity');
            $remaining = max(0, (float) $lockedQuarantine->quantity - $quantityAlreadyReturned);
            $quantity = (float) ($data['quantity'] ?? $remaining);
            if ($quantity <= 0 || $quantity > $remaining + 0.0005) {
                throw new InvalidArgumentException('Số lượng hoàn trả vượt quá số lượng đang cách ly.');
            }

            $batch = $lockedQuarantine->inventory_batch_id
                ? InventoryBatch::query()->where('restaurant_id', $actor->restaurant_id)->lockForUpdate()->find($lockedQuarantine->inventory_batch_id)
                : null;
            if ($lockedQuarantine->inventory_batch_id && ! $batch) {
                throw new InvalidArgumentException('Không tìm thấy lô tồn kho gốc của hồ sơ cách ly.');
            }

            $toBranchId = $data['to_branch_id'] ?? null;
            if ($toBranchId !== null) {
                $this->assertBranchBelongsToRestaurant((int) $toBranchId, (int) $actor->restaurant_id);
                if ((int) $toBranchId === (int) $lockedQuarantine->branch_id) {
                    throw new InvalidArgumentException('Kho đích phải khác kho đang cách ly.');
                }
            }

            $supplierId = $data['supplier_id'] ?? $batch?->supplier_id;
            if ($supplierId !== null) {
                $this->assertSupplierBelongsToRestaurant((int) $supplierId, (int) $actor->restaurant_id);
            }

            $return = InventoryReturn::create([
                'restaurant_id' => $actor->restaurant_id,
                'return_code' => $this->returnCode($actor->restaurant_id),
                'source_type' => $lockedQuarantine->source_type,
                'source_id' => $lockedQuarantine->source_id,
                'from_branch_id' => $lockedQuarantine->branch_id,
                'to_branch_id' => $toBranchId,
                'supplier_id' => $supplierId,
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
                'unit_cost' => (float) ($batch?->unit_cost ?? 0),
                'condition' => $lockedQuarantine->condition,
                'notes' => $data['notes'] ?? null,
            ]);

            $lockedQuarantine->update(['status' => 'return_requested']);

            $this->audit($actor, 'warehouse.reverse_logistics.return_requested', $return, null, [
                'quarantine_id' => $lockedQuarantine->id,
                'quantity' => $quantity,
                'supplier_id' => $supplierId,
                'to_branch_id' => $toBranchId,
            ]);

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

            $this->assertMakerChecker($returnOrder, $actor, 'duyệt');

            foreach ($returnOrder->items as $item) {
                if ($item->quarantine_id) {
                    $batch = $item->inventory_batch_id
                        ? InventoryBatch::query()->where('restaurant_id', $actor->restaurant_id)->lockForUpdate()->find($item->inventory_batch_id)
                        : null;
                    if ($item->inventory_batch_id && (! $batch || (int) $batch->ingredient_id !== (int) $item->ingredient_id || (int) $batch->branch_id !== (int) $returnOrder->from_branch_id)) {
                        throw new InvalidArgumentException('Lô hàng hoàn trả không khớp với kho và nguyên liệu nguồn.');
                    }
                    if ($batch && (float) $batch->quantity_remaining + 0.0005 < (float) $item->quantity) {
                        throw new InvalidArgumentException('Số lượng trong lô cách ly không đủ để hoàn trả.');
                    }

                    // Quarantine stock is already excluded from available inventory.
                    // Approval only authorizes the hand-off; the batch is reduced when
                    // the destination actually confirms receipt/disposal.
                    continue;
                }

                if (! $item->inventory_batch_id) {
                    throw new InvalidArgumentException('Dòng hoàn trả không có lô tồn kho để đối soát.');
                }

                $inventory = Inventory::query()
                    ->where('restaurant_id', $actor->restaurant_id)
                    ->where('branch_id', $returnOrder->from_branch_id)
                    ->where('ingredient_id', $item->ingredient_id)
                    ->lockForUpdate()
                    ->first();
                $batch = InventoryBatch::query()->where('restaurant_id', $actor->restaurant_id)->whereKey($item->inventory_batch_id)->lockForUpdate()->first();
                if (! $inventory || ! $batch || (int) $batch->ingredient_id !== (int) $item->ingredient_id || (int) $batch->branch_id !== (int) $returnOrder->from_branch_id || (float) $inventory->quantity_on_hand + 0.0005 < (float) $item->quantity || (float) $batch->quantity_remaining + 0.0005 < (float) $item->quantity) {
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

            $this->audit($actor, 'warehouse.reverse_logistics.return_approved', $returnOrder, ['status' => 'requested'], [
                'status' => 'in_transit',
                'return_code' => $returnOrder->return_code,
            ]);

            return $returnOrder->fresh('items');
        });
    }

    public function completeReturn(InventoryReturn $returnOrder, User $actor, string $disposition, ?string $notes = null, array $evidencePaths = []): InventoryReturn
    {
        return DB::transaction(function () use ($returnOrder, $actor, $disposition, $notes, $evidencePaths): InventoryReturn {
            $returnOrder = InventoryReturn::query()->where('restaurant_id', $actor->restaurant_id)->with(['items.quarantine', 'items.batch'])->whereKey($returnOrder->id)->lockForUpdate()->firstOrFail();
            if ($returnOrder->status !== 'in_transit') {
                throw new InvalidArgumentException('Phiếu hoàn trả phải được duyệt trước khi chốt giao nhận.');
            }

            $this->assertMakerChecker($returnOrder, $actor, 'chốt');
            if ($disposition === 'supplier_confirmed' && ! $returnOrder->supplier_id && $returnOrder->source_type !== 'stock_transfer') {
                throw new InvalidArgumentException('Hoàn trả cho nhà cung cấp phải xác định nhà cung cấp nhận hàng.');
            }
            if ($disposition === 'central_quarantine' && ! $returnOrder->to_branch_id) {
                throw new InvalidArgumentException('Chuyển cách ly Kho Tổng phải xác định kho đích.');
            }
            if ($disposition === 'destroyed' && $evidencePaths === [] && ($returnOrder->evidence_paths ?? []) === []) {
                throw new InvalidArgumentException('Tiêu hủy hàng hoàn trả bắt buộc có ảnh hoặc biên bản.');
            }

            foreach ($returnOrder->items as $item) {
                $quarantine = $item->quarantine;
                $batch = $item->batch;
                if ($quarantine && ! $batch) {
                    throw new InvalidArgumentException('Hồ sơ cách ly không còn lô tồn kho để đối soát khi chốt.');
                }
                if ($quarantine && $batch) {
                    $remainingInBatch = (float) $batch->quantity_remaining;
                    if ($remainingInBatch + 0.0005 < (float) $item->quantity) {
                        throw new InvalidArgumentException('Lô cách ly không còn đủ số lượng để chốt phiếu này.');
                    }

                    $batch->update([
                        'quantity_remaining' => max(0, $remainingInBatch - (float) $item->quantity),
                        'status' => max(0, $remainingInBatch - (float) $item->quantity) <= 0.0005 ? 'depleted' : 'locked',
                    ]);

                    if ($disposition === 'central_quarantine') {
                        $destinationBatch = $this->createDestinationBatch(
                            (int) $actor->restaurant_id,
                            (int) $returnOrder->to_branch_id,
                            (int) $item->ingredient_id,
                            (float) $item->quantity,
                            (float) $item->unit_cost,
                            $actor,
                            $batch,
                            true,
                            'Hàng hoàn trả đang được cách ly lại để chờ kết luận chất lượng.',
                        );
                        $this->createQuarantine(
                            (int) $actor->restaurant_id,
                            (int) $returnOrder->to_branch_id,
                            (int) $item->ingredient_id,
                            (float) $item->quantity,
                            (string) $item->condition,
                            'Chuyển cách ly lại từ phiếu hoàn trả '.$returnOrder->return_code.'.',
                            $actor,
                            $destinationBatch,
                            'inventory_return',
                            $returnOrder->id,
                            $item->id,
                            array_values(array_filter(array_merge($returnOrder->evidence_paths ?? [], $evidencePaths))),
                            $notes,
                        );
                    }
                }

                if ($quarantine) {
                    $quarantine->update([
                        'status' => $disposition === 'destroyed' ? 'destroyed' : 'returned',
                        'disposition' => $disposition,
                        'disposition_reason' => $notes,
                        'evidence_paths' => array_values(array_filter(array_merge($quarantine->evidence_paths ?? [], $evidencePaths))),
                        'resolved_by' => $actor->id,
                        'resolved_at' => now(),
                    ]);
                }
                $item->update(['received_quantity' => $item->quantity, 'disposition' => $disposition, 'notes' => $notes ?: $item->notes]);
                if ($quarantine) {
                    $settledQuantity = (float) $quarantine->returnItems()
                        ->where(function ($query) use ($returnOrder): void {
                            $query->whereHas('returnOrder', fn ($returnQuery) => $returnQuery->whereIn('status', ['received', 'destroyed']))
                                ->orWhere('return_id', $returnOrder->id);
                        })
                        ->sum('quantity');
                    $remainingQuantity = max(0, (float) $quarantine->quantity - $settledQuantity);
                    $isFullySettled = $remainingQuantity <= 0.0005;
                    $quarantine->update([
                        'status' => $isFullySettled ? ($disposition === 'destroyed' ? 'destroyed' : 'returned') : 'open',
                        'disposition' => $isFullySettled ? $disposition : 'partially_'.$disposition,
                        'resolved_by' => $isFullySettled ? $actor->id : null,
                        'resolved_at' => $isFullySettled ? now() : null,
                    ]);
                }
            }

            $returnEvidence = array_values(array_filter(array_merge($returnOrder->evidence_paths ?? [], $evidencePaths)));
            $returnOrder->update([
                'status' => $disposition === 'destroyed' ? 'destroyed' : 'received',
                'received_by' => $actor->id,
                'received_at' => now(),
                'resolution_notes' => $notes,
                'evidence_paths' => $returnEvidence,
            ]);

            if ($returnOrder->source_type === 'stock_transfer' && $returnOrder->source_id) {
                $sourceFullySettled = $returnOrder->items->every(function (InventoryReturnItem $item): bool {
                    return ! $item->quarantine || in_array($item->quarantine->fresh()->status, ['returned', 'destroyed'], true);
                });
                StockTransferRequest::where('restaurant_id', $actor->restaurant_id)
                    ->whereKey($returnOrder->source_id)
                    ->update([
                        'status' => $sourceFullySettled ? ($disposition === 'destroyed' ? 'destroyed' : 'returned') : 'quarantined',
                        'disposition' => $disposition,
                        'disposition_notes' => $notes,
                        'disposition_by' => $actor->id,
                        'disposition_at' => now(),
                    ]);
            }

            if ($returnOrder->source_type === 'warehouse_receiving_voucher' && $returnOrder->source_id) {
                $voucherStatus = match ($disposition) {
                    'destroyed' => 'destroyed',
                    'supplier_confirmed' => 'returned',
                    default => 'confirmed',
                };
                WarehouseReceivingVoucher::where('restaurant_id', $actor->restaurant_id)
                    ->whereKey($returnOrder->source_id)
                    ->update([
                        'status' => $voucherStatus,
                        'disposition' => $disposition === 'supplier_confirmed' ? 'return_supplier' : $disposition,
                        'disposition_reason' => $notes,
                        'disposed_by' => $actor->id,
                        'disposed_at' => now(),
                        'disposition_evidence_paths' => $returnEvidence,
                    ]);
            }

            $this->audit($actor, 'warehouse.reverse_logistics.return_completed', $returnOrder, ['status' => 'in_transit'], [
                'status' => $returnOrder->status,
                'disposition' => $disposition,
            ]);

            return $returnOrder->fresh('items');
        });
    }

    public function createClaim(User $actor, array $data, array $evidencePaths = []): SupplierClaim
    {
        $supplierId = $data['supplier_id'] ?? null;
        if ($supplierId === null && ($data['source_type'] ?? null) === 'inventory_return' && ($data['source_id'] ?? null)) {
            $supplierId = InventoryReturn::where('restaurant_id', $actor->restaurant_id)
                ->whereKey((int) $data['source_id'])
                ->value('supplier_id');
        }
        if ($supplierId === null && blank($data['carrier_name'] ?? null)) {
            throw new InvalidArgumentException('Khiếu nại phải xác định nhà cung cấp hoặc đơn vị vận chuyển.');
        }
        if ($supplierId !== null) {
            $this->assertSupplierBelongsToRestaurant((int) $supplierId, (int) $actor->restaurant_id);
        }

        if ($supplierId !== null && ($data['source_type'] ?? null) === 'inventory_return' && ($data['source_id'] ?? null)) {
            $sourceSupplierId = InventoryReturn::where('restaurant_id', $actor->restaurant_id)
                ->whereKey((int) $data['source_id'])
                ->value('supplier_id');
            if ($sourceSupplierId !== null && (int) $sourceSupplierId !== (int) $supplierId) {
                throw new InvalidArgumentException('Nhà cung cấp khiếu nại không khớp với phiếu hoàn trả.');
            }
        }

        $this->assertClaimSourceBelongsToRestaurant($actor, $data['source_type'] ?? null, $data['source_id'] ?? null);

        $duplicate = SupplierClaim::query()
            ->where('restaurant_id', $actor->restaurant_id)
            ->whereIn('status', ['open', 'investigating'])
            ->when($data['source_type'] ?? null, fn ($query, $sourceType) => $query->where('source_type', $sourceType))
            ->when($data['source_id'] ?? null, fn ($query, $sourceId) => $query->where('source_id', $sourceId))
            ->exists();
        if ($duplicate && ($data['source_type'] ?? null) && ($data['source_id'] ?? null)) {
            throw new InvalidArgumentException('Nguồn hàng này đã có khiếu nại đang mở.');
        }

        $claim = SupplierClaim::create([
            'restaurant_id' => $actor->restaurant_id,
            'supplier_id' => $supplierId,
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

        $this->audit($actor, 'warehouse.reverse_logistics.claim_created', $claim, null, [
            'source_type' => $claim->source_type,
            'source_id' => $claim->source_id,
            'loss_amount' => $claim->loss_amount,
        ]);

        return $claim;
    }

    public function resolveClaim(SupplierClaim $claim, User $actor, string $responseNotes): SupplierClaim
    {
        return DB::transaction(function () use ($claim, $actor, $responseNotes): SupplierClaim {
            $locked = SupplierClaim::query()
                ->where('restaurant_id', $actor->restaurant_id)
                ->whereKey($claim->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['open', 'investigating'], true)) {
                throw new InvalidArgumentException('Hồ sơ khiếu nại đã được đóng trước đó.');
            }
            if ((int) $locked->created_by === (int) $actor->id && ! $actor->isOwner() && ! $actor->isSuperAdmin()) {
                throw new InvalidArgumentException('Người lập khiếu nại không được tự đóng hồ sơ của mình.');
            }

            $locked->update([
                'status' => 'resolved',
                'response_notes' => $responseNotes,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);
            $this->audit($actor, 'warehouse.reverse_logistics.claim_resolved', $locked, ['status' => 'open'], [
                'status' => 'resolved',
            ]);

            return $locked->fresh();
        });
    }

    private function returnCode(int $restaurantId): string
    {
        do {
            $code = 'RTN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (InventoryReturn::where('restaurant_id', $restaurantId)->where('return_code', $code)->exists());

        return $code;
    }

    private function assertBranchBelongsToRestaurant(int $branchId, int $restaurantId): void
    {
        if (! RestaurantBranch::query()->where('restaurant_id', $restaurantId)->whereKey($branchId)->where('status', 'active')->exists()) {
            throw new InvalidArgumentException('Chi nhánh/kho không thuộc nhà hàng hoặc đã ngừng hoạt động.');
        }
    }

    private function assertIngredientBelongsToRestaurant(int $ingredientId, int $restaurantId): void
    {
        if (! Ingredient::query()->where('restaurant_id', $restaurantId)->whereKey($ingredientId)->exists()) {
            throw new InvalidArgumentException('Nguyên liệu không thuộc nhà hàng.');
        }
    }

    private function assertSupplierBelongsToRestaurant(int $supplierId, int $restaurantId): void
    {
        if (! Supplier::withTrashed()->where('restaurant_id', $restaurantId)->whereKey($supplierId)->exists()) {
            throw new InvalidArgumentException('Nhà cung cấp không thuộc nhà hàng.');
        }
    }

    private function assertBatchBelongsToRestaurant(InventoryBatch $batch, int $restaurantId, int $branchId, int $ingredientId): void
    {
        if ((int) $batch->restaurant_id !== $restaurantId || (int) $batch->ingredient_id !== $ingredientId || (int) $batch->branch_id !== $branchId) {
            throw new InvalidArgumentException('Lô tồn kho không khớp nhà hàng, kho hoặc nguyên liệu.');
        }
    }

    private function assertQuarantineIntegrity(InventoryQuarantine $quarantine, int $restaurantId): void
    {
        if ((int) $quarantine->restaurant_id !== $restaurantId) {
            throw new InvalidArgumentException('Hồ sơ cách ly không thuộc nhà hàng.');
        }
        if ($quarantine->branch_id !== null) {
            $this->assertBranchBelongsToRestaurant((int) $quarantine->branch_id, $restaurantId);
        }
        $this->assertIngredientBelongsToRestaurant((int) $quarantine->ingredient_id, $restaurantId);
        if ($quarantine->inventory_batch_id) {
            $batch = InventoryBatch::query()->where('restaurant_id', $restaurantId)->find($quarantine->inventory_batch_id);
            if (! $batch) {
                throw new InvalidArgumentException('Lô tồn kho gắn với cách ly không còn tồn tại.');
            }
            if ((int) $batch->restaurant_id !== $restaurantId || (int) $batch->ingredient_id !== (int) $quarantine->ingredient_id || ($quarantine->branch_id !== null && (int) $batch->branch_id !== (int) $quarantine->branch_id)) {
                throw new InvalidArgumentException('Lô tồn kho không khớp nhà hàng, kho hoặc nguyên liệu.');
            }
        }
    }

    private function assertMakerChecker(InventoryReturn $returnOrder, User $actor, string $action): void
    {
        if ((int) $returnOrder->created_by === (int) $actor->id && ! $actor->isOwner() && ! $actor->isSuperAdmin()) {
            throw new InvalidArgumentException("Người lập phiếu không được tự {$action} phiếu hoàn trả.");
        }
    }

    private function assertClaimSourceBelongsToRestaurant(User $actor, ?string $sourceType, ?int $sourceId): void
    {
        if ($sourceType === null && $sourceId === null) {
            return;
        }
        if ($sourceType === null || $sourceId === null) {
            throw new InvalidArgumentException('Nguồn khiếu nại phải có cả loại và mã chứng từ.');
        }

        $exists = match ($sourceType) {
            'inventory_return' => InventoryReturn::where('restaurant_id', $actor->restaurant_id)->whereKey($sourceId)->exists(),
            'inventory_quarantine' => InventoryQuarantine::where('restaurant_id', $actor->restaurant_id)->whereKey($sourceId)->exists(),
            'stock_transfer' => StockTransferRequest::where('restaurant_id', $actor->restaurant_id)->whereKey($sourceId)->exists(),
            'supply_request' => SupplyRequest::where('restaurant_id', $actor->restaurant_id)->whereKey($sourceId)->exists(),
            'warehouse_receiving_voucher' => WarehouseReceivingVoucher::where('restaurant_id', $actor->restaurant_id)->whereKey($sourceId)->exists(),
            default => false,
        };
        if (! $exists) {
            throw new InvalidArgumentException('Chứng từ nguồn khiếu nại không thuộc nhà hàng hoặc không được hỗ trợ.');
        }
    }

    private function audit(User $actor, string $action, $subject, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'restaurant_id' => $actor->restaurant_id,
            'branch_id' => $actor->assignedBranchId(),
            'user_id' => $actor->id,
            'user_role' => $actor->roles()->pluck('name')->first() ?? 'staff',
            'event' => $oldValues === null ? 'created' : 'updated',
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
