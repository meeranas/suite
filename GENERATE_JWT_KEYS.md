# How to Generate JWT Keys and Add to .env

## Overview

For **production** with real JWT tokens, you need:
- **Private Key**: Goes on the **main platform** (the one that issues tokens)
- **Public Key**: Goes in **AI Suite's .env** file (this app verifies tokens)

## Step 1: Generate RSA Key Pair

### Option A: Using OpenSSL (Recommended)

```bash
# Generate private key (2048 bits)
openssl genrsa -out jwt_private_key.pem 2048

# Generate public key from private key
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem

# View the keys
echo "=== Private Key (for main platform) ==="
cat jwt_private_key.pem

echo ""
echo "=== Public Key (for AI Suite .env) ==="
cat jwt_public_key.pem
```

### Option B: Using SSH Keygen (Alternative)

```bash
# Generate private key
ssh-keygen -t rsa -b 2048 -f jwt_key -N ""

# Convert to PEM format
ssh-keygen -f jwt_key -e -m pem > jwt_public_key.pem
mv jwt_key jwt_private_key.pem
```

## Step 2: Format Public Key for .env

The `.env` file needs the public key on a **single line** with `\n` for line breaks.

### Method 1: Manual Formatting

1. Take your public key:
```
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...
-----END PUBLIC KEY-----
```

2. Convert to single line with `\n`:
```
JWT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...\n-----END PUBLIC KEY-----"
```

### Method 2: Using Command Line (Easier)

```bash
# Convert public key to single line format for .env
PUBLIC_KEY=$(cat jwt_public_key.pem | tr '\n' '\\n')
echo "JWT_PUBLIC_KEY=\"$PUBLIC_KEY\"" >> .env
```

### Method 3: Using a Script

```bash
# Create script to format key
cat > format_key_for_env.sh << 'SCRIPT'
#!/bin/bash
if [ ! -f jwt_public_key.pem ]; then
    echo "Error: jwt_public_key.pem not found"
    exit 1
fi

# Read key and replace newlines with \n
KEY=$(cat jwt_public_key.pem | sed ':a;N;$!ba;s/\n/\\n/g')

# Output formatted for .env
echo "JWT_PUBLIC_KEY=\"$KEY\""
SCRIPT

chmod +x format_key_for_env.sh
./format_key_for_env.sh
```

## Step 3: Add to .env File

### On Your Server (AI Suite):

```bash
# Navigate to project directory
cd /var/www/html/suite

# Edit .env file
nano .env

# Add this line (replace with your actual formatted key):
JWT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...\n-----END PUBLIC KEY-----"

# Save and exit (Ctrl+X, then Y, then Enter)
```

### Or use command line:

```bash
# Remove old JWT_PUBLIC_KEY if exists
sed -i '/^JWT_PUBLIC_KEY=/d' .env

# Add new one (replace YOUR_KEY_HERE with formatted key)
echo 'JWT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\nYOUR_KEY_HERE\n-----END PUBLIC KEY-----"' >> .env
```

## Step 4: Restart and Verify

```bash
# Restart container
docker compose -f docker-compose.prod.yml restart app

# Clear config cache
docker compose -f docker-compose.prod.yml exec app php artisan config:clear
docker compose -f docker-compose.prod.yml exec app php artisan config:cache

# Verify it's loaded
docker compose -f docker-compose.prod.yml exec app php artisan tinker --execute="echo config('auth.jwt_public_key') ? 'Key loaded!' : 'Key not found';"
```

## Step 5: Configure Main Platform

On your **main platform** (the one that issues JWT tokens), use the **private key**:

```php
// Example in PHP
$privateKey = file_get_contents('jwt_private_key.pem');

$payload = [
    'sub' => $user->id,
    'email' => $user->email,
    'name' => $user->name,
    'iss' => 'your-platform',
    'iat' => time(),
    'exp' => time() + (7 * 24 * 60 * 60), // 7 days
];

$token = JWT::encode($payload, $privateKey, 'RS256');
```

## Complete Example

```bash
# 1. Generate keys
openssl genrsa -out jwt_private_key.pem 2048
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem

# 2. Format public key for .env
PUBLIC_KEY_ENV=$(cat jwt_public_key.pem | sed ':a;N;$!ba;s/\n/\\n/g')

# 3. Add to .env
cd /var/www/html/suite
sed -i '/^JWT_PUBLIC_KEY=/d' .env
echo "JWT_PUBLIC_KEY=\"$PUBLIC_KEY_ENV\"" >> .env

# 4. Verify
grep JWT_PUBLIC_KEY .env

# 5. Restart
docker compose -f docker-compose.prod.yml restart app
docker compose -f docker-compose.prod.yml exec app php artisan config:clear
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
```

## Security Notes

1. **Never commit keys to git** - Add to `.gitignore`:
   ```
   *.pem
   jwt_private_key.pem
   jwt_public_key.pem
   ```

2. **Keep private key secure** - Only on the main platform, never in AI Suite

3. **Use strong keys** - 2048 bits minimum, 4096 bits recommended for production

4. **Rotate keys periodically** - Generate new keys and update both platforms

## Testing

After setting up, test with a real JWT token from your main platform:

```bash
curl -X GET http://localhost:3000/api/user \
  -H "Authorization: Bearer YOUR_REAL_JWT_TOKEN"
```

## Troubleshooting

### If tokens still don't work:

1. **Check key format** - Must be single line with `\n`
2. **Check config cache** - Run `config:clear` and `config:cache`
3. **Check logs** - `docker compose exec app tail -f storage/logs/laravel.log`
4. **Verify key** - Make sure public key matches the private key used to sign tokens

### To go back to test tokens:

```bash
# Set to empty
sed -i 's/^JWT_PUBLIC_KEY=.*/JWT_PUBLIC_KEY=/' .env

# Restart and clear cache
docker compose -f docker-compose.prod.yml restart app
docker compose -f docker-compose.prod.yml exec app php artisan config:clear
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
```
