# Repository agent instructions

- Never read `DevOpsObsidian.txt`, regardless of its directory or filename casing.
- Do not open, preview, search its contents, copy, upload, or otherwise ingest that file through any tool or subprocess.
- Exclude `DevOpsObsidian.txt` from repository-wide content searches and bulk file reads. Use `rg --glob '!**/[Dd][Ee][Vv][Oo][Pp][Ss][Oo][Bb][Ss][Ii][Dd][Ii][Aa][Nn].[Tt][Xx][Tt]'` when searching repository contents, and equivalent exclusions for other tools.
- Read and follow the additional repository rules in [agents/rules.md](agents/rules.md).

These instructions apply throughout this repository.

## Instruction map

Always read [agents/rules.md](agents/rules.md),
[agents/project-overview.md](agents/project-overview.md), and
[agents/best-practices.md](agents/best-practices.md).

Read additional rules for the work being performed:

- [agents/backend.md](agents/backend.md): PHP controllers, services, models, authorization, storage, mail, and caching.
- [agents/api.md](agents/api.md): endpoints, payloads, authentication, integrations, and device protocol compatibility.
- [agents/database.md](agents/database.md): migrations, relationships, factories, seeders, and persisted data.
- [agents/frontend-vue.md](agents/frontend-vue.md): Vue components, shared frontend code, and workspace routing.
- [agents/frontend-legacy.md](agents/frontend-legacy.md): Blade, legacy JavaScript, and server-rendered forms.
- [agents/styling.md](agents/styling.md): styles, layout, responsiveness, and accessibility.
- [agents/infra-docker.md](agents/infra-docker.md): Docker, Compose, deployment, environment configuration, and GitHub Actions.
- [agents/testing.md](agents/testing.md): UI changes, automated tests, regression verification, and preparing changes for merge.

When several files apply, follow the most specific guidance while preserving
the repository-wide restrictions and existing application contracts.
