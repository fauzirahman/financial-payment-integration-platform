<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Webhook\PaymentWebhookService;
use App\Services\Webhook\WebhookQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookService $webhookService,
        private readonly WebhookQueryService $webhookQueryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $events = $this->webhookQueryService->list(
            $request->only([
                'status',
                'gateway',
                'event_type',
                'event_id',
                'gateway_transaction_id',
                'search',
                'per_page',
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Webhook events retrieved successfully.',
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'last_page' => $events->lastPage(),
                'from' => $events->firstItem(),
                'to' => $events->lastItem(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $event = $this->webhookQueryService->find($id);

        return response()->json([
            'success' => true,
            'message' => 'Webhook event retrieved successfully.',
            'data' => $event,
        ]);
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
