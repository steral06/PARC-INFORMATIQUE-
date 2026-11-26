<div align="center">

<img src="public/images/logo-gta.png" alt="GTAC2A-IARDT" width="200"/>

# G-PARC

**Gestionnaire de Parc Informatique**  
*GTAC2A-IARDT (Togo) — Génie et Technologies Appliquées*

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-7.x-000000?style=flat-square&logo=symfony&logoColor=white)
![FrankenPHP](https://img.shields.io/badge/FrankenPHP-Zero--Config-FC6A31?style=flat-square)
![SQLite](https://img.shields.io/badge/SQLite-003B57?style=flat-square&logo=sqlite&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/licence-Propriétaire-orange?style=flat-square)

</div>

---

## 📋 Présentation

**G-PARC** est une application web interne développée pour **GTAC2A-IARDT (Togo)** dans le cadre d'un projet de fin d'études en génie informatique. Elle permet de gérer l'intégralité du parc informatique de la structure : équipements, composants, attributions aux collaborateurs, tickets de maintenance, lieux et utilisateurs.

L'application intègre :
- un système de **sécurité renforcé** (2FA par code PIN sur clavier virtuel, restriction d'accès par adresse IP) ;
- un module de **rapports et d'exports Excel** avec sélection libre des colonnes et vue **Bilan Global 360°** ;
- un déploiement **zéro-configuration** grâce à **FrankenPHP** (aucun Apache, Nginx ou PHP-FPM à installer).

> [!NOTE]
> Ce projet est documenté dans le fichier [`CAHIER_DES_CHARGES_ACADEMIQUE.txt`](./CAHIER_DES_CHARGES_ACADEMIQUE.txt) qui constitue le cahier des charges complet du projet académique.

---

## ✨ Fonctionnalités

### 🖥️ Gestion des Équipements
- Inventaire complet : nom, marque, modèle, numéro de série, tag inventaire, prix d'achat (FCFA)
- Statuts : **En service** · **En stock** · **En panne** · **Prêté** · **Au rebut**
- Calcul automatique de la **vétusté** : < 3 ans → Récent · 3–5 ans → Vieillissant · ≥ 5 ans → Obsolète
- Suivi de la garantie et attribution par lieu/catégorie
- **Recherche multicritère** : nom, marque, numéro de série, catégorie, lieu, *et nom du bénéficiaire actuel* (via sous-requête EXISTS sur les attributions actives)

### 🔩 Composants & Pièces de rechange
- Gestion des composants (RAM, disques, cartes…) liés à un équipement ou en réserve
- Suivi quantitatif et état de chaque composant

### 📋 Attributions / Dotations
- Attribution d'équipements à des collaborateurs avec motif et date
- Statuts : **En cours** · **Restitué** · **Prolongé**
- Recherche par bénéficiaire, service, motif ou équipement
- Affichage du bénéficiaire actif directement sur la fiche équipement

### 🔧 Tickets de Maintenance
- Création et suivi des incidents : titre, description, niveau de priorité, technicien assigné
- Statuts : **Nouveau** · **En cours** · **Résolu** · **Fermé**
- Coût SAV en **FCFA**, durée d'intervention
- Recherche par titre, technicien, équipement ou numéro de série

### 📊 Tableau de Bord (KPIs)
- Valeur totale du parc en **FCFA**
- Taux de disponibilité opérationnelle
- Coûts totaux de maintenance
- Compteurs d'équipements récents / vieillissants / obsolètes
- Derniers équipements ajoutés, tickets ouverts, attributions actives

### 📈 Rapports & Exports
- **Sélecteur de colonnes** par domaine (Équipement, Dotation, Composants, Maintenance)
- **Bilan Global 360°** : tableau unifié combinant tous les domaines en une seule feuille
- Export **Excel (.xls** XML Spreadsheet) et **CSV** avec en-tête institutionnel GTAC2A-IARDT
- Interface en grille 2×2 avec cases à cocher groupées par domaine

### 🔐 Sécurité Multi-niveaux

| Fonctionnalité | Description |
|---|---|
| Authentification | Login / mot de passe avec Symfony SecurityBundle |
| **2FA Code PIN** | Validation d'un code PIN à 4 chiffres sur clavier virtuel (chiffres mélangés) à chaque connexion |
| Expiration MDP | Renouvellement obligatoire du mot de passe tous les **30 jours** |
| **Restriction IP** | Accès limité aux postes autorisés (liste blanche d'IPs, entité `PosteAutorise`) |
| Révocation | Déconnexion immédiate si le poste est révoqué par le Superadmin (ping asynchrone) |
| Rôles | `ROLE_USER` · `ROLE_ADMIN` · `ROLE_SUPERADMIN` |

### 🧭 Navigation SPA (Turbo Drive)
- Intégration **Hotwired Turbo** : la navigation dans les menus ne recharge **pas la page** (Single Page Application)
- Menus : pas de remplissage au survol, soulignement orange uniquement (hover et actif)

---

## 🏗️ Stack Technique

| Couche | Technologie |
|---|---|
| Langage | **PHP 8.2+** |
| Framework | **Symfony 7.x** |
| ORM | Doctrine ORM + Migrations |
| Base de données | **SQLite** (développement) · MySQL/PostgreSQL (production) |
| Templates | **Twig 3.x** |
| CSS / UI | **Bootstrap 5.3** + Bootstrap Icons 1.11.3 |
| Frontend JS | **Hotwired Turbo 8** + **Stimulus 3.2** (pas de Node.js, pas de npm) |
| Assets | **Symfony AssetMapper** — imports natifs ES Modules, zéro Webpack/Vite |
| Serveur HTTP | **FrankenPHP** (mode développement et production) |
| Sécurité | Symfony SecurityBundle, EventListeners, voters |
| Tests | PHPUnit |

---

## ⚡ FrankenPHP — Déploiement Zéro-Configuration

> [!IMPORTANT]
> **Pourquoi FrankenPHP ?**
>
> FrankenPHP est un serveur d'application PHP construit sur [Caddy](https://caddyserver.com/) qui **intègre PHP nativement** dans un seul binaire. Contrairement à une stack LAMP/LEMP, il ne nécessite **ni Apache, ni Nginx, ni PHP-FPM**.
>
> **Avantage principal pour ce projet :** pour transférer l'application sur n'importe quel PC de la DSI (poste serveur), il suffit de **copier le dossier du projet** et de lancer le binaire. Aucune installation de serveur web n'est requise — ce qui simplifie radicalement le déploiement dans un environnement institutionnel.

| Avantage | Détail |
|---|---|
| **Zéro dépendance serveur** | Pas d'Apache, Nginx ou PHP-FPM à configurer |
| **Worker Mode** | Le kernel Symfony reste en mémoire (~20 ms/req vs ~150 ms avec PHP-FPM) |
| **Hot Reload en dev** | Rechargement automatique sans redémarrer |
| **HTTP/2 & HTTP/3** | Activés par défaut, HTTPS automatique avec Caddy |
| **Portabilité** | Un dossier + un binaire = une application fonctionnelle |

### Lancer avec FrankenPHP

```bash
# Mode développement (avec Symfony CLI qui détecte FrankenPHP)
symfony serve -d

# Ou directement via FrankenPHP
frankenphp run --config Caddyfile
```

---

## 🚀 Installation

### Prérequis

- **PHP 8.2+** avec extensions : `ctype`, `iconv`, `pdo`, `pdo_sqlite`
- [Composer](https://getcomposer.org/)
- [Symfony CLI](https://symfony.com/download) *(recommandé)*
- **Aucun Node.js ni npm requis** — AssetMapper gère les assets nativement

### Étapes

```bash
# 1. Cloner ou copier le projet
git clone <url-du-repo> g-parc
cd g-parc

# 2. Installer les dépendances PHP
composer install

# 3. Configurer l'environnement
cp .env .env.local
# Éditer .env.local : DATABASE_URL, APP_SECRET

# 4. Créer la base de données et appliquer les migrations
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate

# 5. (Optionnel) Charger des données de démonstration
symfony console doctrine:fixtures:load

# 6. Lancer le serveur de développement
symfony serve -d
```

L'application est disponible sur **http://localhost:8000**

> [!TIP]
> En production ou sur un poste sans Symfony CLI, lancez simplement :
> ```bash
> APP_ENV=prod composer install --no-dev --optimize-autoloader
> php bin/console cache:warmup --env=prod
> frankenphp run
> ```

---

## 👤 Comptes & Sécurité initiale

> [!CAUTION]
> Changez impérativement les mots de passe après la première connexion. L'application force le renouvellement tous les 30 jours.

| Rôle | Description |
|---|---|
| `ROLE_SUPERADMIN` | Accès total, non soumis aux restrictions IP, gère les postes autorisés |
| `ROLE_ADMIN` | Accès aux modules de gestion, soumis au filtre IP et au 2FA |
| `ROLE_USER` | Accès en consultation, soumis au filtre IP et au 2FA |

### Ajouter des postes autorisés

Après connexion en Superadmin, aller dans **Administration → Postes Autorisés** (`/postes`) pour ajouter les adresses IP des machines qui peuvent accéder à l'application.

---

## 📁 Structure du Projet

```
g-parc/
├── assets/
│   ├── controllers/          # Contrôleurs Stimulus (sidebar, session-ping, 2FA…)
│   └── styles/app.css        # Thème orange #E47000 / noir #121212
├── config/
│   └── packages/             # Configuration des bundles Symfony
├── migrations/               # Migrations Doctrine
├── public/
│   └── images/               # Logo, icônes
├── src/
│   ├── Controller/           # EquipementController, RapportController…
│   ├── Entity/               # Equipement, Attribution, Composant, TicketMaintenance…
│   ├── EventListener/        # Sécurité : 2FA, expiration MDP, restriction IP
│   ├── Form/                 # Types de formulaires Symfony
│   ├── Repository/           # Repositories avec QueryBuilder (recherche, filtres)
│   └── Service/              # Génération des rapports Excel/CSV
├── templates/                # Templates Twig
│   ├── base.html.twig        # Layout principal, sidebar, 2FA guard
│   ├── equipement/
│   ├── attribution/
│   ├── maintenance/
│   ├── rapport/              # Sélecteur de colonnes, Bilan 360°
│   └── security/
├── CAHIER_DES_CHARGES_ACADEMIQUE.txt
├── Caddyfile                 # Configuration FrankenPHP
└── compose.yaml              # Docker Compose (optionnel)
```

---

## 🔑 Entités principales

| Entité | Rôle |
|---|---|
| `Equipement` | Inventaire des matériels informatiques |
| `CategorieEquipement` | Classification (Ordinateur, Imprimante, Switch…) |
| `Lieu` | Localisation physique (bureau, salle, étage…) |
| `Composant` | Pièces internes ou de rechange |
| `Attribution` | Lien équipement ↔ collaborateur (dotation/prêt) |
| `TicketMaintenance` | Incidents et interventions techniques |
| `User` | Comptes utilisateurs applicatifs |
| `PosteAutorise` | Liste blanche des adresses IP autorisées |

---

## 🛠️ Commandes Utiles

```bash
# Serveur de développement
symfony serve -d
symfony serve:stop

# Console Symfony
symfony console <commande>
# ou : php bin/console <commande>

# Migrations
symfony console make:migration
symfony console doctrine:migrations:migrate

# Debug
symfony console debug:router
symfony console debug:container
symfony console debug:autowiring
symfony console lint:container
symfony console about

# Cache
symfony console cache:clear
symfony console cache:warmup

# Tests
php bin/phpunit

# Logs (Linux/macOS)
tail -f var/log/dev.log
# Logs (Windows PowerShell)
Get-Content var/log/dev.log -Wait
```

---

## 🌐 Variables d'Environnement

| Variable | Description | Défaut |
|---|---|---|
| `APP_ENV` | Environnement (`dev`, `prod`) | `dev` |
| `APP_SECRET` | Clé secrète Symfony | *(à définir)* |
| `DATABASE_URL` | URL de connexion BDD | SQLite local |
| `MAILER_DSN` | Serveur mail | `null://null` |

> [!NOTE]
> Ne jamais committer `.env.local`. Les secrets de production doivent être gérés via le vault Symfony :
> ```bash
> php bin/console secrets:set APP_SECRET
> ```

---

## 🎨 Charte Graphique

| Élément | Valeur |
|---|---|
| Couleur principale | **`#E47000`** (orange GTAC2A) |
| Sidebar / Fond sombre | **`#121212`** / `#1E1E1E` |
| Texte | Blanc sur fond sombre, gris sur fond clair |
| Monnaie | **FCFA** (Franc CFA) |
| Langue | **Français** |

---

## 📚 Contexte Académique

Ce projet a été développé dans le cadre d'un **projet de fin d'études** pour la structure **GTAC2A-IARDT (Togo)**. Il constitue une solution de gestion de parc informatique répondant aux besoins opérationnels de la Direction des Systèmes d'Information (DSI).

Le cahier des charges complet — incluant les diagrammes UML (cas d'utilisation, séquence, classes, déploiement), l'analyse technique FrankenPHP vs LAMP/LEMP, et les spécifications fonctionnelles — est disponible dans :

📄 [`CAHIER_DES_CHARGES_ACADEMIQUE.txt`](./CAHIER_DES_CHARGES_ACADEMIQUE.txt)

---

## 📄 Licence

**Propriétaire** — Usage interne GTAC2A-IARDT uniquement.  
Tous droits réservés © 2026 — Développé dans le cadre d'un projet académique.
"# PARC-INFORMATIQUE-" 
