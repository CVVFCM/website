# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Context

CVVFCM V4 — a French sailing club website. Stack: **FrankenPHP 1.11**, **PHP 8.5**, **Symfony 7.4**, **Sulu CMS 3.0**, **PostgreSQL 18**. Everything runs in Docker Compose. No build step for frontend CSS/JS (Symfony AssetMapper, HTTP/3).

## Commands

All commands must be run **inside the Docker container** or via `make` (which wraps `docker compose exec php`).

```bash
make run          # First-time setup + start containers
make up           # Start containers (subsequent runs)
make down         # Stop containers
make cli          # Open bash shell in PHP container
make logs         # Tail PHP container logs (make logs c=php)
make ps           # List running containers
make cc           # Clear Symfony cache (both website + admin kernels)
make cs           # Fix code style (php-cs-fixer + twig-cs-fixer)
make psalm        # Run static analysis
make psalm_strict # Run static analysis with info-level issues
make test         # Run PHPUnit test suite
make stylelint    # Lint website CSS (pass fix=1 to auto-fix)
make reset        # Wipe DB, re-create schema, load fixtures + import weather data
make reset-test   # Same as reset but for test environment
```

**Single test:** `docker compose exec php ./vendor/bin/phpunit --filter TestName`

**Sulu consoles** (use instead of `bin/console` for Sulu contexts):
- `bin/websiteconsole` — website kernel
- `bin/adminconsole` — admin kernel

## Architecture

### Dual Kernel
`src/DualKernel.php` and `src/Kernel.php` — Sulu uses two separate Symfony kernels (website + admin) sharing the same codebase. This is why `bin/websiteconsole` and `bin/adminconsole` exist and why cache must be cleared for both.

### Key Source Directories
- `src/Controller/` — Symfony controllers (keep thin)
- `src/Entity/` — Doctrine entities (attributes-based mapping)
- `src/Repository/` — Doctrine repositories
- `src/Service/` — Business logic
- `src/Twig/Components/` — Symfony UX Twig Components (PHP class + template)
- `src/SmartContent/` — Sulu SmartContent data providers
- `src/Weather/` — Weather data import/ML integration
- `src/DataFixtures/` — Doctrine fixtures (loaded by `make reset`)

### Sulu CMS Templates
- `config/templates/pages/*.xml` — page type definitions
- `config/templates/fragments/*.xml` — fragment definitions
- `config/templates/snippets/*.xml` — snippet definitions
- `templates/pages/` — Twig page templates
- `templates/fragments/` — Twig fragment templates
- `templates/snippets/` — Twig snippet templates
- `templates/components/` — Twig component templates

### CSS File Organization (`assets/website/styles/`)
- `variables.css` — global CSS custom properties (colors, etc.)
- `[page].css` — per-page entry file (e.g., `homepage.css`, `event.css`) — imports only blocks needed for that page
- `[page]/[Block].css` — page-specific component (e.g., `homepage/Header.css`)
- `common/[Block].css` — shared component used across multiple pages
- `pages/[page]/[Block].css` — Sulu CMS content blocks specific to one page

### Key Bundles
- `sulu/sulu` — CMS core (pages, media, snippets, forms)
- `sulu/form-bundle` — Sulu forms
- `symfony/ux-twig-component` + `symfony/ux-live-component` — Twig/Live Components
- `symfony/ux-turbo` — Hotwire Turbo (navigation)
- `symfony/stimulus-bundle` — Stimulus controllers (`assets/controllers/`)
- `symfony/asset-mapper` — no-build asset pipeline (importmap)
- `symfony/messenger` — async message bus
- `symfony/scheduler` — scheduled tasks (`src/Schedule.php`)
- `scheb/2fa-bundle` — two-factor authentication
- `cmsig/seal` — search engine abstraction (Loupe adapter)
- `symfony/ux-map` + `symfony/ux-leaflet-map` — interactive maps

## Rules (Never Skip)

### Git
- **Never commit or push unless explicitly asked.** Prepare changes, verify them, report — then wait for the user to request the commit.

### Quality gates (after EVERY update)
- `make cs` and `make psalm` **must pass after each edit** — run them immediately, not batched at the end of a task. Treat a red psalm like a failing test.
- `make stylelint` **must pass after each CSS edit** — same rule: run immediately, not batched.

### PHP
- Always `declare(strict_types=1);`
- Type all properties, parameters, and return values
- Use PHP 8.4+ native features (readonly, enums, etc.)
- Use constructor injection everywhere
- Use Attributes for routes and entity mapping (not YAML/XML)

### Twig Components
When creating `src/Twig/Components/Foo.php`, you **must** also create:
- `templates/components/Foo.html.twig`
- `assets/website/styles/components/Foo.css`

### Twig Templates
- Override `{% block styles %}` in every page template to load its CSS file:
  ```twig
  {% block styles %}<link rel="stylesheet" href="{{ asset('styles/homepage.css') }}" />{% endblock %}
  ```
- Use `{% types %}` tag to document template variables
- Use self-closing tags: `<img />`, `<br />`, `<link />`
- Sulu content variables: `content.*`, `extension.excerpt.*`, `extension.seo.*`, `urls`, `view.*`

### CSS
- BEM with PascalCase blocks: `.HeroSection__title--modifier`
- One block = one file, named exactly after the BEM block class
- **Never** use `px` for layout/fonts — use `rem`/`em` (`1px` only for borders)
- Mobile-first: base styles without media queries, desktop via `@media (min-width: ...rem)`
- No color literals — always use CSS custom properties from `variables.css`
- Only define new custom properties if reused 3+ times across blocks

## Symfony Superpowers (plugin)

The `superpowers-symfony` plugin is enabled. For Symfony / Doctrine / Twig / Messenger / testing work,
**prefer these agents & skills over free-handing**. Entry point: the `symfony:using-symfony-superpowers`
skill. When a task matches one below, invoke it — but always honour this repo's conventions (see below
and the Rules section).

### Stack caveats (pick the matching variant)
- **Tests = PHPUnit** (`make test`), not Pest → use the PHPUnit variants (`symfony:tdd-with-phpunit`, `/symfony-tdd-phpunit`, `symfony-tdd-coach` in PHPUnit mode).
- **Static analysis = Psalm** (`make psalm`), **not PHPStan**. Keep style via `make cs` (php-cs-fixer `@Symfony`). Do **not** substitute PHPStan when a skill mentions it.
- **CMS = Sulu, no API Platform** → the `api-platform-*` agents/skills are **N/A** unless API Platform is added.
- Fixtures already use Doctrine fixtures (`make reset`); Foundry is optional.
- Async: `symfony/scheduler` + `symfony/messenger` (sync transport) — `symfony:symfony-scheduler` / `symfony:symfony-messenger` apply.
- Planning already handled by the built-in `/plan` flow; the plugin's `writing-plans`/`executing-plans` are complementary, not a replacement.

### Agents (delegate via the Agent tool)
| Agent | Use when |
|---|---|
| `symfony-engineer` | General Symfony impl (controllers, services, DI, VOs/DTOs, forms, Twig components) when no specialised agent fits |
| `doctrine-architect` | Entity schema, relationships, migration planning **before** implementing |
| `doctrine-performance-optimizer` | Read-only N+1 / fetch-mode / index / cache audit after adding entities/queries or a slow page |
| `symfony-reviewer` | Proactive quality/architecture review after code changes |
| `symfony-security-auditor` | After changes to security, voters, forms, controllers (read-only audit) |
| `symfony-tdd-coach` | Writing tests / adding coverage — PHPUnit mode |
| `api-platform-builder` | Only if API Platform is introduced (currently N/A) |

### Skills (invoke via the Skill tool, `symfony:<name>`)
- **Testing**: `tdd-with-phpunit`, `functional-tests`, `test-doubles-mocking`, `doctrine-fixtures-foundry` (Foundry optional).
- **Doctrine**: `doctrine-migrations`, `doctrine-relations`, `doctrine-transactions`, `doctrine-batch-processing`, `doctrine-events`, `doctrine-fetch-modes`.
- **Async / caching**: `symfony-messenger`, `messenger-retry-failures`, `symfony-scheduler`, `symfony-cache`, `rate-limiting`.
- **Architecture**: `value-objects-and-dtos`, `interfaces-and-autowiring`, `ports-and-adapters`, `cqrs-and-handlers`, `strategy-pattern`, `controller-cleanup`, `config-env-parameters`.
- **Frontend**: `twig-components` (this repo leans on Twig UX components).
- **Security**: `symfony-voters` (+ the `symfony-security-auditor` agent).
- **Quality**: `quality-checks` — but run this repo's `make cs` + `make psalm` (not PHPStan).
- **Workflow / meta**: `using-symfony-superpowers`, `daily-workflow`, `effective-context`.
- **API Platform** (`api-platform-*`): skip unless API Platform is added.

### Slash commands
Thin wrappers over the skills above, e.g. `/symfony-check` → `symfony:quality-checks`,
`/symfony-tdd-phpunit`, `/symfony-migrations`, `/symfony-voters`, `/symfony-messenger`, `/symfony-fixtures`.
