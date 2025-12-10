# Database Connection Fix

## Issue
The application was trying to connect to MySQL instead of PostgreSQL.

## Solution Applied

1. ✅ Created proper `config/database.php` with PostgreSQL configuration
2. ✅ Updated `docker-compose.yml` to set `DB_CONNECTION=pgsql`
3. ✅ Updated both Dockerfiles to install PostgreSQL PHP extensions (`pdo_pgsql`, `pgsql`)
4. ✅ Cleared Laravel config cache

## Next Steps

### 1. Rebuild Docker Containers

Since we updated the Dockerfiles, you need to rebuild:

```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### 2. Verify Database Connection

Check if PostgreSQL is accessible:

```bash
docker-compose exec app php artisan tinker
```

Then test:
```php
DB::connection()->getPdo();
// Should return PDO object without errors
exit
```

### 3. Run Migrations

```bash
docker-compose exec app php artisan migrate
```

### 4. Run Seeders

```bash
docker-compose exec app php artisan db:seed
```

## Verify Configuration

Check your `.env` file (inside container or locally) has:

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=laravel
```

## Troubleshooting

If you still get connection errors:

1. **Check if PostgreSQL container is running:**
   ```bash
   docker-compose ps db
   ```

2. **Check PostgreSQL logs:**
   ```bash
   docker-compose logs db
   ```

3. **Test connection from app container:**
   ```bash
   docker-compose exec app php -r "echo (new PDO('pgsql:host=db;port=5432;dbname=laravel', 'laravel', 'laravel')) ? 'Connected' : 'Failed';"
   ```

4. **Verify PostgreSQL extensions are installed:**
   ```bash
   docker-compose exec app php -m | grep pgsql
   ```
   Should show: `pdo_pgsql` and `pgsql`

## Notes

- The docker-compose.yml now sets `DB_CONNECTION=pgsql` as an environment variable
- Both development and production Dockerfiles now include PostgreSQL support
- The database config file supports both PostgreSQL and MySQL (for flexibility)

