# Design

The visual system for the Rows CRM. A refined, branded product surface — Linear's
density and precision, Stripe's data-forward polish — built on shadcn/Radix
primitives, Inter, and the Rows navy + blue. Light and dark are first-class.

## Theme

- **Strategy:** Committed. The brand **navy owns the app frame in both themes** —
  via the `inset` shell, the full nav rail plus a thin perimeter gutter are deep
  navy, with the workspace floating as a light rounded card on top. One sharp
  action color (Rows blue, pushed to chroma 0.19) carries action, selection, and
  state. Color is committed at the structure level, not sprinkled as decoration.
- **Three deliberate levels:** navy frame (rail + gutter) → cool-tinted canvas
  card → crisp white panels, separated by stronger-than-hairline borders and a
  real elevation shadow on the floating card. Depth is structural, not flat grey.
- All colors are OKLCH. Neutrals are tinted toward the brand hue (256) so light
  surfaces read navy-cool, never dead grey; ink is pushed darker for presence.

## Color palette

Brand: **blue** `oklch(0.55 0.19 256)` (≈ #1975E2 family, sharpened, AA on white) ·
**navy** `oklch(0.37 0.07 256)` (#1D4069) · **deep navy** `oklch(0.17 0.025 258)` (#0B101E).

Light roles: nav frame `oklch(0.22 0.04 262)` (deep navy — rail + inset gutter),
canvas card `oklch(0.972 0.009 256)`, panel `oklch(1 0 0)`, ink `oklch(0.2 0.03 264)`,
muted-ink `oklch(0.44 0.028 264)`, border `oklch(0.885 0.012 256)`. On-navy rail
text `oklch(0.9 0.014 256)`; active nav indicator/icon brightens to
`oklch(0.65 0.18 256)`. Dark roles: canvas `oklch(0.17 0.025 258)`, panel
`oklch(0.21 0.025 258)`, nav rail `oklch(0.14 0.025 258)`, ink `oklch(0.96 0.008 256)`,
border `oklch(0.29 0.02 258)`. The blue primary is shared across themes.

Charts use a brand-anchored ramp: blue → navy → sky → amber → violet.

State vocabulary (standardized across every component): default, hover, focus,
active, disabled, selected, loading, error, warning, success, info.

## Typography

- **One family: Inter** (already bundled), used in multiple weights — no pairing.
- **Fixed rem scale** (no fluid clamps in-app), tight ratio ~1.125–1.2. Hierarchy
  carried by weight more than size.
- **Tabular numerals** for all data, metrics, and currency (`tabular-nums`).
- Inter OpenType features enabled on the body. Prose capped at 65–75ch; tables may
  run denser.

## Shape & elevation

- Radius: `--radius` = 0.5rem (8px). Panels 8px, controls 6px, pills full.
- Hairline 1px borders everywhere. Single subtle overlay shadow token
  (`--shadow-overlay`) reserved for dropdowns, popovers, dialogs.

## Motion

- 150–250ms, `--ease-out: cubic-bezier(0.22, 1, 0.36, 1)`. State and feedback only
  (selection, expand/collapse, skeleton→content). No page-load choreography.
- Every animation has a `prefers-reduced-motion: reduce` fallback (crossfade/instant).

## Components

shadcn/Radix primitives, restyled to this system. Every interactive component
ships the full state vocabulary, a single brand focus ring (2px, offset), skeleton
loading (not spinners), and teaching empty states. Cards are demoted in favor of
panels + tables. A ⌘K command palette and keyboard list navigation are core.

## Layout

- App shell: nav rail (distinct layer) + top bar with global search and the
  notification bell. Responsive behavior is structural (rail collapses, tables
  adapt) — never fluid typography.
- Lists/tables are Linear-dense (defined row heights, hairline separators); detail
  and forms are calmer and roomier.
