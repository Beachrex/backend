# Deployment Documentation

> **Current infrastructure (Sept 2026):** the authoritative doc is [DEPLOYMENT_GUIDE.md](./DEPLOYMENT_GUIDE.md): a single Hetzner VPS running the **dev / uat / prod** environments, deployed by GitHub Actions (`.github/workflows/deploy.yml`). See also [VPS_MIGRATION_PLAN.md](./VPS_MIGRATION_PLAN.md) and [CASE_STUDY_VIBECODING.md](./CASE_STUDY_VIBECODING.md).
> References in this folder to **Laravel Cloud** or **`stage-api.beachrex.com`** (e.g. `staging-server-config.md`, parts of the Meilisearch guides) are **historical** and predate the VPS migration. The Meilisearch server itself (`search.beachrex.com`, dedicated Hetzner box) is still in use.

This directory contains all deployment documentation for the BeachRex API infrastructure.

## 📁 Directory Structure

```
docs/deployment/
├── README.md                           # This file
├── MEILISEARCH_HETZNER_SETUP.md       # Full Meilisearch setup guide
├── MEILISEARCH_QUICK_START.md         # Quick start guide
├── MEILISEARCH_CHECKLIST.md           # Deployment checklist
├── MEILISEARCH_COMMON_ISSUES.md       # Common issues & solutions
└── scripts/
    └── setup-meilisearch-hetzner.sh   # Automated setup script
```

## 🔍 Meilisearch Deployment

### Overview

Meilisearch is deployed on a dedicated Hetzner server because Laravel Cloud doesn't support Meilisearch natively. This provides:

- ✅ Dedicated resources for search operations
- ✅ Independent scaling
- ✅ Better security through network isolation
- ✅ Cost efficiency (~€8/month vs €50+/month for managed services)
- ✅ High availability

### Quick Links

| Document | Purpose | Time Required |
|----------|---------|---------------|
| [MEILISEARCH_DEPLOYMENT_SUMMARY.md](../../MEILISEARCH_DEPLOYMENT_SUMMARY.md) | Overview and quick reference | 5 min read |
| [MEILISEARCH_HETZNER_SETUP.md](./MEILISEARCH_HETZNER_SETUP.md) | Complete step-by-step guide | 30-40 min |
| [MEILISEARCH_QUICK_START.md](./MEILISEARCH_QUICK_START.md) | Condensed setup guide | 20-30 min |
| [MEILISEARCH_CHECKLIST.md](./MEILISEARCH_CHECKLIST.md) | Deployment checklist | Use during deployment |
| [MEILISEARCH_COMMON_ISSUES.md](./MEILISEARCH_COMMON_ISSUES.md) | Common issues & solutions | Reference when needed |
| [setup-meilisearch-hetzner.sh](./scripts/setup-meilisearch-hetzner.sh) | Automated setup script | 5-10 min |

### Which Guide Should I Use?

**New to server deployment?**
→ Start with [MEILISEARCH_DEPLOYMENT_SUMMARY.md](../../MEILISEARCH_DEPLOYMENT_SUMMARY.md), then follow [MEILISEARCH_HETZNER_SETUP.md](./MEILISEARCH_HETZNER_SETUP.md)

**Experienced with Linux servers?**
→ Use [MEILISEARCH_QUICK_START.md](./MEILISEARCH_QUICK_START.md)

**Want fastest deployment?**
→ Use the automated script: [setup-meilisearch-hetzner.sh](./scripts/setup-meilisearch-hetzner.sh)

**Deploying to production?**
→ Use [MEILISEARCH_CHECKLIST.md](./MEILISEARCH_CHECKLIST.md) to ensure nothing is missed

**Encountering issues?**
→ Check [MEILISEARCH_COMMON_ISSUES.md](./MEILISEARCH_COMMON_ISSUES.md) for solutions to common problems

## 🚀 Quick Start

### Option 1: Automated Script (Recommended)

```bash
# 1. Create Hetzner server (Ubuntu 24.04, CPX21)
# 2. SSH into server
ssh root@YOUR_SERVER_IP

# 3. Download and run setup script
wget https://raw.githubusercontent.com/YOUR_REPO/main/docs/deployment/scripts/setup-meilisearch-hetzner.sh
chmod +x setup-meilisearch-hetzner.sh
sudo ./setup-meilisearch-hetzner.sh

# 4. Follow prompts and save the master key!
```

### Option 2: Manual Setup

Follow the guide in [MEILISEARCH_QUICK_START.md](./MEILISEARCH_QUICK_START.md)

## 📊 Architecture

```
┌─────────────────┐
│  Next.js        │
│  Frontend       │
└────────┬────────┘
         │ HTTPS
         ▼
┌─────────────────┐
│  Laravel Cloud  │
│  (Main API)     │
│  - PostgreSQL   │
│  - Redis        │
└────────┬────────┘
         │ HTTPS
         ▼
┌─────────────────┐
│  Hetzner Server │
│  Meilisearch    │
│  - Nginx SSL    │
│  - Backups      │
└─────────────────┘
```

## 💰 Cost Breakdown

| Service | Monthly Cost | Yearly Cost |
|---------|--------------|-------------|
| Hetzner CPX21 | €8.21 | ~€98 |
| Backups (optional) | €1.64 | ~€20 |
| **Total** | **€8-10** | **€98-118** |

**Comparison:**
- Algolia: €50-200/month
- Elasticsearch Cloud: €50-100/month
- Typesense Cloud: €30-50/month
- **Hetzner + Meilisearch: €8/month** ✅

## 🔒 Security Features

- ✅ Firewall configured (UFW)
- ✅ SSL certificate (Let's Encrypt, auto-renewing)
- ✅ Master key authentication required
- ✅ Meilisearch only listens on localhost
- ✅ Access only via HTTPS through Nginx reverse proxy
- ✅ Service runs as non-root user
- ✅ Strict file system permissions
- ✅ Regular automated backups

## 📈 Current Data Size

- **Beaches:** ~3,000 documents
- **Cities:** ~1,000 documents
- **Islands:** ~30 documents
- **Regions:** ~75 documents
- **Countries:** ~15 documents
- **Total:** ~4,120 documents
- **Indexed Size:** ~50-100 MB

## 🛠️ Maintenance

### Daily (Automated)
- Backups at 2 AM
- Service health monitoring

### Weekly
- Review logs
- Check disk usage
- Verify backups

### Monthly
- Update system packages
- Test backup restoration
- Check for Meilisearch updates
- Review resource usage

## 📞 Support

### Documentation
- [Meilisearch Official Docs](https://www.meilisearch.com/docs)
- [Hetzner Cloud Docs](https://docs.hetzner.com/)
- [Laravel Scout Docs](https://laravel.com/docs/scout)

### Troubleshooting

**Service won't start:**
```bash
sudo journalctl -u meilisearch -n 50
sudo systemctl restart meilisearch
```

**Can't connect from Laravel:**
```bash
curl https://search.beachrex.com/health
php artisan config:clear
```

**Search returns no results:**
```bash
php artisan search:index --flush
```

**For detailed troubleshooting:**
- [MEILISEARCH_COMMON_ISSUES.md](./MEILISEARCH_COMMON_ISSUES.md) - Common issues and solutions
- [MEILISEARCH_HETZNER_SETUP.md](./MEILISEARCH_HETZNER_SETUP.md#troubleshooting) - Full troubleshooting guide

## 📝 Deployment Checklist

Before deploying, ensure you have:

- [ ] Hetzner Cloud account
- [ ] Domain name (e.g., `search.beachrex.com`)
- [ ] DNS access
- [ ] Email for SSL certificate
- [ ] SSH key
- [ ] 30-40 minutes for deployment

Use [MEILISEARCH_CHECKLIST.md](./MEILISEARCH_CHECKLIST.md) during deployment.

## 🔄 Deployment Process

1. **Pre-deployment** (5 min)
   - Review documentation
   - Prepare credentials
   - Create Hetzner server

2. **Deployment** (20-30 min)
   - Run setup script OR follow manual guide
   - Configure DNS
   - Obtain SSL certificate

3. **Configuration** (5 min)
   - Update Laravel environment variables
   - Test connection

4. **Indexing** (5 min)
   - Run `php artisan search:index`
   - Verify indexes

5. **Testing** (5 min)
   - Test search API
   - Verify performance

6. **Post-deployment** (5 min)
   - Setup monitoring
   - Document credentials
   - Update team

**Total Time:** 30-40 minutes

## 🎯 Success Criteria

✅ Meilisearch service running and healthy  
✅ SSL certificate valid  
✅ Search API returning results in <100ms  
✅ All 5 indexes created (beaches, cities, islands, regions, countries)  
✅ Backups configured and tested  
✅ Firewall configured  
✅ Documentation updated  

## 📚 Additional Resources

### Related Documentation
- [Main README](../../README.md)
- [API Documentation](../api/)
- [Weather Documentation](../weather/)
- [Admin Documentation](../admin/)

### External Resources
- [Meilisearch GitHub](https://github.com/meilisearch/meilisearch)
- [Laravel Scout GitHub](https://github.com/laravel/scout)
- [Hetzner Cloud API](https://docs.hetzner.cloud/)

## 🤝 Contributing

When updating deployment documentation:

1. Update the relevant guide(s)
2. Update this README if structure changes
3. Update the deployment summary
4. Test changes on a fresh server
5. Update the checklist if new steps are added

## 📅 Version History

| Date | Version | Changes |
|------|---------|---------|
| 2025-12-01 | 1.0 | Initial Meilisearch deployment documentation |

---

**Last Updated:** 2025-12-01  
**Maintained By:** BeachRex DevOps Team

