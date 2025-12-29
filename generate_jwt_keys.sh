#!/bin/bash

# Script to generate JWT keys and add public key to .env

set -e

echo "=== JWT Key Generation Script ==="
echo ""

# Check if OpenSSL is installed
if ! command -v openssl &> /dev/null; then
    echo "❌ Error: OpenSSL is not installed"
    echo "Install it with: apt-get install openssl (or brew install openssl on Mac)"
    exit 1
fi

# Generate keys
echo "1. Generating RSA key pair (2048 bits)..."
openssl genrsa -out jwt_private_key.pem 2048
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem

echo "✅ Keys generated:"
echo "   - jwt_private_key.pem (for main platform)"
echo "   - jwt_public_key.pem (for AI Suite)"
echo ""

# Format public key for .env
echo "2. Formatting public key for .env..."
PUBLIC_KEY_ENV=$(cat jwt_public_key.pem | sed ':a;N;$!ba;s/\n/\\n/g')

echo "✅ Public key formatted"
echo ""

# Ask user if they want to add to .env
read -p "3. Add public key to .env file? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    # Find .env file
    if [ -f .env ]; then
        ENV_FILE=".env"
    elif [ -f ../.env ]; then
        ENV_FILE="../.env"
    else
        echo "❌ Error: .env file not found in current directory"
        echo "Please run this script from the directory containing .env"
        exit 1
    fi
    
    echo "   Found .env file: $ENV_FILE"
    
    # Backup .env
    cp "$ENV_FILE" "${ENV_FILE}.backup.$(date +%Y%m%d_%H%M%S)"
    echo "   ✅ Backup created"
    
    # Remove old JWT_PUBLIC_KEY if exists
    sed -i '/^JWT_PUBLIC_KEY=/d' "$ENV_FILE"
    
    # Add new JWT_PUBLIC_KEY
    echo "" >> "$ENV_FILE"
    echo "# JWT Public Key (for verifying tokens from main platform)" >> "$ENV_FILE"
    echo "JWT_PUBLIC_KEY=\"$PUBLIC_KEY_ENV\"" >> "$ENV_FILE"
    
    echo "   ✅ Public key added to .env"
    echo ""
    echo "   Next steps:"
    echo "   1. Restart container: docker compose -f docker-compose.prod.yml restart app"
    echo "   2. Clear cache: docker compose -f docker-compose.prod.yml exec app php artisan config:clear"
    echo "   3. Cache config: docker compose -f docker-compose.prod.yml exec app php artisan config:cache"
else
    echo "   Skipped adding to .env"
    echo ""
    echo "   To add manually, use this line in .env:"
    echo "   JWT_PUBLIC_KEY=\"$PUBLIC_KEY_ENV\""
fi

echo ""
echo "=== Summary ==="
echo ""
echo "✅ Private key: jwt_private_key.pem"
echo "   → Use this on your MAIN PLATFORM (the one that issues tokens)"
echo ""
echo "✅ Public key: jwt_public_key.pem"
echo "   → Use this in AI Suite's .env file (this app verifies tokens)"
echo ""
echo "⚠️  Security:"
echo "   - Never commit these keys to git"
echo "   - Keep private key secure (only on main platform)"
echo "   - Add *.pem to .gitignore"
echo ""


