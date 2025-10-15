# ChatLinkr Backend

Backend API per la piattaforma di messaggistica ChatLinkr.

## 🚀 Deployment su Railway

### 1. Variabili Ambiente
Configura queste variabili su Railway:

```
DB_HOST=aws-1-eu-west-1.pooler.supabase.com
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres.uelwlouanllzmnakbiod
DB_PASS=CrJEuyiB5BVO4SO0
DB_SSLMODE=disable
SESSION_SECRET=your_super_secret_key_change_in_production_2024
WEBSOCKET_PORT=3001
CORS_ORIGIN=*
APP_ENV=production
DEBUG_MODE=false
```

### 2. Build Command
Railway rileverà automaticamente PHP e userà:
- Build: `composer install --no-dev --optimize-autoloader`
- Start: `php -S 0.0.0.0:$PORT -t . server.php`

### 3. API Endpoints
- `POST /api/auth.php` - Autenticazione
- `GET /api/messages.php` - Messaggi
- `GET /api/users.php` - Utenti
- `POST /api/update_status.php` - Status

## 📁 Struttura
```
backend/
├── api/              # API endpoints
├── config/           # Configurazione database
├── utils/            # Utilities (JWT, CORS)
├── websocket/        # WebSocket server
├── database/         # Schema database
└── server.php        # Entry point
```
