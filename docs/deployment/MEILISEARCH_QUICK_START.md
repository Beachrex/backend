# Meilisearch Quick Start Guide

This is a condensed version of the full deployment guide. For detailed instructions, see [MEILISEARCH_HETZNER_SETUP.md](./MEILISEARCH_HETZNER_SETUP.md).

## Prerequisites

- Hetzner Cloud account
- Domain name (e.g., `search.beachrex.com`)
- SSH access

## Quick Setup (30 minutes)

### 1. Create Hetzner Server (5 min)

```bash
# Server specs:
- Type: CPX22 (2 vCPU, 4GB RAM, 80GB SSD) - often the lowest option
- Image: Ubuntu 24.04 LTS
- Location: Nuremberg (or closest to your API)
- Cost: ~€5-8/month
```

### 2. Initial Setup (5 min)

```bash
# SSH into server
ssh root@YOUR_SERVER_IP

# Update system
apt update && apt upgrade -y

# Install dependencies
apt install -y curl wget ufw nginx certbot python3-certbot-nginx
```

### 3. Install Meilisearch (5 min)

**⚠️ Make sure you're logged in as root (not another user)**

```bash
# Verify you're root
whoami  # Should output: root

# Install Meilisearch
curl -L https://install.meilisearch.com | sh
mv ./meilisearch /usr/local/bin/
chmod +x /usr/local/bin/meilisearch

# Create user and directories
sudo useradd -r -s /bin/false -d /var/lib/meilisearch meilisearch
sudo mkdir -p /var/lib/meilisearch/{data,dumps}
sudo chown -R meilisearch:meilisearch /var/lib/meilisearch

# Generate master key (SAVE THIS!)
openssl rand -base64 32
```

### 4. Configure Systemd Service (5 min)

```bash
sudo nano /etc/systemd/system/meilisearch.service
```

Paste (replace `YOUR_MASTER_KEY`):

```ini
[Unit]
Description=Meilisearch
After=network.target

[Service]
Type=simple
User=meilisearch
Group=meilisearch
WorkingDirectory=/var/lib/meilisearch
Environment="MEILI_HTTP_ADDR=127.0.0.1:7700"
Environment="MEILI_MASTER_KEY=YOUR_MASTER_KEY_HERE"
Environment="MEILI_ENV=production"
Environment="MEILI_DB_PATH=/var/lib/meilisearch/data"
Environment="MEILI_DUMP_DIR=/var/lib/meilisearch/dumps"
Environment="MEILI_NO_ANALYTICS=true"
Environment="MEILI_LOG_LEVEL=INFO"
Environment="MEILI_MAX_INDEXING_MEMORY=2GB"
ExecStart=/usr/local/bin/meilisearch
Restart=on-failure
RestartSec=5s
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/lib/meilisearch

[Install]
WantedBy=multi-user.target
```

Start service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable meilisearch
sudo systemctl start meilisearch
sudo systemctl status meilisearch
```

### 5. Setup Firewall (2 min)

```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 6. Configure DNS (2 min)

**If using Cloudflare:**
1. Go to Cloudflare Dashboard → DNS
2. Add record:
   - Type: A
   - Name: search
   - IPv4: YOUR_SERVER_IP
   - Proxy: DNS only (gray cloud ☁️, NOT orange 🟠)
   - TTL: Auto

**If using other DNS:**
```
Type: A
Name: search
Value: YOUR_SERVER_IP
TTL: 300
```

Verify: `nslookup search.beachrex.com` (wait 1-2 min for propagation)

### 7. Setup Nginx + SSL (5 min)

**Create HTTP-only config first:**

```bash
sudo nano /etc/nginx/sites-available/meilisearch
```

Paste (replace `search.beachrex.com`):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name search.beachrex.com;

    location / {
        proxy_pass http://127.0.0.1:7700;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**Enable site and get SSL:**

```bash
sudo ln -s /etc/nginx/sites-available/meilisearch /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx

# Test HTTP works first
curl http://search.beachrex.com/health

# Now get SSL (Certbot will add HTTPS config automatically)
sudo certbot --nginx -d search.beachrex.com
# Follow prompts: enter email, agree to terms
```

### 8. Configure Laravel (3 min)

Update `.env` on Laravel Cloud:

```env
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=https://search.beachrex.com
MEILISEARCH_KEY=YOUR_MASTER_KEY_HERE
```

### 9. Index Data (5 min)

```bash
# On Laravel server
php artisan search:index

# Wait for indexing to complete
# Expected: ~4,000 documents indexed
```

### 10. Test (2 min)

```bash
# Test Meilisearch health
curl https://search.beachrex.com/health

# Test search API
curl -H "Accept-Language: en" \
  "https://api.beachrex.com/api/v1/search?q=miami&limit=5"
```

## Verification Checklist

- [ ] Meilisearch service running: `sudo systemctl status meilisearch`
- [ ] Health check works: `curl https://search.beachrex.com/health`
- [ ] SSL certificate valid: `curl -I https://search.beachrex.com`
- [ ] Firewall configured: `sudo ufw status`
- [ ] Indexes created: Check via API or Laravel
- [ ] Search API works: Test `/api/v1/search` endpoint
- [ ] Logs clean: `sudo journalctl -u meilisearch -n 50`

## Common Commands

```bash
# Service management
sudo systemctl status meilisearch
sudo systemctl restart meilisearch
sudo journalctl -u meilisearch -f

# Re-index data
php artisan search:index --flush

# Check indexes
curl -H "Authorization: Bearer YOUR_KEY" \
  https://search.beachrex.com/indexes

# View stats
curl -H "Authorization: Bearer YOUR_KEY" \
  https://search.beachrex.com/stats

# Backup
curl -X POST -H "Authorization: Bearer YOUR_KEY" \
  https://search.beachrex.com/dumps
```

## Troubleshooting

**Service won't start:**
```bash
sudo journalctl -u meilisearch -n 50
sudo chown -R meilisearch:meilisearch /var/lib/meilisearch
```

**Can't connect from Laravel:**
```bash
# Test DNS
nslookup search.beachrex.com

# Test connection
curl https://search.beachrex.com/health

# Check Laravel config
php artisan config:clear
php artisan cache:clear
```

**Search returns no results:**
```bash
# Re-index
php artisan search:index --flush

# Check indexes
curl -H "Authorization: Bearer YOUR_KEY" \
  https://search.beachrex.com/indexes
```

## Security Notes

- ✅ Meilisearch only accessible via HTTPS
- ✅ Master key required for all operations
- ✅ Firewall blocks direct access to port 7700
- ✅ Service runs as non-root user
- ✅ SSL certificate auto-renews

## Monitoring

```bash
# Resource usage
htop

# Disk usage
df -h
du -sh /var/lib/meilisearch/data

# Service logs
sudo journalctl -u meilisearch -f

# Nginx logs
sudo tail -f /var/log/nginx/meilisearch-access.log
sudo tail -f /var/log/nginx/meilisearch-error.log
```

## Backup

```bash
# Manual backup
curl -X POST -H "Authorization: Bearer YOUR_KEY" \
  https://search.beachrex.com/dumps

# Backup file location
ls -lh /var/lib/meilisearch/dumps/

# Copy to safe location
scp root@YOUR_SERVER_IP:/var/lib/meilisearch/dumps/*.dump ./backups/
```

## Cost

- **Hetzner CPX21:** €8.21/month
- **Total yearly:** ~€98
- **Bandwidth:** Included (20TB)

## Next Steps

1. Setup automated backups (cron job)
2. Configure monitoring/alerts
3. Test failover scenarios
4. Document recovery procedures
5. Setup staging environment (optional)

## Support Resources

- Full Guide: [MEILISEARCH_HETZNER_SETUP.md](./MEILISEARCH_HETZNER_SETUP.md)
- Meilisearch Docs: https://www.meilisearch.com/docs
- Hetzner Docs: https://docs.hetzner.com/
- Laravel Scout: https://laravel.com/docs/scout

---

**Estimated Setup Time:** 30-40 minutes  
**Difficulty:** Intermediate  
**Last Updated:** 2025-12-01

