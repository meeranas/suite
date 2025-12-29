# Debug HTML Output

## Check what HTML is actually being served

```bash
# Get full HTML output
docker compose -f docker-compose.prod.yml exec app curl http://localhost | head -50

# Or save to file and check
docker compose -f docker-compose.prod.yml exec app curl http://localhost > /tmp/page.html
docker compose -f docker-compose.prod.yml exec app cat /tmp/page.html

# Check for Vite references
docker compose -f docker-compose.prod.yml exec app curl http://localhost | grep -i vite

# Check for any script/link tags
docker compose -f docker-compose.prod.yml exec app curl http://localhost | grep -E "(script|link)" | head -10
```

## Check if manifest.json is readable

```bash
# Check manifest exists and is readable
docker compose -f docker-compose.prod.yml exec app cat public/build/manifest.json

# Check file permissions
docker compose -f docker-compose.prod.yml exec app ls -la public/build/
```

## Check APP_ENV

```bash
# Verify APP_ENV is production
docker compose -f docker-compose.prod.yml exec app php artisan tinker --execute="echo config('app.env');"
```

## Check if public/hot exists

```bash
# This file forces dev server mode
docker compose -f docker-compose.prod.yml exec app ls -la public/hot
```


