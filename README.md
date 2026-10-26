# 🎓 ULT Payment System

[![PHP Version](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-orange.svg)](https://mariadb.org/)
[![Composer](https://img.shields.io/badge/Composer-Dependencies-brown.svg)](https://getcomposer.org/)
[![Status](https://img.shields.io/badge/Status-Academic%20Project-success.svg)](#)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

> Application web de gestion des paiements universitaires pour l'Université du Lac Tanganyika (ULT) ASBL.

## 📖 Description

**ULT Payment System** est une application web développée dans le cadre d'un cours de Programmation Web. Elle centralise la gestion des paiements scolaires, des étudiants, des départements, des tranches, des pénalités et des notifications.

Le système propose deux espaces distincts :

- **Administrateur** : gestion complète des étudiants, départements, paiements, pénalités, utilisateurs, alertes, thèmes et paramètres système.
- **Étudiant** : consultation du dossier, suivi des paiements, paiements partiels, pénalités et profil utilisateur.

🔗 Dépôt GitHub : [https://github.com/APDYd-dotcom/ult_payment.git](https://github.com/APDYd-dotcom/ult_payment.git)

## ✨ Fonctionnalités

- ✅ Authentification sécurisée avec rôles `admin` et `student`
- ✅ Inscription, connexion, déconnexion et réinitialisation du mot de passe
- ✅ Gestion des profils utilisateurs
- ✅ Authentification à deux facteurs (2FA) avec QR Code
- ✅ Verrouillage automatique des comptes après tentatives échouées
- ✅ CRUD complet des étudiants avec matricule
- ✅ CRUD des départements avec création automatique des 4 tranches
- ✅ Enregistrement et suivi des paiements
- ✅ Gestion des paiements partiels
- ✅ Calcul automatique des pénalités via procédures stockées et triggers
- ✅ Suivi des retards et de l'accès aux examens
- ✅ Tableau de bord administrateur avec statistiques et graphiques Chart.js
- ✅ Notifications internes et alertes système
- ✅ Envoi d'emails avec PHPMailer
- ✅ Journalisation des activités et historique de connexion
- ✅ Gestion dynamique des thèmes
- ✅ Paramétrage système dynamique via table `settings`
- ✅ Protection CSRF, sessions sécurisées et mots de passe hashés

## 🧰 Technologies utilisées

| Couche | Technologies |
|---|---|
| Backend | PHP 8.4, PDO |
| Base de données | MySQL / MariaDB, vues, procédures stockées, triggers |
| Frontend | HTML5, CSS3, JavaScript vanilla |
| Graphiques | Chart.js |
| Emails | PHPMailer |
| 2FA | robthree/twofactorauth, endroid/qr-code |
| Gestion des dépendances | Composer |
| Serveur | Apache / environnement local type XAMPP, LAMP ou équivalent |

## 🚀 Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/APDYd-dotcom/ult_payment.git
cd ult_payment
```

### 2. Installer les dépendances Composer

```bash
composer install
```

### 3. Créer la base de données

```sql
CREATE DATABASE ult_payment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Importer les scripts SQL

Importez d'abord le schéma principal de votre projet, puis les scripts complémentaires selon les fonctionnalités utilisées.

```bash
mysql -u root -p ult_payment < sql.sql
mysql -u root -p ult_payment < notifications.sql
mysql -u root -p ult_payment < alerts.sql
mysql -u root -p ult_payment < password_reset_tokens.sql
mysql -u root -p ult_payment < account_locking.sql
mysql -u root -p ult_payment < student_user_accounts.sql
mysql -u root -p ult_payment < two_factor.sql
mysql -u root -p ult_payment < settings.sql
mysql -u root -p ult_payment < system_settings.sql
```

> ⚠️ `system_settings.sql` recrée des fonctions, procédures et triggers liés aux paiements. Vérifiez le script avant exécution sur une base contenant déjà des données de production.

### 5. Configurer Apache

Placez le projet dans le dossier web local, par exemple :

```bash
/var/www/html/payment
```

Puis ouvrez :

```text
http://localhost/payment
```

## ⚙️ Configuration

### Base de données

La connexion PDO est actuellement définie dans les fichiers PHP principaux avec les paramètres suivants :

```php
$bdd = new PDO(
    'mysql:host=localhost;dbname=ult_payment;charset=utf8',
    'app_user',
    'secure_password_123'
);
```

Adaptez ces informations selon votre environnement local :

- `host` : serveur MySQL/MariaDB
- `dbname` : nom de la base de données
- utilisateur : compte MySQL
- mot de passe : mot de passe du compte MySQL

### SMTP / Emails

L'envoi des emails utilise PHPMailer. Configurez les paramètres SMTP dans les fichiers concernés, notamment `functions.php` et `forgot_password.php`.

```php
$smtpHost = 'smtp.gmail.com';
$smtpPort = 587;
$smtpUsername = 'your-email@example.com';
$smtpPassword = 'your-app-password';
$smtpEncryption = 'tls';
```

> 🔐 Recommandation : pour un déploiement réel, déplacez les identifiants sensibles dans des variables d'environnement ou un fichier `config.php` non versionné.

### Paramètres système

Les paramètres métier sont stockés dans la table `settings`, catégorie `system` :

- dates des tranches
- délais et pourcentages de pénalités
- informations de l'établissement
- durée de session
- nombre maximal de tentatives de connexion
- activation 2FA par défaut

Ils sont administrables via :

```text
/payment/admin/system_settings.php
```

### Gestion des thèmes

Les paramètres visuels sont stockés dans la table `settings`, catégorie `theme` :

- couleur primaire
- couleur secondaire
- couleur de fond
- police
- logo
- favicon
- nom du thème

Pages disponibles :

```text
/payment/admin/theme_settings.php
/payment/student/theme_settings.php
```

## 🗂️ Structure du projet

```text
payment/
├── admin/                         # Interface administrateur
│   ├── dashboard.php              # Tableau de bord
│   ├── student.php                # Gestion des étudiants
│   ├── departement.php            # Gestion des départements
│   ├── payment.php                # Gestion des paiements
│   ├── penalty.php                # Gestion des pénalités
│   ├── manage_users.php           # Gestion des comptes
│   ├── system_settings.php        # Paramétrage système
│   ├── theme_settings.php         # Gestion des thèmes
│   ├── sidebar.php                # Navigation admin
│   └── styles.css                 # Styles admin
├── student/                       # Interface étudiant
│   ├── student.php                # Dossier étudiant
│   ├── payment.php                # Paiements étudiant
│   ├── partial.php                # Paiements partiels
│   ├── penalty.php                # Pénalités étudiant
│   ├── profile.php                # Profil étudiant
│   ├── theme_settings.php         # Paramètres de thème
│   ├── sidebar.php                # Navigation étudiant
│   └── styles.css                 # Styles étudiant
├── uploads/                       # Fichiers uploadés
│   └── themes/                    # Logos et favicons
├── vendor/                        # Dépendances Composer
├── auth_check.php                 # Vérification de session et rôles
├── functions.php                  # Fonctions communes
├── index.php                      # Connexion
├── signup.php                     # Inscription
├── logout.php                     # Déconnexion
├── forgot_password.php            # Mot de passe oublié
├── reset_password.php             # Réinitialisation du mot de passe
├── verify_2fa.php                 # Vérification 2FA
├── notifications.js               # Notifications frontend
├── session-timeout.js             # Gestion expiration session
├── *.sql                          # Scripts SQL et migrations
├── composer.json                  # Dépendances PHP
└── README.md                      # Documentation du projet
```

## 👥 Utilisation

### Administrateur

L'administrateur peut :

- gérer les étudiants
- gérer les départements
- enregistrer les paiements
- consulter et suivre les pénalités
- gérer les utilisateurs
- consulter l'historique de connexion
- gérer les notifications et alertes
- personnaliser le thème
- modifier les paramètres métier du système

### Étudiant

L'étudiant peut :

- consulter son dossier
- consulter ses paiements
- suivre ses paiements partiels
- consulter ses pénalités
- gérer son profil
- activer/configurer la 2FA si disponible
- visualiser le thème appliqué

## 🖼️ Captures d'écran

Ajoutez vos captures dans un dossier `docs/screenshots/`, puis remplacez les placeholders ci-dessous.

### Page de connexion

![Page de connexion](docs/screenshots/login.png)

### Tableau de bord administrateur

![Tableau de bord admin](docs/screenshots/admin-dashboard.png)

### Gestion des étudiants

![Gestion des étudiants](docs/screenshots/students.png)

### Gestion des paiements

![Gestion des paiements](docs/screenshots/payments.png)

### Profil utilisateur

![Profil utilisateur](docs/screenshots/profile.png)

### Page des pénalités

![Pénalités](docs/screenshots/penalties.png)

### Notifications et alertes

![Notifications](docs/screenshots/notifications.png)

## 📚 Documentation

Documentation principale :

- [Dépôt GitHub](https://github.com/APDYd-dotcom/ult_payment.git)
- Scripts SQL inclus dans le projet : `*.sql`
- Paramètres système : `system_settings.sql`
- Paramètres de thème : `settings.sql`

Une documentation technique détaillée peut être ajoutée dans :

```text
docs/
├── database.md
├── installation.md
└── user-guide.md
```

## 🤝 Contributions

Les contributions sont les bienvenues dans le cadre de l'amélioration du projet.

### Étapes recommandées

1. Forker le dépôt
2. Créer une branche

```bash
git checkout -b feature/ma-fonctionnalite
```

3. Effectuer les modifications
4. Vérifier la syntaxe PHP

```bash
find . -path './vendor' -prune -o -name '*.php' -print -exec php -l {} \;
```

5. Commiter les changements

```bash
git commit -m "Ajout de ma fonctionnalité"
```

6. Pousser la branche

```bash
git push origin feature/ma-fonctionnalite
```

7. Ouvrir une Pull Request

## 📄 Licence

Ce projet peut être distribué sous licence **MIT**.

> Si aucune licence n'est encore présente dans le dépôt, ajoutez un fichier `LICENSE` avant toute publication officielle.

## 👨‍💻 Auteur

**Concepteur / Développeur** : APDYd-dotcom  
**GitHub** : [APDYd-dotcom](https://github.com/APDYd-dotcom)  
**Projet** : [ULT Payment System](https://github.com/APDYd-dotcom/ult_payment.git)

## 🙏 Remerciements

Merci à :

- l'Université du Lac Tanganyika (ULT) ASBL
- l'enseignant du cours de Programmation Web
- les encadrants et collègues ayant contribué aux tests et retours
- la communauté open source PHP, Composer, PHPMailer et Chart.js

---

**ULT Payment System** — Une solution académique pour digitaliser et sécuriser la gestion des paiements universitaires.
