# Best practices

- Understand the implementation and adjacent workflows before changing code. Search for similar implementations, excluding the restricted file as required by `AGENTS.md`.
- Reuse established services, components, stores, and helpers before adding new ones.
- Keep work scoped to the request. Avoid unrelated rewrites, dependencies, frameworks, and speculative abstractions.
- Prefer readable code and explicit business rules. Extract shared logic when reuse or maintenance needs justify it.
- Review affected API consumers, authorization, persisted data, and documentation when changing behavior.
- Maintain UI/external API business-capability parity under [external-api.md](external-api.md). Every user-visible business change requires an external-contract review, corresponding implementation and tests, or a documented applicable exclusion.
- Update relevant documentation with behavior, API contract, and deployment changes. Use existing documentation locations, including `docs/external-api-keys.md`; do not invent a separate public guide hierarchy.
- Never commit or expose credentials, tokens, private keys, full authorization headers, or secret values in logs, errors, browser code, or reports. Use configured secret stores and environment variables.
- Write concise comments for assumptions, constraints, business rules, and non-obvious decisions. Avoid decorative blocks, emojis, assistant/user commentary, and restating the code. Remove stale comments in modified areas.
- Keep validation, retrieval, business logic, persistence, and response construction easy to follow.
- Follow [testing.md](testing.md), report verification gaps accurately, and fix relevant failures before recommending a merge.

Use judgment for routine implementation decisions within the user's authorized
scope. Do not add approval steps solely because a rule was imported from another
repository.
