<?php

namespace App\Services;

use App\Models\BatchRecallOrder;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryQuarantine;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BatchRecallService
{
    /**
     * Initiate a Batch Recall Order (Kích hoạt lệnh thu hồi lô khẩn cấp toàn chuỗi).
     */
    public function initiateRecall(int $restaurantId, int $batchId, array $data, User $user): BatchRecallOrder
    {
        return DB::transaction(function () use ($restaurantId, $batchId, $data, $user) {
            $batch = InventoryBatch::where('restaurant_id', $restaurantId)->findOrFail($batchId);

            $code = 'RCL-'.Carbon::now()->format('Ymd').'-'.str_pad((string) (BatchRecallOrder::where('restaurant_id', $restaurantId)->count() + 1), 4, '0', STR_PAD_LEFT);

            // 1. Lock batch at system level
            $batch->update(['status' => 'recalled']);

            // 2. Count affected branches holding this batch code or number
            $batchCode = $batch->batch_code ?: $batch->batch_number;
            $affectedBatches = InventoryBatch::where('restaurant_id', $restaurantId)
                ->where(function ($query) use ($batch, $batchCode) {
                    $query->where('id', $batch->id);
                    if ($batchCode) {
                        $query->orWhere('batch_number', $batchCode);
                    }
                })
                ->get();

            $affectedBranchesCount = $affectedBatches->pluck('branch_id')->filter()->unique()->count();
            $totalQuarantinedQty = (float) $affectedBatches->sum('quantity_remaining');

            // Lock all matching batches across branches
            foreach ($affectedBatches as $b) {
                $b->update(['status' => 'recalled']);
            }

            // 3. Create Recall Order Record
            $recallOrder = BatchRecallOrder::create([
                'restaurant_id' => $restaurantId,
                'batch_id' => $batch->id,
                'recall_code' => $code,
                'severity' => $data['severity'] ?? 'high',
                'reason' => $data['reason'],
                'action_taken' => $data['action_taken'] ?? 'quarantine',
                'status' => BatchRecallOrder::STATUS_ACTIVE,
                'affected_branches_count' => $affectedBranchesCount,
                'total_quarantined_quantity' => $totalQuarantinedQty,
                'initiated_by' => $user->id,
            ]);

            foreach ($affectedBatches as $affectedBatch) {
                $quantity = (float) $affectedBatch->quantity_remaining;
                if ($quantity <= 0 || ! $affectedBatch->branch_id) {
                    continue;
                }
                $inventory = Inventory::where('restaurant_id', $restaurantId)
                    ->where('branch_id', $affectedBatch->branch_id)
                    ->where('ingredient_id', $affectedBatch->ingredient_id)
                    ->lockForUpdate()
                    ->first();
                $available = min($quantity, (float) ($inventory?->quantity_on_hand ?? 0));
                if ($available > 0 && $inventory) {
                    $before = (float) $inventory->quantity_on_hand;
                    $transaction = InventoryTransaction::createWithIdempotency([
                        'restaurant_id' => $restaurantId,
                        'branch_id' => $affectedBatch->branch_id,
                        'ingredient_id' => $affectedBatch->ingredient_id,
                        'inventory_id' => $inventory->id,
                        'performed_by' => $user->id,
                        'type' => 'adjustment',
                        'direction' => 'out',
                        'quantity' => $available,
                        'unit_cost' => $affectedBatch->unit_cost,
                        'total_cost' => $available * (float) $affectedBatch->unit_cost,
                        'source_type' => 'batch_recall',
                        'source_id' => $recallOrder->id,
                        'idempotency_key' => 'recall_quarantine_'.$recallOrder->id.'_'.$affectedBatch->id,
                        'reference_code' => $recallOrder->recall_code,
                        'notes' => 'Đưa lô thu hồi vào cách ly.',
                        'occurred_at' => now(),
                    ]);
                    $inventory->update([
                        'quantity_on_hand' => $before - $available,
                        'theoretical_quantity' => max(0, (float) $inventory->theoretical_quantity - $available),
                        'updated_by' => $user->id,
                    ]);
                    InventoryBatchAllocation::create([
                        'restaurant_id' => $restaurantId,
                        'branch_id' => $affectedBatch->branch_id,
                        'inventory_batch_id' => $affectedBatch->id,
                        'inventory_transaction_id' => $transaction->id,
                        'direction' => 'out',
                        'quantity' => $available,
                        'unit_cost' => $affectedBatch->unit_cost,
                    ]);
                }
                InventoryQuarantine::create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $affectedBatch->branch_id,
                    'ingredient_id' => $affectedBatch->ingredient_id,
                    'inventory_batch_id' => $affectedBatch->id,
                    'source_type' => 'batch_recall',
                    'source_id' => $recallOrder->id,
                    'quantity' => $quantity,
                    'condition' => 'recall',
                    'status' => 'open',
                    'reason' => $data['reason'],
                    'notes' => $data['action_taken'] ?? 'Cách ly lô thu hồi.',
                    'created_by' => $user->id,
                ]);
            }

            return $recallOrder->load(['batch.ingredient', 'initiator']);
        });
    }

    /**
     * Complete / Resolve a Batch Recall Order.
     */
    public function completeRecall(BatchRecallOrder $recallOrder, User $user, ?string $resolutionNotes = null): BatchRecallOrder
    {
        if (InventoryQuarantine::where('restaurant_id', $recallOrder->restaurant_id)
            ->where('source_type', 'batch_recall')
            ->where('source_id', $recallOrder->id)
            ->whereIn('status', ['open', 'return_requested'])
            ->exists()) {
            throw new \InvalidArgumentException('Chưa thể hoàn tất thu hồi: vẫn còn lô cách ly chưa được trả hoặc tiêu hủy.');
        }

        $recallOrder->update([
            'status' => BatchRecallOrder::STATUS_COMPLETED,
            'completed_at' => now(),
            'resolution_notes' => $resolutionNotes,
        ]);

        return $recallOrder->fresh(['batch.ingredient', 'initiator']);
    }
}
