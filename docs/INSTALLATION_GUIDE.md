# SIDAK TEJO - Installation & Administrator Guide

## Minimum Requirements
- **PHP**: 8.3 or higher (with `pdo_mysql`, `curl`, `json`, `mbstring`, `openssl`, `gd`, `zip` extensions enabled)
- **Database**: MySQL 8.0+ or MariaDB 10.5+
- **Web Server**: Apache 2.4+ / Nginx (Hostinger Cloud Compatible)
- **SSL Certificate**: HTTPS Required for Secure Webhooks & JWT

## Step-by-Step Installation
1. Upload codebase to server `public_html` or domain root.
2. Ensure `writable` directory permissions are set to `775` or `777`.
3. Configure database settings in `.env` or `app/Config/Database.php`.
4. Run index optimization: Visit `/status/optimize-database`.
5. Setup Cron Job for Background Queue & Notifications:
   ```cron
   * * * * * php /path/to/sidak-tejo/spark queue:work >> /dev/null 2>&1
   ```
