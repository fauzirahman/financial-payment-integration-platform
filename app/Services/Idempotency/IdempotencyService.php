<?php

namespace App\Services\Idempotency;

use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class IdempotencyService
{
    public function acquire(
        string $key,
        array $payload,
        string $resourceType
    ): IdempotencyKey {
        $requestHash = hash(
            'sha256',
            json_encode($payload)
        );

        $record = IdempotencyKey::query()
            ->where('key', $key)
            ->first();

        if ($record) {
            if ($record->request_hash !== $requestHash) {
                throw new RuntimeException(
                    'Idempotency key was already used with a different request.'
                );
            }

            if ($record->status === 'COMPLETED') {
                return $record;
            }

            throw new RuntimeException(
                'A request with this idempotency key is already being processed.'
            );
        }

        return IdempotencyKey::create([
            'key' => $key,
            'request_hash' => $requestHash,
            'resource_type' => $resourceType,
            'status' => 'PROCESSING',
        ]);
    }

    public function complete(
        IdempotencyKey $record,
        int $status,
        array $body,
        ?string $resourceId = null
    ): void {
        $record->update([
            'resource_id' => $resourceId,
            'response_status' => $status,
            'response_body' => $body,
            'status' => 'COMPLETED',
        ]);
    }
}