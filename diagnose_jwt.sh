#!/bin/bash

echo "=== JWT Authentication Diagnostic Script ==="
echo ""

echo "1. Checking .env file on HOST:"
if [ -f .env ]; then
    echo "   ✅ .env exists"
    JWT_LINE=$(grep "^JWT_PUBLIC_KEY=" .env 2>/dev/null || echo "")
    if [ -z "$JWT_LINE" ]; then
        echo "   ✅ JWT_PUBLIC_KEY not set (test tokens will work)"
    else
        if [ "$JWT_LINE" = "JWT_PUBLIC_KEY=" ] || [ "$JWT_LINE" = "JWT_PUBLIC_KEY=\"\"" ]; then
            echo "   ✅ JWT_PUBLIC_KEY is empty (test tokens will work)"
        else
            echo "   ⚠️  JWT_PUBLIC_KEY is SET (test tokens will NOT work)"
            echo "   Value preview: ${JWT_LINE:0:50}..."
        fi
    fi
else
    echo "   ❌ .env not found"
fi

echo ""
echo "2. Checking Laravel config in CONTAINER:"
CONFIG_VALUE=$(docker compose -f docker-compose.prod.yml exec -T app php artisan tinker --execute="echo config('auth.jwt_public_key');" 2>/dev/null || echo "ERROR")
if [ -z "$CONFIG_VALUE" ] || [ "$CONFIG_VALUE" = "" ] || [ "$CONFIG_VALUE" = "ERROR" ]; then
    echo "   ✅ Config shows empty (test tokens will work)"
else
    echo "   ⚠️  Config shows value is SET (test tokens will NOT work)"
    echo "   Value length: ${#CONFIG_VALUE} characters"
fi

echo ""
echo "3. Testing token generation:"
TOKEN_RESPONSE=$(curl -s -X POST http://localhost:3000/api/test/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@aihub.com", "password": "password"}' 2>/dev/null || echo "ERROR")

if [ "$TOKEN_RESPONSE" = "ERROR" ]; then
    echo "   ❌ Failed to generate token"
else
    TOKEN=$(echo "$TOKEN_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4 || echo "")
    if [ -n "$TOKEN" ]; then
        echo "   ✅ Token generated successfully"
        echo "   Token preview: ${TOKEN:0:50}..."
        
        echo ""
        echo "4. Testing token validation:"
        USER_RESPONSE=$(curl -s -X GET http://localhost:3000/api/user \
          -H "Authorization: Bearer $TOKEN" 2>/dev/null || echo "ERROR")
        
        if echo "$USER_RESPONSE" | grep -q "Unauthenticated\|Invalid\|error"; then
            echo "   ❌ Token validation FAILED"
            echo "   Response: $USER_RESPONSE"
        else
            echo "   ✅ Token validation SUCCESS"
            echo "   User data: $(echo "$USER_RESPONSE" | head -c 100)..."
        fi
    else
        echo "   ❌ Failed to extract token from response"
        echo "   Response: $TOKEN_RESPONSE"
    fi
fi

echo ""
echo "5. Checking Laravel logs (last 10 JWT-related entries):"
docker compose -f docker-compose.prod.yml exec -T app tail -50 storage/logs/laravel.log 2>/dev/null | grep -i "jwt\|token\|bearer" | tail -10 || echo "   No JWT logs found"

echo ""
echo "=== Recommendations ==="
echo ""
echo "If token validation is failing:"
echo "1. Make sure JWT_PUBLIC_KEY is empty in .env:"
echo "   sed -i 's/^JWT_PUBLIC_KEY=.*/JWT_PUBLIC_KEY=/' .env"
echo ""
echo "2. Restart container:"
echo "   docker compose -f docker-compose.prod.yml restart app"
echo ""
echo "3. Clear config cache:"
echo "   docker compose -f docker-compose.prod.yml exec app php artisan config:clear"
echo "   docker compose -f docker-compose.prod.yml exec app php artisan config:cache"
echo ""


