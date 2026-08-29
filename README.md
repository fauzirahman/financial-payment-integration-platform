# Financial Payment Integration Platform

A portfolio-grade finance and payment backend built with **Laravel 12**, designed to demonstrate financial domain modeling, payment gateway integration, idempotency, webhook processing, and double-entry bookkeeping.

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

PostgreSQL
  ├── customers / accounts
  ├── payments
  ├── financial_transactions
  ├── ledger_entries
  ├── chart_of_accounts
  ├── idempotency_keys
  └── payment_webhook_events

Redis (available for cache/queue)
```

## Implemented

- Customer and account domain
- Payment creation API
- Mock payment gateway abstraction
- Payment status lifecycle: `PENDING -> SUCCESS/FAILED`
- **Idempotency-Key** support and replay protection
- Double-entry ledger with debit/credit balancing
- Automatic ledger posting for successful payments
- Chart of Accounts
- Payment webhook event persistence and duplicate-event protection
- UUID primary keys for financial domain entities
- Database transactions around payment + ledger posting
- Feature tests for ledger and payment/idempotency flows
- PostgreSQL + Redis Docker infrastructure

## API

### Health

```http
GET /api/health
```

### Create payment

```http
POST /api/payments
Idempotency-Key: pay-demo-001
Content-Type: application/json
```

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

### Mock webhook

```http
POST /api/webhooks/mock-payment
Content-Type: application/json
```

```json
{
  "event_id": "evt-demo-001",
  "event_type": "payment.succeeded",
  "gateway": "mock",
  "gateway_transaction_id": "MOCK-XXXXXXXX"
}
```

## Local setup

Requirements:

- PHP 8.2+
- Composer
- Node.js + npm
- Docker + Docker Compose

Install dependencies:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Start infrastructure:

```bash
docker compose up -d
```

Set PostgreSQL values in `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5434
DB_DATABASE=financial_payment
DB_USERNAME=financial_user
DB_PASSWORD=financial_password
```

Then:

```bash
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

The API is available at `http://127.0.0.1:8000/api`.

## Test

```bash
php artisan test
```

The test suite covers ledger balancing, rollback behavior, duplicate transaction numbers, debit/credit validation, payment processing, and idempotent replay.

## Suggested next portfolio milestones

### Phase 2 — Finance

- Account balance service with row-level locking
- Transfer between customer accounts
- Transaction history and pagination
- Reconciliation report
- Trial balance / general ledger report
- Fee and tax handling

### Phase 3 — Payment

- Real gateway adapter behind `PaymentGatewayInterface`
- Signed webhook verification
- Refunds
- Payment expiration
- Retry policy
- Gateway request/response audit log

### Phase 4 — Enterprise quality

- Laravel Sanctum authentication
- Roles/permissions
- Rate limiting
- Structured audit logs
- OpenAPI documentation
- Queue-based webhook processing
- Dockerized application + CI/CD
- Dashboard for payment and finance KPIs

## Portfolio positioning

This project demonstrates more than CRUD. It is intentionally designed to show backend engineering concepts relevant to financial systems:

- **Consistency:** payment and ledger posting are committed atomically.
- **Idempotency:** repeated payment requests do not create duplicate charges.
- **Accounting integrity:** every posted transaction must balance debit and credit.
- **Extensibility:** payment gateways are accessed through an interface.
- **Traceability:** payments, financial transactions, ledger entries, idempotency records, and webhook events can be correlated.
- **Failure handling:** invalid or inconsistent financial operations are rejected and rolled back.
