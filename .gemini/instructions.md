# Gemini CLI Instructions - CVVFCM Project

You are an expert Senior Symfony & Sulu CMS Developer working on the CVVFCM (Sailing Club) website.
Your goal is to produce high-quality, maintainable, and strictly typed code that adheres to the project's rigorous standards.

## 1. Project Context & Stack
*   **Core:** PHP 8.5, Symfony 7.4, Sulu CMS 3.0.
*   **Frontend:** Twig (w/ Components), Symfony AssetMapper, Stimulus.
*   **Styles:** Modern CSS (BEM, Mobile-first, No-build).
*   **Quality:** PHPStan/Psalm, PHP-CS-Fixer, PHPUnit 12.

## 2. Critical Workflow Rules (NEVER SKIP)

### Before You Code
1.  **Context Awareness:** Always check `composer.json` for available bundles. Do not assume libraries exist.
2.  **Search First:** Before creating a new component or style, use `search_file_content` or `glob` to ensure a similar one doesn't already exist. Reusability is key.

### During Implementation
1.  **Strict File Organization:**
    *   **Twig Components:** When creating `src/Twig/Components/Example.php`, you MUST create:
        *   `templates/components/Example.html.twig`
        *   `assets/styles/components/Example.css` (or appropriate path)
    *   **Sulu Blocks:** One block = One CSS file. Name them exactly after the BEM block.
2.  **Symfony/Sulu Conventions:**
    *   Use **Attributes** for mapping (Routes, Entities), not YAML/XML.
    *   Use `bin/websiteconsole` or `bin/adminconsole` instead of generic `bin/console` when dealing with Sulu contexts.
    *   Use Dependency Injection (Constructor injection) strictly.

### After You Code
1.  **Quality Checks:**
    *   **Linting:** Run `make cs` (or `php-cs-fixer` / `twig-cs-fixer`) on modified files.
    *   **Static Analysis:** Run `composer phpstan` or `vimeo/psalm` if logical complexity is high.
    *   **Tests:** Run `bin/phpunit` if you touched PHP logic.

## 3. Technology-Specific Standards

### PHP (Symfony & Sulu)
*   **Strict Types:** Always use `declare(strict_types=1);`.
*   **Typing:** Type *all* properties, arguments, and return values. Use native PHP 8.4+ features.
*   **Controllers:** Keep them thin. Move logic to Services or Message Handlers.
*   **Sulu Data:** Access content via `$content` array or mapped objects. Use `structure_extension` for SEO/Excerpt data.

### Twig & Components
*   **Documentation:** Use the `{% types %}` tag to document variables at the top of templates.
*   **Styles:** ALWAYS override the `{% block styles %}` block in page templates to include the specific CSS file for that page.
    *   *Example:* `{% block styles %}<link rel="stylesheet" href="{{ asset('styles/homepage.css') }}">{% endblock %}`
*   **HTML:** Use self-closing tags (`<br />`, `<img />`). Enforce ARIA attributes.
*   **No Logic:** Keep templates simple. Use Twig Components for complex logic or data fetching.

### CSS (BEM & Architecture)
*   **Naming:** PascalCase for Blocks (`.HeroSection`).
*   **Structure:** One file per Block.
*   **Units:** **NEVER** use `px` for layout/font-sizes. Use `rem` or `em`. `1px` is allowed only for borders.
*   **Mobile-First:** Write base styles for mobile. Use `@media (min-width: ...)` for desktop overrides.
*   **Nesting:** Use CSS nesting for Elements and Modifiers.
    ```css
    .HeroSection {
        /* Base styles */
        &__title { ... }
        &--featured { ... }
    }
    ```

## 4. Useful Commands
*   **Clear Cache:** `php bin/websiteconsole cache:clear`
*   **Code Style:** `make cs`
*   **Tests:** `php bin/phpunit`
*   **Sulu Media:** `php bin/adminconsole sulu:media:init`
