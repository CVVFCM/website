---
name: scaffold-twig-component
description: Scaffold a Symfony UX Twig Component for this repo — generates the mandatory triad (PHP class + template + CSS). Use when the user asks to create a new Twig component, UX component, or AsTwigComponent.
---

# Scaffold a Twig Component

This repo has a hard rule: a Twig component is three files, always created together. Skipping any of
them is a defect.

## Steps

1. **Get the name.** Ask for a PascalCase component name (e.g. `EventCountdown`) if not given.
2. **Create the PHP class** `src/Twig/Components/<Name>.php`. Mirror the existing components (see
   `src/Twig/Components/WeatherToday.php`):
   ```php
   <?php

   declare(strict_types=1);

   namespace App\Twig\Components;

   use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

   #[AsTwigComponent]
   final readonly class <Name>
   {
       public function __construct(
           // constructor injection for any dependency (repositories, services)
       ) {
       }

       // public methods/props become `this.*` in the template
   }
   ```
   Note: `readonly class` is fine for a **component** (it has no Doctrine proxy). Do NOT use it on
   entities — see `src/AGENTS.md`.
3. **Create the template** `templates/components/<Name>.html.twig`. Root element carries the BEM block
   class and `{{ attributes }}`:
   ```twig
   <div class="<Name>" {{ attributes }}>
       {# markup — semantic, accessible, self-closing tags #}
   </div>
   ```
4. **Create the CSS** `assets/website/styles/components/<Name>.css` — BEM PascalCase, mobile-first,
   rem/em, colours from `variables.css` (see `assets/website/styles/AGENTS.md`):
   ```css
   .<Name> {
       /* block styles */
   }
   ```
5. **Wire the CSS** into whichever page(s) use the component (import in the page's entry CSS or add a
   `<link>` in that page's `{% block styles %}`).
6. **Quality gates**: run `make cs` and `make psalm`; if you touched CSS, `make stylelint`. Fix before
   finishing.

## Reference
- Example: `src/Twig/Components/WeatherToday.php` + `templates/components/WeatherToday.html.twig`.
- Rules: root `AGENTS.md`, `templates/AGENTS.md`, `assets/website/styles/AGENTS.md`.
