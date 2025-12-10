# Quick Start Guide

## 🚀 Get Started in 5 Minutes

### 1. Install Dependencies

```bash
# PHP packages
composer require spatie/laravel-permission firebase/php-jwt barryvdh/laravel-dompdf phpoffice/phpword smalot/pdfparser

# Publish Spatie config
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

### 2. Configure Environment

Copy `.env.example` to `.env` and update:

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=laravel

JWT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\nYOUR_KEY_HERE\n-----END PUBLIC KEY-----"

OPENAI_API_KEY=sk-your-key-here
CHROMA_URL=http://chroma:8000
```

### 3. Start Docker Services

```bash
docker-compose up -d
```

### 4. Run Migrations

```bash
docker-compose exec app php artisan migrate
```

### 5. Create Roles

```bash
docker-compose exec app php artisan tinker
```

Then run:
```php
use Spatie\Permission\Models\Role;
Role::create(['name' => 'admin']);
Role::create(['name' => 'user']);
exit
```

### 6. Test API

```bash
# Get JWT token from your main platform, then:
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" http://localhost:8000/api/suites
```

### 7. Start React Frontend (Optional)

```bash
cd resources/react
npm install
npm run dev
```

Visit: http://localhost:3000

---

## 📋 Checklist

- [ ] Composer packages installed
- [ ] Environment variables configured
- [ ] Docker services running
- [ ] Database migrated
- [ ] Roles created
- [ ] JWT token tested
- [ ] React frontend running (optional)

---

## 🎯 First Steps After Setup

1. **Create a Suite** (via API or admin panel)
2. **Create an Agent** in the suite
3. **Create a Workflow** with agent sequence
4. **Start a Chat** and send a message
5. **Upload a File** for RAG processing

---

## 🔍 Verify Installation

```bash
# Check services
docker-compose ps

# Check database
docker-compose exec db psql -U laravel -d laravel -c "\dt"

# Check Chroma
curl http://localhost:8001/api/v1/heartbeat
```

---

## ❓ Need Help?

See `README_AI_HUB.md` for complete documentation.

