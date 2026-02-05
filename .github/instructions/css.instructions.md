---
applyTo: "**/*.css"
---
# CRITICAL RULES (Never Skip)
 * **BEM with PascalCase**: `BlockName__element--modifier` (block starts with capital)
 * **One block = one file**: `BlockName.css` must contain only `.BlockName` and its elements/modifiers
 * **Mobile-first ALWAYS**: base styles first, then `@media (min-width: ...)` for desktop
 * **NO px units** (except border-width: 1px) - use rem/em for all sizes, spacing, breakpoints

# BEM Structure
 * Block: `.HeroSection` (unique, PascalCase, top-level container)
 * Element: `.HeroSection__title` (child of block, nested in CSS)
 * Modifier: `.HeroSection--featured` (variant of block/element)
 * Elements/modifiers ONLY exist inside their block file
 * Blocks can be "wide" (whole page sections) or small (button, card)

# File Organization
 * **Page CSS files** at root: `homepage.css`, `event.css`, `default.css`
   * Named after the page template
   * Import only the blocks/components needed for that page
 * **Page-specific components** in `[page]/` folder: `homepage/Header.css`, `homepage/Ctas.css`
   * Components used only by one specific page
 * **Shared/common components** in `common/` folder: `common/Map.css`, `common/Pager.css`
   * Reusable components across multiple pages (navigation, maps, pagination, etc.)
 * **Page-specific blocks** in `pages/[page]/` folder: `pages/homepage/HomepageFacebook.css`
   * Sulu CMS content blocks specific to a page
 * **Global variables**: `variables.css` at root
   * Common colors, shared custom properties
 * **Per-page imports**: Create one CSS file per page template that imports only needed blocks
   * Example: `assets/styles/homepage.css` imports only homepage blocks
   * Common/reusable blocks can be imported across multiple pages
   * With HTTP/3 + Early Hints + Brotli, keep files separate (no concatenation needed)
   * Each Twig template loads only its page-specific CSS file

# Layout & Responsiveness
 * Mobile-first: write base styles without media queries, add desktop with `@media`
 * Breakpoints: use rem in media queries (e.g., `@media (min-width: 48rem)`)
 * Layouts: CSS Grid or Flexbox (keep simple)
 * Units: rem/em everywhere (viewport units allowed, px forbidden except 1px borders)
 * No IDs in selectors

# Modern CSS
 * **Use nesting** for elements/modifiers (native CSS nesting):
   ```css
   .HeroSection {
     /* block styles */
   }
   
   .HeroSection__title {
     /* element styles */
   }
   
   .HeroSection--featured {
     /* modifier styles */
   }
   ```
   Or nest related selectors:
   ```css
   .HeroSection {
     /* block styles */
     
     & .HeroSection__nested {
       /* only if truly nested in HTML */
     }
   }
   ```
 * **Minimal custom properties** - only define when reused 3+ times across blocks
 * **Reuse global variables** from `variables.css` - don't create new ones unnecessarily
 * **No color literals** - always use custom properties for colors

# Quality
 * Run `make cs` before committing
