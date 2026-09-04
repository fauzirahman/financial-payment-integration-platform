# Implementation Notes

## Completed changes

### 1. PaymentLedgerService

Added `app/Services/Payment/PaymentLedgerService.php` and moved successful-payment accounting logic into this service.

Why: payment creation and webhook completion must use exactly the same double-entry accounting rules. This prevents a payment completed by webhook from becoming `SUCCESS` without a corresponding ledger entry.

### 2. Webhook accounting consistency

`PaymentWebhookService` now:

- transitions `PENDING -> SUCCESS` for `payment.succeeded`;
- posts the ledger when the webhook is the operation that completes the payment;
- transitions `PENDING -> FAILED` for `payment.failed`;
- does not post a ledger for failed payments;
- does not duplicate the ledger when an already-successful webhook is delivered again.

### 3. Webhook validation

Added `PaymentWebhookRequest` with an explicit allow-list:

- `payment.succeeded`
- `payment.failed`

This prevents arbitrary event types from being accepted as successfully processed.

### 4. Webhook signature verification

Added `VerifyWebhookSignature` middleware.

If `MOCK_WEBHOOK_SECRET` is configured, the API expects:

`X-Webhook-Signature = HMAC-SHA256(raw request body, MOCK_WEBHOOK_SECRET)`

If the environment variable is empty, signature verification remains disabled for simple local portfolio demos.

### 5. Scheduler

`bootstrap/app.php` now registers the retry dispatcher every minute with `withoutOverlapping()`.

Run:

```bash
php artisan schedule:work
```

### 6. Payment response correctness

The payment endpoint now reports `success: false` and HTTP `422` when the gateway returns a failed payment result instead of always returning a successful response.

### 7. Webhook race handling

Creation of webhook events handles a unique-constraint race by reloading the event that another concurrent request created.

### 8. Swagger/OpenAPI

Updated the webhook documentation to:

- use `payment.succeeded` instead of the old `payment.success` example;
- document the optional `X-Webhook-Signature` header;
- align the documented `Idempotency-Key` maximum length with the implementation.

### 9. Automated tests

Added regression tests for:

- webhook-driven ledger posting;
- duplicate webhook delivery without duplicate ledger entries;
- failed webhook changing a pending payment to `FAILED`;
- optional webhook signature mode;
- invalid HMAC signature rejection;
- valid HMAC signature acceptance.

The repository contains 65 test methods in the current test suite.

## Verification performed

PHP syntax validation was run against the application, routes, tests, and migrations. All checked PHP files passed `php -l`.

The uploaded environment did not contain the Composer `vendor/` directory and Composer was not available in the execution environment, so the full Laravel test suite could not be executed here. Run the following locally after `composer install`:

```bash
php artisan migrate:fresh --seed
php artisan test
php artisan l5-swagger:generate
php artisan schedule:list
```
