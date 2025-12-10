# Meilisearch Deployment Summary

## Overview

This document provides a quick overview of deploying Meilisearch on a dedicated Hetzner server for the BeachRex API.

## Why Separate Hetzner Server?

✅ **Dedicated Resources** - Search operations don't compete with API/database  
✅ **Independent Scaling** - Scale search independently from main infrastructure  
✅ **Better Security** - Isolated network, separate firewall rules  
✅ **Cost Efficient** - Hetzner pricing (~€8/month) vs managed services (€50+/month)  
✅ **High Availability** - Main API continues working if search is down  
✅ **Laravel Cloud Compatible** - Since Laravel Cloud doesn't support Meilisearch natively

## Documentation Files

### 📘 Full Setup Guide
**File:** `docs/deployment/MEILISEARCH_HETZNER_SETUP.md`

Comprehensive step-by-step guide covering:
- Server creation and configuration
- Meilisearch installation
- SSL setup with Let's Encrypt
- Nginx reverse proxy configuration
- Security hardening
- Monitoring and maintenance
- Backup strategies
- Troubleshooting

**Use this for:** First-time setup, detailed understanding, reference

### 🚀 Quick Start Guide
**File:** `docs/deployment/MEILISEARCH_QUICK_START.md`

Condensed version for experienced users:
- 30-minute setup checklist
- Essential commands only
- Quick troubleshooting
- Common operations

**Use this for:** Quick deployment, experienced sysadmins

### 🤖 Automated Setup Script
**File:** `docs/deployment/scripts/setup-meilisearch-hetzner.sh`

Bash script that automates the entire setup:
- System updates
- Meilisearch installation
- Service configuration
- Firewall setup
- Nginx + SSL configuration
- Backup script creation

**Use this for:** Fastest deployment, consistent setup across environments

## Quick Start (Choose Your Path)

### Option 1: Automated Script (Recommended)

```bash
# 1. Create Hetzner server (Ubuntu 24.04)
# 2. SSH into server
ssh root@YOUR_SERVER_IP

# 3. Download and run setup script
wget https://raw.githubusercontent.com/YOUR_REPO/main/docs/deployment/scripts/setup-meilisearch-hetzner.sh
chmod +x setup-meilisearch-hetzner.sh
sudo ./setup-meilisearch-hetzner.sh

# 4. Follow prompts (domain, email)
# 5. Save the generated master key!
```

### Option 2: Manual Setup

Follow the guide in `docs/deployment/MEILISEARCH_QUICK_START.md`

## Server Specifications

### Recommended (Production)
- **Type:** Hetzner CPX22 (or CPX21 if available)
- **vCPU:** 2-3 cores
- **RAM:** 4 GB
- **Storage:** 80 GB SSD
- **Cost:** €5-8/month
- **Location:** Nuremberg, Germany (or closest to your API)

### Minimum (Small Sites)
- **Type:** Hetzner CPX11 or CPX22
- **vCPU:** 2 cores
- **RAM:** 2-4 GB
- **Storage:** 40-80 GB SSD
- **Cost:** €4-6/month

**Note:** CPX22 (2 vCPU, 4GB RAM, 80GB SSD) is often the lowest available option and works perfectly for most use cases.

## Current Data Size

- **Beaches:** ~3,000 documents
- **Cities:** ~1,000 documents
- **Islands:** ~30 documents
- **Regions:** ~75 documents
- **Countries:** ~15 documents
- **Total:** ~4,120 documents
- **Indexed Size:** ~50-100 MB

## Architecture

```
┌─────────────────┐
│  Laravel Cloud  │
│   (Main API)    │
└────────┬────────┘
         │
         │ HTTPS
         │
         ▼
┌─────────────────┐
│    Hetzner      │
│  Meilisearch    │
│   (Dedicated)   │
└─────────────────┘
```

**Flow:**
1. User searches via API: `GET /api/v1/search?q=miami`
2. Laravel API connects to Meilisearch: `https://search.beachrex.com`
3. Meilisearch returns results
4. Laravel formats and caches response
5. User receives search results

## Configuration

### On Hetzner Server

**Domain:** `search.beachrex.com` (or your choice)  
**SSL:** Let's Encrypt (auto-renewing)  
**Port:** 443 (HTTPS only)  
**Firewall:** UFW (ports 22, 80, 443 only)  
**Service:** Systemd (auto-start on boot)

### On Laravel Cloud

Update environment variables:

```env
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=https://search.beachrex.com
MEILISEARCH_KEY=YOUR_MASTER_KEY_HERE
```

## Post-Deployment Steps

### 1. Index Data

```bash
# On Laravel server
php artisan search:index

# Expected output:
# Indexing beaches... ✓ 3000 documents
# Indexing cities... ✓ 1000 documents
# Indexing islands... ✓ 30 documents
# Indexing regions... ✓ 75 documents
# Indexing countries... ✓ 15 documents
```

### 2. Verify Search

```bash
# Test health
curl https://search.beachrex.com/health

# Test search API
curl -H "Accept-Language: en" \
  "https://api.beachrex.com/api/v1/search?q=miami&limit=5"
```

### 3. Monitor

```bash
# On Hetzner server
sudo systemctl status meilisearch
sudo journalctl -u meilisearch -f
```

## Maintenance

### Daily Operations

```bash
# Check service status
sudo systemctl status meilisearch

# View logs
sudo journalctl -u meilisearch -n 100

# Check disk usage
df -h
du -sh /var/lib/meilisearch/data
```

### Weekly Tasks

- Review logs for errors
- Check disk space
- Verify backups are running

### Monthly Tasks

- Review and test backup restoration
- Update system packages: `apt update && apt upgrade`
- Review Meilisearch version for updates

### Re-indexing (When Needed)

```bash
# Only needed when changing index settings or after major data changes
php artisan search:index --flush
```

## Backup

### Automated Backups

The setup script creates a daily backup cron job:

```bash
# Runs daily at 2 AM
/usr/local/bin/meilisearch-backup.sh

# Backup location
/var/backups/meilisearch/

# Retention: 7 days
```

### Manual Backup

```bash
# Create dump
curl -X POST -H "Authorization: Bearer YOUR_KEY" \
  https://search.beachrex.com/dumps

# Download dump
scp root@YOUR_SERVER_IP:/var/lib/meilisearch/dumps/*.dump ./backups/
```

### Restore from Backup

```bash
# Stop service
sudo systemctl stop meilisearch

# Restore dump
curl -X POST "http://127.0.0.1:7700/dumps/YOUR_DUMP_FILE.dump" \
  -H "Authorization: Bearer YOUR_KEY"

# Start service
sudo systemctl start meilisearch
```

## Security

✅ **Firewall:** UFW configured (only ports 22, 80, 443)  
✅ **SSL:** Let's Encrypt certificate (auto-renewing)  
✅ **Authentication:** Master key required for all operations  
✅ **Network:** Meilisearch only listens on localhost (127.0.0.1)  
✅ **Access:** Only via HTTPS through Nginx reverse proxy  
✅ **User:** Service runs as non-root user  
✅ **Permissions:** Strict file system permissions  

## Cost Breakdown

| Item | Cost |
|------|------|
| Hetzner CPX22 Server | €5-8/month |
| Bandwidth (20TB included) | €0 |
| Backups (optional) | €1-2/month |
| **Total** | **€5-10/month** |

**Comparison:**
- Algolia: ~€50-200/month
- Elasticsearch Cloud: ~€50-100/month
- Typesense Cloud: ~€30-50/month
- **Hetzner + Meilisearch: €5-8/month** ✅

## Troubleshooting

### Service Won't Start

```bash
# Check logs
sudo journalctl -u meilisearch -n 50

# Check permissions
sudo chown -R meilisearch:meilisearch /var/lib/meilisearch

# Restart
sudo systemctl restart meilisearch
```

### Can't Connect from Laravel

```bash
# Test DNS
nslookup search.beachrex.com

# Test connection
curl https://search.beachrex.com/health

# Check Laravel config
php artisan config:clear
php artisan cache:clear
```

### Search Returns No Results

```bash
# Check indexes
curl -H "Authorization: Bearer YOUR_KEY" \
  https://search.beachrex.com/indexes

# Re-index
php artisan search:index --flush
```

## Support Resources

- **Full Setup Guide:** `docs/deployment/MEILISEARCH_HETZNER_SETUP.md`
- **Quick Start:** `docs/deployment/MEILISEARCH_QUICK_START.md`
- **Setup Script:** `docs/deployment/scripts/setup-meilisearch-hetzner.sh`
- **Meilisearch Docs:** https://www.meilisearch.com/docs
- **Hetzner Docs:** https://docs.hetzner.com/
- **Laravel Scout:** https://laravel.com/docs/scout

## Next Steps

1. ✅ Read this summary
2. 🔄 Choose deployment method (automated script or manual)
3. 🔄 Create Hetzner server
4. 🔄 Run setup (30-40 minutes)
5. 🔄 Configure Laravel environment
6. 🔄 Index data
7. 🔄 Test search functionality
8. 🔄 Setup monitoring
9. 🔄 Document credentials securely

## Questions?

If you encounter any issues or have questions:

1. Check the troubleshooting section in the full guide
2. Review Meilisearch logs: `sudo journalctl -u meilisearch -f`
3. Test connectivity: `curl https://search.beachrex.com/health`
4. Verify DNS: `nslookup search.beachrex.com`

---

**Last Updated:** 2025-12-01  
**Version:** 1.0  
**Status:** Production Ready

