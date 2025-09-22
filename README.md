# Architecture Microservices Laravel

Une architecture de microservices événementielle utilisant Laravel, RabbitMQ et MailPit pour gérer l'authentification, les profils utilisateurs et les notifications par email.

## 🏗️ Architecture

```
┌─────────────────────┐    ┌─────────────────────┐    ┌─────────────────────┐
│  AuthService        │    │  UserService        │    │  EmailService       │
│  (Port 8002)        │    │  (Port 8000)        │    │  (Port 8001)        │
│                     │    │                     │    │                     │
│ • Authentification  │    │ • Profils users     │    │ • Envoi d'emails    │
│ • Gestion tokens    │    │ • Données métier    │    │ • Templates email   │
│ • Événements user   │    │ • Synchronisation   │    │ • Historique        │
└─────────────────────┘    └─────────────────────┘    └─────────────────────┘
         │                           │                           │
         │                           │                           │
         └─────────────────┐         │         ┌─────────────────┘
                           │         │         │
                    ┌─────────────────────────────────┐
                    │         RabbitMQ Exchange       │
                    │       (user_events)             │
                    │                                 │
                    │ • user.created                  │
                    │ • user.updated                  │
                    │ • user.verified                 │
                    │ • send_email queue              │
                    └─────────────────────────────────┘
                                   │
                    ┌─────────────────────────────────┐
                    │          MailPit                │
                    │      (Interface Web)            │
                    │      http://localhost:8025      │
                    └─────────────────────────────────┘
```

## 📋 Services

### 🔐 AuthService (authentificationService)
- **Port**: 8002
- **Base de données**: `authservice`
- **Responsabilités**:
  - Inscription/Connexion utilisateurs
  - Gestion des tokens Sanctum
  - Vérification d'email
  - Reset de mot de passe
  - Publication d'événements utilisateur

### 👤 UserService (user-microservice)
- **Port**: 8000
- **Base de données**: `userservice`
- **Responsabilités**:
  - Stockage des profils utilisateurs
  - Consommation des événements d'authentification
  - Synchronisation des données utilisateur
  - Gestion des métadonnées utilisateur

### 📧 EmailService (sendEmailService)
- **Port**: 8001
- **Responsabilités**:
  - Envoi d'emails transactionnels
  - Templates d'emails
  - Gestion de la queue d'emails
  - Interface avec MailPit

## 🚀 Installation et Configuration

### Prérequis
- PHP 8.1+
- Composer
- MySQL
- MailPit installé localement
- Accès à RabbitMQ (Coolify ou local)

### 1. Configuration des bases de données

```sql
-- Créer les bases de données
CREATE DATABASE authservice;
CREATE DATABASE userservice;
CREATE DATABASE emailservice;
```

### 2. Configuration des services

Chaque service a son propre fichier `.env` :

**authentificationService/.env**:
```env
APP_URL=http://localhost:8002
DB_DATABASE=authservice
RABBITMQ_HOST=rabbitmq.192.168.1.10.sslip.io
RABBITMQ_USER=jXRJrNVeNInyCjZY
RABBITMQ_PASSWORD=OP6rHtYgxpXpZpmBqfBi355ffAN0Av8m
QUEUE_CONNECTION=rabbitmq
```

**user-microservice/.env**:
```env
APP_URL=http://localhost:8000
DB_DATABASE=userservice
RABBITMQ_HOST=rabbitmq.192.168.1.10.sslip.io
RABBITMQ_USER=jXRJrNVeNInyCjZY
RABBITMQ_PASSWORD=OP6rHtYgxpXpZpmBqfBi355ffAN0Av8m
QUEUE_CONNECTION=rabbitmq
```

**sendEmailService/.env**:
```env
APP_URL=http://localhost:8001
DB_DATABASE=emailservice
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
RABBITMQ_HOST=rabbitmq.192.168.1.10.sslip.io
RABBITMQ_USER=jXRJrNVeNInyCjZY
RABBITMQ_PASSWORD=OP6rHtYgxpXpZpmBqfBi355ffAN0Av8m
QUEUE_CONNECTION=rabbitmq
```

### 3. Installation des dépendances

```bash
# Pour chaque service
cd authentificationService && composer install
cd user-microservice && composer install
cd sendEmailService && composer install
```

### 4. Migrations

```bash
# AuthService
cd authentificationService
php artisan migrate

# UserService
cd user-microservice
php artisan migrate

# EmailService
cd sendEmailService
php artisan migrate
```

## 🏃‍♂️ Démarrage des Services

### Ordre de démarrage important:

#### Terminal 1 - AuthService
```bash
cd authentificationService
php artisan serve --host=127.0.0.1 --port=8002
```

#### Terminal 2 - UserService
```bash
cd user-microservice
php artisan serve --host=127.0.0.1 --port=8000
```

#### Terminal 3 - EmailService
```bash
cd sendEmailService
php artisan serve --host=127.0.0.1 --port=8001
```

#### Terminal 4 - UserService Consumer (Événements)
```bash
cd user-microservice
php artisan rabbitmq:consume-user-events
```

#### Terminal 5 - UserService Worker (Processing)
```bash
cd user-microservice
php artisan queue:work --queue=user_events_processing
```

#### Terminal 6 - EmailService Worker
```bash
cd sendEmailService
php artisan queue:work --queue=send_email
```

#### Terminal 7 - AuthService Worker
```bash
cd authentificationService
php artisan queue:work --queue=events
```

#### Terminal 8 - MailPit (si pas déjà démarré)
```bash
mailpit
```

## 🔄 Flux de Données

### Inscription d'un utilisateur

1. **Requête POST** vers `AuthService:8002/api/register`
2. **AuthService** crée l'utilisateur dans `authservice` DB
3. **AuthService** publie événement `user.created` → RabbitMQ
4. **AuthService** envoie job `SendEmailJob` → queue `send_email`
5. **UserService** consomme événement → crée profil dans `userservice` DB
6. **EmailService** traite job → envoie email → MailPit

### Vérification d'email

1. **Utilisateur clique** sur le lien de vérification
2. **AuthService** met à jour `email_verified_at`
3. **AuthService** publie événement `user.verified` → RabbitMQ
4. **UserService** met à jour le profil utilisateur

## 📡 API Endpoints

### AuthService (Port 8002)

```bash
# Inscription
POST /api/register
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}

# Connexion
POST /api/login
{
  "email": "john@example.com",
  "password": "password123"
}

# Vérification email
GET /api/email/verify/{id}/{hash}

# Reset mot de passe
POST /api/forgot-password
{
  "email": "john@example.com"
}

POST /api/reset-password
{
  "email": "john@example.com",
  "token": "reset_token",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}

# Déconnexion
POST /api/logout
```

### EmailService (Port 8001)

```bash
# Test d'envoi d'email
GET /test-email
```

## 🧪 Tests et Debug

### Test complet du système

```bash
# 1. Créer un utilisateur
curl -X POST http://localhost:8002/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'

# 2. Vérifier dans MailPit
# Ouvrir http://localhost:8025

# 3. Vérifier les bases de données
mysql -u root -p
USE authservice; SELECT * FROM users;
USE userservice; SELECT * FROM user_profiles;
```

### Vérification des logs

```bash
# AuthService
tail -f authentificationService/storage/logs/laravel.log

# UserService
tail -f user-microservice/storage/logs/laravel.log

# EmailService
tail -f sendEmailService/storage/logs/laravel.log
```

### Interface RabbitMQ
- **URL**: http://rabbitmq.192.168.1.10.sslip.io:15672
- **Login**: jXRJrNVeNInyCjZY
- **Password**: OP6rHtYgxpXpZpmBqfBi355ffAN0Av8m

### Interface MailPit
- **URL**: http://localhost:8025

## ⚠️ Troubleshooting

### Jobs dans la mauvaise queue
Si des jobs `ProcessUserEventJob` apparaissent dans la queue `send_email`:
1. Arrêter tous les workers
2. Vider les queues RabbitMQ
3. Redémarrer dans l'ordre correct

### Emails ne s'affichent pas dans MailPit
1. Vérifier que MailPit écoute sur port 1025: `netstat -an | findstr :1025`
2. Vérifier la configuration SMTP dans `.env`
3. Tester l'envoi direct: `curl http://localhost:8001/test-email`

### Événements non reçus
1. Vérifier que le consumer RabbitMQ fonctionne
2. Vérifier les credentials RabbitMQ
3. Vérifier les logs des workers

## 🔧 Configuration des Queues

### Queues utilisées:
- **events**: AuthService - Publication d'événements
- **send_email**: EmailService - Envoi d'emails
- **user_events_processing**: UserService - Traitement des événements utilisateur

### RabbitMQ Exchange:
- **user_events** (topic): Événements utilisateur
  - `user.created`: Utilisateur créé
  - `user.updated`: Utilisateur mis à jour
  - `user.verified`: Email vérifié

## 📈 Monitoring

### Commandes utiles

```bash
# Statut des queues
php artisan queue:monitor

# Restart workers
php artisan queue:restart

# Voir les jobs failed
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

## 🛠️ Développement

### Ajouter un nouveau type d'événement

1. **AuthService**: Publier l'événement dans `PublishUserEventJob`
2. **UserService**: Ajouter le traitement dans `ProcessUserEventJob`
3. **UserService**: Ajouter le binding dans `ConsumeUserEventsCommand`

### Ajouter un nouveau service

1. Créer le service Laravel
2. Configurer RabbitMQ dans `.env`
3. Créer les consumers/jobs appropriés
4. Mettre à jour la documentation

---

**Auteur**: Votre équipe de développement
**Version**: 1.0
**Dernière mise à jour**: 2025-09-22