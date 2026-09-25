# Vue frontend rules

- For every user-visible business change, follow [external-api.md](external-api.md). Data, filters, calculations, and actions require API parity even when implemented in a Vue component; purely presentational changes require only a documented exclusion.

- Follow the existing mounts in `resources/js/entries/app.js` and Blade payloads. Preserve server-owned routes and release flags.
- Retain the bounded workspace in `resources/js/workspace`, including its Vue Router history mode. Do not expand it into an unrestricted SPA or intercept document-only actions incidentally.
- Keep feature components under `resources/js/features`; check `resources/js/shared/components`, `composables`, `stores`, and `api` before adding reusable code.
- Keep root setup and layouts focused. Pages coordinate data and workflow state; child components should have clear responsibilities, accept props, and emit events.
- Reuse stores for genuinely shared state and centralize reusable API interactions. Avoid unnecessary stores and deeply threaded props.
- Preserve CSRF, validation messages, loading/error states, cancellation, focus restoration, and native form/document behavior where used.
- Keep frontend visibility consistent with permissions while retaining server authorization as the authority.
- Vite builds the frontend. Use the current package scripts and manifest pipeline; do not revive Laravel Mix for modern components.
- Distinguish missing assets from disabled Vue flags when debugging production. Consult [Vue troubleshooting](../docs/fixing-vue-production.md).
- Follow [styling.md](styling.md) and [testing.md](testing.md). Component tests and compilation alone do not verify complete browser workflows.
