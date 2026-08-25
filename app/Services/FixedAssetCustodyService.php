<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FixedAsset;
use App\Models\FixedAssetHandover;
use App\Models\FixedAssetInspection;
use App\Models\RestaurantBranch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixedAssetCustodyService
{
    public function createHandover(FixedAsset $asset, User $actor, array $data): FixedAssetHandover
    {
        return DB::transaction(function () use ($asset, $actor, $data): FixedAssetHandover {
            $lockedAsset = FixedAsset::withoutGlobalScopes()->lockForUpdate()->findOrFail($asset->id);

            if ($lockedAsset->restaurant_id !== $actor->restaurant_id) {
                abort(403);
            }

            $branch = RestaurantBranch::where('restaurant_id', $actor->restaurant_id)
                ->where('status', 'active')
                ->find($data['branch_id']);

            if (! $branch) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Chi nhánh bàn giao không tồn tại hoặc đang ngừng hoạt động.',
                ]);
            }

            if ($lockedAsset->status !== 'active') {
                throw ValidationException::withMessages([
                    'asset' => 'Chỉ tài sản đang hoạt động mới được bàn giao.',
                ]);
            }

            if (FixedAssetHandover::withoutGlobalScopes()
                ->where('fixed_asset_id', $lockedAsset->id)
                ->where('status', FixedAssetHandover::STATUS_PENDING)
                ->exists()) {
                throw ValidationException::withMessages([
                    'asset' => 'Tài sản này đang có một biên bản bàn giao chờ xác nhận.',
                ]);
            }

            $recipient = User::where('restaurant_id', $actor->restaurant_id)
                ->where('status', 'active')
                ->find($data['to_user_id']);

            if (! $recipient || ! $recipient->isBranchManager() || $recipient->assignedBranchId() !== (int) $data['branch_id']) {
                throw ValidationException::withMessages([
                    'to_user_id' => 'Người nhận phải là Quản lý đang phụ trách đúng chi nhánh được chọn.',
                ]);
            }

            $handover = FixedAssetHandover::create([
                'restaurant_id' => $lockedAsset->restaurant_id,
                'fixed_asset_id' => $lockedAsset->id,
                'branch_id' => $data['branch_id'],
                'handover_code' => $this->nextCode($lockedAsset->restaurant_id, 'FAH', 'fixed_asset_handovers', 'handover_code', $data['handover_date']),
                'handed_over_by' => $actor->id,
                'to_user_id' => $recipient->id,
                'previous_branch_id' => $lockedAsset->branch_id,
                'previous_custodian_user_id' => $lockedAsset->custodian_user_id,
                'previous_custody_status' => $lockedAsset->custody_status ?? 'unassigned',
                'previous_custody_location' => $lockedAsset->custody_location,
                'status' => FixedAssetHandover::STATUS_PENDING,
                'handover_date' => $data['handover_date'],
                'condition_at_handover' => $data['condition_at_handover'],
                'custody_location' => $data['custody_location'] ?? null,
                'evidence_path' => $data['evidence_path'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $lockedAsset->update([
                'custody_status' => 'pending_handover',
            ]);

            AuditLog::log('fixed_asset_handover_created', 'created', $handover, null, [
                'asset_id' => $lockedAsset->id,
                'handover_code' => $handover->handover_code,
                'branch_id' => $handover->branch_id,
                'to_user_id' => $handover->to_user_id,
                'status' => $handover->status,
            ]);

            return $handover;
        });
    }

    public function acceptHandover(FixedAssetHandover $handover, User $recipient, ?string $notes = null): FixedAssetHandover
    {
        return DB::transaction(function () use ($handover, $recipient, $notes): FixedAssetHandover {
            $lockedHandover = FixedAssetHandover::withoutGlobalScopes()->lockForUpdate()->findOrFail($handover->id);

            if ($lockedHandover->restaurant_id !== $recipient->restaurant_id) {
                abort(403);
            }

            if ((int) $lockedHandover->to_user_id !== (int) $recipient->id) {
                abort(403, 'Chỉ đúng Quản lý được chỉ định mới xác nhận nhận tài sản.');
            }

            if ($lockedHandover->status !== FixedAssetHandover::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'handover' => 'Biên bản này không còn chờ xác nhận.',
                ]);
            }

            $asset = FixedAsset::withoutGlobalScopes()->lockForUpdate()->findOrFail($lockedHandover->fixed_asset_id);
            $oldValues = [
                'custody_status' => $asset->custody_status,
                'branch_id' => $asset->branch_id,
                'custodian_user_id' => $asset->custodian_user_id,
            ];

            $lockedHandover->update([
                'status' => FixedAssetHandover::STATUS_ACCEPTED,
                'accepted_by' => $recipient->id,
                'accepted_at' => now(),
                'notes' => $notes ?: $lockedHandover->notes,
            ]);

            $asset->update([
                'branch_id' => $lockedHandover->branch_id,
                'custodian_user_id' => $recipient->id,
                'custody_status' => 'assigned',
                'condition_status' => $lockedHandover->condition_at_handover,
                'custody_location' => $lockedHandover->custody_location,
            ]);

            AuditLog::log('fixed_asset_handover_accepted', 'updated', $lockedHandover, $oldValues, [
                'custody_status' => $asset->custody_status,
                'branch_id' => $asset->branch_id,
                'custodian_user_id' => $asset->custodian_user_id,
                'accepted_by' => $recipient->id,
            ]);

            return $lockedHandover->fresh(['asset', 'branch', 'toUser']);
        });
    }

    public function rejectHandover(FixedAssetHandover $handover, User $recipient, string $reason): FixedAssetHandover
    {
        return DB::transaction(function () use ($handover, $recipient, $reason): FixedAssetHandover {
            $lockedHandover = FixedAssetHandover::withoutGlobalScopes()->lockForUpdate()->findOrFail($handover->id);

            if ($lockedHandover->restaurant_id !== $recipient->restaurant_id) {
                abort(403);
            }

            if ((int) $lockedHandover->to_user_id !== (int) $recipient->id) {
                abort(403, 'Chỉ đúng Quản lý được chỉ định mới có thể từ chối nhận tài sản.');
            }

            if ($lockedHandover->status !== FixedAssetHandover::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'handover' => 'Biên bản này không còn chờ xác nhận.',
                ]);
            }

            $asset = FixedAsset::withoutGlobalScopes()->lockForUpdate()->findOrFail($lockedHandover->fixed_asset_id);
            $lockedHandover->update([
                'status' => FixedAssetHandover::STATUS_REJECTED,
                'rejected_by' => $recipient->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $asset->update([
                'branch_id' => $lockedHandover->previous_branch_id,
                'custodian_user_id' => $lockedHandover->previous_custodian_user_id,
                'custody_status' => $lockedHandover->previous_custody_status ?: 'unassigned',
                'custody_location' => $lockedHandover->previous_custody_location,
            ]);

            AuditLog::log('fixed_asset_handover_rejected', 'updated', $lockedHandover, [
                'status' => FixedAssetHandover::STATUS_PENDING,
            ], [
                'status' => $lockedHandover->status,
                'rejected_by' => $recipient->id,
                'rejection_reason' => $reason,
            ]);

            return $lockedHandover->fresh(['asset', 'branch', 'toUser']);
        });
    }

    public function inspect(FixedAsset $asset, User $inspector, array $data): FixedAssetInspection
    {
        return DB::transaction(function () use ($asset, $inspector, $data): FixedAssetInspection {
            $lockedAsset = FixedAsset::withoutGlobalScopes()->lockForUpdate()->findOrFail($asset->id);

            if ($lockedAsset->restaurant_id !== $inspector->restaurant_id) {
                abort(403);
            }

            $inspection = FixedAssetInspection::create([
                'restaurant_id' => $lockedAsset->restaurant_id,
                'fixed_asset_id' => $lockedAsset->id,
                'fixed_asset_handover_id' => $data['fixed_asset_handover_id'] ?? null,
                'branch_id' => $lockedAsset->branch_id,
                'inspection_code' => $this->nextCode($lockedAsset->restaurant_id, 'FAI', 'fixed_asset_inspections', 'inspection_code', $data['inspected_at']),
                'inspector_id' => $inspector->id,
                'inspection_type' => $data['inspection_type'],
                'inspected_at' => $data['inspected_at'],
                'condition_status' => $data['condition_status'],
                'result' => $data['result'],
                'score' => $data['score'] ?? null,
                'findings' => $data['findings'],
                'action_required' => $data['action_required'] ?? null,
                'evidence_path' => $data['evidence_path'] ?? null,
                'status' => 'completed',
            ]);

            $lockedAsset->update([
                'condition_status' => $data['condition_status'],
                'last_inspected_at' => CarbonImmutable::parse($data['inspected_at'])->endOfDay(),
                'custody_status' => $data['result'] === FixedAssetInspection::RESULT_PASS
                    ? ($lockedAsset->custodian_user_id ? 'assigned' : 'unassigned')
                    : 'attention',
            ]);

            AuditLog::log('fixed_asset_inspected', 'created', $inspection, null, [
                'asset_id' => $lockedAsset->id,
                'inspection_code' => $inspection->inspection_code,
                'branch_id' => $inspection->branch_id,
                'result' => $inspection->result,
                'condition_status' => $inspection->condition_status,
                'score' => $inspection->score,
            ]);

            return $inspection->fresh(['asset', 'branch', 'inspector']);
        });
    }

    private function nextCode(int $restaurantId, string $prefix, string $table, string $column, string $date): string
    {
        $datePart = CarbonImmutable::parse($date)->format('Ymd');
        $base = $prefix.'-'.$datePart.'-';
        $sequence = DB::table($table)
            ->where('restaurant_id', $restaurantId)
            ->where($column, 'like', $base.'%')
            ->count() + 1;

        do {
            $code = $base.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (DB::table($table)->where('restaurant_id', $restaurantId)->where($column, $code)->exists());

        return $code;
    }
}
