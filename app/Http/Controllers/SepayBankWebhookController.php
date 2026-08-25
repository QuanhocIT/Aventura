<?php

namespace App\Http\Controllers;

use App\Services\SepayBankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class SepayBankWebhookController extends Controller
{
    public function __construct(private readonly SepayBankService $sepayBankService) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->sepayBankService->isValidWebhookRequest($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $result = $this->sepayBankService->ingestWebhook($request->json()->all());

            return response()->json([
                'success' => true,
                'created' => $result['created'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? 'Invalid transaction payload',
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to persist SePay transaction',
            ], 500);
        }
    }
}
