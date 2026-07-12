# CSS rules (`assets/website/styles/`)

Applies to every `.css` file under this directory. See root `AGENTS.md` for project-wide rules.

## Critical rules (never skip)
- **BEM with PascalCase blocks**: `.HeroSection`, `.HeroSection__title`, `.HeroSection--featured`
  (the block starts with a capital).
- **One block = one file**, named exactly after the block class: `HeroSection.css` contains only
  `.HeroSection` and its elements/modifiers.
- **Mobile-first, always**: base styles carry no media query; add desktop with
  `@media (min-width: ...rem)`.
- **No `px`** for layout, spacing, fonts, or breakpoints — use `rem`/`em` (viewport units allowed).
  The only exception is border width (`1px`).
- **No color literals** — colours come from custom properties in `variables.css`.

## Structure
- Block: top-level container, unique, PascalCase.
- Element: `.Block__element` — only inside its block file.
- Modifier: `.Block--modifier` — only inside its block file.
- No IDs in selectors.
- Use native CSS nesting for elements/modifiers.

## File organization
- **Per-page entry files** at root: `homepage.css`, `event.css`, `default.css` — named after the page
  template; each imports only the blocks that page needs.
- **Page-specific components** in `[page]/`: e.g. `homepage/Header.css`.
- **Shared components** in `common/`: e.g. `common/Map.css`, `common/Pager.css`.
- **Sulu content blocks** in `pages/[page]/`: e.g. `pages/homepage/HomepageFacebook.css`.
- **Twig component styles** in `components/`: `components/<Name>.css` (paired with the component).
- **Global custom properties** in `variables.css`. Only add a new one if it's reused 3+ times across
  blocks — otherwise use a local value.
- **Keep files separate — do not concatenate.** HTTP/3 + Early Hints + Brotli make many small files
  cheap; each Twig template loads only its own page CSS.

## Quality gate
Run `make stylelint` immediately after each CSS edit (pass `fix=1` to auto-fix). Not batched.
