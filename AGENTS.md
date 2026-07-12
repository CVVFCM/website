# AGENTS.md

This file provides guidance to AI coding agents (Claude Code, and any tool that reads `AGENTS.md`)
when working with code in this repository. Area-specific rules live in nested `AGENTS.md` files —
they attach automatically when you edit files under that directory:
- `src/AGENTS.md` — PHP / Symfony / Doctrine
- `templates/AGENTS.md` — Twig / HTML
- `assets/website/styles/AGENTS.md` — CSS

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
- `templates/components/` — Twig component templates (paired with `src/Twig/Components/*` + a CSS file)
- `templates/partials/` — shared macros/includes (notably `_picture.html.twig`, the responsive-image macro)
- `templates/emails/`, `templates/error/`, `templates/search/`, `templates/forgie/` — mail, error pages, search UI, Forgie feature
- `templates/bundles/` — third-party (Sulu) template overrides

### Responsive images
Never emit a bare `<img>` for Sulu media. Import the macro and let it build the AVIF/WebP `<picture>`:
```twig
{% import 'partials/_picture.html.twig' as picture %}
{{ picture.render(media, 'sulu-400x400', alt, { widths: [['640x',640],['1024x',1024]], sizes: '100vw' }) }}
```
Width formats are declared in `config/image-formats.xml`.

### CSS File Organization (`assets/website/styles/`)
- `variables.css` — global CSS custom properties (colors, etc.)
- `[page].css` — per-page entry file (e.g., `homepage.css`, `event.css`) — imports only blocks needed for that page
- `[page]/[Block].css` — page-specific component (e.g., `homepage/Header.css`)
- `common/[Block].css` — shared component used across multiple pages
- `pages/[page]/[Block].css` — Sulu CMS content blocks specific to one page

### Key Bundles
- `sulu/sulu` — CMS core (pages, media, snippets, forms)
- `sulu/form-bundle` — Sulu forms
- `symfony/ux-twig-component` — Twig Components (the primary UX pattern here)
- `symfony/ux-live-component` — **installed but currently unused on the frontend** (its JS/controllers were removed from `importmap.php` + `controllers.json`). Don't re-wire it unless a feature needs it.
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
- `make test` **must pass before finishing any task** — the PHPUnit suite is the final gate; a red test means the task is not done.

### Area-specific rules (nested `AGENTS.md`)
The detailed PHP, Twig, and CSS conventions live next to the code so they attach only when relevant:
- **PHP / Symfony / Doctrine** → `src/AGENTS.md` (strict types, typing, constructor injection,
  attribute mapping, the entity `readonly` caveat, DualKernel).
- **Twig / HTML** → `templates/AGENTS.md` (the component triad, `{% block styles %}`, self-closing
  tags, Sulu vars, accessibility, `_picture.html.twig`).
- **CSS** → `assets/website/styles/AGENTS.md` (BEM PascalCase, one-block-one-file, mobile-first,
  rem/em only, `variables.css` colors, folder map).

One rule bears repeating everywhere: creating `src/Twig/Components/Foo.php` **requires** also creating
`templates/components/Foo.html.twig` and `assets/website/styles/components/Foo.css`.

## How to Work

- **Ask, don't guess.** When requirements, scope, or behaviour are unclear, ask the user — prefer high
  confidence over assumptions. This overrides any urge to "just pick something".
- **Empty beats broken.** If a design/layout is hard to get right, ship clean, simple, semantic
  structure (or leave it empty) rather than a subpar complex build. Structure + a11y first.
- **Verify frontend work in the browser** at `https://localhost` before calling it done: check
  rendering, responsive behaviour, and the console for errors. If no browser MCP is connected, ask the
  user to eyeball it. (See the `verify-in-browser` skill / `frontend-verifier` agent.)
- **Iterate, then report blockers.** Loop at most ~3 times on a stuck problem; after that, explain
  what's not working and ask for guidance instead of thrashing.
- **Minimal, surgical changes.** Modify only what the task needs; extract rather than duplicate.

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
