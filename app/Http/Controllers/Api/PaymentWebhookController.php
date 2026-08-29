<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Webhook\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookService $webhookService
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $event = $this->webhookService->process(
            $request->validate([
                'event_id' => ['required', 'string', 'max:100'],
                'event_type' => ['required', 'string', 'max:50'],
                'gateway' => ['required', 'string', 'max:50'],
                'gateway_transaction_id' => [
                    'required',
                    'string',
                    'max:100',
                ],
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully.',
            'data' => $event,
        ]);
    }
}