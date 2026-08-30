<?php

namespace App\Services\Idempotency;

use App\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

class IdempotencyService
{
    public function acquire(
        string $key,
        array $payload,
        string $resourceType
    ): IdempotencyKey {
        if (trim($key) === '' || strlen($key) > 100) {
            throw new RuntimeException('Idempotency-Key must be between 1 and 100 characters.');
        }

        $requestHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $record = IdempotencyKey::query()->where('key', $key)->first();

        if ($record) {
            return $this->validateExisting($record, $requestHash);
        }

        try {
            return IdempotencyKey::create([
                'key' => $key,
                'request_hash' => $requestHash,
                'resource_type' => $resourceType,
                'status' => 'PROCESSING',
            ]);
        } catch (UniqueConstraintViolationException) {
            $record = IdempotencyKey::query()->where('key', $key)->firstOrFail();

            return $this->validateExisting($record, $requestHash);
        }
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

    public function fail(IdempotencyKey $record, string $message): void
    {
        $record->update([
            'response_status' => 500,
            'response_body' => [
                'success' => false,
                'message' => $message,
            ],
            'status' => 'FAILED',
        ]);
    }

    private function validateExisting(IdempotencyKey $record, string $requestHash): IdempotencyKey
    {
        if (!hash_equals($record->request_hash, $requestHash)) {
            throw new IdempotencyConflictException(
                'Idempotency key was already used with a different request.'
            );
        }

        if ($record->status === 'COMPLETED' && $record->resource_id) {
            return $record;
        }

        if ($record->status === 'FAILED') {
            throw new RuntimeException(
                'The previous request with this idempotency key failed.'
            );
        }

        throw new RuntimeException(
            'A request with this idempotency key is already being processed.'
        );
    }
}
