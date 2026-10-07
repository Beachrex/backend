# Beachrex API Documentation

This folder contains all technical documentation for the Beachrex API project.

---

## 📁 Documentation Structure

### 🔐 [admin/](./admin/)
**Admin panel implementation and upgrade documentation**

- `ADMIN_IMPLEMENTATION_GUIDE.md` - Guide for admin panel features
- `ADMIN_UPGRADE_ANALYSIS.md` - Analysis of admin panel upgrades
- `ADMIN_UPGRADE_SUMMARY.md` - Summary of admin panel changes

---

### 🌐 [api/](./api/)
**API endpoint documentation and analysis**

- `BEACH_DETAILS_API.md` - Beach details endpoint documentation
- `FILTER_HIERARCHY_ANALYSIS.md` - Analysis of filter hierarchy and cascading filters

---

### 🚀 [deployment/](./deployment/)
**Deployment and server configuration**

- `DEPLOYMENT_GUIDE.md` - **Authoritative current infra doc**: Hetzner VPS, dev/uat/prod, GitHub Actions CI/CD, branch policy, env isolation, cache rules
- `VPS_MIGRATION_PLAN.md` - Migration plan from Laravel Cloud/Vercel to Hetzner VPS, with status per environment
- `CASE_STUDY_VIBECODING.md` - Case study / lessons learned from the VPS migration
- `MEILISEARCH_*.md` - Meilisearch setup on Hetzner
- `staging-server-config.md` - Staging server configuration guide (historical, pre-VPS)

---

### ✨ [features/](./features/)
**Feature-specific documentation**

- `BEACH_TRANSLATION_SCRIPT.md` - Beach translation script documentation
- `IMAGOR_INTEGRATION.md` - Imagor CDN integration guide

---

### 🌤️ [weather/](./weather/)
**OpenWeatherMap integration documentation**

- `README.md` - Weather documentation overview
- `OPENWEATHER_IMPLEMENTATION_SUMMARY.md` - Implementation summary and checklist
- `WEATHER_ENDPOINT_GUIDE.md` - Complete API endpoint documentation
- `WEATHER_IMPLEMENTATION_FOR_OTHER_ENTITIES.md` - Guide for adding weather to regions/cities/islands
- `OPENWEATHER_BACKEND_SETUP.md` - Backend setup and configuration

**👉 Start here for weather-related documentation:** [weather/README.md](./weather/README.md)

---

### 📊 Other Documentation

- `DATABASE_PERFORMANCE_ANALYSIS.md` - Database performance analysis
- `swagger-configuration.md` - Swagger/OpenAPI configuration

---

## 🎯 Quick Links

### For Frontend Developers
- [Weather API Guide](./weather/WEATHER_ENDPOINT_GUIDE.md)
- [Beach Details API](./api/BEACH_DETAILS_API.md)
- [Imagor Integration](./features/IMAGOR_INTEGRATION.md)

### For Backend Developers
- [Weather Implementation](./weather/OPENWEATHER_IMPLEMENTATION_SUMMARY.md)
- [Admin Panel Guide](./admin/ADMIN_IMPLEMENTATION_GUIDE.md)
- [Database Performance](./DATABASE_PERFORMANCE_ANALYSIS.md)

### For DevOps
- [Deployment Guide (VPS, CI/CD)](./deployment/DEPLOYMENT_GUIDE.md)
- [VPS Migration Plan](./deployment/VPS_MIGRATION_PLAN.md)
- [MCP Server README](../mcp-server/README.md)
- [Staging Server Config](./deployment/staging-server-config.md) (historical)
- [Swagger Configuration](./swagger-configuration.md)

---

## 📝 Root-Level Documentation

Important documentation files in the project root:

- `README.md` - Main project README
- `DEV_HANDOFF.md` - Developer handoff documentation
- `mcp-server/README.md` - Beachrex MCP server (AI agent access to the API)

---

**Last Updated:** 2026-10-07

