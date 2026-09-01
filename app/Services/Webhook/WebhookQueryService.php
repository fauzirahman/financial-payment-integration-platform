<?php

namespace App\Services\Webhook;

use App\Models\PaymentWebhookEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WebhookQueryService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = PaymentWebhookEvent::query()
            ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (!empty($filters['event_id'])) {
            $query->where(
                'event_id',
                'like',
                '%' . $filters['event_id'] . '%'
            );
        }

        if (!empty($filters['gateway_transaction_id'])) {
            $query->where(
                'gateway_transaction_id',
                'like',
                '%' . $filters['gateway_transaction_id'] . '%'
            );
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('event_id', 'like', "%{$search}%")
                    ->orWhere(
                        'gateway_transaction_id',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'event_type',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $perPage = min(
            max((int) ($filters['per_page'] ?? 20), 1),
            100
        );

        return $query->paginate($perPage);
    }

    public function find(string $id): PaymentWebhookEvent
    {
        return PaymentWebhookEvent::query()
            ->findOrFail($id);
    }
}
