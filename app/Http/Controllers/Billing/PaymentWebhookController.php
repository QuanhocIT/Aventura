<?php

namespace App\Http\Controllers\Billing;

use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateInvoiceDocuments;
use App\Jobs\SendBillingInvoiceEmail;
use App\Models\Order;
use App\Models\User;
use App\Services\BillingService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly OrderService $orderService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = (string) ($request->header('X-SePay-Signature') ?? $request->header('X-Signature') ?? '');
        $secret = (string) config('billing.webhook_secret');
        $rawBody = $request->getContent();

        if (empty($secret)) {
            Log::critical('Billing Webhook Secret is not configured.');

            return response()->json(['message' => 'Configuration error'], 500);
        }

        if (! hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // The bank sends every transfer on this account to a single webhook URL, so a
        // signature-verified payload may confirm either a table order (marked with the
        // AVTORDxxx reference we generate for VietQR) or a tenant subscription payment.
        $orderResult = $this->tryHandleOrderPayment($request);

        if ($orderResult !== null) {
            return response()->json($orderResult, $orderResult['success'] ? 200 : 422);
        }

        $result = $this->billingService->handleWebhook(
            $request->all(),
            $request->headers->all(),
            $signature,
            (string) config('billing.webhook_provider', 'bank')
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'Webhook processing failed'], 422);
        }

        if (isset($result['invoice'])) {
            GenerateInvoiceDocuments::dispatch($result['invoice']->id)->onQueue(config('billing.queue'));
            SendBillingInvoiceEmail::dispatch($result['invoice']->id)->onQueue(config('billing.queue'));
        }

        return response()->json(['message' => $result['message'] ?? 'Webhook processed']);
    }

    /**
     * Detect and process an order-level VietQR bank-transfer payment ("AVTORD{id}" reference).
     * Returns null when the payload isn't an order-reference transfer, so the caller can fall
     * back to the tenant-subscription webhook handling.
     *
     * @return array{success: bool, message: string}|null
     */
    private function tryHandleOrderPayment(Request $request): ?array
    {
        $content = (string) ($request->input('content')
            ?? $request->input('description')
            ?? $request->input('transferContent')
            ?? $request->input('memo')
            ?? $request->input('addInfo')
            ?? '');

        if (! preg_match('/AVTORD(\d+)/i', $content, $matches)) {
            return null;
        }

        $order = Order::find((int) $matches[1]);

        if (! $order) {
            Log::warning('Payment webhook: order referenced in transfer content not found', ['content' => $content]);

            return ['success' => false, 'message' => "Không tìm thấy đơn hàng từ nội dung: {$content}"];
        }

        if ($order->payment_status === 'paid') {
            return ['success' => true, 'message' => 'Đơn hàng này đã được thanh toán từ trước.'];
        }

        $user = User::find($order->created_by)
            ?? User::where('restaurant_id', $order->restaurant_id)->first();

        if (! $user) {
            Log::error('Payment webhook: no user found to process order payment', ['order_id' => $order->id]);

            return ['success' => false, 'message' => 'Không tìm thấy user thích hợp để xử lý đơn hàng.'];
        }

        $this->orderService->payOrder($order, [
            'payment_method' => 'bank_transfer',
            'cash_received' => $order->total_amount,
            'change_amount' => 0,
        ], $user, true);

        if ($order->table_id) {
            event(new OrderPaid($order, (int) $order->restaurant_id, (int) $order->table_id));
        }

        return ['success' => true, 'message' => "Thanh toán đơn hàng #{$order->order_number} thành công."];
    }
}