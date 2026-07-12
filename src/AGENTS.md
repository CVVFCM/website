# PHP / Symfony / Doctrine rules (`src/`)

Applies to PHP under this directory. See root `AGENTS.md` for project-wide rules.

## Codestyle
- Always `declare(strict_types=1);`.
- Type every property, parameter, and return value.
- Use PHP 8.4+ native features (readonly, enums, first-class callable, `new` in initializers…).
- Constructor injection everywhere — no service locators, no `$container->get()`.
- Attributes for routes and Doctrine mapping — never YAML/XML mapping.
- Follow Symfony best practices and SOLID; keep controllers thin (logic in services).

## Doctrine entities — `readonly` caveat
**Do not mark an entity a `readonly class`.** opcache preload (production) compiles Doctrine's
generated proxy, which is a *non-readonly* class and cannot extend a `readonly` class — that fatals
at boot (CrashLoopBackOff). Use **per-property `readonly`** on a normal class instead: same
immutability guarantee, proxy compiles.
```php
#[Entity]
class WeatherForecastRecord      // NOT: readonly class
{
    #[Column(type: Types::FLOAT)]
    public readonly float $temperature;   // per-property readonly
}
```
See `src/Entity/LiveWeatherRecord.php` and `src/Entity/WeatherForecastRecord.php` for the pattern.

## DualKernel
Sulu runs two kernels (website + admin) from `src/DualKernel.php` / `src/Kernel.php`, sharing this
codebase with separate cache dirs. Use `bin/websiteconsole` / `bin/adminconsole` for kernel-specific
commands, and `make cc` (clears both). Preload requires whichever per-context caches exist
(`config/preload.php`).

## Quality gates (after EVERY edit — not batched)
- `make cs` — code style (php-cs-fixer `@Symfony` + twig-cs-fixer).
- `make psalm` — static analysis (Psalm, **not** PHPStan). Treat a red Psalm like a failing test.
- `make test` — PHPUnit suite must pass before a task is done.
