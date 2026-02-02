---
applyTo: "**/*.css"
---
# Codestyle
 * Classes must follow BEM with the following syntax : `ThisIsABlock__thisIsAnElement--thisIsAModifier`
 * A block could be quite "wide". It can cover a whole section of the page.
 * Each block must have a unique name.
 * Each block should be in its own file named after the block (e.g. `ThisIsABlock.css`).
 * Elements and modifiers should only be used inside their block file.
 * If a block is specific to a page, it should be in a folder named after the page (e.g. `homepage/ThisIsABlock.css`).
 * The page folder should be named after the page sulu key
 * CSS files are loaded via Symfony's AssetMapper
 * Modern CSS features are used (e.g. custom properties, nesting, etc.)
 * Layouts should be kept as simple as possible
 * Layouts should be done with CSS Grid or Flexbox
 * Avoid using IDs in CSS
 * Use em and rem units as much as possible. px are strongly discouraged. Viewports units are allowed.
 * Use a mobile first approach. Mobile style should be the default, without any media queries, and desktop styles should be added with media queries.
 * Media query should use rem units.
 * All colors, fonts, important sizes should be defined in custom properties, but you should keep variable number as less as possible.
 * 
