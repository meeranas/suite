# LiteSpeed Web Server Integration Guide

Since port 80 is already used by LiteSpeed on your server, the AI Suite application runs on port **8080** by default (configurable via `APP_PORT` in `.env`).

## Configuration Options

### Option 1: Use Custom Port (Recommended)

The application is configured to run on port **8080** by default. You can change this in your `.env` file:

```bash
APP_PORT=8080
```

Then access the application at:
```
http://YOUR_SERVER_IP:8080
```

### Option 2: Configure LiteSpeed as Reverse Proxy (Recommended for Production)

Set up LiteSpeed to proxy requests to the Docker container on port 8080.

#### Step 1: Access LiteSpeed Admin Panel

1. Go to: `https://YOUR_SERVER_IP:7080` (or your configured admin port)
2. Login with your LiteSpeed admin credentials

#### Step 2: Create a Virtual Host

1. Navigate to **Virtual Hosts** → **Add**
2. Set the following:
   - **Virtual Host Name**: `ai-suite` (or your preferred name)
   - **Domain**: `yourdomain.com` (or `*` for all domains)
   - **Document Root**: `/usr/local/lsws/Example/html` (or your preferred path)

#### Step 3: Configure Proxy

1. Go to your Virtual Host → **Actions** → **View/Edit**
2. Navigate to **Script Handler** tab
3. Add a new script handler:
   - **Suffixes**: `*`
   - **Handler Type**: `Proxy`
   - **Handler Name**: `proxy`
   - **Handler**: `http://127.0.0.1:8080`

4. Or configure via **Rewrite** tab:
   - Enable **Rewrite**
   - Add rewrite rule:
   ```
   RewriteRule ^(.*)$ http://127.0.0.1:8080$1 [P,L]
   ```

#### Step 4: Configure SSL (Optional but Recommended)

1. Go to **SSL** tab
2. Enable SSL
3. Set your SSL certificate paths:
   - **Private Key File**: `/path/to/private.key`
   - **Certificate File**: `/path/to/certificate.crt`
   - **CA Certificate File**: `/path/to/ca.crt` (if applicable)

4. Or use Let's Encrypt:
   ```bash
   # Install certbot
   sudo apt-get install certbot

   # Get certificate
   sudo certbot certonly --standalone -d yourdomain.com

   # Certificate will be at:
   # /etc/letsencrypt/live/yourdomain.com/privkey.pem
   # /etc/letsencrypt/live/yourdomain.com/fullchain.pem
   ```

#### Step 5: Update .env File

Set your domain in the `.env` file:

```bash
APP_URL=https://yourdomain.com
```

#### Step 6: Restart LiteSpeed

```bash
sudo systemctl restart lsws
# or
/usr/local/lsws/bin/lswsctrl restart
```

### Option 3: Use Different Port

If you prefer a different port, update your `.env`:

```bash
APP_PORT=3000
```

Then restart the Docker containers:

```bash
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml up -d
```

## LiteSpeed Configuration File (Alternative Method)

You can also configure LiteSpeed by editing the configuration file directly:

### Location
```
/usr/local/lsws/conf/vhosts/ai-suite/vhost.conf
```

### Configuration Example

```xml
<virtualHost ai-suite>
  <name>ai-suite</name>
  <domain>yourdomain.com</domain>
  <docRoot>/usr/local/lsws/Example/html</docRoot>
  
  <scriptHandler>
    <type>proxy</type>
    <uri>*</uri>
    <handler>http://127.0.0.1:8080</handler>
  </scriptHandler>
  
  <rewrite>
    <enable>1</enable>
    <rewriteRule>
      <from>^.*$</from>
      <to>http://127.0.0.1:8080$0</to>
      <type>P</type>
    </rewriteRule>
  </rewrite>
  
  <ssl>
    <keyFile>/etc/letsencrypt/live/yourdomain.com/privkey.pem</keyFile>
    <certFile>/etc/letsencrypt/live/yourdomain.com/fullchain.pem</certFile>
    <certChain>1</certChain>
  </ssl>
</virtualHost>
```

After editing, restart LiteSpeed:
```bash
/usr/local/lsws/bin/lswsctrl restart
```

## Testing the Setup

### 1. Test Direct Access (Port 8080)

```bash
curl http://localhost:8080
```

### 2. Test via LiteSpeed (Port 80/443)

```bash
curl http://yourdomain.com
# or
curl https://yourdomain.com
```

### 3. Check Docker Container

```bash
# Verify container is running
docker-compose -f docker-compose.prod.yml ps

# Check logs
docker-compose -f docker-compose.prod.yml logs -f app
```

## Troubleshooting

### Issue: Can't access on port 8080

**Solution:**
1. Check if port 8080 is available:
   ```bash
   sudo netstat -tulpn | grep :8080
   ```

2. Check firewall:
   ```bash
   sudo ufw allow 8080/tcp
   ```

3. Verify Docker container is running:
   ```bash
   docker-compose -f docker-compose.prod.yml ps
   ```

### Issue: LiteSpeed proxy not working

**Solution:**
1. Check LiteSpeed error logs:
   ```bash
   tail -f /usr/local/lsws/logs/error.log
   ```

2. Verify proxy configuration in LiteSpeed admin panel

3. Test direct connection to Docker:
   ```bash
   curl http://127.0.0.1:8080
   ```

4. Check if LiteSpeed can reach the container:
   ```bash
   # From LiteSpeed server
   curl http://localhost:8080
   ```

### Issue: SSL not working

**Solution:**
1. Verify certificate paths in LiteSpeed admin
2. Check certificate permissions:
   ```bash
   sudo chmod 644 /etc/letsencrypt/live/yourdomain.com/fullchain.pem
   sudo chmod 600 /etc/letsencrypt/live/yourdomain.com/privkey.pem
   ```
3. Restart LiteSpeed after SSL configuration

## Quick Reference

| Service | Port | Access |
|---------|------|--------|
| AI Suite (Docker) | 8080 | `http://SERVER_IP:8080` |
| LiteSpeed | 80/443 | `http://yourdomain.com` |
| LiteSpeed Admin | 7080 | `https://SERVER_IP:7080` |
| PostgreSQL | 5432 | Internal only |
| Redis | 6379 | Internal only |
| ChromaDB | 8001 | Internal only |

## Environment Variables

Make sure these are set in your `.env` file:

```bash
# Application port (default: 8080)
APP_PORT=8080

# Application URL (use your domain if using LiteSpeed proxy)
APP_URL=https://yourdomain.com

# Or if accessing directly:
APP_URL=http://YOUR_SERVER_IP:8080
```

## Next Steps

1. ✅ Configure LiteSpeed as reverse proxy (Option 2)
2. ✅ Set up SSL certificate
3. ✅ Update `APP_URL` in `.env`
4. ✅ Test access via domain
5. ✅ Configure firewall rules
6. ✅ Set up monitoring

