# pgAdmin Database Connection Setup

## 📋 Step-by-Step Guide

### Step 1: Access pgAdmin
1. Open your browser
2. Go to: **http://localhost:8080**
3. Login with:
   - **Email:** `admin@admin.com`
   - **Password:** `admin`

### Step 2: Register a New Server

1. **Right-click** on "Servers" in the left sidebar
2. Select **"Register" → "Server"**

### Step 3: Fill in the Connection Details

#### General Tab:
- **Name:** `Laravel Database` (or any name you prefer)

#### Connection Tab:
- **Host name/address:** `db` ⚠️ **Important: Use `db` not `localhost`**
- **Port:** `5432`
- **Maintenance database:** `laravel`
- **Username:** `laravel`
- **Password:** `laravel`
- ✅ **Check "Save password"** (so you don't have to enter it every time)

#### Click "Save"

### Step 4: Explore Your Database

Once connected, you'll see:
- Expand **"Laravel Database"** → **"Databases"** → **"laravel"**
- Expand **"Schemas"** → **"public"** → **"Tables"**

You should see all your tables:
- `users`
- `suites`
- `agents`
- `chats`
- `messages`
- `files`
- `vector_embeddings`
- `usage_logs`
- etc.

---

## 🔍 Quick Database Queries

Right-click on any table → **"View/Edit Data"** → **"All Rows"**

Or use the Query Tool:
1. Right-click on "Laravel Database"
2. Select **"Query Tool"**
3. Run SQL queries:

```sql
-- List all tables
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'public'
ORDER BY table_name;

-- View users
SELECT id, email, first_name, last_name, subscription_tier 
FROM users;

-- View suites
SELECT id, name, status, subscription_tiers 
FROM suites;

-- View agents
SELECT id, name, model_provider, model_name, is_active 
FROM agents;

-- Count records
SELECT 
  (SELECT COUNT(*) FROM users) as users,
  (SELECT COUNT(*) FROM suites) as suites,
  (SELECT COUNT(*) FROM agents) as agents,
  (SELECT COUNT(*) FROM chats) as chats;
```

---

## 🐛 Troubleshooting

### "Could not connect to server"

**Solution:** Make sure you're using `db` as the hostname, not `localhost` or `127.0.0.1`

### "Password authentication failed"

**Solution:** 
- Username: `laravel`
- Password: `laravel`
- Make sure the database container is running: `docker-compose ps db`

### "Server doesn't listen"

**Solution:**
```bash
# Check if PostgreSQL is running
docker-compose ps db

# Restart if needed
docker-compose restart db
```

---

## ✅ Verify Connection

After connecting, you should see:
- ✅ Green connection indicator
- ✅ Database "laravel" listed
- ✅ All tables visible under "Tables"

---

## 🎯 Quick Access

**pgAdmin URL:** http://localhost:8080  
**Login:** admin@admin.com / admin  
**Database Host:** `db`  
**Database:** `laravel`  
**User:** `laravel`  
**Password:** `laravel`

That's it! 🎉





