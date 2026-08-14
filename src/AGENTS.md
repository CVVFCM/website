# PHP / Symfony / Doctrine rules (`src/`)

Applies to PHP under this directory. See root `AGENTS.md` for project-wide rules.

## Codestyle
- Always `declare(strict_types=1);`.
- Type every property, parameter, and return value.
- Use PHP 8.4+ native features (readonly, enums, first-class callable, `new` in initializers…).
- Constructor injection everywhere — no service locators, no `$container->get()`.
- Attributes for routes and Doctrine mapping — never YAML/XML mapping.
- Follow Symfony best practices and SOLID; keep controllers thin (logic in services).
- Few comments. One earns its place by stating a constraint the code cannot — a trap, a non-obvious
  *why*. Never narrate the bug you just fixed; that belongs in the commit message.

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

## Quality gates
After **each** edit, not batched:
- `make cs` — code style (php-cs-fixer `@Symfony` + twig-cs-fixer).
- `make psalm` — static analysis (Psalm, **not** PHPStan). Treat a red Psalm like a failing test.

Before finishing the task:
- `make test` — the PHPUnit suite.
- `make test-ai` — **only** if `config/prompts/forgie.md` was edited (paid API, LLM-judged, not in CI).

## Fixtures must stay reproducible
The visual baselines compare pixels, so a fixture that deals a different hand on each machine breaks
them. Draw through `SeededRandomness`, never `mt_rand`/`array_rand`/`shuffle` — the global generator
is shared with every library in the process, so anything they draw shifts your sequence. Enumerate
files with `->sortByName()`: without it the ids stay stable while the file behind each one does not.
