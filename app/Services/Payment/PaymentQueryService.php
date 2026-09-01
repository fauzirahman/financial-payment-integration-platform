<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentQueryService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Payment::query()
            ->with('customer')
            ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['payment_number'])) {
            $query->where(
                'payment_number',
                'like',
                '%' . $filters['payment_number'] . '%'
            );
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('payment_number', 'like', "%{$search}%")
                    ->orWhere(
                        'gateway_transaction_id',
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

    public function find(string $id): Payment
    {
        return Payment::query()
            ->with('customer')
            ->findOrFail($id);
    }
}
