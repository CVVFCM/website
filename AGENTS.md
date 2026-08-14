# AGENTS.md

This file provides guidance to AI coding agents (Claude Code, and any tool that reads `AGENTS.md`)
when working with code in this repository. Area-specific rules live in nested `AGENTS.md` files —
they attach automatically when you edit files under that directory:
- `src/AGENTS.md` — PHP / Symfony / Doctrine
- `templates/AGENTS.md` — Twig / HTML
- `assets/website/styles/AGENTS.md` — CSS

## Rules (never skip)

### Quality gates
Every gate must be green before a task is done. A red gate means the task is not finished.

- `make cs` and `make psalm` after **each** PHP edit — immediately, never batched at the end.
- `make stylelint` after **each** CSS edit — same rule.
- `make test` — the PHPUnit suite.
- `make test-screenshots` — **only if** the task touched `templates/**` or
  `assets/website/styles/**`. Needs `make up` and loaded fixtures. When a comparison fails: if the
  visual change is what the task asked for, re-record with `make test-screenshots-update` and say so
  in your report; otherwise it is a regression — fix it, don't re-record it.
- `make test-ai` — **only** when `config/prompts/forgie.md` was edited. These call a paid API and are
  judged by an LLM; they are not part of CI and never run "just in case".

Run the linters and fix what they report. Reporting a lint failure instead of fixing it is not done.

### Git
Never commit or push unless explicitly asked. Prepare, verify, report — then wait.

### Remote environments
Never connect to preprod or production, except to read logs, or to run an operation the user
explicitly asked for — and then only that operation. Never point local tooling at a remote database.

### Decisions
Make no design or technical-design choices. Whenever the task leaves something open — layout,
naming, schema shape, library, trade-off — ask. Announcing a decision afterwards is not asking.

### Task size
Before starting anything that spans several files, changes the schema, touches infrastructure, or
redesigns a surface: stop and ask the user to switch to plan mode and to a highly capable model
(Opus). Don't start a large task from a one-line prompt.

### Comments
Few. A comment earns its place by stating a constraint the code cannot: a trap, a non-obvious *why*.
Never narrate the bug you just fixed — that belongs in the commit message. No blocks longer than a
couple of lines.

## Project Context

CVVFCM V4 — a French sailing club website. Stack: **FrankenPHP 1.11**, **PHP 8.5**, **Symfony 7.4**, **Sulu CMS 3.0**, **PostgreSQL 18**. Everything runs in Docker Compose. No build step for frontend CSS/JS (Symfony AssetMapper, HTTP/3).

## Commands

All commands must be run **inside the Docker container** or via `make` (which wraps `docker compose exec php`).

```bash
make run                     # First-time setup + start containers
make up                      # Start containers (subsequent runs)
make down                    # Stop containers
make cli                     # Open bash shell in PHP container
make logs                    # Tail PHP container logs (make logs c=php)
make ps                      # List running containers
make cc                      # Clear Symfony cache (both website + admin kernels)
make cs                      # Fix code style (php-cs-fixer + twig-cs-fixer)
make stylelint               # Lint website CSS (pass fix=1 to auto-fix)
make psalm                   # Run static analysis
make psalm_strict            # Run static analysis with info-level issues
make hadolint                # Lint the Dockerfiles
make ml_cs                   # Fix code style in the Python ML code (ruff)
make test                    # Run PHPUnit test suite
make test-ai                 # Forgie judge tests — paid API, only when the prompt changed
make test-screenshots        # Visual regression suite
make test-screenshots-update # Re-record the visual baselines
make reset                   # Wipe DB, re-create schema, load fixtures + import weather data
make reset-test              # Same as reset but for test environment
```

**Single test:** `docker compose exec php ./vendor/bin/phpunit --filter TestName`

**Screenshots** drive the dev server at `https://localhost`, so `make up` must be running and the
fixtures loaded (`make reset`). Baselines live in `tests/visual/__screenshots__/`.

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
- **Read the output of what you run.** A command whose result you piped to `/dev/null` has not been
  verified, and "the tests pass" is a claim you own.

## Symfony Superpowers (plugin)

The `superpowers-symfony` plugin is enabled. For Symfony / Doctrine / Twig / Messenger / testing work,
prefer its agents and skills over free-handing. Entry point: the `symfony:using-symfony-superpowers`
skill, which lists everything available — no need to mirror that catalogue here.

Honour this repo's stack over whatever a generic skill assumes:
- **Tests = PHPUnit** (`make test`), not Pest → use the PHPUnit variants (`symfony:tdd-with-phpunit`, `symfony-tdd-coach` in PHPUnit mode).
- **Static analysis = Psalm** (`make psalm`), **not PHPStan**. Style via `make cs` (php-cs-fixer `@Symfony`). Do **not** substitute PHPStan when a skill mentions it.
- **CMS = Sulu, no API Platform** → the `api-platform-*` agents/skills are **N/A** unless API Platform is added.
- Fixtures are Doctrine fixtures (`make reset`); Foundry is optional.
- Async: `symfony/scheduler` + `symfony/messenger` (sync transport).
- Planning is handled by the built-in `/plan` flow; the plugin's `writing-plans` / `executing-plans` are complementary, not a replacement.
