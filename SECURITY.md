# Security

## Reporting a vulnerability

Email security@nomba.com with a description, the affected version and steps to reproduce. Please do not open a public issue for security problems.

We aim to acknowledge reports within 3 working days.

## Supported versions

| Version | Supported |
|---------|-----------|
| 0.1.x   | Yes       |

Only the latest release receives fixes. Before 1.0.0, upgrade to the newest 0.x release.

## What this SDK does to protect you

- Never retries POST, PUT or DELETE requests, so money is not moved twice.
- Keeps the client secret, access token and refresh token out of exception messages and out of `var_dump()`/`print_r()` output.
- Validates and URL-encodes path parameters, so an ID cannot redirect a request to another endpoint.
- Refuses non-HTTPS base URLs (except `localhost`/`127.0.0.1`) and does not follow redirects.
- Verifies webhook signatures in constant time.
