# How to Access the AI Control Hub

## 🌐 Web Browser Access

### React Frontend (Development)
**URL:** http://localhost:5173

The React development server is running on port **5173** and is accessible from your web browser.

### Laravel Backend API
**URL:** http://localhost:8000

The Laravel API is running on port **8000**.

---

## 📋 Quick Access Guide

### 1. Open Your Web Browser
Open any modern web browser (Chrome, Firefox, Safari, Edge).

### 2. Navigate to the React App
Type in the address bar:
```
http://localhost:5173
```

### 3. You Should See
- **Login Page** - Enter your JWT token to authenticate
- After login, you'll be redirected to either:
  - **Admin Dashboard** (if you have admin role)
  - **User Dashboard** (if you have user role)

---

## 🔐 Test Credentials

If you want to test with the seeded users (via Laravel web routes, not JWT):

- **Admin:** admin@aihub.com / password
- **User:** user@aihub.com / password

**Note:** For the React app, you need a JWT token from your main platform.

---

## 🛠️ Troubleshooting

### If you see "Connection Refused" or "Can't Connect"

1. **Check if containers are running:**
   ```bash
   docker-compose ps
   ```
   All services should show "Up"

2. **Check if Vite is running:**
   ```bash
   docker-compose logs app | grep vite
   ```
   Should show "vite entered RUNNING state"

3. **Restart the dev server:**
   ```bash
   docker-compose restart app
   ```

4. **Check port 5173 is accessible:**
   ```bash
   curl http://localhost:5173
   ```
   Should return HTML content

### If you see a blank page

1. **Open browser developer console** (F12)
2. **Check for errors** in the Console tab
3. **Check Network tab** to see if files are loading

### If API calls fail

The React app proxies `/api` requests to `http://app:80` (the Laravel container).

Make sure:
- Laravel is running on port 8000
- The proxy is configured correctly in `vite.config.js`

---

## 🔄 Alternative: Access via Laravel (Port 8000)

If you want to use the existing Laravel + Inertia.js setup:

**URL:** http://localhost:8000

This serves the Vue.js frontend that was already in the project.

---

## 📱 Port Summary

| Service | Port | URL |
|---------|------|-----|
| React Dev Server | 5173 | http://localhost:5173 |
| Laravel API | 8000 | http://localhost:8000 |
| PostgreSQL | 5432 | localhost:5432 |
| Chroma Vector DB | 8001 | http://localhost:8001 |
| pgAdmin | 8080 | http://localhost:8080 |
| Redis | 6379 | localhost:6379 |

---

## ✅ Quick Test

1. Open browser: http://localhost:5173
2. You should see the **AI Control Hub** login page
3. Enter a JWT token to proceed

That's it! 🎉





