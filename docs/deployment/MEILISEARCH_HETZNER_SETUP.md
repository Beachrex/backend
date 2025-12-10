# Meilisearch Deployment on Hetzner

This guide walks you through setting up a dedicated Meilisearch server on Hetzner for the BeachRex API.

## Table of Contents
- [Why Separate Server?](#why-separate-server)
- [Server Requirements](#server-requirements)
- [Step 1: Create Hetzner Server](#step-1-create-hetzner-server)
- [Step 2: Initial Server Setup](#step-2-initial-server-setup)
- [Step 3: Install Meilisearch](#step-3-install-meilisearch)
- [Step 4: Configure Meilisearch](#step-4-configure-meilisearch)
- [Step 5: Setup Firewall](#step-5-setup-firewall)
- [Step 6: Setup SSL with Nginx](#step-6-setup-ssl-with-nginx)
- [Step 7: Configure Laravel API](#step-7-configure-laravel-api)
- [Step 8: Index Data](#step-8-index-data)
- [Monitoring & Maintenance](#monitoring--maintenance)
- [Backup Strategy](#backup-strategy)
- [Troubleshooting](#troubleshooting)

---

## Why Separate Server?

✅ **Dedicated Resources** - Search operations don't compete with API/database  
✅ **Independent Scaling** - Scale search independently from main infrastructure  
✅ **Better Security** - Isolated network, separate firewall rules  
✅ **Cost Efficient** - Hetzner pricing is very competitive  
✅ **High Availability** - Main API continues working if search is down  

---

## Server Requirements

### Recommended Hetzner Server Specs

**For Production (recommended):**
- **Server Type:** CPX22 (or CPX21/CX31 if available)
- **vCPU:** 2-4 cores
- **RAM:** 4-8 GB
- **Storage:** 80-160 GB SSD
- **Cost:** ~€5-15/month
- **Location:** Choose closest to your main API server (e.g., Nuremberg, Helsinki, Falkenstein)

**For Small/Medium Sites:**
- **Server Type:** CPX11 or CPX22
- **vCPU:** 2 cores
- **RAM:** 2-4 GB
- **Storage:** 40-80 GB SSD
- **Cost:** ~€4-8/month

**Note:** Hetzner's CPX22 (2 vCPU, 4GB RAM, 80GB SSD) is often the lowest available option and works perfectly for most use cases.

**Current Index Size Estimate:**
- Beaches: ~3,000 documents
- Cities: ~1,000 documents
- Islands: ~30 documents
- Regions: ~75 documents
- Countries: ~15 documents
- **Total:** ~4,120 documents (~50-100 MB indexed data)

---

## Step 1: Create Hetzner Server

### 1.1 Create Server via Hetzner Cloud Console

1. Go to [Hetzner Cloud Console](https://console.hetzner.cloud/)
2. Click **"New Project"** → Name it `beachrex-search` or similar
3. Click **"Add Server"**
4. Choose:
   - **Location:** Nuremberg (or closest to your main API)
   - **Image:** Ubuntu 24.04 LTS
   - **Type:** CPX21 (3 vCPU, 4GB RAM, 80GB SSD)
   - **Networking:** 
     - Enable IPv4 and IPv6
     - Create new SSH key or use existing
   - **Firewall:** We'll configure this later
   - **Backups:** Optional (recommended for production)
   - **Name:** `meilisearch-prod`

5. Click **"Create & Buy Now"**

### 1.2 Note Server Details

After creation, note down:
- **Public IPv4:** e.g., `95.217.123.45`
- **Public IPv6:** e.g., `2a01:4f8:c2c:abcd::1`
- **Server Name:** `meilisearch-prod`

---

## Step 2: Initial Server Setup

### 2.1 Connect to Server

```bash
# SSH into your server (replace with your IP)
ssh root@95.217.123.45
```

### 2.2 Update System

```bash
# Update package lists
apt update

# Upgrade all packages
apt upgrade -y

# Install essential tools
apt install -y curl wget git ufw nginx certbot python3-certbot-nginx
```

### 2.3 Stay as Root for Installation

**⚠️ IMPORTANT:** For the installation steps, stay logged in as **root**. Do NOT create a non-root user yet or switch users. The meilisearch system user will be created automatically in the next step.

If you accidentally created a user and switched to it, type `exit` to return to root.

```bash
# Verify you're root
whoami
# Should output: root
```

---

## Step 3: Install Meilisearch

### 3.1 Install via Official Script

**Make sure you're logged in as root before running these commands.**

```bash
# Verify you're root
whoami
# Should output: root

# Download and install Meilisearch
curl -L https://install.meilisearch.com | sh

# Move binary to system path (no sudo needed as root)
mv ./meilisearch /usr/local/bin/

# Make executable
chmod +x /usr/local/bin/meilisearch

# Verify installation
meilisearch --version
# Should output: meilisearch 1.28.1 or newer
```

### 3.2 Create Meilisearch User and Directories

```bash
# Create system user for Meilisearch
sudo useradd -r -s /bin/false -d /var/lib/meilisearch meilisearch

# Create data directory
sudo mkdir -p /var/lib/meilisearch/data
sudo mkdir -p /var/lib/meilisearch/dumps

# Set permissions
sudo chown -R meilisearch:meilisearch /var/lib/meilisearch
```

---

## Step 4: Configure Meilisearch

### 4.1 Generate Master Key

```bash
# Generate a secure random key (save this!)
openssl rand -base64 32
# Example output: Kx9vJ2mP8nQ5rT6wY3zA1bC4dE7fG0hI2jK5lM8nO9pQ=
```

**⚠️ IMPORTANT:** Save this key securely! You'll need it for:
- Meilisearch configuration
- Laravel API configuration
- API requests

### 4.2 Create Systemd Service

```bash
sudo nano /etc/systemd/system/meilisearch.service
```

Paste the following configuration:

```ini
[Unit]
Description=Meilisearch
After=network.target

[Service]
Type=simple
User=meilisearch
Group=meilisearch
WorkingDirectory=/var/lib/meilisearch

# Environment variables
Environment="MEILI_HTTP_ADDR=127.0.0.1:7700"
Environment="MEILI_MASTER_KEY=YOUR_MASTER_KEY_HERE"
Environment="MEILI_ENV=production"
Environment="MEILI_DB_PATH=/var/lib/meilisearch/data"
Environment="MEILI_DUMP_DIR=/var/lib/meilisearch/dumps"
Environment="MEILI_NO_ANALYTICS=true"
Environment="MEILI_LOG_LEVEL=INFO"
Environment="MEILI_MAX_INDEXING_MEMORY=2GB"
Environment="MEILI_MAX_INDEXING_THREADS=2"

ExecStart=/usr/local/bin/meilisearch

# Restart policy
Restart=on-failure
RestartSec=5s

# Security hardening
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/lib/meilisearch

[Install]
WantedBy=multi-user.target
```

**Replace `YOUR_MASTER_KEY_HERE`** with the key you generated in step 4.1.

### 4.3 Start Meilisearch Service

```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable Meilisearch to start on boot
sudo systemctl enable meilisearch

# Start Meilisearch
sudo systemctl start meilisearch

# Check status
sudo systemctl status meilisearch

# View logs
sudo journalctl -u meilisearch -f
```

### 4.4 Verify Meilisearch is Running

```bash
# Test locally
curl http://127.0.0.1:7700/health
# Should return: {"status":"available"}
```

---

## Step 5: Setup Firewall

### 5.1 Configure UFW (Uncomplicated Firewall)

```bash
# Allow SSH (IMPORTANT: Do this first!)
sudo ufw allow 22/tcp

# Allow HTTP and HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status
```

**Note:** We're NOT opening port 7700 directly. Meilisearch will be accessed via Nginx reverse proxy on port 443 (HTTPS).

---

## Step 6: Setup SSL with Nginx

### 6.1 Create DNS Record

Before setting up SSL, create an A record in your DNS provider.

#### If Using Cloudflare:

1. Log into [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Select your domain (e.g., `beachrex.com`)
3. Click **DNS** in the left menu
4. Click **Add record**
5. Fill in:
   ```
   Type: A
   Name: search
   IPv4 address: YOUR_SERVER_IP (e.g., 46.224.101.110)
   Proxy status: DNS only (gray cloud ☁️, NOT orange cloud 🟠)
   TTL: Auto
   ```
6. Click **Save**

**⚠️ IMPORTANT:** Make sure **Proxy status** is set to **DNS only** (gray cloud), NOT **Proxied** (orange cloud). This is required for Let's Encrypt SSL verification.

#### If Using Other DNS Provider:

Create an A record:
```
Type: A
Name: search (or meilisearch)
Value: YOUR_SERVER_IP (e.g., 46.224.101.110)
TTL: 300
```

This will create: `search.beachrex.com` (or `meilisearch.beachrex.com`)

#### Verify DNS Propagation

Wait 1-2 minutes, then test from your local machine:

```bash
# On your Mac/local terminal (not the server)
nslookup search.beachrex.com

# Should show your server IP
```

Wait for DNS propagation before continuing (usually 1-5 minutes).

### 6.2 Configure Nginx (HTTP Only First)

**⚠️ IMPORTANT:** We'll configure Nginx for HTTP first, then let Certbot add SSL automatically. This avoids the chicken-and-egg problem of referencing SSL certificates that don't exist yet.

```bash
sudo nano /etc/nginx/sites-available/meilisearch
```

Paste the following **simple HTTP-only** configuration:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name search.beachrex.com;

    location / {
        proxy_pass http://127.0.0.1:7700;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

**Save and exit:** Press `Ctrl+X`, then `Y`, then `Enter`

**Note:** Certbot will automatically add the SSL configuration and HTTPS redirect in the next step.

### 6.3 Enable Site and Test HTTP

```bash
# Enable the site
sudo ln -s /etc/nginx/sites-available/meilisearch /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test Nginx configuration
sudo nginx -t
# Should show: syntax is ok, test is successful

# Reload Nginx
sudo systemctl reload nginx

# Test HTTP connection (should work now)
curl http://search.beachrex.com/health
# Should return: {"status":"available"}
```

### 6.4 Get SSL Certificate

Now that HTTP is working, let Certbot add SSL automatically:

```bash
# Get SSL certificate from Let's Encrypt
sudo certbot --nginx -d search.beachrex.com
```

**Follow the prompts:**
1. Enter your email address (for renewal notifications)
2. Agree to terms of service: `Y`
3. Share email with EFF (optional): `Y` or `N`
4. Certbot will automatically:
   - Obtain the SSL certificate
   - Update Nginx configuration with SSL settings
   - Add HTTPS redirect
   - Reload Nginx

**Expected output:**
```
Successfully received certificate.
Certificate is saved at: /etc/letsencrypt/live/search.beachrex.com/fullchain.pem
Key is saved at:         /etc/letsencrypt/live/search.beachrex.com/privkey.pem
This certificate expires on 2026-03-01.
These files will be updated when the certificate renews.
Certbot has set up a scheduled task to automatically renew this certificate in the background.

Deploying certificate
Successfully deployed certificate for search.beachrex.com to /etc/nginx/sites-enabled/meilisearch
Congratulations! You have successfully enabled HTTPS on https://search.beachrex.com
```

### 6.5 Test SSL

```bash
# Test HTTPS endpoint
curl https://search.beachrex.com/health
# Should return: {"status":"available"}

# Test HTTP redirect (should redirect to HTTPS)
curl -I http://search.beachrex.com/health
# Should show: 301 Moved Permanently, Location: https://...
```

---

## Step 7: Configure Laravel API

### 7.1 Update .env on Laravel Cloud

Update your Laravel Cloud environment variables (or `.env` file):

```env
# Scout Configuration
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
SCOUT_PREFIX=

# Meilisearch Configuration
MEILISEARCH_HOST=https://search.beachrex.com
MEILISEARCH_KEY=YOUR_MASTER_KEY_HERE
```

**Replace:**
- `search.beachrex.com` with your actual domain
- `YOUR_MASTER_KEY_HERE` with the master key from step 4.1

### 7.2 Test Connection from Laravel

```bash
# SSH into your Laravel Cloud server
# Or run locally if testing

# Test Meilisearch connection
php artisan tinker

# In tinker:
use MeiliSearch\Client;
$client = new Client(env('MEILISEARCH_HOST'), env('MEILISEARCH_KEY'));
$client->health();
// Should return: ["status" => "available"]
```

---

## Step 8: Index Data

### 8.1 Initial Indexing

```bash
# On your Laravel server (Laravel Cloud or local)

# Index all searchable models
php artisan search:index

# This will index:
# - Beaches (~3,000 documents)
# - Cities (~1,000 documents)
# - Islands (~30 documents)
# - Regions (~75 documents)
# - Countries (~15 documents)

# Expected output:
# Indexing beaches...
# Indexed 3000 beaches
# Indexing cities...
# Indexed 1000 cities
# ...
```

### 8.2 Verify Indexing

```bash
# Check indexes via API
curl -H "Authorization: Bearer YOUR_MASTER_KEY" \
  https://search.beachrex.com/indexes

# Should return list of indexes:
# - beaches
# - cities
# - islands
# - regions
# - countries
```

### 8.3 Test Search

```bash
# Test search endpoint from your API
curl -H "Accept-Language: en" \
  "https://api.beachrex.com/api/v1/search?q=miami&limit=5"

# Should return search results
```

---

## Monitoring & Maintenance

### Monitor Meilisearch Service

```bash
# Check service status
sudo systemctl status meilisearch

# View real-time logs
sudo journalctl -u meilisearch -f

# View last 100 lines
sudo journalctl -u meilisearch -n 100

# Check resource usage
htop
# Look for meilisearch process
```

### Monitor Disk Usage

```bash
# Check disk usage
df -h

# Check Meilisearch data directory size
du -sh /var/lib/meilisearch/data
```

### Monitor via Meilisearch API

```bash
# Get stats
curl -H "Authorization: Bearer YOUR_MASTER_KEY" \
  https://search.beachrex.com/stats

# Get index stats
curl -H "Authorization: Bearer YOUR_MASTER_KEY" \
  https://search.beachrex.com/indexes/beaches/stats
```

---

## Backup Strategy

### Automated Dumps

Create a cron job to backup Meilisearch data:

```bash
# Create backup script
sudo nano /usr/local/bin/meilisearch-backup.sh
```

Paste:

```bash
#!/bin/bash
# Meilisearch Backup Script

MASTER_KEY="YOUR_MASTER_KEY_HERE"
BACKUP_DIR="/var/backups/meilisearch"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Create dump
curl -X POST "http://127.0.0.1:7700/dumps" \
  -H "Authorization: Bearer $MASTER_KEY"

# Wait for dump to complete
sleep 30

# Copy dump to backup directory
cp /var/lib/meilisearch/dumps/*.dump $BACKUP_DIR/meilisearch_$DATE.dump

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.dump" -mtime +7 -delete

echo "Backup completed: meilisearch_$DATE.dump"
```

Make executable and schedule:

```bash
# Make executable
sudo chmod +x /usr/local/bin/meilisearch-backup.sh

# Add to crontab (daily at 2 AM)
sudo crontab -e

# Add line:
0 2 * * * /usr/local/bin/meilisearch-backup.sh >> /var/log/meilisearch-backup.log 2>&1
```

---

## Troubleshooting

### Meilisearch Won't Start

```bash
# Check logs
sudo journalctl -u meilisearch -n 50

# Common issues:
# 1. Port already in use
sudo lsof -i :7700

# 2. Permission issues
sudo chown -R meilisearch:meilisearch /var/lib/meilisearch

# 3. Invalid master key
# Check /etc/systemd/system/meilisearch.service
```

### Can't Connect from Laravel

```bash
# Test from Laravel server
curl https://search.beachrex.com/health

# If fails:
# 1. Check DNS
nslookup search.beachrex.com

# 2. Check firewall
sudo ufw status

# 3. Check Nginx
sudo nginx -t
sudo systemctl status nginx
```

### Search Not Working

```bash
# Check if indexes exist
curl -H "Authorization: Bearer YOUR_MASTER_KEY" \
  https://search.beachrex.com/indexes

# Re-index if needed
php artisan search:index --flush
```

---

## Security Checklist

- ✅ Meilisearch runs as non-root user
- ✅ Master key is strong and secure
- ✅ Meilisearch only listens on localhost (127.0.0.1)
- ✅ Access only via HTTPS through Nginx
- ✅ Firewall configured (UFW)
- ✅ SSL certificate from Let's Encrypt
- ✅ Regular backups configured
- ✅ System updates automated

---

## Cost Estimate

**Hetzner CPX21 Server:**
- Monthly: €8.21
- Yearly: ~€98

**Total Infrastructure:**
- Meilisearch Server: €8.21/month
- Very cost-effective compared to managed search services!

---

## Next Steps

1. ✅ Create Hetzner server
2. ✅ Install and configure Meilisearch
3. ✅ Setup SSL with Nginx
4. ✅ Configure Laravel API
5. ✅ Index all data
6. ✅ Setup monitoring and backups
7. 🔄 Test search functionality
8. 🔄 Monitor performance
9. 🔄 Setup alerts (optional)

---

## Support

- **Meilisearch Docs:** https://www.meilisearch.com/docs
- **Hetzner Docs:** https://docs.hetzner.com/
- **Laravel Scout:** https://laravel.com/docs/scout

---

**Last Updated:** 2025-12-01

