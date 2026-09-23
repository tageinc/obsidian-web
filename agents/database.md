# Database rules

- Model explicit business entities and relationships. Prefer a single source of truth over duplicate fields or comma-separated ID lists.
- Follow existing mass-assignment conventions. Review `$fillable` and casts when attributes change; do not make sensitive fields mass assignable just because they exist in the schema.
- Use explicit foreign keys and constraints where appropriate. Add indexes for demonstrated filters, joins, and sorts, accounting for write cost.
- Use nullable columns only for meaningful missing or legacy states. Use soft deletes only when recoverability or audit requirements justify them.
- Choose representations that fit existing device contracts. Do not impose another repository's enum or integer-mapping conventions.
- Keep each migration focused on one logical change. Consider existing production data, deployment ordering, rollback limits, and dependent code.
- Obtain explicit authorization for destructive production data operations. Never use reset/fresh migrations against a shared or production database as test setup.
- Keep required application data in appropriate seeders and test-only data in isolated fixtures/factories. Do not import TAGCSOFT role or employee seeders.
- Review schema, models, validation, API payloads, frontend callers, and tests together when persisted behavior changes.
- Test ownership boundaries with records belonging to different users when relevant.
