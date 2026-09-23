# Backend rules

- Controllers should authorize, validate, coordinate domain logic, and return responses. Move substantial reusable or multi-step workflows into focused services.
- Do not create a service solely to relocate simple CRUD. Give services business-oriented names and keep HTTP response/rendering responsibilities in controllers.
- Keep models focused on relationships, casts, scopes, accessors, and small entity helpers.
- Validate all user input, including referenced device IDs, files, and partial updates. Use `sometimes` only when omission is valid for the endpoint contract.
- Enforce authentication, device ownership, verification, and developer permissions on the server. UI visibility never substitutes for authorization.
- Apply ownership checks consistently to lists, searches, maps, details, exports, nested data, and writes. Do not import TAGCSOFT company/God roles or global access exceptions.
- Prefer existing Eloquent relationships and scopes, avoid repeated queries, and use transactions for related writes that must succeed together.
- Handle expected errors without exposing stack traces, secrets, or internal details. Keep diagnostic logging useful and redacted.
- Follow the configured filesystem and existing upload/download services. Validate access and file handling; do not assume S3-only storage or publicly readable uploads.
- Reuse existing mail and job patterns. Verify the configured queue behavior rather than assuming a worker exists or changing sync/async behavior incidentally.
- Preserve optional Redis workload behavior and fallback paths. Do not assume Redis is the default cache or change session/queue storage as part of unrelated work.
- Test outcomes, ownership denials, validation, persistence, and relevant file/mail/job side effects using this repository's fixtures.

See [api.md](api.md), [database.md](database.md), and [testing.md](testing.md)
when those areas are affected.
