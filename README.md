# Financial Payment Integration Platform

A portfolio-grade finance and payment backend built with **Laravel 12**, designed to demonstrate financial domain modeling, payment gateway integration, idempotency, webhook processing, retry mechanisms, queue-based processing, and double-entry bookkeeping.

## Architecture

```text
Client / Postman
      |
      v
Laravel API
      |
      +--> PaymentController
      |       |
      |       +--> StorePaymentRequest
      |       +--> PaymentService
      |               |
      |               +--> IdempotencyService
      |               +--> PaymentGatewayInterface
      |               |       +--> MockPaymentGateway
      |               +--> LedgerService
      |
      +--> PaymentWebhookController
              |
              +--> PaymentWebhookService
                      |
                      +--> WebhookRetryService
                      |
                      +--> RetryPaymentWebhookJob
                              ^
                              |
                  DispatchWebhookRetries

PostgreSQL
  ├── customers / accounts
  ├── payments
  ├── financial_transactions
  ├── ledger_entries
  ├── chart_of_accounts
  ├── idempotency_keys
  └── payment_webhook_events

Database Queue
  └── jobs
```

## Implemented Features

### Finance

- Customer and account domain
- Chart of Accounts
- Double-entry bookkeeping
- Debit/credit balancing validation
- Financial transaction creation
- Ledger entry posting
- Transaction rollback on failure
- Duplicate transaction number protection
- UUID primary keys for financial domain entities
- Database transactions around payment + ledger posting

### Payment

- Payment creation API
- Mock payment gateway abstraction
- `PaymentGatewayInterface`
- Payment status lifecycle: `PENDING -> SUCCESS/FAILED`
- Automatic mock gateway transaction ID generation
- Automatic ledger posting for successful payments
- Payment description and currency support

### Idempotency

- `Idempotency-Key` header requirement
- Idempotent payment request processing
- Replay protection
- Same key + same payload returns the original payment
- Same key + different payload returns HTTP `409 Conflict`
- Idempotency records persisted in the database
- Request payload hash validation

### Webhook

- Mock payment webhook endpoint
- Webhook event persistence
- Duplicate webhook event protection
- Processed event idempotency
- Failed webhook persistence for audit and retry
- Successful webhook updates payment status
- Webhook processing inside a database transaction
- Payment row locking with `lockForUpdate()`
- Error message persistence

### Webhook Retry Mechanism

- Configurable maximum retry attempts
- Retry attempt counter
- Scheduled retry timestamp
- Exponential-style backoff schedule:

| Attempt | Retry Delay |
|---:|---:|
| 1 | 1 minute |
| 2 | 5 minutes |
| 3 | 15 minutes |
| 4 | 30 minutes |
| 5 | Permanently failed |

- `FAILED` events can be retried after `next_retry_at`
- `PERMANENTLY_FAILED` events are not automatically retried
- Retry eligibility validation through `WebhookRetryService`

### Queue / Worker

- Laravel database queue configured with `QUEUE_CONNECTION=database`
- `RetryPaymentWebhookJob` implements `ShouldQueue`
- Retry jobs contain the webhook event ID
- Queued jobs verify event state before processing
- Processed events are skipped
- Permanently failed events are skipped
- Jobs cannot execute before their scheduled retry time
- `DispatchWebhookRetries` command finds due failed webhook events
- Due retry events are dispatched to the queue
- Queue worker processes retry jobs asynchronously

## API

### Health

```http
GET /api/health
```

### Create Payment

```http
POST /api/payments
Idempotency-Key: pay-demo-001
Content-Type: application/json
```

Request:

```json
{
  "payment_number": "PAY-000001",
  "customer_id": "<customer-uuid>",
  "amount": "150000.00",
  "currency": "IDR",
  "method": "BANK_TRANSFER",
  "description": "Demo customer payment"
}
```

A successful payment creates:

1. A `payments` record.
2. A mock gateway transaction ID.
3. A balanced `financial_transactions` record.
4. Two `ledger_entries` records:
   - Debit `1100 Cash / Bank`
   - Credit `4000 Payment Revenue`
5. A completed `idempotency_keys` record.

Repeating the same request with the same `Idempotency-Key` returns the original payment instead of charging the gateway again.

### Mock Payment Webhook

```http
POST /api/webhooks/mock-payment
Content-Type: application/json
```

Request:

```json
{
  "event_id": "evt-demo-001",
  "event_type": "payment.succeeded",
  "gateway": "mock",
  "gateway_transaction_id": "MOCK-XXXXXXXX"
}
```

## Webhook Retry Flow

```text
Payment Webhook
      |
      v
PaymentWebhookService
      |
      +---- success ----> PROCESSED
      |
      +---- failure ----> FAILED
                            |
                            v
                    WebhookRetryService
                            |
                            v
                     next_retry_at
                            |
                            v
              DispatchWebhookRetries
                            |
                            v
                 RetryPaymentWebhookJob
                            |
                            v
                 PaymentWebhookService
                            |
                 +----------+----------+
                 |                     |
               success               failure
                 |                     |
                 v                     v
             PROCESSED          schedule next retry
                                      |
                                      v
                              max attempts reached
                                      |
                                      v
                              PERMANENTLY_FAILED
```

## Queue Commands

Dispatch webhook retries that are already due:

```bash
php artisan app:dispatch-webhook-retries
```

Run one queued job:

```bash
php artisan queue:work --once
```

Run the queue worker continuously:

```bash
php artisan queue:work
```

Check queue jobs directly through Laravel/database tooling when troubleshooting.

## Local Setup

### Requirements

- PHP 8.2+
- Composer
- Node.js + npm
- Docker + Docker Compose
- PostgreSQL
- Redis (available for cache/queue infrastructure)

### Install Dependencies

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### Start Infrastructure

```bash
docker compose up -d
```

### PostgreSQL Configuration

Set the following values in `.env` according to your local Docker configuration:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5434
DB_DATABASE=financial_payment
DB_USERNAME=financial_user
DB_PASSWORD=financial_password
```

### Queue Configuration

The project currently uses Laravel's database queue:

```dotenv
QUEUE_CONNECTION=database
```

Then run migrations:

```bash
php artisan migrate --seed
```

Install and build frontend assets:

```bash
npm install
npm run build
```

Start the API:

```bash
php artisan serve
```

The API is available at:

```text
http://127.0.0.1:8000/api
```

## Database Migrations

The project includes migrations for:

- Users
- Cache
- Jobs / database queue
- Customers
- Accounts
- Chart of Accounts
- Financial Transactions
- Ledger Entries
- Payments
- Idempotency Keys
- Payment Webhook Events
- Webhook retry metadata

Check migration status:

```bash
php artisan migrate:status
```

Apply pending migrations:

```bash
php artisan migrate
```

## Testing

Run the complete test suite:

```bash
php artisan test
```

Current coverage includes:

### Ledger

- Balanced transaction creation
- Unbalanced transaction rejection
- Transaction rollback
- Duplicate transaction number rejection
- Debit/credit validation
- Zero amount validation

### Payment API

- Payment processing
- Automatic ledger posting
- Idempotent replay
- Missing `Idempotency-Key`
- Different payload with the same idempotency key

### Payment Webhook

- Successful webhook processing
- Duplicate processed webhook protection
- Failed webhook persistence
- Failed webhook retry
- Retry metadata scheduling

### Webhook Retry Service

- 1-minute retry
- 5-minute retry
- 15-minute retry
- 30-minute retry
- Permanent failure after maximum attempts
- Retry eligibility after scheduled time
- Retry blocked before scheduled time
- Permanent failure cannot retry

### Webhook Retry Queue

- Due webhook is dispatched
- Future webhook is not dispatched
- Permanently failed webhook is not dispatched
- Maximum-attempt webhook is not dispatched

## Current Test Status

The latest complete test run:

```text
Tests:    25 passed
Assertions: 72
```

The retry service test suite:

```text
Tests:    8 passed
Assertions: 18
```

The webhook test suite:

```text
Tests:    5 passed
Assertions: 22
```

The queue dispatch test suite:

```text
Tests:    4 passed
Assertions: 12
```

## Portfolio Engineering Highlights

This project intentionally demonstrates backend engineering concepts relevant to financial and payment systems.

### Consistency

Payment processing and ledger posting are protected by database transactions so financial changes are committed atomically.

### Idempotency

Repeated payment requests with the same idempotency key do not create duplicate payments or duplicate ledger entries.

### Accounting Integrity

Every posted financial transaction must contain balanced debit and credit entries.

### Concurrency Safety

Payment webhook processing uses row-level locking to prevent conflicting updates to the same payment.

### Webhook Reliability

Failed webhook events remain persisted instead of being rolled back, allowing them to be diagnosed and retried.

### Retry Strategy

Webhook failures use scheduled retries with increasing delays and a maximum attempt limit.

### Asynchronous Processing

Retry work is separated from the HTTP request using Laravel's queue system and `ShouldQueue` jobs.

### Traceability

Payment records, financial transactions, ledger entries, idempotency keys, and webhook events can be correlated for troubleshooting and audit purposes.

### Extensibility

Payment gateways are accessed through an interface, allowing the mock gateway to be replaced by a real provider adapter.

## Suggested Next Portfolio Milestones

### Phase 4 — Enterprise Reliability

- Signed webhook verification
- Webhook signature validation
- Queue monitoring
- Failed job handling
- Structured audit logs
- OpenAPI / Swagger documentation
- Rate limiting
- Authentication and authorization
- Laravel Sanctum
- Roles and permissions
- Dockerized application
- CI/CD pipeline

### Phase 5 — Finance Features

- Account balance service with row-level locking
- Transfer between customer accounts
- Transaction history and pagination
- Reconciliation report
- Trial balance
- General ledger report
- Fee and tax handling

### Phase 6 — Payment Features

- Real payment gateway adapter
- Refunds
- Payment expiration
- Payment cancellation
- Gateway request/response audit log
- Dead-letter / failed-job handling
- Operational monitoring dashboard

## Portfolio Positioning

This project demonstrates more than CRUD. It is designed as a practical financial backend showcasing:

- Financial domain modeling
- REST API development
- Payment gateway abstraction
- Idempotency
- Double-entry accounting
- Database transactions
- Row-level locking
- Webhook processing
- Retry policies
- Queue-based asynchronous processing
- Failure recovery
- Auditability and traceability
- Automated feature testing
- PostgreSQL
- Redis-ready infrastructure
- Docker
- Laravel 12

These capabilities make the project suitable as a portfolio demonstration for backend, financial technology, payment integration, API integration, and enterprise application development roles.