# JWT Token Guide

## 🔐 How to Get JWT Tokens

### Option 1: Generate Test Tokens (Development Only)

For **development and testing**, you can generate test tokens using the API:

#### Generate Token for Existing User

```bash
# For admin user
curl -X POST http://localhost:8000/api/test/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@aihub.com", "role": "admin"}'

# For regular user
curl -X POST http://localhost:8000/api/test/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email": "user@aihub.com", "role": "user"}'
```

#### Login with Email/Password

```bash
curl -X POST http://localhost:8000/api/test/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@aihub.com", "password": "password"}'
```

**Response:**
```json
{
  "token": "eyJzdWIiOiIxIiwiZW1haWwiOiJhZG1pbkBhaWh1Yi5jb20iLCJuYW1lIjoiQWRtaW4gVXNlciIsImlzcyI6InRlc3QtcGxhdGZvcm0iLCJpYXQiOjE3MDAwMDAwMDAsImV4cCI6MTcwMDYwNDgwMCwic3Vic2NyaXB0aW9uX3RpZXIiOlsiZW50ZXJwcmlzZSJdfQ==",
  "user": {
    "id": 1,
    "email": "admin@aihub.com",
    "name": "Admin User",
    "roles": ["admin"]
  }
}
```

Copy the `token` value and paste it into the login form.

---

### Option 2: Use Seeded Users (Quick Test)

**Test Credentials:**
- **Admin:** `admin@aihub.com` / `password`
- **User:** `user@aihub.com` / `password`

Use the login endpoint above with these credentials.

---

### Option 3: Get Token from Main Platform (Production)

In **production**, JWT tokens should come from your main Laravel + Angular platform.

1. **Login to your main platform**
2. **Get the JWT token** from:
   - Local storage: `localStorage.getItem('token')` or `localStorage.getItem('jwt_token')`
   - API response after login
   - Auth service in your Angular app

3. **Copy the token** and paste it into the AI Control Hub login form

---

## 🔧 Development Mode

The JWT service supports a **development mode** that:
- Accepts simple base64-encoded JSON tokens (not real JWTs)
- Works without a JWT public key configured
- Only active in `local`, `testing`, or `development` environments

This allows you to test the application without setting up JWT key pairs.

---

## 📝 Token Format

Test tokens are base64-encoded JSON with this structure:

```json
{
  "sub": "1",                    // User ID (external_user_id)
  "email": "admin@aihub.com",    // User email
  "name": "Admin User",          // User name
  "iss": "test-platform",       // Issuer
  "iat": 1700000000,            // Issued at (timestamp)
  "exp": 1700604800,            // Expires at (timestamp)
  "subscription_tier": ["enterprise"]  // Subscription tiers
}
```

---

## 🚀 Quick Start

1. **Generate a test token:**
   ```bash
   curl -X POST http://localhost:8000/api/test/auth/token \
     -H "Content-Type: application/json" \
     -d '{"email": "admin@aihub.com", "role": "admin"}'
   ```

2. **Copy the token** from the response

3. **Paste it** into the login form at http://localhost:5173

4. **Click Login** - You should be redirected to the dashboard!

---

## ⚠️ Important Notes

- **Test tokens only work in development mode**
- **Production requires real JWT tokens** from your main platform
- **Test tokens expire after 7 days**
- **Never use test tokens in production**

---

## 🎯 Example: Complete Flow

```bash
# 1. Generate token
TOKEN=$(curl -s -X POST http://localhost:8000/api/test/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@aihub.com", "role": "admin"}' \
  | jq -r '.token')

# 2. Use token in API calls
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/suites

# 3. Or paste token in browser login form
echo "Token: $TOKEN"
```

---

## 🔍 Verify Token Works

```bash
# Test the token
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  http://localhost:8000/api/user
```

Should return user information if token is valid.





