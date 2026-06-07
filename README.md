# ArdhiWEB

## Description
ArdhiWEB est une plateforme web développée avec Symfony 6.4. Elle propose diverses fonctionnalités liées à la gestion de l'agriculture, des parcelles, la marketplace, et l'intégration de services IA et de communication.

## Technologies utilisées
- Frontend : HTML, CSS, JS, Twig
- Backend : PHP 8.1+, Symfony 6.4
- Base de données : MySQL 8

## Prérequis
- PHP 8.1+
- Composer
- Serveur MySQL 8
- wkhtmltopdf (optionnel, pour les PDF)
- **Ollama** (requis pour le modèle IA local de la Marketplace)

## Installation
```bash
# 1. Installation des dépendances et base de données
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console assets:install
php bin/console importmap:install

# 2. Installation du modèle IA local (Marketplace)
# Assurez-vous qu'Ollama est installé et lancé, puis exécutez la commande suivante.
# Ceci téléchargera automatiquement le modèle "mistral" sans saturer le dépôt Git.
ollama run mistral
```
*(Note : Si vous préférez, un script SQL complet `ardhi-5.sql` est fourni à la racine du projet pour importer directement la base de données avec un jeu d'essai).*

## Lancement
```bash
symfony server:start
```

## Variables d'environnement
Voir `.env.example`

## Démo
- Vidéo : https://www.youtube.com/watch?v=EUZm2hC9VuE

## Auteurs, Classe, Tuteur
- Auteurs : Souibgui Saifeddine, Affi Rim, Ben Attia Yasmine, Rahmouni Yasmine, Delhoumi Elyes, Haj Salem Adel 
- Classe : 3A18
- Tuteur : El Hakim Imen, Gaudria Khaled

