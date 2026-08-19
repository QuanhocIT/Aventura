<?php

namespace App\Http\Middleware;

use App\Models\SupplyRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware chặn self-approval trong các nghiệp vụ kho:
 *
 * - Tự duyệt đơn cấp phát do chính mình tạo.
 * - Tự thực hiện xuất kho sau khi đã duyệt.
 * - Tự nhận hàng sau khi đã xuất kho.
 *
 * Áp dụng cho: warehouse_manager, warehouse_staff, manager (chi nhánh).
 * Miễn: owner và super_admin (có thể duyệt trong mọi tình huống khẩn cấp).
 */
class PreventSelfApproval
{
    /**
     * @param  string  $action  Hành động cần kiểm tra: 'approve' | 'dispatch' | 'receive' | 'dispatch_approve'
     */
    public function handle(Request $request, Closure $next, string $action = 'approve'): Response
    {
        $user = $request->user();

        // Owner và super_admin được miễn kiểm tra
        if ($user && ($user->isOwner() || $user->isSuperAdmin())) {
            return $next($request);
        }

        // Lấy đơn cấp phát từ route parameter
        $supplyRequestId = $request->route('id') ?? $request->route('supply_request');
        if (! $supplyRequestId) {
            return $next($request);
        }

        $supplyRequest = SupplyRequest::find($supplyRequestId);
        if (! $supplyRequest) {
            return $next($request);
        }

        $userId = $user?->id;

        $violation = match ($action) {
            // Không được tự duyệt đơn do mình tạo
            'approve' => $supplyRequest->created_by === $userId
                ? 'Bạn không thể duyệt đơn cấp phát do chính mình tạo. Yêu cầu phân tách người tạo – người duyệt.'
                : null,

            // Không được vừa duyệt đơn vừa soạn hàng
            'dispatch' => $supplyRequest->approved_by === $userId
                ? 'Người duyệt đơn không được phép tự soạn/xuất hàng. Yêu cầu phân tách người duyệt – người xuất kho.'
                : null,

            // Trưởng kho duyệt xuất: không được trùng người soạn hàng
            'dispatch_approve' => $supplyRequest->prepared_by === $userId
                ? 'Người soạn hàng không được phép tự duyệt số lượng xuất. Yêu cầu trưởng kho khác xác nhận.'
                : null,

            // Người nhận phải khác người xuất kho
            'receive' => $supplyRequest->dispatched_by === $userId
                ? 'Người xuất kho không được tự xác nhận nhận hàng. Yêu cầu phân tách người xuất – người nhận.'
                : null,

            default => null,
        };

        if ($violation) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $violation,
                    'error'   => 'self_approval_prevented',
                ], Response::HTTP_FORBIDDEN);
            }

            return back()->withErrors(['self_approval' => $violation])->withInput();
        }

        return $next($request);
    }
}
