# Styling and accessibility rules

- Check nearby components, `resources/css/frontend.css`, and the relevant component styles before introducing new styles. Inspect legacy Sass only when that pipeline is involved.
- Reuse established colors, spacing, and Bootstrap utilities where practical. Use custom CSS when it produces clearer, maintainable markup.
- Match the surrounding visual language rather than importing TAGCSOFT-specific tokens, rounded/borderless requirements, or unavailable theme helpers.
- Check mobile and desktop layouts, tables, forms, navigation, scrolling, and horizontal overflow.
- Preserve readable contrast, keyboard access, visible focus, accessible labels, touch targets, and focus restoration in dialogs and menus. Do not convey status only by color.
- If the affected area supports themes, verify the full surface hierarchy, controls, charts, empty states, and theme switching. Do not introduce dark mode as an incidental requirement.
- Verify positioned and teleported menus for stacking, clipping, scrolling, viewport edges, and keyboard behavior when changed.
- Use the browser verification requirements in [testing.md](testing.md), including for style-only changes.
