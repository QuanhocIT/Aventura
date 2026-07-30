<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateInvoiceDocuments;
use App\Jobs\SendBillingInvoiceEmail;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(private readonly BillingService $billingService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = (string) ($request->header('X-SePay-Signature') ?? $request->header('X-Signature') ?? '');
        $secret = (string) config('billing.webhook_secret');
        $rawBody = $request->getContent();

        if (empty($secret)) {
            if (app()->environment('production')) {
                Log::critical('Billing Webhook Secret is not configured in production!');

                return response()->json(['message' => 'Configuration error'], 500);
            }
        } elseif (! hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
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
}
