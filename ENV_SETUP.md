# Environment Configuration

## Database Settings

Make sure your `.env` file (both locally and in the container) has these PostgreSQL settings:

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=laravel
```

## Important Notes

1. **Port 5432** is for PostgreSQL (not 3306 which is MySQL)
2. **DB_CONNECTION=pgsql** (not `mysql`)
3. The `docker-compose.yml` sets these as environment variables, but `.env` file takes precedence

## If You Still Get Connection Errors

1. **Clear config cache:**
   ```bash
   docker-compose exec app php artisan config:clear
   docker-compose exec app php artisan cache:clear
   ```

2. **Restart the app container:**
   ```bash
   docker-compose restart app
   ```

3. **Verify environment variables:**
   ```bash
   docker-compose exec app php artisan tinker --execute="echo config('database.default');"
   ```
   Should output: `pgsql`

4. **Test database connection:**
   ```bash
   docker-compose exec app php artisan tinker --execute="DB::connection()->getPdo();"
   ```
   Should not throw any errors

## Docker Environment Variables

The `docker-compose.yml` file sets these environment variables automatically:
- `DB_CONNECTION=pgsql`
- `DB_HOST=db`
- `DB_PORT=5432`
- `DB_DATABASE=laravel`
- `DB_USERNAME=laravel`
- `DB_PASSWORD=laravel`

If your local `.env` file has different values, they will override the Docker environment variables.





