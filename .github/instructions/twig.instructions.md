---
applyTo: "**/*.html.twig"
---
# CRITICAL RULES (Never Skip)
 * **ALWAYS create matching CSS file** when creating new Twig block/component (e.g., `HeroSection.html.twig` → `HeroSection.css`)
 * **ALWAYS override {% block styles %}** in page templates to load page-specific CSS file
 * **NEVER use inline styles or scripts** - always use separate CSS files via `{{ asset() }}`

# Codestyle
 * Use BEM classes matching CSS file (e.g., `HeroSection__title`)
 * **Page templates**: Override `{% block styles %}` to load page-specific CSS
   * Example: `{% block styles %}<link rel="stylesheet" href="{{ asset('styles/homepage.css') }}" />{% endblock %}`
   * Each page template (homepage.html.twig, event.html.twig, etc.) loads its own CSS file
   * Page CSS files import only the blocks needed for that page
 * Load JS: `{{ importmap('app') }}` for entrypoints
 * **HTML style**: XML-valid with self-closing tags (`<img />`, `<link />`, `<meta />`, `<br />`, etc.)
 * HTML: valid, semantic, accessible (ARIA, alt texts), well-indented

# Sulu CMS Variables
 * `content.*` - page content (title, article, etc.)
 * `extension.excerpt.*` - excerpt data (title, description, images)
 * `extension.seo.*` - SEO metadata
 * `urls` - multilingual routing
 * `view.*` - template configuration

# Template Reuse
 * **Twig Components** (in `src/Twig/Components/`) - use when data fetching/logic is needed
 * **Twig Includes** - use for simple, static HTML fragments without logic
 * **Never duplicate HTML** - always extract reusable parts

# File Organization
 * **Page CSS files** at root: `assets/styles/homepage.css`, `assets/styles/event.css`
   * Each page CSS imports only what it needs
 * **Page-specific components** in page folder: `assets/styles/homepage/Header.css`
   * Used only by one specific page
 * **Shared/common components** in common folder: `assets/styles/common/Map.css`
   * Reusable across multiple pages
 * **Page-specific blocks** (Sulu blocks): `assets/styles/pages/homepage/HomepageFacebook.css`
   * One block = one CSS file, named exactly like the BEM block class
 * **Global variables**: `assets/styles/variables.css`
