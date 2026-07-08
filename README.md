# CVVFCM V4

Site web du **Club de Voile des Vieilles Forges de Charleville-Mézières**, bâti sur le CMS Sulu. En plus des
pages classiques, il embarque un assistant IA (Forgie), la prévision météo locale, une webcam
et les infos pratiques du club (stages, locations, régates, sorties en mer).

## Fonctionnalités

 * **Forgie** — assistant IA conversationnel (Mistral) qui répond aux visiteurs et sait
   consulter la météo, les événements, les régates et les sorties, parcourir les pages du site,
   et envoyer un message de contact aux responsables du club.
 * **Prévision météo** issue d'un modèle maison (machine learning) alimenté par les relevés locaux.
 * **Webcam** du plan d'eau (flux RTSP diffusé dans le navigateur).
 * **Recherche** sur le contenu du site.
 * **Cartes** interactives (Leaflet).
 * **Contact & formulaires** avec envoi d'emails, et notifications Google Chat côté admins.
 * **Espace d'administration** Sulu, protégé par double authentification (2FA).
 * **API** (API Platform).

## Stack technique

 * FrankenPHP 1.11 / **PHP 8.5**
 * **PostgreSQL 18**
 * Sulu 3 / Symfony 7.4
 * Symfony AI + Mistral (Forgie)
 * Prévision météo : Python / PyTorch, exporté en ONNX (dossier `ml/`)
 * Temps réel : Mercure ; traitement asynchrone : Messenger (service `consumer`)
 * Recherche : CMS-IG Seal (adaptateur Loupe)
 * Emails : Brevo (prod) / Mailpit (local) ; notifications : Google Chat

Le tout tourne en Docker Compose : `php`, `consumer` (worker), `database` (Postgres),
`ml` (météo) et `rtsp-to-web` (webcam).

## Configuration

La config par défaut est dans `.env`. Les secrets et surcharges locales vont dans `.env.local`
(non versionné) ; en production, via la configmap Helm. Variables principales :

 * `DATABASE_URL` — base PostgreSQL
 * `MAILER_DSN` — envoi d'emails (Brevo en prod, Mailpit en local)
 * `SULU_ADMIN_EMAIL` — destinataire des messages de contact
 * `MISTRAL_API_KEY` — clé de l'assistant Forgie
 * `GOOGLE_CHAT_DSN` — notifications Google Chat des admins
 * `MERCURE_URL` / `MERCURE_JWT_SECRET` — temps réel
 * `SEAL_DSN` — moteur de recherche
 * `MESSENGER_TRANSPORT_DSN` — file des messages asynchrones
 * `UX_MAP_DSN` — cartes

## Nécessaire sur le poste

 * Environnement Linux (testé sur Debian Trixie)
 * mkcert (https://github.com/FiloSottile/mkcert)
 * docker compose

## Installation

```bash

 $ make run

```
La dernière étape est très longue (build des JS de l'admin) mais plus besoin de la refaire à chaque démarrage.

## Éteindre les conteneurs

```bash

 $ make down

```

## Désinstallation / Nettoyage

    ATTENTION : Cette commande supprime toutes les données de la base de données
    Et le prochain démarrage sera comme une première installation (long).

```bash

 $ make clean

```

## Commandes utiles

```bash

 $ make test    # Lance la suite de tests (PHPUnit)
 $ make cs      # Corrige le style de code (PHP + Twig)
 $ make psalm   # Analyse statique
 $ make reset   # Réinitialise la base + les données de démo
 $ make cli     # Ouvre un terminal dans le container PHP

```

Météo (machine learning) :

```bash

 $ make rebuild_model         # Ré-entraîne le modèle de prévision
 $ make view_model_accuracy   # Affiche la précision du modèle

```

## Debug / Logs

```bash

 $ make ps # Affiche les containers
 $ make logs # Affiche les logs PHP
 $ make logs c=php # Affiche les logs PHP
 $ make cli # Ouvre un terminal dans le container PHP

```
