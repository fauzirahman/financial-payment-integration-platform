<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'key',
        'request_hash',
        'resource_type',
        'resource_id',
        'response_status',
        'response_body',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
        ];
    }
}