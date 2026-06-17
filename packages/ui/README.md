# @openmes/ui

Shared design system for the OpenMES web app (`backend/`, React + Inertia + Tailwind 4)
and mobile app (`mobile/`, Expo / React Native). Implements the **Geist White**
system from `design/openmes-fable-remix/project/OpenMES Components.dc.html`.

## How it resolves

- Consumed as a `file:` dependency (symlink) by both apps — no build step, raw source.
- **Twin components**: `Component/index.web.jsx` (Tailwind, DOM) + `Component/index.native.tsx`
  (StyleSheet, RN) with an **identical props API**. Vite resolves `.web.*` first
  (see `backend/vite.config.js`); Metro resolves `.native.*`/platform files; `tsc` in
  `mobile/` follows via `moduleSuffixes: [".native", ""]`.
- Never add an `exports` map to package.json — it would bypass platform-extension resolution.

## Conventions

- Tokens live in `src/tokens/index.ts` and are mirrored as Tailwind theme vars
  (`--color-om-*`, `--radius-om*`, `--animate-om-pulse`) in `backend/resources/css/app.css`.
  Change a value → update both.
- Web twins: plain JSX (the backend is not typechecked), Tailwind utilities using the
  `om-*` theme tokens (`bg-om-accent`, `border-om-line`, `rounded-om-sm`, `font-mono`…).
  Spread extra `...props` onto the root element and accept `className`.
- Native twins: strict TypeScript, `StyleSheet.create`, tokens imported from `../tokens`.
  Accept `style` and set `accessibilityRole`/`accessibilityState`. Typography uses
  `fonts.sans.native.*` / `fonts.mono.native.*` (loaded app-side via @expo-google-fonts).
- Mono uppercase letterspaced labels are the system's metadata idiom — web:
  `font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint`; native: `monoLabel` token.
- Universal components export from `src/index.ts`; native-only patterns (sheets,
  snackbar, app bars…) export from `src/native.ts` → imported as `@openmes/ui/native`.
- Reference implementation showing all of the above: `src/Button/`.
