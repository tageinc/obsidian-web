# Project overview

Obsidian manages devices and solar trackers, including registration, telemetry,
maps, device lifecycle, remote controls, and firmware/configuration distribution.

## Stack and architecture

- Laravel 8 and PHP 8.3 provide browser routes, APIs, authentication, and authorization.
- Production uses MySQL; the isolated browser suite uses disposable SQLite.
- Vue 3, Pinia, Bootstrap, and Vite power the modern frontend. Blade and legacy assets remain for fallback pages.
- `resources/js/entries/app.js` mounts frontend pages. Feature code lives in `resources/js/features`, reusable code in `resources/js/shared`, and bounded client navigation in `resources/js/workspace`.
- Workspace routing uses Vue Router history mode for selected destinations. Preserve Laravel route ownership, middleware, document navigation boundaries, and direct-link behavior.
- `config/frontend.php` selects Vue versus legacy rendering. Per-page flags override `FRONTEND_VUE3_ENABLED`; workspace enablement also depends on destination flags.
- Docker and Compose are used for production. The Node build stage compiles assets and the PHP/Apache image serves them. GitHub Actions deploys releases to the VPS.

Do not infer active production flags from the commit or example environment files.
Existing server environment settings survive deployment.

## UI and external API parity

User-visible business capabilities must have equivalent authorized behavior and
data through the existing `/api/external/v1` API. Implement affected UI/API
behavior, tests, capability discovery, and documentation in the same change set.
Follow [external-api.md](external-api.md) for required coverage, explicit
exclusions, and Obsidian's ownership and Developer authorization boundaries.
This is a requirement for changes, not a claim that every existing capability
has already been audited for parity.

## References

Prefer current code and nearby implementations over imported patterns. Consult
[frontend architecture](../docs/modernization/architecture.md),
[Vue troubleshooting](../docs/fixing-vue-production.md),
[deployment](../docs/github-actions-deployment.md), and
[browser testing](../tests/browser/README.md) as relevant; verify details against
the implementation when documentation differs.

These rules adapt TAGCSOFT guidance to Obsidian. TAGCSOFT-specific roles, storage,
external API architecture, build commands, and directory names do not apply here.
