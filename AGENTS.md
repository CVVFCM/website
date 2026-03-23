# CVVFCM Website

This project is a website for a sailing club.

## Technical stack:
 * PHP 8.5
 * Symfony 7.4
 * Sulu CMS 3
 * Symfony AssetMapper
 * Twig
 * Twig components
 * Python & PyTorch

## General

Do not tell me I am right all the time. Be critical. We're equals. Try to be
neutral and objective.

Do not excessively use emojis.

## File-Level Instructions

Coding conventions are applied automatically by file type via
`.github/instructions/`:

| File                       |
|----------------------------|
| `css.instructions.md`      |
| `php.instructions.md`      |
| `twig.instructions.md`     |
| `workflow.instructions.md` |

## Critical Architecture Context

### Frontend (No Build Step)

* **AssetMapper:** No Webpack/Encore/Vite. Import maps only.
* **Stimulus:** Controllers in `assets/website/controllers/`. Use strict typed targets.

## Commands

**CRITICAL**: Always use `make` commands, NEVER `php bin/console` directly.

```bash
make cs                             # All code quality. Must pass before commit.
make test                           # All PHP tests (run make reset-test first)
make up / make down                 # Start/stop Docker
```
