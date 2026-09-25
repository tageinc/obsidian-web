# Blade and legacy frontend rules

- For every user-visible business change, follow [external-api.md](external-api.md). The same parity requirement applies to Blade and legacy JavaScript; rendering choices do not exempt business data or actions.

- Preserve working Blade/fallback functionality unless the requested change includes its migration or removal.
- Keep Blade focused on layout, server-provided data, forms, and Vue mount points. Put substantial business logic in the backend.
- Inspect the current Vue/legacy branch and asset selection before editing a template. Avoid mixing incompatible Bootstrap versions or duplicating bundles.
- Keep page-specific JavaScript localized and reuse existing helpers. Check actual response shapes before consuming legacy endpoints.
- Preserve defined routes, CSRF protection, validation errors, user input, redirects, and document-action behavior.
- Do not assume legacy filenames indicate the current build system. Modern frontend assets use Vite; inspect the existing legacy asset pipeline before changing it.
- Verify affected rendering branches when the change applies to both Vue and legacy pages.
- Follow [styling.md](styling.md) and [testing.md](testing.md) for browser behavior changes.
