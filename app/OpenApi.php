<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Financial Payment Integration API',
    description: 'REST API for financial payment integration, transaction processing, idempotency handling, webhook processing, and payment gateway integration.',
    contact: new OA\Contact(
        name: 'Fauzi Rahman'
    )
)]
#[OA\Server(
    url: '/api',
    description: 'Financial Payment Integration API'
)]
#[OA\Tag(
    name: 'Health',
    description: 'Application health and system status'
)]
#[OA\Tag(
    name: 'Payments',
    description: 'Payment processing and transaction management'
)]
#[OA\Tag(
    name: 'Webhooks',
    description: 'Payment gateway webhook processing'
)]
#[OA\Get(
    path: '/health',
    operationId: 'healthCheck',
    summary: 'Health check',
    description: 'Returns the current availability status of the Financial Payment Integration API.',
    tags: ['Health'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'API is running',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: true
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Financial Payment Integration API is running.'
                    )
                ]
            )
        )
    ]
)]
#[OA\Post(
    path: '/payments',
    operationId: 'createPayment',
    summary: 'Create and process payment',
    description: 'Creates a payment and processes it through the configured payment gateway. The Idempotency-Key header prevents duplicate payment processing.',
    tags: ['Payments'],
    parameters: [
        new OA\Parameter(
            name: 'Idempotency-Key',
            description: 'Unique key used to safely retry the same payment request without creating a duplicate payment.',
            in: 'header',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                maxLength: 100
            ),
            example: 'payment-request-2026-000001'
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'payment_number',
                'customer_id',
                'amount',
                'currency',
                'method'
            ],
            properties: [
                new OA\Property(
                    property: 'payment_number',
                    type: 'string',
                    maxLength: 40,
                    example: 'PAY-2026-000001'
                ),
                new OA\Property(
                    property: 'customer_id',
                    type: 'string',
                    format: 'uuid',
                    example: '550e8400-e29b-41d4-a716-446655440000'
                ),
                new OA\Property(
                    property: 'amount',
                    type: 'number',
                    format: 'double',
                    minimum: 0.01,
                    example: 150000.00
                ),
                new OA\Property(
                    property: 'currency',
                    type: 'string',
                    minLength: 3,
                    maxLength: 3,
                    pattern: '^[A-Z]{3}$',
                    example: 'IDR'
                ),
                new OA\Property(
                    property: 'method',
                    type: 'string',
                    maxLength: 30,
                    example: 'BANK_TRANSFER'
                ),
                new OA\Property(
                    property: 'description',
                    type: 'string',
                    maxLength: 1000,
                    nullable: true,
                    example: 'Payment for invoice INV-2026-000001'
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Payment processed successfully',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: true
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Payment processed successfully.'
                    ),
                    new OA\Property(
                        property: 'data',
                        ref: '#/components/schemas/Payment'
                    )
                ]
            )
        ),
        new OA\Response(
            response: 200,
            description: 'Idempotent request replayed',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: true
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Payment processed successfully.'
                    ),
                    new OA\Property(
                        property: 'data',
                        ref: '#/components/schemas/Payment'
                    )
                ]
            )
        ),
        new OA\Response(
            response: 400,
            description: 'Idempotency-Key header is missing',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: false
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Idempotency-Key header is required.'
                    )
                ]
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Payment request conflict',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: false
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Payment request conflict.'
                    )
                ]
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Payment processing failed or validation failed',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: false
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Payment failed.'
                    )
                ]
            )
        )
    ]
)]
#[OA\Post(
    path: '/webhooks/mock-payment',
    operationId: 'processMockPaymentWebhook',
    summary: 'Process mock payment webhook',
    description: 'Receives and processes a payment event from the mock payment gateway. When MOCK_WEBHOOK_SECRET is configured, the raw request body must be signed with HMAC-SHA256 and sent in X-Webhook-Signature.',
    tags: ['Webhooks'],
    parameters: [
        new OA\Parameter(
            name: 'X-Webhook-Signature',
            description: 'HMAC-SHA256 signature of the raw request body. Required when MOCK_WEBHOOK_SECRET is configured.',
            in: 'header',
            required: false,
            schema: new OA\Schema(type: 'string'),
            example: '9d4e1e23bd5b727046a9e3b1c4a9f1a1a4f6b9f1f4c6e7d8c9b0a1b2c3d4e5f6'
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'event_id',
                'event_type',
                'gateway',
                'gateway_transaction_id'
            ],
            properties: [
                new OA\Property(
                    property: 'event_id',
                    type: 'string',
                    maxLength: 100,
                    example: 'evt_2026_000001'
                ),
                new OA\Property(
                    property: 'event_type',
                    type: 'string',
                    maxLength: 50,
                    example: 'payment.succeeded'
                ),
                new OA\Property(
                    property: 'gateway',
                    type: 'string',
                    maxLength: 50,
                    example: 'mock_gateway'
                ),
                new OA\Property(
                    property: 'gateway_transaction_id',
                    type: 'string',
                    maxLength: 100,
                    example: 'GTX-2026-000001'
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Webhook processed successfully',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: true
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Webhook processed successfully.'
                    ),
                    new OA\Property(
                        property: 'data',
                        type: 'object'
                    )
                ]
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Webhook validation failed'
        )
    ]
)]
#[OA\Schema(
    schema: 'Payment',
    description: 'Payment transaction resource',
    properties: [
        new OA\Property(
            property: 'id',
            type: 'string',
            format: 'uuid',
            example: '550e8400-e29b-41d4-a716-446655440000'
        ),
        new OA\Property(
            property: 'payment_number',
            type: 'string',
            example: 'PAY-2026-000001'
        ),
        new OA\Property(
            property: 'customer_id',
            type: 'string',
            format: 'uuid',
            example: '550e8400-e29b-41d4-a716-446655440000'
        ),
        new OA\Property(
            property: 'amount',
            type: 'string',
            example: '150000.00'
        ),
        new OA\Property(
            property: 'currency',
            type: 'string',
            example: 'IDR'
        ),
        new OA\Property(
            property: 'method',
            type: 'string',
            example: 'BANK_TRANSFER'
        ),
        new OA\Property(
            property: 'status',
            type: 'string',
            example: 'SUCCESS'
        ),
        new OA\Property(
            property: 'gateway',
            type: 'string',
            nullable: true,
            example: 'mock_gateway'
        ),
        new OA\Property(
            property: 'gateway_transaction_id',
            type: 'string',
            nullable: true,
            example: 'GTX-2026-000001'
        ),
        new OA\Property(
            property: 'paid_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-29T07:00:00Z'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true,
            example: 'Payment for invoice INV-2026-000001'
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2026-08-29T06:55:00Z'
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            example: '2026-08-29T07:00:00Z'
        )
    ]
)]
class OpenApi
{
}
