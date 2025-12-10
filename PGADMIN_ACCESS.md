# pgAdmin Access Guide

## 🌐 Accessing pgAdmin

### URL
**http://localhost:8080**

### Login Credentials
- **Email:** `admin@admin.com`
- **Password:** `admin`

---

## 🔧 Setting Up Database Connection in pgAdmin

After logging into pgAdmin:

1. **Right-click on "Servers"** in the left sidebar
2. **Select "Register" → "Server"**

3. **General Tab:**
   - **Name:** `Laravel Database` (or any name you prefer)

4. **Connection Tab:**
   - **Host name/address:** `db` (this is the Docker service name)
   - **Port:** `5432`
   - **Maintenance database:** `laravel`
   - **Username:** `laravel`
   - **Password:** `laravel`
   - **Save password:** ✓ (check this box)

5. **Click "Save"**

---

## 🐛 Troubleshooting

### If you see "redirect to /main"

This is normal! pgAdmin redirects to `/browser` or `/main` after login. Try:

1. **Clear your browser cache**
2. **Access directly:** http://localhost:8080/browser
3. **Or wait a few seconds** after accessing http://localhost:8080

### If pgAdmin won't load

1. **Check if container is running:**
   ```bash
   docker-compose ps pgadmin
   ```

2. **Check logs:**
   ```bash
   docker-compose logs pgadmin
   ```

3. **Restart pgAdmin:**
   ```bash
   docker-compose restart pgadmin
   ```

### If connection to database fails

Make sure:
- PostgreSQL container (`db`) is running
- You're using `db` as the hostname (not `localhost`)
- Port is `5432`
- Credentials match your `.env` file

---

## 🔍 Alternative: Use psql Command Line

If pgAdmin doesn't work, you can use the command line:

```bash
# Connect to PostgreSQL
docker-compose exec db psql -U laravel -d laravel

# Then run SQL commands:
\dt                    # List all tables
SELECT * FROM users;   # Query users table
\q                     # Quit
```

---

## 📊 Quick Database Queries

Once connected in pgAdmin, you can run:

```sql
-- List all tables
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'public';

-- Count users
SELECT COUNT(*) FROM users;

-- View suites
SELECT * FROM suites;

-- View agents
SELECT * FROM agents;
```

---

## ✅ Verification

To verify pgAdmin is working:

1. Go to: http://localhost:8080
2. Login with: `admin@admin.com` / `admin`
3. Register server with hostname: `db`
4. You should see the `laravel` database
5. Expand it to see all tables

That's it! 🎉





