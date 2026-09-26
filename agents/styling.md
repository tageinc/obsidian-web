# Styling and accessibility rules

- Check nearby components, `resources/css/frontend.css`, and the relevant component styles before introducing new styles. Inspect legacy Sass only when that pipeline is involved.
- Reuse established colors, spacing, and Bootstrap utilities where practical. Use custom CSS when it produces clearer, maintainable markup.
- Match the surrounding visual language rather than importing TAGCSOFT-specific tokens, rounded/borderless requirements, or unavailable theme helpers.
- Check mobile and desktop layouts, tables, forms, navigation, scrolling, and horizontal overflow.
- Preserve readable contrast, keyboard access, visible focus, accessible labels, touch targets, and focus restoration in dialogs and menus. Do not convey status only by color.
- If the affected area supports themes, verify the full surface hierarchy, controls, charts, empty states, and theme switching. Do not introduce dark mode as an incidental requirement.
- Verify positioned and teleported menus for stacking, clipping, scrolling, viewport edges, and keyboard behavior when changed.
- Use the browser verification requirements in [testing.md](testing.md), including for style-only changes.

## Theme behavior and zebra protection

- Keep the complete surface hierarchy in the same resolved theme: page,
  navigation, filters, cards, tables, charts, menus, dialogs, tooltips, controls,
  and populated, empty, loading, and error states. Prevent the "zebra effect"
  caused by isolated hardcoded light surfaces or dark text left in Dark mode
  (and the reverse in Light mode). Intentional, subtle table striping is allowed.
- Reuse the shared `--obsidian-*` semantic tokens in `public/css/accent.css`.
  Pair every surface with suitable text, muted text, borders, and interaction
  colors. Avoid hardcoded themeable neutrals and broad `filter: invert(...)`
  effects; photographs, logos, map tiles, and status/data hues must keep their
  meaning. Use theme-aware shades when needed for contrast.
- Dark mode exchanges the light surfaces/dark primary accent for dark
  surfaces/light primary accent. Maintain surface depth with restrained neutral
  differences rather than alternating bright and dark blocks. Preserve visible
  focus, hover, selection, disabled states, validation feedback, and adequate
  contrast. Never rely on color alone to communicate status.
- Preserve the saved UserSettings mode independently of the resolved theme.
  Adaptive follows browser-local time: Light from 06:00 through 17:59, Dark from
  18:00 through 05:59. Explicit Light and Dark override that schedule and OS
  preferences. Apply the mode before first paint, update Adaptive at boundaries
  and when a suspended page resumes, and clean up listeners/timers on teardown.
  Do not leak one account's preference into another account through global
  browser storage. Cancelled or failed settings changes must preserve the saved
  appearance.
- Canvas charts must read semantic theme tokens and redraw existing data when
  `obsidian:theme-changed` fires; CSS changes alone cannot recolor drawn pixels.
  Keep axes, legends, grids, tooltips, and series readable while preserving
  ranges, selected metrics, and semantic data colors. Theme changes must not
  refetch telemetry or issue physical-device commands.
- Keep modern Vue and legacy Blade surfaces consistent, including teleported
  popovers/dialogs and fallback Bootstrap styles. Printed/exported documents may
  retain their deliberate light palette; document that boundary instead of
  applying screen-theme inversion to PDF output.
- Verify Light and Dark plus Adaptive boundary changes in the isolated browser
  harness. Cover switching without reload, persisted reloads, cancellation,
  representative surface hierarchies, charts, empty states, keyboard focus,
  contrast, scrolling, and desktop/mobile overflow. Follow [testing.md](testing.md)
  for the required final checks.
