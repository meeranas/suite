# Docker Setup Guide

This project includes Docker configuration to run the Laravel application in containers.

## Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose)
- At least 4GB of available RAM

## Quick Start

1. **Copy environment file** (if you don't have one):
   ```bash
   cp .env.example .env
   ```

2. **Update your `.env` file** with these database settings:
   ```
   DB_CONNECTION=mysql
   DB_HOST=db
   DB_PORT=3306
   DB_DATABASE=laravel
   DB_USERNAME=laravel
   DB_PASSWORD=laravel
   ```

3. **Build and start containers**:
   ```bash
   docker-compose up -d --build
   ```

4. **Install PHP dependencies**:
   ```bash
   docker-compose exec app composer install
   ```

5. **Install Node dependencies**:
   ```bash
   docker-compose exec app npm install
   ```

6. **Generate application key** (if needed):
   ```bash
   docker-compose exec app php artisan key:generate
   ```

7. **Run migrations**:
   ```bash
   docker-compose exec app php artisan migrate
   ```

8. **Build frontend assets** (for production):
   ```bash
   docker-compose exec app npm run build
   ```

   Or run in development mode (with hot reload):
   ```bash
   docker-compose exec app npm run dev
   ```

## Access the Application

- **Web Application**: http://localhost:8000
- **phpMyAdmin**: http://localhost:8080
  - Server: `db`
  - Username: `root` (or `laravel`)
  - Password: `root` (or `laravel`)
- **Database**: localhost:3306
  - Username: `laravel`
  - Password: `laravel`
  - Database: `laravel`
- **Redis**: localhost:6379
- **Vite Dev Server**: http://localhost:5173

## Useful Commands

### View logs
```bash
docker-compose logs -f app
```

### Execute Artisan commands
```bash
docker-compose exec app php artisan [command]
```

### Execute Composer commands
```bash
docker-compose exec app composer [command]
```

### Execute NPM commands
```bash
docker-compose exec app npm [command]
```

### Access container shell
```bash
docker-compose exec app bash
```

### Stop containers
```bash
docker-compose down
```

### Stop and remove volumes (clean slate)
```bash
docker-compose down -v
```

## Production Build

For production, use the `Dockerfile` (not `Dockerfile.dev`):

```bash
docker build -t laravel-app -f Dockerfile .
docker run -p 8000:80 laravel-app
```

## Troubleshooting

### Permission issues
If you encounter permission issues with storage or cache:
```bash
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose exec app chmod -R 755 storage bootstrap/cache
```

### Clear cache
```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

### Rebuild containers
```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

