# Debug Nginx 500 Error

## Step 1: Check Nginx Error Logs

```bash
# Check nginx error logs
docker compose -f docker-compose.prod.yml exec app cat /var/log/nginx.err.log | tail -20

# Or check all logs
docker compose -f docker-compose.prod.yml logs app | grep -i error | tail -20
```

## Step 2: Test Nginx Configuration

```bash
# Test nginx config syntax
docker compose -f docker-compose.prod.yml exec app nginx -t
```

## Step 3: Check React App index.html

```bash
# Check what paths the React app is using
docker compose -f docker-compose.prod.yml exec app cat public/react/index.html
```

## Step 4: Fix Based on Error

The error will tell us what's wrong. Common issues:
- Path not found
- Alias directive issues
- try_files syntax errors

## Quick Fix: Restart Nginx

```bash
# Copy updated nginx config
docker compose -f docker-compose.prod.yml exec app cp /var/www/html/docker/nginx/default.conf /etc/nginx/sites-available/default

# Test config
docker compose -f docker-compose.prod.yml exec app nginx -t

# Reload nginx
docker compose -f docker-compose.prod.yml exec app nginx -s reload

# Or restart container
docker compose -f docker-compose.prod.yml restart app
```


