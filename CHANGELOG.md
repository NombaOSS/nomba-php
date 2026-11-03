# Changelog

## [Unreleased]

### Added
- `Nomba\Nomba` client for the Nomba API (version 0.1.0): configuration from options or `NOMBA_*` environment variables, sandbox by default, optional injected Guzzle client.
- Automatic access tokens: issued on first use, refreshed within 5 minutes of expiry (falling back to a new token), one retry after a 401, `auth->revoke()`, and a pluggable `TokenStore` for sharing a token between processes.
- Resources: `accounts`, `virtualAccounts`, `checkout`, `transfers`, `transactions`, `terminals`, `directDebits`, `bills` (v2 purchase endpoints), `globalPayout`, `globalCollection`.
- Generated `merchantTxRef` (UUID v4) for transfers and bill purchases, and `order.orderReference` for `checkout->chargeSavedCard()`, always returned in the result, and on failure attached to the exception (`$e->getReference()`).
- Retries with exponential backoff for GET requests and token calls only; POST, PUT and DELETE are never retried.
- Exception hierarchy under `Nomba\Exception\`, with HTTP status, Nomba code, description, response body, method and path.
- Path parameter validation and URL encoding.
- Auto-paging generators: `transactions->listAll()`, `filterAll()`, `listAllBankTransactions()`, `accounts->listAllTerminals()`, `virtualAccounts->listAll()`.
- Webhook signature verification (`Nomba\Webhook::verify()` and `$nomba->webhooks->verify()`) with optional timestamp tolerance.
- `TransactionStatus` and `WebhookEventType` constants.
- PHPStan array shapes for every request and response.
