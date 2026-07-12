# Twig / HTML rules (`templates/`)

Applies to every `*.html.twig` file under this directory. See root `AGENTS.md` for project-wide rules.

## Critical rules (never skip)
- **Component triad**: a new Twig block/component needs a matching CSS file
  (`HeroSection.html.twig` → `assets/website/styles/.../HeroSection.css`). A `src/Twig/Components/Foo.php`
  additionally requires `templates/components/Foo.html.twig` **and**
  `assets/website/styles/components/Foo.css`.
- **Load page CSS via `{% block styles %}`** in every page template — never inline:
  ```twig
  {% block styles %}<link rel="stylesheet" href="{{ asset('styles/homepage.css') }}" />{% endblock %}
  ```
- **Never** use inline `style=""` or inline `<script>` — always a separate CSS/JS file via `{{ asset() }}`.
- **XML-valid, self-closing tags**: `<img />`, `<br />`, `<link />`, `<meta />`, `<input />`.

## Codestyle
- BEM class names matching the CSS file (`.HeroSection__title`).
- Use `{% types %}` to document the template's variables.
- Load JS entrypoints with `{{ importmap('app') }}`.
- HTML must be valid, **semantic, and accessible**: correct landmarks/headings, ARIA where needed,
  `alt` on every image, labels on form controls.

## Images
Use the responsive macro instead of a raw `<img>` for Sulu media:
```twig
{% import 'partials/_picture.html.twig' as picture %}
{{ picture.render(media, 'sulu-400x400', alt, { widths: [['640x',640],['1024x',1024]], sizes: '100vw' }) }}
```

## Sulu content variables
- `content.*` — page content (title, article, blocks…)
- `extension.excerpt.*` — excerpt (title, description, images)
- `extension.seo.*` — SEO metadata
- `urls` — multilingual routing
- `view.*` — template configuration

## Reuse
- **Twig Components** (`src/Twig/Components/`) when data-fetching/logic is needed.
- **Includes / macros** (`templates/partials/`) for static fragments without logic.
- Never duplicate HTML — extract.

After changing a page's structure, verify at `https://localhost` (see root `AGENTS.md` → How to Work).
