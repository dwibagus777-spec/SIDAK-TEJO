# DEPLOYMENT CHECKLIST - Hostinger Production (PHP 8.3)

## Hostinger Environment Specs:
- **PHP Version**: PHP 8.3
- **Database**: MariaDB 10.11 / MySQL 8.0
- **Web Server**: LiteSpeed / Apache 2.4
- **SSL**: Active HTTPS (`https://sidaktejo.site`)

## Deployment Checklist:
- [x] Codebase pulled from `origin/main` (commit `release(v1.0.0)`).
- [x] All 22 new Phase 24 & 25 files verified with `php -l`.
- [x] Environment set to `production` in `.env`.
- [x] Database indexes created via `/status/optimize-database`.
- [x] Writable directory permissions set to `775`.
- [x] Public verification link tested (`/documents/verify/...`).
- [x] Health check verified (`/api/health`).
- [x] OpenAPI Swagger UI verified (`/api/docs/ui`).
