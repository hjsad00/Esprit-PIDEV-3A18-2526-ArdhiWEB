# 📘 Guide de Validation Technique - Projet ARDHI

Ce document contient tous les détails techniques, les choix d'architecture et les réponses aux questions probables pour votre soutenance de projet. Il se concentre sur le module **Matériel & Maintenance** et l'infrastructure globale.

---

## 🏗️ 1. Architecture Logicielle
*   **Framework** : Symfony 6.4 (Full-stack) - Choisi pour sa robustesse, son système d'injection de dépendances et sa sécurité intégrée.
*   **Architecture** : MVC (Model-View-Controller) avec une **couche Service** (`GoogleCalendarService`) pour isoler la logique métier complexe.
*   **Base de Données** : MySQL avec Doctrine ORM (Object-Relational Mapping). Utilisation de migrations pour assurer la cohérence du schéma entre les environnements de développement.

---

## ⚙️ 2. Fichiers de Configuration Critiques

### 📄 Le fichier `.env` (Variables d'environnement)
C'est le "cœur" de la configuration de l'infrastructure.
*   **DATABASE_URL** : Définit la connexion au serveur MySQL local (Port 3306).
*   **GOOGLE_CLIENT_ID / SECRET** : Identifiants OAuth indispensables pour l'accès à l'API Google.
*   **MESSENGER_TRANSPORT_DSN** : Gère la file d'attente des messages (Doctrine).

> [!IMPORTANT]
> Ne jamais mettre de mots de passe réels dans le fichier `.env` en production; utilisez des *Secrets* Symfony.

### 📄 `config/packages/messenger.yaml`
Gère l'exécution des tâches en arrière-plan.
*   **Transports** : Nous avons configuré un transport `sync://` pour s'assurer que les événements Google Calendar soient créés immédiatement lors de la validation du formulaire de maintenance.
*   **Avantage** : Cela garantit que l'utilisateur reçoit un retour immédiat si l'appel API échoue.

---

## 📦 3. Gestion des Dépendances (Composer)
Si le jury vous interroge sur le fichier `composer.json` :

*   **Le Rôle** : C'est le gestionnaire de paquets de PHP. Il liste toutes les bibliothèques externes (les "dépendances") utilisées par Ardhi.
*   **`require`** : Liste les paquets nécessaires en production (Symfony Framework, Google API Client, Twig).
*   **`require-dev`** : Liste les outils utilisés uniquement par le développeur (MakerBundle pour générer du code, WebProfiler pour le débuggage).
*   **`composer.lock`** : (Point crucial) Ce fichier fige les versions exactes des dépendances pour garantir que l'application fonctionne de la même manière sur tous les serveurs.
*   **Autoload** : Configure la norme PSR-4, permettant à PHP de charger automatiquement vos classes sans faire de `require` manuel.

---

## 🏗️ 4. Évolution du Schéma (Migrations)
Le dossier `migrations/` contient l'historique de la structure de votre base de données.

*   **Le Concept** : C'est un système de versionnage pour la base de données. On ne modifie jamais les tables à la main dans phpMyAdmin.
*   **Les Versions (Timestamps)** : Le nom `VersionYYYYMMDDHHMMSS` correspond à la date et l'heure précises de création (Année, Mois, Jour, Heure, Minute, Seconde).
*   **`up()` vs `down()`** :
    *   `up()` : Applique les changements (ex: créer la table `maintenance`).
    *   `down()` : Annule les changements en cas de problème.
*   **Avantage** : Permet de reconstruire la base de données de zéro sur n'importe quel ordinateur en une seule commande (`migrate`).

---

## 💾 5. Lexique de la Persistence (Doctrine ORM)
C'est la partie "Base de Données". Voici ce qu'il faut expliquer au jury :

*   **L'EntityManager** : C'est le service principal qui gère le cycle de vie de vos objets (Entités).
*   **`$em->persist($entity)`** : "Prépare" l'objet. On dit à Doctrine que cet objet doit être sauvegardé. (Aucune requête SQL n'est faite à ce stade).
*   **`$em->flush()`** : "Exécute". C'est la commande qui synchronise tout ce qui a été préparé avec la base de données. C'est là que l'INSERT ou l'UPDATE se produit.
*   **`$em->remove($entity)`** : Prépare la suppression d'un objet. Il faudra aussi un `flush()` pour que la suppression soit réelle.
*   **`getRepository()`** : Utilisé pour "Rechercher" des données (SELECT). Exemple : `findBy()`, `findAll()`, `findOneBy()`.

---

## 📧 4. Système d'Emailing (Symfony Mailer)
L'application peut envoyer des notifications automatiques (Reset password, Attestations, Événements).

*   **Configuration** : Tout passe par la variable `MAILER_DSN` dans le fichier `.env`. Nous utilisons le pont Gmail (`google-mailer`) pour l'envoi.
*   **MailerInterface** : C'est le service natif de Symfony que l'on "injecte" dans les contrôleurs ou les services pour envoyer un mail.
*   **Services Dédiés** : Pour respecter le principe de responsabilité unique, nous avons créé des classes spécifiques comme `EvenementParticipationMailer`. Cela permet de séparer la logique d'envoi de la logique du contrôleur.

---

## 🛠️ 5. Stratégie de Validation "Zéro JS"
L'une des décisions techniques majeures a été d'utiliser une **validation 100% côté serveur (PHP)**.

*   **Pourquoi ?** : La validation JavaScript est facilement contournable (désactivation du JS dans le navigateur, injection via la console). La validation PHP (via `Constraints` dans l'Entité) est la seule garantie de **l'intégrité des données** en base.
*   **Mise en œuvre** : Utilisation d'attributs PHP (`#[Assert\NotBlank]`, `#[Assert\GreaterThan]`).
*   **UX** : Les erreurs sont renvoyées via le moteur Twig avec des messages personnalisés et un affichage "Premium" (classes `is-invalid` et messages d'alerte filtrés).

---

## 🔍 6. Où est le "Contrôle de Saisie" ? (Code à montrer)

Si le jury vous demande de montrer le code de validation, voici les deux fichiers clés :

### A. Les Règles (Entité)
Dans `src/Entity/MaterielEtMaintenance/Maintenance.php` :
```php
#[Assert\NotBlank(message: "La date est obligatoire")]
private ?\DateTimeInterface $dateMaintenance = null;

#[Assert\NotBlank(message: "Le type ne peut pas être vide")]
private ?string $typeMaintenance = null;
```

### B. Le Moteur (Contrôleur)
Dans `src/Controller/MaterielEtMaintenance/MaintenanceController.php` :
```php
$errors = $validator->validate($maintenance);
if (count($errors) > 0) {
    // Si des erreurs existent, on bloque l'enregistrement 
    // et on réaffiche le formulaire avec les messages.
}
```

---

## 🚜 7. Focus : Module Matériel & Maintenance
*   **Gestion des Matériels** : Chaque matériel est lié à un utilisateur (`user_id`). Un système de sécurité via le contrôleur vérifie que seul le propriétaire peut modifier ou supprimer ses machines.
*   **Synchronisation Google Calendar** : 
    *   **Flux OAuth** : L'utilisateur autorise l'application à accéder à son calendrier via un jeton (AccessToken).
    *   **Automatisation** : Lors de la planification d'une maintenance, un événement est automatiquement créé avec la date, le type d'entretien et le matériel concerné.

---

## ❓ 5. Questions Probables du Jury (Q&A)

### Q : Pourquoi avoir choisi Symfony plutôt qu'un PHP natif ?
**R** : Symfony offre une structure standardisée, une sécurité native contre les failles (XSS, CSRF, Injection SQL) et des composants réutilisables (Form, Validator, Security) qui permettent un développement rapide et maintenable.

### Q : Comment gérez-vous la sécurité des données privées ?
**R** : Nous utilisons le système de sécurité de Symfony (RBAC). Chaque Entité vérifie l'appartenance à l'utilisateur (`$this->getUser()`) avant toute action sensible.

### Q : Pourquoi votre Header est-il en CSS Vanilla plutôt qu'avec un Framework comme Tailwind ?
**R** : Le CSS natif permet un contrôle total sur les performances et le design "Premium" (Glassmorphism, animations au scroll complexes) sans dépendance lourde, garantissant une meilleure pérennité du code.

### Q : Que se passe-t-1 si l'API Google est indisponible ?
**R** : L'application gère les exceptions via des blocs `try/catch`. En cas d'erreur API, la maintenance est enregistrée en base de données Ardhi, et un message flash informe l'utilisateur que la synchronisation calendrier a échoué (mode dégradé).

---

## 📂 6. Structure des fichiers à montrer (si demandée) :
*   `src/Entity/MaterielEtMaintenance/` : La logique métier.
*   `src/Controller/MaterielEtMaintenance/` : La gestion des routes et de la sécurité.
*   `templates/base.html.twig` : La structure globale et le design du Header.
*   `public/assets/css/materiel/` : Le design system personnalisé.

---

> [!TIP]
> Lors de la présentation, montrez d'abord la fluidité du **Header transparent**, puis le **Formulaire de Maintenance** en expliquant que chaque champ est validé directement par le serveur pour une sécurité maximale.
