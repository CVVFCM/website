---
name: verify-in-browser
description: Verify frontend work by loading the running app at https://localhost — check rendering, responsive behaviour, and the console. Use after changing pages/templates/CSS, before declaring frontend work done. Requires a browser MCP (chrome-devtools or playwright).
---

# Verify in Browser

The app runs at **`https://localhost`** (FrankenPHP, self-signed cert). Confirm frontend changes
actually render before calling them done.

## Prerequisite: a browser MCP

This skill drives a browser MCP server (`chrome-devtools` or `playwright`). If none is connected:
- **Do not fake a pass.** Tell the user it's not connected, describe exactly what to check manually
  (which URL, what to look for), and ask them to confirm — or offer to add the MCP.

Check availability first (e.g. via ToolSearch for `navigate` / `screenshot` / `snapshot` tools).

## Steps (when a browser MCP is available)

1. **Confirm the app is up**: `make ps` (or `curl -k -sI https://localhost` for a quick 200 check).
2. **Navigate** to the changed page (e.g. `https://localhost/`, `https://localhost/events`).
3. **Snapshot** the DOM/accessibility tree — verify content and structure match the requirement.
4. **Screenshot** desktop, then a mobile viewport (~375px wide) — the site is mobile-first, so both
   must look right.
5. **Read the console** — no new errors/warnings tied to your change.
6. **Report** with the screenshot(s). If the result doesn't match the requirement, iterate (max ~3
   times per the root `AGENTS.md` working loop), then report blockers.

## Notes
- Accept the self-signed cert (`-k` for curl; ignore-HTTPS-errors for the browser MCP).
- Don't screenshot-diff pixel-for-pixel — judge rendering, layout, responsiveness, console health.
