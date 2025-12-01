# Meilisearch Deployment - Common Issues & Solutions

This document covers common issues encountered during Meilisearch deployment on Hetzner and their solutions.

## Table of Contents
- [Installation Issues](#installation-issues)
- [SSL/Certificate Issues](#sslcertificate-issues)
- [DNS Issues](#dns-issues)
- [Permission Issues](#permission-issues)
- [Firewall Issues](#firewall-issues)

---

## Installation Issues

### Issue 1: "meilisearch is not in the sudoers file"

**Symptom:**
```bash
$ sudo mv ./meilisearch /usr/local/bin/
[sudo] password for meilisearch: 
meilisearch is not in the sudoers file.
```

**Cause:** You're logged in as a non-root user (e.g., `meilisearch` user) that doesn't have sudo privileges.

**Solution:**
```bash
# Exit from the current user back to root
exit

# Verify you're root
whoami
# Should output: root

# Now continue with installation
mv ./meilisearch /usr/local/bin/
chmod +x /usr/local/bin/meilisearch
```

**Prevention:** Stay logged in as **root** during the entire installation process. Only create the meilisearch system user for running the service, don't log in as that user.

---

## SSL/Certificate Issues

### Issue 2: Nginx Can't Load SSL Certificate (Doesn't Exist Yet)

**Symptom:**
```bash
$ sudo certbot --nginx -d search.beachrex.com
2025/12/01 18:28:06 [emerg] cannot load certificate "/etc/letsencrypt/live/search.beachrex.com/fullchain.pem": 
BIO_new_file() failed (SSL: error:80000002:system library::No such file or directory)
nginx: configuration file /etc/nginx/nginx.conf test failed

The nginx plugin is not working; there may be problems with your existing configuration.
```

**Cause:** Your Nginx configuration references SSL certificate files that don't exist yet. This is a chicken-and-egg problem - Certbot needs Nginx to work to verify the domain, but Nginx won't start because the certificates don't exist.

**Solution:**

1. **Edit Nginx config to remove SSL references:**
```bash
sudo nano /etc/nginx/sites-available/meilisearch
```

2. **Replace with HTTP-only configuration:**
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

3. **Test and reload Nginx:**
```bash
sudo nginx -t
sudo systemctl reload nginx
```

4. **Test HTTP works:**
```bash
curl http://search.beachrex.com/health
# Should return: {"status":"available"}
```

5. **Now run Certbot (it will add SSL config automatically):**
```bash
sudo certbot --nginx -d search.beachrex.com
```

**Prevention:** Always start with HTTP-only Nginx configuration, then let Certbot add the SSL configuration automatically.

---

## DNS Issues

### Issue 3: Cloudflare Proxied Mode Breaks SSL Verification

**Symptom:**
```bash
$ sudo certbot --nginx -d search.beachrex.com
Certbot failed to authenticate some domains (authenticator: nginx). 
The Certificate Authority reported these problems:
  Domain: search.beachrex.com
  Type:   unauthorized
  Detail: Invalid response from http://search.beachrex.com/.well-known/acme-challenge/...
```

**Cause:** Cloudflare's "Proxied" mode (orange cloud 🟠) intercepts traffic and prevents Let's Encrypt from verifying domain ownership.

**Solution:**

1. **Log into Cloudflare Dashboard**
2. Go to **DNS** section
3. Find your A record for `search`
4. Click the **orange cloud icon** to toggle it to **gray cloud** (DNS only)
5. Wait 1-2 minutes for DNS to propagate
6. Retry Certbot:
```bash
sudo certbot --nginx -d search.beachrex.com
```

**After SSL is obtained:** You can optionally switch back to Proxied mode if you want Cloudflare's CDN/DDoS protection, but it's not necessary for Meilisearch.

**Prevention:** Always use "DNS only" (gray cloud) mode for services that need direct SSL certificate verification.

---

### Issue 4: DNS Not Propagated Yet

**Symptom:**
```bash
$ curl http://search.beachrex.com
curl: (6) Could not resolve host: search.beachrex.com
```

**Cause:** DNS changes haven't propagated yet.

**Solution:**

1. **Check DNS from your local machine:**
```bash
nslookup search.beachrex.com
```

2. **If it shows the wrong IP or no result, wait 2-5 minutes**

3. **Check DNS propagation globally:**
   - Visit: https://www.whatsmydns.net/
   - Enter: `search.beachrex.com`
   - Check if it shows your server IP

4. **Once DNS is correct, retry:**
```bash
curl http://search.beachrex.com/health
```

**Prevention:** Always verify DNS propagation before attempting SSL certificate setup.

---

## Permission Issues

### Issue 5: Meilisearch Service Can't Write to Data Directory

**Symptom:**
```bash
$ sudo systemctl status meilisearch
● meilisearch.service - Meilisearch
   Active: failed (Result: exit-code)

$ sudo journalctl -u meilisearch -n 50
Error: Permission denied (os error 13)
```

**Cause:** The meilisearch user doesn't have write permissions to the data directory.

**Solution:**
```bash
# Fix ownership
sudo chown -R meilisearch:meilisearch /var/lib/meilisearch

# Fix permissions
sudo chmod -R 755 /var/lib/meilisearch

# Restart service
sudo systemctl restart meilisearch

# Check status
sudo systemctl status meilisearch
```

**Prevention:** Always run `chown -R meilisearch:meilisearch /var/lib/meilisearch` after creating directories.

---

## Firewall Issues

### Issue 6: SSH Connection Dropped After Enabling Firewall

**Symptom:**
```bash
$ sudo ufw enable
Command may disrupt existing ssh connections. Proceed with operation (y|n)? y
[Connection lost]
```

**Cause:** Firewall was enabled before allowing SSH (port 22).

**Solution:**

If you still have access:
```bash
sudo ufw allow 22/tcp
sudo ufw enable
```

If you're locked out:
1. Access server via Hetzner Cloud Console (web-based console)
2. Log in as root
3. Allow SSH:
```bash
ufw allow 22/tcp
ufw reload
```

**Prevention:** **ALWAYS** allow SSH first before enabling the firewall:
```bash
# Correct order:
sudo ufw allow 22/tcp    # Allow SSH FIRST
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable          # Enable firewall LAST
```

---

### Issue 7: Can't Access Meilisearch After Firewall Enabled

**Symptom:**
```bash
$ curl http://search.beachrex.com/health
curl: (7) Failed to connect to search.beachrex.com port 80: Connection refused
```

**Cause:** Firewall is blocking HTTP/HTTPS traffic.

**Solution:**
```bash
# Allow HTTP and HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Check firewall status
sudo ufw status

# Should show:
# 22/tcp    ALLOW    Anywhere
# 80/tcp    ALLOW    Anywhere
# 443/tcp   ALLOW    Anywhere
```

**Prevention:** Always allow ports 80 and 443 before testing web access.

---

## Service Issues

### Issue 8: Meilisearch Service Won't Start

**Symptom:**
```bash
$ sudo systemctl start meilisearch
Job for meilisearch.service failed.

$ sudo systemctl status meilisearch
● meilisearch.service - Meilisearch
   Active: failed (Result: exit-code)
```

**Solution:**

1. **Check logs for specific error:**
```bash
sudo journalctl -u meilisearch -n 50
```

2. **Common causes and fixes:**

**Missing master key:**
```bash
# Edit service file
sudo nano /etc/systemd/system/meilisearch.service

# Make sure this line exists:
Environment="MEILI_MASTER_KEY=YOUR_ACTUAL_KEY_HERE"

# Reload and restart
sudo systemctl daemon-reload
sudo systemctl restart meilisearch
```

**Port already in use:**
```bash
# Check what's using port 7700
sudo lsof -i :7700

# Kill the process if needed
sudo kill -9 PID
```

**Binary not found:**
```bash
# Verify binary exists
ls -la /usr/local/bin/meilisearch

# If missing, reinstall
curl -L https://install.meilisearch.com | sh
mv ./meilisearch /usr/local/bin/
chmod +x /usr/local/bin/meilisearch
```

---

## Connection Issues

### Issue 9: Laravel Can't Connect to Meilisearch

**Symptom:**
```bash
$ php artisan search:index
Connection refused [tcp://search.beachrex.com:7700]
```

**Solution:**

1. **Verify Meilisearch is running:**
```bash
# On Hetzner server
sudo systemctl status meilisearch
curl http://127.0.0.1:7700/health
```

2. **Verify HTTPS works:**
```bash
curl https://search.beachrex.com/health
```

3. **Check Laravel .env:**
```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://search.beachrex.com  # Note: HTTPS, not HTTP
MEILISEARCH_KEY=YOUR_MASTER_KEY_HERE
```

4. **Clear Laravel cache:**
```bash
php artisan config:clear
php artisan cache:clear
```

5. **Test connection from Laravel:**
```bash
php artisan tinker
>>> use MeiliSearch\Client;
>>> $client = new Client(env('MEILISEARCH_HOST'), env('MEILISEARCH_KEY'));
>>> $client->health();
```

---

## Quick Diagnostic Commands

When troubleshooting, run these commands to gather information:

```bash
# Service status
sudo systemctl status meilisearch

# Recent logs
sudo journalctl -u meilisearch -n 50

# Live logs
sudo journalctl -u meilisearch -f

# Test local connection
curl http://127.0.0.1:7700/health

# Test HTTPS connection
curl https://search.beachrex.com/health

# Check firewall
sudo ufw status

# Check Nginx
sudo nginx -t
sudo systemctl status nginx

# Check DNS
nslookup search.beachrex.com

# Check port usage
sudo lsof -i :7700
sudo lsof -i :80
sudo lsof -i :443

# Check permissions
ls -la /var/lib/meilisearch/
```

---

## Getting Help

If you encounter an issue not covered here:

1. **Check logs first:**
   ```bash
   sudo journalctl -u meilisearch -n 100
   ```

2. **Check Nginx logs:**
   ```bash
   sudo tail -f /var/log/nginx/meilisearch-error.log
   ```

3. **Verify all services are running:**
   ```bash
   sudo systemctl status meilisearch
   sudo systemctl status nginx
   ```

4. **Test each component individually:**
   - Meilisearch: `curl http://127.0.0.1:7700/health`
   - Nginx: `curl http://search.beachrex.com/health`
   - SSL: `curl https://search.beachrex.com/health`

5. **Consult official documentation:**
   - [Meilisearch Docs](https://www.meilisearch.com/docs)
   - [Hetzner Docs](https://docs.hetzner.com/)
   - [Certbot Docs](https://certbot.eff.org/docs/)

---

**Last Updated:** 2025-12-01  
**Version:** 1.0

