---
name: scaffold-sulu-page
description: Scaffold a new Sulu CMS page type for this repo — the XML template definition, the Twig view, and its per-page CSS entry file. Use when the user asks to add a new page type, Sulu template, or content type.
---

# Scaffold a Sulu Page Type

A Sulu page type is three coordinated files. Model them on the existing `event` page.

## Steps

1. **Get the key.** Ask for a lowercase page key (e.g. `program`) and its French title if not given.
   `<key>`, the `<view>` path, and the Twig/CSS filenames must all match.
2. **XML template** `config/templates/pages/<key>.xml` — model on `config/templates/pages/event.xml`
   (or `default.xml` for a simpler start). Required shape:
   ```xml
   <?xml version="1.0" ?>
   <template xmlns="http://schemas.sulu.io/template/template"
             xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
             xmlns:xi="http://www.w3.org/2001/XInclude"
             xsi:schemaLocation="http://schemas.sulu.io/template/template http://schemas.sulu.io/template/template-1.0.xsd">
       <key><key></key>
       <view>pages/<key></view>
       <controller>Sulu\Content\UserInterface\Controller\Website\ContentController::indexAction</controller>
       <cacheLifetime>3600</cacheLifetime>
       <meta><title lang="fr">…</title></meta>
       <properties>
           <!-- title (headline + sulu.rlp.part), url (route), then page-specific properties -->
       </properties>
   </template>
   ```
   Keep a `title` (`sulu.rlp.part` tag) and a `url` (`type="route"`) property — Sulu needs them.
3. **Twig view** `templates/pages/<key>.html.twig`:
   ```twig
   {% extends 'base.html.twig' %}
   {% import 'partials/_picture.html.twig' as picture %}

   {% block styles %}<link rel="stylesheet" href="{{ asset('styles/<key>.css') }}" />{% endblock %}

   {% block structured_data %}{{ parent() }}{# add JSON-LD if relevant #}{% endblock %}

   {% block content %}
       {# use content.*, extension.*; semantic + accessible markup; picture.render for images #}
   {% endblock %}
   ```
4. **Per-page CSS** `assets/website/styles/<key>.css` — imports only the blocks this page needs
   (see `assets/website/styles/AGENTS.md`).
5. **Register / clear cache**: run `make cc` (clears both kernels) so Sulu picks up the new template;
   the page type then appears in the admin.
6. **Quality gates**: `make cs`, `make psalm`, `make stylelint`. Verify at `https://localhost`.

## Reference
- `config/templates/pages/event.xml`, `templates/pages/event.html.twig`.
- Sulu content vars + Twig rules: `templates/AGENTS.md`.
