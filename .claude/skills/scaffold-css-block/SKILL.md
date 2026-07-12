---
name: scaffold-css-block
description: Scaffold a single BEM CSS block file in the correct folder for this repo (common/, pages/[page]/, [page]/, or components/). Use when the user asks to add a CSS block, component styles, or a new stylesheet.
---

# Scaffold a CSS Block

One BEM block = one file, named exactly after the block class. Get the folder right — it encodes what
the block is.

## Steps

1. **Get the block name** (PascalCase, e.g. `PriceTable`) and where it's used.
2. **Choose the folder** (ask if unclear):
   - `components/<Block>.css` — styles for a Twig UX component (`src/Twig/Components/<Block>.php`).
   - `common/<Block>.css` — reused across multiple pages (nav, map, pager…).
   - `<page>/<Block>.css` — used by exactly one page (e.g. `homepage/Header.css`).
   - `pages/<page>/<Block>.css` — a Sulu content block for one page.
3. **Create the file** with a mobile-first skeleton:
   ```css
   .<Block> {
       /* base (mobile) styles — rem/em only, colours from variables.css */
   }

   .<Block>__element {
   }

   @media (min-width: 48rem) {
       .<Block> {
           /* desktop overrides */
       }
   }
   ```
   Rules (see `assets/website/styles/AGENTS.md`): no `px` except `1px` borders, no color literals
   (use `var(--…)` from `variables.css`), no IDs, native nesting for elements/modifiers, only add a
   new custom property if reused 3+ times.
4. **Import it** where it's used — the relevant per-page entry CSS (e.g. `homepage.css`), or the
   page's `{% block styles %}`. A block file is not auto-loaded.
5. **Quality gate**: `make stylelint` (pass `fix=1` to auto-fix). Verify at `https://localhost`.

## Reference
`assets/website/styles/AGENTS.md`, `assets/website/styles/variables.css`.
