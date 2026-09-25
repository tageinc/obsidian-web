# Repository agent instructions

- Never read `DevOpsObsidian.txt`, regardless of its directory or filename casing.
- Do not open, preview, search its contents, copy, upload, or otherwise ingest that file through any tool or subprocess.
- Exclude `DevOpsObsidian.txt` from repository-wide content searches and bulk file reads. Use `rg --glob '!**/[Dd][Ee][Vv][Oo][Pp][Ss][Oo][Bb][Ss][Ii][Dd][Ii][Aa][Nn].[Tt][Xx][Tt]'` when searching repository contents, and equivalent exclusions for other tools.
- Read and follow the additional repository rules in [agents/rules.md](agents/rules.md).

These instructions apply throughout this repository.

## Developer access and naming

- There is no admin role in Obsidian. The developer is a regular application user with additional developer functionality.
- Exactly one user in the database shall be the developer. Do not introduce multiple developer accounts or a separate admin account or role.
- The developer's email is determined exclusively by `DEVELOPER_EMAIL` in `.env`, exposed through `config('app.developer_email')`. Use the existing `User::isDeveloper()` policy for authorization; do not hardcode an email, use `ADMIN_EMAIL`, or add alternative role-based access. Missing or blank developer configuration must deny developer access.
- Use Developer terminology consistently in UI labels, components, directories, controllers, tests, and documentation. The workspace page is `DeveloperPage.vue` under `resources/js/features/developer`, not `AdminPage`.
- Preserve existing legacy URL and configuration compatibility only where required for deployed installations; compatibility aliases do not define an admin role or grant additional access.

## Instruction map

Always read [agents/rules.md](agents/rules.md),
[agents/project-overview.md](agents/project-overview.md), and
[agents/best-practices.md](agents/best-practices.md).

Read additional rules for the work being performed:

- [agents/backend.md](agents/backend.md): PHP controllers, services, models, authorization, storage, mail, and caching.
- [agents/api.md](agents/api.md): endpoints, payloads, authentication, integrations, and device protocol compatibility.
- [agents/external-api.md](agents/external-api.md): every user-visible feature or business-behavior change, UI/external API parity, external keys, and integration contracts.
- [agents/database.md](agents/database.md): migrations, relationships, factories, seeders, and persisted data.
- [agents/frontend-vue.md](agents/frontend-vue.md): Vue components, shared frontend code, and workspace routing.
- [agents/frontend-legacy.md](agents/frontend-legacy.md): Blade, legacy JavaScript, and server-rendered forms.
- [agents/styling.md](agents/styling.md): styles, layout, responsiveness, and accessibility.
- [agents/infra-docker.md](agents/infra-docker.md): Docker, Compose, deployment, environment configuration, and GitHub Actions.
- [agents/testing.md](agents/testing.md): UI changes, automated tests, regression verification, and preparing changes for merge.

When several files apply, follow the most specific guidance while preserving
the repository-wide restrictions and existing application contracts.
