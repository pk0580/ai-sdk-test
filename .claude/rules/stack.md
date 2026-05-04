# Stack and Tooling

Default to these versions unless the project pins something older. Do not introduce new top-level packages without justification.

---

## Runtime

- **PHP 8.4** — readonly classes, property hooks, asymmetric visibility, `#[\Override]`, `new in initializer`
- **Laravel 12** — Laravel 11+ streamlined skeleton (`bootstrap/app.php`, minimal providers)

## Required Dev Tools

- **Laravel Pint** — formatting (PSR-12 + Laravel preset)
- **PHPStan** level 8 or **Larastan** — static analysis
- **Pest 4** — testing (PHPUnit compatible if the project already uses PHPUnit)
- **Rector** — optional but recommended for upgrades

## Preferred First-Party Packages

- `laravel/sanctum` — API tokens for SPA / mobile
- `laravel/horizon` — queue dashboard (Redis)
- `laravel/telescope` — local debugging only; never ship to prod
- `laravel/pulse` — production observability

## Preferred Community Packages

Add only when the use case appears:

- `spatie/laravel-data` — rich DTOs with validation and transformation
- `spatie/laravel-permission` — role/permission layer on top of Gates
- `spatie/laravel-query-builder` — safe filtering/sorting from query strings
- `spatie/laravel-medialibrary` — file attachments
- `league/flysystem-*` — storage adapters (already bundled with Laravel)

## Do Not Introduce

- ORMs other than Eloquent in new code (e.g., Doctrine) unless the project already uses them
- Service locator packages
- Generic "helper" packages that duplicate Laravel features

---

## Running Things

Always propose commands that are standard for a Laravel 12 project:

```
php artisan test              # tests
./vendor/bin/pint             # format
./vendor/bin/phpstan analyse  # static analysis
php artisan migrate:fresh --seed
php artisan queue:work
```

If the project runs in Docker, run the commands inside the PHP container. Ask for the container name once, then remember it for the session.

---

## PHP 8.4 Usage Rules

- Use `readonly class` for DTOs, commands, queries, and value objects.
- Use property hooks where they replace a trivial getter/setter that validates or normalizes.
- Use asymmetric visibility (`public private(set)`) instead of manual setters when the property is immutable after construction but set in `__construct`.
- Use `#[\Override]` on all methods that implement or override a parent to catch typo'd signatures.
