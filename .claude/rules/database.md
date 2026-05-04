# Database

PostgreSQL preferred. MySQL acceptable. SQLite for tests only.

---

## Migrations

- One migration per change. Never edit a shipped migration.
- Reversible: implement `down()` unless the change is genuinely irreversible (then state why in a comment).
- Use schema builder; raw SQL only for database-specific features (`->using('CAST(...)')`, partial indexes, GIN indexes).
- Name migrations clearly: `2026_04_24_120000_add_payment_status_to_orders_table.php`.

## Naming

- Tables: snake_case, plural (`orders`, `order_items`).
- Columns: snake_case (`created_at`, `customer_id`).
- Foreign keys: `{singular_table}_id` (`customer_id`).
- Pivot tables: alphabetical, singular (`role_user`).
- Indexes: `{table}_{cols}_index`, unique: `{table}_{cols}_unique`.

## Data Types

| Use case | Type |
|---|---|
| Identifiers | `uuid` (preferred) or `bigint` |
| Money amount | `bigint` (cents) plus `string(3)` currency |
| Status enums | `string(32)` with check constraint, or PG enum if rarely changing |
| Timestamps | `timestamptz` (PostgreSQL); `timestamp` with explicit UTC handling (MySQL) |
| Long text | `text` (no `varchar(big number)`) |
| JSON | `jsonb` (PostgreSQL); `json` (MySQL 8+) |

Avoid `float` / `double` for money. Avoid `enum` columns at the database level — they are painful to migrate; store as `string` with a check constraint or rely on application-level enum.

## Indexes

- Index every foreign key (Laravel does not do this automatically).
- Composite indexes for the most common WHERE + ORDER BY combination.
- Partial indexes for hot subsets (`WHERE status = 'active'`).
- Covering indexes (`INCLUDE`) for read-heavy queries on PostgreSQL.
- Drop unused indexes — they slow writes and waste storage.

```php
$table->index(['status', 'created_at']);                          // composite
$table->unique(['customer_id', 'reference']);                     // business uniqueness
$table->rawIndex('lower(email)', 'customers_email_lower_index');  // expression index
```

## Constraints

- `NOT NULL` by default; nullable only when the absence has meaning.
- Foreign keys with `cascade`, `restrict`, or `set null` chosen deliberately.
- Check constraints for invariants the database can enforce (`amount_cents >= 0`).

## Migrations and Code

- Never edit a model's `$casts` without a migration that backfills existing rows in the new shape.
- Backfills run in batches inside their own command, not inline in a migration that may time out.
- New nullable columns first, then a follow-up backfill, then a separate migration to make them NOT NULL.

## Seeders and Factories

- Factories for tests (`OrderFactory`).
- Seeders for local development and CI fixtures (`DatabaseSeeder`, `ProductionSampleSeeder`).
- Never seed production data through Laravel seeders unless the data is small and the seeder is idempotent.

## Performance

- Run `EXPLAIN ANALYZE` on any new query that reads from a large table.
- Watch for sequential scans on large tables.
- For full-text search, use `tsvector` + GIN (PostgreSQL) or a dedicated engine (Meilisearch, Typesense). Do not use `LIKE '%term%'` on millions of rows.

## Soft Deletes

- Use only when business needs the record to survive (audit, undo). Otherwise, hard delete.
- Soft-deleted rows must be filtered out of unique indexes (`WHERE deleted_at IS NULL` on the index).

## Connection Pooling

- High-throughput services: PgBouncer in transaction mode for PostgreSQL.
- Be aware of session-level features that pooling breaks (advisory locks across requests).
