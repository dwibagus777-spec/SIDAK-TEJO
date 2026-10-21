---
name: database-architecture
description: Use when designing or changing database schemas, migrations, relationships, data integrity, or query performance in an existing application.
---

# Database Architecture for Existing Applications

Help make database changes consistent with the application, accurate for its data, and safe to deploy. Treat the existing repository and database as the source of conventions; do not impose a generic schema template.

## Workflow

1. **Understand the request and data**
   - Identify the business entity, lifecycle, source of truth, relationships, expected reads/writes, and required reports.
   - Inspect the current schema, migrations, models/services, query callers, database engine/version, and project instructions before proposing a new table or column.
   - Reuse existing entities and conventions when they meet the need. Distinguish observed facts from assumptions; ask only when a missing business rule changes the design.

2. **Design the smallest correct change**
   - Choose column types, nullability, defaults, keys, and constraints from actual value ranges, lifecycle, and compatibility requirements.
   - Normalize independently maintained or repeated facts. Use a detail/junction table when it represents a real one-to-many or many-to-many relationship; choose primary keys and audit fields to match established repository patterns.
   - Store a value once when practical. Denormalize only for a measured query/reporting need, and document how it stays consistent.
   - Add constraints for invariants the application must enforce, while accounting for existing data and rollout compatibility.

3. **Plan a safe migration**
   - Follow the framework's migration conventions and supported database engine.
   - For populated tables, consider expand/backfill/validate/contract steps and application compatibility during deployment.
   - Make rollback behavior explicit. If a change is destructive or cannot be safely reversed, preserve data and explain the recovery plan before execution.
   - Do not run migrations or data-changing SQL against production without explicit authorization and a verified backup/rollback procedure.

4. **Optimize from real query patterns**
   - Inspect filters, joins, sort/group operations, row counts, and existing indexes. Add indexes that support observed queries; consider composite-index column order and write/storage costs.
   - Use parameterized queries and preserve authentication, tenant/ULP, and authorization scopes.
   - Prefer selecting needed columns in production paths. Avoid non-sargable predicates when they cause a measured or likely index problem; use range predicates or supported indexed/generated-column alternatives where appropriate.
   - Compare JOIN, EXISTS, and subqueries based on semantics and query plan; no form is always faster. Use EXPLAIN or the database's equivalent when available. Do not claim complexity or performance gains without evidence.

5. **Verify data integrity and behavior**
   - Check uniqueness, nulls, duplicates, orphan rows, enum/code mappings, and totals affected by filters before and after the change.
   - Add or update focused tests for constraints, writes, reads, aggregation, and migration behavior where the project supports them.
   - Run syntax/lint and targeted tests. Report unrun checks and concrete blockers; do not describe a migration as safe or a query as faster without verification.

## Response Shape

Keep the response proportional to the request. For a small query fix, give the change and verification briefly. For a schema or migration change, summarize:
- existing pattern and root need;
- schema/migration and data-transition impact;
- query/index rationale based on actual access patterns;
- verification performed and any remaining risk.

Include SQL/DDL only when it helps implementation or review. Do not force a fixed report or boilerplate for unrelated tasks.

