# CVVFCM V4

Architecture projet :
 * FrankenPHP 1.9
 * PHP 8.4
 * PostgreSQL 18
 * Sulu 3

Hébergement :
 * Mutu OVH

## Nécessaire sur le poste

 * Environnement linux (testé sur Ubuntu 22.04)
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

## Debug / Logs

```bash

 $ make ps # Affiche les containers
 $ make logs # Affiche les logs PHP
 $ make logs c=apache # Affiche les logs Apache
 $ make cli # Ouvre un terminal dans le container PHP

```
