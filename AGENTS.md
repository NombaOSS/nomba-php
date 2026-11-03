# nomba-php

Official PHP SDK for the Nomba API. Composer package `nombaoss/nomba-php`, namespace `Nomba\`, PHP 8.1+, one runtime dependency (`guzzlehttp/guzzle` ^7). Endpoints and fields come from NombaOSS/openapi and developer.nomba.com; where they disagree, the per-endpoint docs page wins.

## Rules
- Conventional commits; main squash-merges, so the PR title becomes the commit. Stage files by name.
- Never push to main. Branch and open a PR. Merge only when Sam says so.
- No AI attribution in commits, PRs or code comments.
- Public repo: don't name private Nomba repos.
- Library convention: commit `composer.json`, never `composer.lock` or `vendor/`.
- Use only PHP 8.1 features (readonly properties are fine; no readonly classes, no typed class constants). `declare(strict_types=1)` in every file.
- Keep method and option names in step with the Node and Python SDKs (same design document); a rename is a breaking change in all three.

## Safety invariants
- Only GET requests and token issue/refresh are retried (`Http\ApiClient`, `Auth\TokenManager`). Never retry POST, PUT or DELETE: money could move twice. The single replay after a 401 is the only exception, because a 401 means Nomba did not process the request.
- Secrets (client secret, access and refresh tokens, Authorization header) never appear in exception messages, logs or debug output. Classes holding them implement `__debugInfo()`; parameters carrying them use `#[\SensitiveParameter]`. Connection errors keep Guzzle's message (which contains the full URL) only as the previous exception.
- Every path parameter goes through `Internal\Params::segment()` (rejects "", ".", "..", "/" and "\", then URL-encodes).
- Money-moving calls (`transfers->toBank/toWallet`, every `bills` purchase, `checkout->chargeSavedCard`) generate a reference when missing and return it.
- Webhook verification compares signatures with `hash_equals` on the raw body. Never re-encode a parsed body.
- Tests use Guzzle's `MockHandler` only. Nothing in `composer test` may reach the network.

## Releasing
Never create tags or Releases by hand; see `RELEASING.md`.

## Before pushing
`composer validate --strict && composer cs-check && composer analyse && composer test`
