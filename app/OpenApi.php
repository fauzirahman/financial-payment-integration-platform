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
    name: 'Auth',
    description: 'Authentication and current user operations'
)]
#[OA\Tag(
    name: 'Customers',
    description: 'Customer management'
)]
#[OA\Tag(
    name: 'Accounts',
    description: 'Customer account management'
)]
#[OA\Tag(
    name: 'Payments',
    description: 'Payment processing and transaction management'
)]
#[OA\Tag(
    name: 'Webhooks',
    description: 'Payment gateway webhook processing'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Personal access token'
)]
#[OA\Post(
    path: '/auth/login',
    operationId: 'login',
    summary: 'Log in',
    tags: ['Auth'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret')
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Login successful; returns a Sanctum bearer token',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Login successful.'),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            new OA\Property(property: 'token', type: 'string', example: '1|sanctum-token'),
                            new OA\Property(property: 'token_type', type: 'string', example: 'Bearer')
                        ],
                        type: 'object'
                    )
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Invalid email or password'),
        new OA\Response(response: 422, description: 'Request validation failed')
    ]
)]
#[OA\Get(
    path: '/auth/me',
    operationId: 'getAuthenticatedUser',
    summary: 'Get authenticated user',
    tags: ['Auth'],
    security: [['sanctum' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Authenticated user retrieved successfully',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Authenticated user retrieved successfully.'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/User')
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Unauthenticated')
    ]
)]
#[OA\Post(
    path: '/auth/logout',
    operationId: 'logout',
    summary: 'Log out',
    tags: ['Auth'],
    security: [['sanctum' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Current access token revoked',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Logout successful.')
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Unauthenticated')
    ]
)]
#[OA\Get(
    path: '/customers',
    operationId: 'listCustomers',
    summary: 'List customers',
    tags: ['Customers'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', description: 'Results per page (1-100)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15))
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Customers retrieved successfully',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Customers retrieved successfully.'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Customer')),
                    new OA\Property(
                        property: 'meta',
                        properties: [
                            new OA\Property(property: 'current_page', type: 'integer', example: 1),
                            new OA\Property(property: 'per_page', type: 'integer', example: 15),
                            new OA\Property(property: 'total', type: 'integer', example: 1),
                            new OA\Property(property: 'last_page', type: 'integer', example: 1)
                        ],
                        type: 'object'
                    )
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Unauthenticated')
    ]
)]
#[OA\Post(
    path: '/customers',
    operationId: 'createCustomer',
    summary: 'Create customer',
    tags: ['Customers'],
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['customer_number', 'name', 'email'],
            properties: [
                new OA\Property(property: 'customer_number', type: 'string', maxLength: 30, example: 'CUS-2026-0001'),
                new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Example Customer'),
                new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 150, example: 'customer@example.com'),
                new OA\Property(property: 'phone', type: 'string', maxLength: 30, nullable: true, example: '+628123456789')
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Customer created', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Customer created successfully.'), new OA\Property(property: 'data', ref: '#/components/schemas/Customer')])),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 422, description: 'Validation failed or customer number/email already exists')
    ]
)]
#[OA\Get(
    path: '/customers/{customer}',
    operationId: 'getCustomer',
    summary: 'Get customer',
    tags: ['Customers'],
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    responses: [
        new OA\Response(response: 200, description: 'Customer retrieved', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Customer retrieved successfully.'), new OA\Property(property: 'data', ref: '#/components/schemas/Customer')])),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 404, description: 'Customer not found')
    ]
)]
#[OA\Get(
    path: '/customers/{customer}/accounts',
    operationId: 'listCustomerAccounts',
    summary: 'List customer accounts',
    tags: ['Accounts'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', description: 'Results per page (1-100)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Customer accounts retrieved', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Customer accounts retrieved successfully.'), new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Account')), new OA\Property(property: 'meta', type: 'object')])),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 404, description: 'Customer not found')
    ]
)]
#[OA\Post(
    path: '/customers/{customer}/accounts',
    operationId: 'createCustomerAccount',
    summary: 'Create account for customer',
    tags: ['Accounts'],
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['account_number', 'currency'],
            properties: [
                new OA\Property(property: 'account_number', type: 'string', maxLength: 30, example: 'ACC-2026-0001'),
                new OA\Property(property: 'currency', type: 'string', minLength: 3, maxLength: 3, pattern: '^[A-Z]{3}$', example: 'IDR')
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Customer account created', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Customer account created successfully.'), new OA\Property(property: 'data', ref: '#/components/schemas/Account')])),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 404, description: 'Customer not found'),
        new OA\Response(response: 422, description: 'Validation failed or account number already exists')
    ]
)]
#[OA\Get(
    path: '/payments',
    operationId: 'listPayments',
    summary: 'List payments',
    tags: ['Payments'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', example: 'SUCCESS')),
        new OA\Parameter(name: 'gateway', in: 'query', schema: new OA\Schema(type: 'string', example: 'mock_gateway')),
        new OA\Parameter(name: 'customer_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'payment_number', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'search', in: 'query', description: 'Search supported payment fields', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Payments retrieved', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Payments retrieved successfully.'), new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment')), new OA\Property(property: 'meta', type: 'object')])),
        new OA\Response(response: 401, description: 'Unauthenticated')
    ]
)]
#[OA\Get(
    path: '/payments/{id}',
    operationId: 'getPayment',
    summary: 'Get payment',
    tags: ['Payments'],
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    responses: [
        new OA\Response(response: 200, description: 'Payment retrieved', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Payment retrieved successfully.'), new OA\Property(property: 'data', ref: '#/components/schemas/Payment')])),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 404, description: 'Payment not found')
    ]
)]
#[OA\Get(
    path: '/webhooks',
    operationId: 'listWebhookEvents',
    summary: 'List webhook events',
    tags: ['Webhooks'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'gateway', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'event_type', in: 'query', schema: new OA\Schema(type: 'string', example: 'payment.succeeded')),
        new OA\Parameter(name: 'event_id', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'gateway_transaction_id', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Webhook events retrieved', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Webhook events retrieved successfully.'), new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/WebhookEvent')), new OA\Property(property: 'meta', type: 'object')])),
        new OA\Response(response: 401, description: 'Unauthenticated')
    ]
)]
#[OA\Get(
    path: '/webhooks/{id}',
    operationId: 'getWebhookEvent',
    summary: 'Get webhook event',
    tags: ['Webhooks'],
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    responses: [
        new OA\Response(response: 200, description: 'Webhook event retrieved', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Webhook event retrieved successfully.'), new OA\Property(property: 'data', ref: '#/components/schemas/WebhookEvent')])),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 404, description: 'Webhook event not found')
    ]
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
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'name', type: 'string', example: 'API User'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true)
    ]
)]
#[OA\Schema(
    schema: 'Customer',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'customer_number', type: 'string', example: 'CUS-2026-0001'),
        new OA\Property(property: 'name', type: 'string', example: 'Example Customer'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'customer@example.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+628123456789'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
    ]
)]
#[OA\Schema(
    schema: 'Account',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'customer_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'account_number', type: 'string', example: 'ACC-2026-0001'),
        new OA\Property(property: 'currency', type: 'string', example: 'IDR'),
        new OA\Property(property: 'balance', type: 'string', example: '0.00'),
        new OA\Property(property: 'status', type: 'string', example: 'ACTIVE'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
    ]
)]
#[OA\Schema(
    schema: 'WebhookEvent',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'event_id', type: 'string', example: 'evt_2026_000001'),
        new OA\Property(property: 'event_type', type: 'string', example: 'payment.succeeded'),
        new OA\Property(property: 'gateway', type: 'string', example: 'mock_gateway'),
        new OA\Property(property: 'gateway_transaction_id', type: 'string', example: 'GTX-2026-000001'),
        new OA\Property(property: 'status', type: 'string', example: 'PROCESSED'),
        new OA\Property(property: 'payload', type: 'object', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
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
