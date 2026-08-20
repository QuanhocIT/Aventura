<?php

namespace App\Services;

use App\Models\BatchRecallOrder;
use App\Models\InventoryBatch;
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
                        $query->orWhere('batch_code', $batchCode)
                              ->orWhere('batch_number', $batchCode);
                    }
                })
                ->get();

            $affectedBranchesCount = $affectedBatches->pluck('branch_id')->filter()->unique()->count();
            $totalQuarantinedQty   = (float) $affectedBatches->sum('quantity_remaining');

            // Lock all matching batches across branches
            foreach ($affectedBatches as $b) {
                $b->update(['status' => 'recalled']);
            }

            // 3. Create Recall Order Record
            $recallOrder = BatchRecallOrder::create([
                'restaurant_id'              => $restaurantId,
                'batch_id'                   => $batch->id,
                'recall_code'                => $code,
                'severity'                   => $data['severity'] ?? 'high',
                'reason'                     => $data['reason'],
                'action_taken'               => $data['action_taken'] ?? 'quarantine',
                'status'                     => BatchRecallOrder::STATUS_ACTIVE,
                'affected_branches_count'    => $affectedBranchesCount,
                'total_quarantined_quantity' => $totalQuarantinedQty,
                'initiated_by'               => $user->id,
            ]);

            return $recallOrder->load(['batch.ingredient', 'initiator']);
        });
    }

    /**
     * Complete / Resolve a Batch Recall Order.
     */
    public function completeRecall(BatchRecallOrder $recallOrder, User $user, ?string $resolutionNotes = null): BatchRecallOrder
    {
        $recallOrder->update([
            'status'           => BatchRecallOrder::STATUS_COMPLETED,
            'completed_at'     => now(),
            'resolution_notes' => $resolutionNotes,
        ]);

        return $recallOrder->fresh(['batch.ingredient', 'initiator']);
    }
}
