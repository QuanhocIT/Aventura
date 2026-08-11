<?php

namespace App\Http\Controllers;

use App\Models\CashHandover;
use App\Models\ShiftClosing;
use App\Models\User;
use App\Notifications\CashHandoverDisputedNotification;
use App\Notifications\CashHandoverPendingNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bàn giao tiền cuối ca — người giao và người nhận cùng ký.
 *
 * Chữ ký được vẽ trên màn hình và gửi lên dưới dạng data URI; mỗi bên ký bằng
 * chính tài khoản của mình nên không ai ký thay được.
 */
class CashHandoverController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'cashier']), 403);

        $data = $request->validate([
            'shift_closing_id' => ['nullable', 'integer'],
            'to_user_id' => ['required', 'integer', 'different:from_user_id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'signature' => ['required', 'string', 'max:400000'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $restaurantId = (int) $user->restaurant_id;

        // Người nhận phải là tài khoản thật trong cùng nhà hàng, và không được
        // là chính người giao — bàn giao cho chính mình thì không chứng minh gì.
        $recipient = User::where('restaurant_id', $restaurantId)->find($data['to_user_id']);
        abort_unless($recipient, 422, 'Người nhận không hợp lệ.');
        abort_if((int) $recipient->id === (int) $user->id, 422, 'Người giao và người nhận phải khác nhau.');

        $closing = null;
        if (! empty($data['shift_closing_id'])) {
            $closing = ShiftClosing::where('restaurant_id', $restaurantId)->find($data['shift_closing_id']);
            abort_unless($closing, 404);
            abort_if(
                (int) $closing->cashier_user_id !== (int) $user->id && ! $user->hasAnyRole(['owner', 'manager']),
                403,
                'Chỉ người chốt ca hoặc quản lý mới lập được biên bản bàn giao.',
            );
        }

        $handover = DB::transaction(function () use ($data, $user, $recipient, $closing, $restaurantId, $request) {
            $handover = CashHandover::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $closing?->branch_id ?? $user->assignedBranchId(),
                'shift_closing_id' => $closing?->id,
                'from_user_id' => $user->id,
                'to_user_id' => $recipient->id,
                'amount' => $data['amount'],
                'from_signature_path' => $this->storeSignature($data['signature'], $restaurantId),
                'from_signed_at' => now(),
                'photo_path' => $request->file('photo')?->store("handovers/{$restaurantId}", 'public'),
                'notes' => $data['notes'] ?? null,
                'status' => CashHandover::STATUS_PENDING,
            ]);

            $closing?->update(['handover_id' => $handover->id]);

            return $handover;
        });

        $recipient->notify(new CashHandoverPendingNotification($handover, $user));

        return back()->with('success', 'Đã lập biên bản bàn giao. Chờ người nhận ký xác nhận.');
    }

    /**
     * Người nhận ký xác nhận. Chỉ đúng tài khoản người nhận mới ký được.
     */
    public function acknowledge(Request $request, CashHandover $handover): RedirectResponse
    {
        $user = $request->user();
        abort_if($handover->restaurant_id !== $user->restaurant_id, 403);
        abort_if((int) $handover->to_user_id !== (int) $user->id, 403, 'Chỉ người nhận mới ký xác nhận được.');
        abort_unless($handover->status === CashHandover::STATUS_PENDING, 422);

        $data = $request->validate([
            'signature' => ['required', 'string', 'max:400000'],
        ]);

        $handover->update([
            'to_signature_path' => $this->storeSignature($data['signature'], (int) $handover->restaurant_id),
            'to_signed_at' => now(),
            'status' => CashHandover::STATUS_COMPLETED,
        ]);

        return back()->with('success', 'Đã ký xác nhận nhận tiền.');
    }

    /**
     * Người nhận từ chối vì số tiền không khớp.
     */
    public function dispute(Request $request, CashHandover $handover): RedirectResponse
    {
        $user = $request->user();
        abort_if($handover->restaurant_id !== $user->restaurant_id, 403);
        abort_if((int) $handover->to_user_id !== (int) $user->id, 403);
        abort_unless($handover->status === CashHandover::STATUS_PENDING, 422);

        $data = $request->validate([
            'dispute_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $handover->update([
            'status' => CashHandover::STATUS_DISPUTED,
            'dispute_reason' => $data['dispute_reason'],
        ]);

        $handover->fromUser?->notify(
            new CashHandoverDisputedNotification($handover, $user)
        );

        return back()->with('success', 'Đã ghi nhận không khớp. Quản lý sẽ xử lý.');
    }

    /**
     * Lưu chữ ký data URI thành file ảnh.
     */
    private function storeSignature(string $dataUri, int $restaurantId): ?string
    {
        if (! preg_match('/^data:image\/(png|jpeg);base64,/', $dataUri, $matches)) {
            abort(422, 'Chữ ký không hợp lệ.');
        }

        $binary = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true);

        if ($binary === false) {
            abort(422, 'Chữ ký không hợp lệ.');
        }

        $path = "signatures/{$restaurantId}/".Str::uuid().'.'.($matches[1] === 'jpeg' ? 'jpg' : 'png');
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
