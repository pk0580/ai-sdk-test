# Security

Defense in depth. Validate, authorize, escape, log without leaking.

---

## Input

- Never trust the request. Use Form Request or DTO validation.
- Use parameter binding in every database query. Never concatenate.
- Avoid `Model::create($request->all())` — go through a DTO.
- Reject unknown fields explicitly when the API is supposed to be strict.

## Authentication

- Sanctum for first-party SPA / mobile.
- Passport for third-party OAuth flows.
- 2FA for admin and high-risk accounts.
- Password rules: at least 12 chars, breach check against haveibeenpwned (Laravel `Password::min(12)->letters()->numbers()->symbols()->uncompromised()`).
- Hash with Bcrypt or Argon2id (Laravel default). Never roll your own.

## Authorization

See `rules/authorization.md`. Every write endpoint runs through a Policy. Reads enforce row-level access.

## Mass Assignment

- Explicit `$fillable` lists, or `$guarded = []` with DTO boundary.
- Treat `Model::create([...])` with a DTO-built array as the only write path.

## SQL Injection

- Eloquent / Query Builder bind by default.
- Raw queries (`DB::raw`, `whereRaw`) take **parameters as bindings**, never string interpolation.

```php
// Bad
DB::select("SELECT * FROM orders WHERE status = '$status'");

// Good
DB::select('SELECT * FROM orders WHERE status = ?', [$status]);
```

## XSS

- Blade `{{ }}` escapes by default. Use `{!! !!}` only for trusted, sanitized HTML.
- Sanitize user-provided HTML with a library (`mews/purifier` or similar).
- Set `Content-Security-Policy` header globally.

## CSRF

- Web routes: VerifyCsrfToken middleware enabled (default).
- API routes: stateless tokens via Sanctum / Passport. Do not use cookie auth across origins without `SameSite` and CSRF protection.

## File Uploads

- Validate MIME type (`mimes:jpg,png`) and re-derive type from content, not extension.
- Cap file size (`max:5120` for 5 MB).
- Store outside the web root or in object storage (S3) with private ACL.
- Generate filenames; never trust user-supplied names.

## Secrets

- Read from `env()` only inside `config/*.php`. Read `config('...')` everywhere else.
- Never commit secrets. `.env` is gitignored; `.env.example` is not.
- Rotate secrets on personnel changes and on any leak.
- Vault, Doppler, or AWS Secrets Manager for production.

## Logging

Never log:

- Passwords, tokens, secrets, API keys
- Full credit card numbers (PCI scope)
- Government IDs unless explicitly required and access-controlled
- PII without need

Use Laravel's built-in `LogProcessor` to redact known sensitive fields before write.

## HTTPS and Headers

- HTTPS-only. HSTS with `includeSubDomains; preload`.
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY` or CSP `frame-ancestors`
- `Referrer-Policy: strict-origin-when-cross-origin`

## Dependencies

- `composer audit` in CI.
- Dependabot or Renovate for updates.
- Pin versions; review changelogs before bumping major/minor.

## Multi-Tenancy

- Scope queries by tenant via global scopes.
- Test tenant isolation with explicit cross-tenant assertions in feature tests.
- Never allow user-supplied `tenant_id` from the request body to override the authenticated tenant.

## Rate Limiting and Throttling

- Throttle login, password reset, signup endpoints aggressively.
- Differentiate per IP and per user.
- Slow down (delay) before locking out — better UX, equivalent protection.

## Webhooks

- Verify HMAC signatures.
- Use idempotency keys.
- Reject requests older than 5 minutes (`X-Timestamp` + signed payload).
