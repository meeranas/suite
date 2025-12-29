# Troubleshooting Guide

## Common Deployment Issues

### 1. DB_PASSWORD Not Set

**Error:**
```
The "DB_PASSWORD" variable is not set. Defaulting to a blank string.
```

**Solution:**
1. Open your `.env` file:
   ```bash
   nano .env
   ```

2. Set a strong password:
   ```bash
   DB_PASSWORD=your_strong_password_here
   ```

3. Save and try deployment again:
   ```bash
   ./deploy.sh
   ```

### 2. Port Already in Use

#### Redis Port 6379 Already in Use

**Error:**
```
failed to bind host port 0.0.0.0:6379/tcp: address already in use
```

**Solution:**

The application is configured to use port **6380** by default to avoid conflicts. If you still get this error:

**Option A: Use Different Port**
1. Edit `.env`:
   ```bash
   REDIS_PORT=6381
   ```

2. Restart services:
   ```bash
   docker-compose -f docker-compose.prod.yml down
   docker-compose -f docker-compose.prod.yml up -d
   ```

**Option B: Stop Existing Redis Service**
```bash
# Check what's using port 6379
sudo lsof -i :6379
# or
sudo netstat -tulpn | grep 6379

# Stop the service (if it's safe to do so)
sudo systemctl stop redis
# or
sudo systemctl stop redis-server
```

**Option C: Don't Expose Redis Port (Recommended for Production)**
Edit `docker-compose.prod.yml` and remove the ports section for Redis:
```yaml
redis:
  # ... other config ...
  # Remove or comment out:
  # ports:
  #   - "${REDIS_PORT:-6380}:6379"
```

#### Database Port 5432 Already in Use

**Error:**
```
failed to bind host port 0.0.0.0:5432/tcp: address already in use
```

**Solution:**
1. Edit `.env`:
   ```bash
   DB_PORT=5433
   ```

2. Restart services:
   ```bash
   docker-compose -f docker-compose.prod.yml down
   docker-compose -f docker-compose.prod.yml up -d
   ```

#### Application Port 8080 Already in Use

**Error:**
```
failed to bind host port 0.0.0.0:8080/tcp: address already in use
```

**Solution:**
1. Edit `.env`:
   ```bash
   APP_PORT=8081
   ```

2. Restart services:
   ```bash
   docker-compose -f docker-compose.prod.yml down
   docker-compose -f docker-compose.prod.yml up -d
   ```

### 3. Check What's Using a Port

```bash
# Check specific port
sudo lsof -i :PORT_NUMBER
# Example:
sudo lsof -i :6379
sudo lsof -i :5432
sudo lsof -i :8080

# Alternative command
sudo netstat -tulpn | grep PORT_NUMBER
# Example:
sudo netstat -tulpn | grep 6379
```

### 4. Container Networking Issues

**Error:**
```
failed to set up container networking
```

**Solution:**
1. Clean up Docker networks:
   ```bash
   docker-compose -f docker-compose.prod.yml down
   docker network prune -f
   ```

2. Try again:
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```

### 5. Permission Denied Errors

**Error:**
```
Permission denied
```

**Solution:**
```bash
# Fix storage permissions
docker-compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose -f docker-compose.prod.yml exec app chmod -R 775 storage bootstrap/cache
```

### 6. Database Connection Failed

**Error:**
```
SQLSTATE[HY000] [2002] Connection refused
```

**Solution:**
1. Check if database container is running:
   ```bash
   docker-compose -f docker-compose.prod.yml ps db
   ```

2. Check database logs:
   ```bash
   docker-compose -f docker-compose.prod.yml logs db
   ```

3. Verify database credentials in `.env`:
   ```bash
   DB_HOST=db
   DB_PORT=5432
   DB_DATABASE=ai_suite
   DB_USERNAME=ai_suite_user
   DB_PASSWORD=your_password
   ```

4. Restart database:
   ```bash
   docker-compose -f docker-compose.prod.yml restart db
   ```

### 7. Build Failures

**Error:**
```
failed to solve: process "/bin/sh -c composer install..." did not complete successfully
```

**Solution:**
1. Clear Docker build cache:
   ```bash
   docker system prune -a
   ```

2. Rebuild without cache:
   ```bash
   docker-compose -f docker-compose.prod.yml build --no-cache
   ```

3. Try deployment again:
   ```bash
   ./deploy.sh
   ```

### 8. Environment Variables Not Loading

**Error:**
```
Variable not set
```

**Solution:**
1. Make sure `.env` file exists:
   ```bash
   ls -la .env
   ```

2. Check file permissions:
   ```bash
   chmod 644 .env
   ```

3. Verify variables are set:
   ```bash
   grep DB_PASSWORD .env
   ```

4. Source the file manually (for testing):
   ```bash
   source .env
   echo $DB_PASSWORD
   ```

## Quick Diagnostic Commands

```bash
# Check all running containers
docker-compose -f docker-compose.prod.yml ps

# Check all services status
docker-compose -f docker-compose.prod.yml ps -a

# View logs for all services
docker-compose -f docker-compose.prod.yml logs

# View logs for specific service
docker-compose -f docker-compose.prod.yml logs app
docker-compose -f docker-compose.prod.yml logs db
docker-compose -f docker-compose.prod.yml logs redis

# Check Docker network
docker network ls
docker network inspect suite_ai_suite_network

# Check volumes
docker volume ls
docker volume inspect suite_dbdata

# Test database connection
docker-compose -f docker-compose.prod.yml exec app php artisan tinker
# Then in tinker: DB::connection()->getPdo();
```

## Still Having Issues?

1. **Check logs:**
   ```bash
   docker-compose -f docker-compose.prod.yml logs -f
   ```

2. **Verify .env file:**
   ```bash
   cat .env | grep -v PASSWORD
   ```

3. **Check Docker resources:**
   ```bash
   docker system df
   docker stats
   ```

4. **Restart everything:**
   ```bash
   docker-compose -f docker-compose.prod.yml down
   docker-compose -f docker-compose.prod.yml up -d
   ```

