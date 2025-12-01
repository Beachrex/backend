# Meilisearch Deployment Checklist

Use this checklist to ensure a smooth deployment of Meilisearch on Hetzner.

## Pre-Deployment Checklist

### 1. Prerequisites

- [ ] Hetzner Cloud account created
- [ ] Payment method added to Hetzner
- [ ] Domain name available (e.g., `search.beachrex.com`)
- [ ] DNS access to create A records
- [ ] Email address for Let's Encrypt SSL
- [ ] SSH key generated (or ready to create one)
- [ ] Access to Laravel Cloud environment variables

### 2. Planning

- [ ] Decided on server location (closest to main API)
- [ ] Decided on server size (CPX11 or CPX21)
- [ ] Decided on domain name for Meilisearch
- [ ] Reviewed cost estimate (~€8-10/month)
- [ ] Scheduled deployment time (30-40 minutes)

### 3. Documentation Review

- [ ] Read `MEILISEARCH_DEPLOYMENT_SUMMARY.md`
- [ ] Reviewed either:
  - [ ] `docs/deployment/MEILISEARCH_HETZNER_SETUP.md` (full guide), OR
  - [ ] `docs/deployment/MEILISEARCH_QUICK_START.md` (quick guide)
- [ ] Decided on deployment method:
  - [ ] Automated script, OR
  - [ ] Manual setup

---

## Deployment Checklist

### Step 1: Create Hetzner Server

- [ ] Logged into Hetzner Cloud Console
- [ ] Created new project (e.g., `beachrex-search`)
- [ ] Created server with specs:
  - [ ] Location: Nuremberg (or chosen location)
  - [ ] Image: Ubuntu 24.04 LTS
  - [ ] Type: CPX21 (or chosen size)
  - [ ] SSH key added
  - [ ] Server name: `meilisearch-prod`
- [ ] Noted server IP address: `___________________`
- [ ] Tested SSH connection: `ssh root@SERVER_IP`

### Step 2: DNS Configuration

- [ ] Created A record in DNS:
  - [ ] Type: A
  - [ ] Name: `search` (or chosen subdomain)
  - [ ] Value: `SERVER_IP`
  - [ ] TTL: 300
- [ ] Waited for DNS propagation (2-5 minutes)
- [ ] Verified DNS: `nslookup search.beachrex.com`

### Step 3: Server Setup

**If using automated script:**
- [ ] Downloaded setup script
- [ ] Made script executable: `chmod +x setup-meilisearch-hetzner.sh`
- [ ] Ran script: `sudo ./setup-meilisearch-hetzner.sh`
- [ ] Provided domain name when prompted
- [ ] Provided email for SSL when prompted
- [ ] **SAVED MASTER KEY:** `___________________`
- [ ] Script completed successfully

**If using manual setup:**
- [ ] Updated system: `apt update && apt upgrade -y`
- [ ] Installed dependencies: `apt install -y curl wget ufw nginx certbot python3-certbot-nginx`
- [ ] Installed Meilisearch
- [ ] Created meilisearch user and directories
- [ ] Generated master key: `openssl rand -base64 32`
- [ ] **SAVED MASTER KEY:** `___________________`
- [ ] Created systemd service
- [ ] Started Meilisearch service
- [ ] Configured firewall (UFW)
- [ ] Configured Nginx
- [ ] Obtained SSL certificate
- [ ] Created backup script

### Step 4: Verification

- [ ] Service running: `systemctl status meilisearch`
- [ ] Local health check: `curl http://127.0.0.1:7700/health`
- [ ] HTTPS health check: `curl https://search.beachrex.com/health`
- [ ] SSL certificate valid: `curl -I https://search.beachrex.com`
- [ ] Firewall configured: `ufw status`
- [ ] Logs clean: `journalctl -u meilisearch -n 50`

---

## Laravel Configuration Checklist

### Step 5: Update Laravel Environment

- [ ] Logged into Laravel Cloud dashboard
- [ ] Updated environment variables:
  - [ ] `SCOUT_DRIVER=meilisearch`
  - [ ] `SCOUT_QUEUE=true`
  - [ ] `MEILISEARCH_HOST=https://search.beachrex.com`
  - [ ] `MEILISEARCH_KEY=YOUR_MASTER_KEY`
- [ ] Deployed changes to Laravel Cloud
- [ ] Verified environment variables are set

### Step 6: Test Connection

- [ ] SSH'd into Laravel Cloud server (or local)
- [ ] Tested connection:
  ```bash
  php artisan tinker
  use MeiliSearch\Client;
  $client = new Client(env('MEILISEARCH_HOST'), env('MEILISEARCH_KEY'));
  $client->health();
  ```
- [ ] Connection successful

---

## Data Indexing Checklist

### Step 7: Index Data

- [ ] Ran indexing command: `php artisan search:index`
- [ ] Verified output:
  - [ ] Beaches indexed (~3,000 documents)
  - [ ] Cities indexed (~1,000 documents)
  - [ ] Islands indexed (~30 documents)
  - [ ] Regions indexed (~75 documents)
  - [ ] Countries indexed (~15 documents)
- [ ] No errors in output

### Step 8: Verify Indexes

- [ ] Checked indexes via API:
  ```bash
  curl -H "Authorization: Bearer YOUR_KEY" \
    https://search.beachrex.com/indexes
  ```
- [ ] All 5 indexes present:
  - [ ] beaches
  - [ ] cities
  - [ ] islands
  - [ ] regions
  - [ ] countries

---

## Testing Checklist

### Step 9: Test Search Functionality

- [ ] Tested search API:
  ```bash
  curl -H "Accept-Language: en" \
    "https://api.beachrex.com/api/v1/search?q=miami&limit=5"
  ```
- [ ] Received valid JSON response
- [ ] Results contain expected data
- [ ] Tested with different queries:
  - [ ] `q=beach`
  - [ ] `q=split`
  - [ ] `q=croatia`
- [ ] Tested with filters:
  - [ ] `q=beach&country_code=HR`
  - [ ] `q=beach&types=beach,city`
- [ ] Tested with different locales:
  - [ ] `Accept-Language: en`
  - [ ] `Accept-Language: de`
  - [ ] `Accept-Language: hr`

### Step 10: Performance Testing

- [ ] Tested response time (should be <100ms)
- [ ] Tested with typos (e.g., `q=miaim` should find Miami)
- [ ] Tested with partial matches
- [ ] Tested with special characters

---

## Post-Deployment Checklist

### Step 11: Security Verification

- [ ] Firewall active: `ufw status`
- [ ] Only ports 22, 80, 443 open
- [ ] SSL certificate valid and auto-renewing
- [ ] Meilisearch only accessible via HTTPS
- [ ] Master key secure and documented
- [ ] Service running as non-root user

### Step 12: Monitoring Setup

- [ ] Verified systemd service auto-starts on boot
- [ ] Checked logs are being written
- [ ] Verified backup cron job is scheduled: `crontab -l`
- [ ] Tested manual backup: `/usr/local/bin/meilisearch-backup.sh`
- [ ] Verified backup files created in `/var/backups/meilisearch/`

### Step 13: Documentation

- [ ] Documented master key in secure location (password manager)
- [ ] Documented server IP address
- [ ] Documented domain name
- [ ] Documented SSH access details
- [ ] Updated team documentation with:
  - [ ] Meilisearch URL
  - [ ] Maintenance procedures
  - [ ] Backup/restore procedures
  - [ ] Troubleshooting steps

---

## Maintenance Checklist

### Daily (Automated)

- [ ] Backups running at 2 AM
- [ ] Service running: `systemctl status meilisearch`

### Weekly

- [ ] Review logs: `journalctl -u meilisearch -n 100`
- [ ] Check disk usage: `df -h`
- [ ] Check Meilisearch data size: `du -sh /var/lib/meilisearch/data`
- [ ] Verify backups exist: `ls -lh /var/backups/meilisearch/`

### Monthly

- [ ] Update system packages: `apt update && apt upgrade`
- [ ] Review and test backup restoration
- [ ] Check for Meilisearch updates
- [ ] Review SSL certificate expiry (should auto-renew)
- [ ] Review resource usage (CPU, RAM, disk)

---

## Troubleshooting Checklist

### If Service Won't Start

- [ ] Check logs: `journalctl -u meilisearch -n 50`
- [ ] Check permissions: `ls -la /var/lib/meilisearch`
- [ ] Fix permissions: `chown -R meilisearch:meilisearch /var/lib/meilisearch`
- [ ] Check port: `lsof -i :7700`
- [ ] Restart service: `systemctl restart meilisearch`

### If Can't Connect from Laravel

- [ ] Test DNS: `nslookup search.beachrex.com`
- [ ] Test HTTPS: `curl https://search.beachrex.com/health`
- [ ] Check firewall: `ufw status`
- [ ] Check Nginx: `nginx -t && systemctl status nginx`
- [ ] Clear Laravel cache: `php artisan config:clear && php artisan cache:clear`

### If Search Returns No Results

- [ ] Check indexes: `curl -H "Authorization: Bearer KEY" https://search.beachrex.com/indexes`
- [ ] Check index stats: `curl -H "Authorization: Bearer KEY" https://search.beachrex.com/stats`
- [ ] Re-index: `php artisan search:index --flush`

---

## Rollback Checklist

### If Deployment Fails

- [ ] Document error messages
- [ ] Save logs: `journalctl -u meilisearch > meilisearch-error.log`
- [ ] Revert Laravel environment variables:
  - [ ] `SCOUT_DRIVER=null` (or previous value)
- [ ] Deploy Laravel changes
- [ ] Search functionality disabled gracefully
- [ ] Review error logs
- [ ] Fix issues
- [ ] Retry deployment

---

## Success Criteria

✅ All items in this checklist completed  
✅ Meilisearch service running and healthy  
✅ SSL certificate valid  
✅ Search API returning results  
✅ Response time <100ms  
✅ Backups configured and tested  
✅ Documentation updated  
✅ Team notified of new infrastructure  

---

## Contact Information

**In case of issues:**

- Meilisearch Docs: https://www.meilisearch.com/docs
- Hetzner Support: https://docs.hetzner.com/
- Laravel Scout: https://laravel.com/docs/scout

**Internal:**
- DevOps Lead: ___________________
- Backend Lead: ___________________
- On-call: ___________________

---

**Deployment Date:** ___________________  
**Deployed By:** ___________________  
**Server IP:** ___________________  
**Domain:** ___________________  
**Master Key Location:** ___________________  

---

**Status:** [ ] Not Started | [ ] In Progress | [ ] Completed | [ ] Failed

**Notes:**
```
[Add any deployment notes here]
```

