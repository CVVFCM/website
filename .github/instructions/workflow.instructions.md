# Development Workflow

This guide outlines the standard development loop for implementing features from GitHub issues.

## Development Loop Process

When working on a GitHub issue, follow this iterative workflow (maximum 3 iterations):

### 1. Read Development Instructions
 * **Use GitHub MCP**: `github-mcp-server-issue_read` to fetch issue details
 * Extract requirements, acceptance criteria, and any design references
 * If issue lacks clarity: **ASK the user** - prefer high confidence over assumptions
 * **Never implement design images** - if something is hard to do (e.g., complex visual design), leave it empty rather than build something subpar

### 2. Plan the Update
 * **Analyze codebase** to understand current implementation
 * **Find all relevant files**:
   * **Sulu XML templates**: `config/templates/pages/*.xml`, `config/templates/fragments/*.xml`, `config/templates/snippets/*.xml`
   * **Twig templates**: `templates/pages/*.html.twig`, `templates/fragments/*.html.twig`
   * **CSS files**: `assets/website/styles/*.css`, `assets/website/styles/pages/**/*.css`, `assets/website/styles/common/*.css`
   * **PHP code**: `src/**/*.php`
   * **Database related**: Check `migrations/`, `src/DataFixtures/` (if exists)
   * **Instruction files**: `.github/instructions/*.instructions.md` (read applicable rules before coding)
 * **Create or update plan** at `/home/yohan/.copilot/session-state/7f34985c-0d83-4ba1-a2cb-d29654dd2201/plan.md` for non-trivial changes
 * **Ask questions** if scope, behavior, or approach is unclear - don't guess

### 3. Write Code
 * **Read instruction files first**: Always check `.github/instructions/*.instructions.md` for file-specific rules
 * **Follow coding standards**:
   * PHP: PSR-1, PSR-2, PSR-4, PSR-12, Symfony best practices, SOLID principles
   * Twig: XML-valid HTML, BEM classes, semantic structure, accessibility (ARIA, alt texts)
   * CSS: BEM with PascalCase, mobile-first, rem/em units, one block per file
 * **Make minimal surgical changes** - only modify what's necessary
 * **Extract reusable components** - never duplicate HTML/CSS
 * **Create matching CSS files** when adding Twig blocks/components

### 4. Lint Code (If PHPStorm MCP Available)
 * **Use PHPStorm MCP tools**:
   * `phpstorm-get_file_problems` to check for errors/warnings in modified files
   * `phpstorm-build_project` to validate compilation (if applicable)
 * **Run make commands**:
   * `make cs` - Check code style (PSR standards)
 * **Only lint if tools are available** - skip this step if MCP unavailable

### 5. Fix Code If Needed
 * **Address all errors** from linting step
 * **Fix warnings** that are relevant to your changes
 * **Ignore unrelated issues** - not your responsibility unless they block your work
 * **Re-lint after fixes** to confirm resolution

### 6. Check Result in Browser
 * **Base URL**: `https://localhost`
 * **Use Chrome DevTools MCP** (if available):
   * `chrome-devtools-navigate_page` to load the page
   * `chrome-devtools-take_snapshot` to verify content/structure
   * `chrome-devtools-take_screenshot` to capture visual result
   * Check console for errors with `chrome-devtools-list_console_messages`
 * **Verify**:
   * Visual rendering matches requirements (if unclear, show user and ask)
   * Functionality works as expected
   * No console errors related to your changes
   * Responsive behavior (mobile-first)

### 7. Iterate If Needed
 * **If result doesn't match requirements**: Go back to step 2
 * **Maximum 3 iterations** - if still not working, report blockers to user
 * **After 3 attempts**: Explain what's not working and ask for guidance

## Key Principles

### High Confidence, Not Opinions
 * **Don't know?** → **ASK the user**
 * Don't guess at requirements, behavior, or scope
 * Prefer clarifying questions over assumptions

### Design Complexity
 * **Hard to implement** (complex design images, intricate layouts) → **Leave empty**
 * **Prefer simple and empty** over complex and broken
 * Focus on structure, semantics, and accessibility first

### MCP Tool Usage
 * **Use MCP tools only when needed** - don't force them into workflow
 * **Tool availability varies** - gracefully skip steps if tools unavailable
 * **Available MCP servers**:
   * `github-mcp-server` - Issue management, PR review, code search
   * `phpstorm` - Linting, file analysis, refactoring, Symfony/Doctrine tools
   * `chrome-devtools` - Browser automation, testing, screenshots

### Code Quality
 * **Read instruction files** before writing any code
 * **Follow established patterns** in the codebase
 * **Test your changes** before considering task complete
 * **Minimal changes** - surgical edits, not rewrites

## Example Workflow

```
1. Fetch issue #42 with github-mcp-server-issue_read
2. Issue unclear about pagination limit → ASK user: "Should pagination show 10 or 20 items per page?"
3. User responds: "20 items"
4. Plan: Update EventController, modify event list template, add pagination CSS
5. Read .github/instructions/php.instructions.md and twig.instructions.md
6. Write code: EventController.php, event-list.html.twig, EventList.css
7. Lint: make cs (passes)
8. Check browser: Navigate to https://localhost/events, verify 20 items display
9. Screenshot shows correct pagination → Done
```

## Notes
 * This workflow is a guide, not a strict requirement
 * Adapt based on task complexity and available tools
 * When in doubt, ask the user for clarification
 * Document any new patterns or decisions in instruction files if they'll be reused
