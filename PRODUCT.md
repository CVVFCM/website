# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Trois audiences prioritaires, dans cet ordre d'usage :

1. **Grand public / prospects** — familles, touristes et curieux (Ardennes, lac des Vieilles Forges) qui découvrent le club et veulent réserver un stage, louer un bateau ou venir une première fois.
2. **Membres actuels** — adhérent·es qui consultent le calendrier, les régates, la météo, les infos pratiques et les documents officiels du club.
3. **Régatiers / compétiteurs** — coureurs (ex. National Maraudeur) cherchant avis de course, résultats et inscriptions.

## Product Purpose

Site officiel du **Club de Voile des Vieilles Forges de Charleville-Mézières (CVVFCM)**. Il présente le club, ses activités (stages, locations, régates, sorties en mer) et sert d'outil quotidien : météo locale, webcam du plan d'eau, calendrier des événements, contact.

**Succès n°1 : générer des demandes de réservation de stages et de locations** (via le formulaire de contact et l'assistant Forgie). Les autres résultats (adhésions, information, rayonnement) sont secondaires.

## Positioning

Un site de club de voile associatif qui embarque des outils qu'aucun club voisin n'offre : **Forgie**, assistant IA conversationnel connaissant le club (événements, tarifs, règlements, bureau) et capable de transmettre une demande de contact ; **prévision météo maison** (modèle ML entraîné sur les relevés locaux du lac) ; **webcam en direct** du plan d'eau. Le site n'est pas une simple vitrine : c'est l'interface pratique du lac.

## Operating Context

- Lieu unique : lac des Vieilles Forges (Ardennes), près de Charleville-Mézières. Activité saisonnière (stages/locations surtout à la belle saison).
- Consultation fréquente en mobilité (au bord du lac, avant de venir) : météo, webcam, horaires.
- Le club est une association loi 1901 : statuts, règlement intérieur et règlement préfectoral de navigation du lac (arrêté du 08/04/1976) sont des documents de référence servis par le site (snippets « Document officiel », outil `club_rules` de Forgie).
- Contenu géré par des bénévoles via le back-office Sulu ; les pages, formulaires et snippets doivent rester éditables sans développeur.
- Contact par email (contact@cvvfcm.fr) ; notifications admins via Google Chat.

## Capabilities and Constraints

- **Capacités** : pages Sulu (homepage, default, event, category, list, live), recherche plein texte, cartes Leaflet, formulaire de contact dynamique (Sulu Form), Forgie (Mistral, outils : météo, événements, régates, sorties, pages, membres du bureau, documents officiels, contact), météo ML (ONNX), webcam RTSP, API Platform, temps réel Mercure.
- **Langues** : site en français ; Forgie répond en français et en anglais uniquement.
- **Contraintes techniques** : Sulu 3 / Symfony 7.4 / PHP 8.5 / PostgreSQL 18 / FrankenPHP, Docker Compose ; CSS en BEM PascalCase, un bloc par fichier, mobile-first, couleurs uniquement via `assets/website/styles/variables.css`, pas de `px` sauf bordures `1px`.
- **Terminologie** : « stages », « locations », « régates », « sorties en mer », « comité de direction » / « bureau », « Forgie ».

## Brand Commitments

- **Logo et couleurs actuels figés** : identité visuelle existante à préserver (`assets/website/images/logo_cvvfcm.svg`, `logo_forgie.svg`, palette de `variables.css`). Ne pas la remplacer.
- **Écriture inclusive** : engagement de marque pour tout le site (déjà appliqué dans le prompt de Forgie), quand c'est adapté.
- Ton chaleureux mais sobre (cf. prompt Forgie) : aller à l'essentiel, pas de formules creuses.

## Evidence on Hand

- Contenu réel géré en fixtures : événements, régates, sorties en mer, infos club, documents officiels (statuts AG 24/01/2026, règlement intérieur, arrêté préfectoral 1976), contacts du bureau (tag « Forgie »).
- Assets : `assets/website/images/` (logo CVVFCM, logo Forgie, carte du lac, pictos).
- Préprod : https://preprod.cvvfcm.fr — référence de contenu réel.
- Pas de témoignages ni de chiffres marketing : ne pas en inventer.

## Product Principles

1. **La réservation d'abord** — chaque surface grand public doit rapprocher le visiteur d'une demande de stage ou de location (contact ou Forgie).
2. **Utile au bord de l'eau** — météo, webcam et calendrier doivent être immédiats, surtout sur mobile.
3. **Vrai, jamais inventé** — le site et Forgie ne servent que des faits du club ; en cas de doute, renvoyer vers le contact.
4. **Éditable par des bénévoles** — tout contenu passe par le back-office Sulu ; pas de contenu en dur qu'un bénévole ne peut pas modifier.
5. **Association avant produit** — le ton reste celui d'un club associatif : accueillant, inclusif, sans jargon commercial.

## Accessibility & Inclusion

- **Conformité RGAA/WCAG visée** (public associatif et institutionnel, tous âges).
- Écriture inclusive dans les contenus et l'assistant.
- Mobile-first obligatoire (usage principal en mobilité).
