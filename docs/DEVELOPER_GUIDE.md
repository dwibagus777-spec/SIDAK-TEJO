# SIDAK TEJO - Developer Guide (v1.0.0)

## System Architecture Overview
SIDAK TEJO is built with **Clean Architecture** principles using **CodeIgniter 4.7 (PHP 8.3)**, **MySQL/MariaDB**, **Riverpod Mobile Native Backend**, and **Vanilla Bootstrap 5 JS/CSS**.

### Key Architectural Layers:
1. **Controllers Layer** (`app/Controllers`): Thin controllers handling input validation, response formatting, and route dispatching.
2. **Services Layer** (`app/Services`): Core business logic engines (Document Intelligence, Notification Engine, Integration Platform, Queue Worker).
3. **Repository Layer** (`app/Repositories`): Data Access Object (DAO) layer with safe query execution wrappers (`safeGet`, `safeRow`, `safeResult`).
4. **Models Layer** (`app/Models`): Database tables, soft deletes, and auto-forge table initializations.
5. **Drivers Pattern** (`app/Services/Drivers`): Multi-protocol drivers for REST, SOAP, MQTT, FTP, SFTP, CSV, JSON, XML.

## Development Setup
```bash
# Clone repository
git clone https://github.com/dwibagus777-spec/sidak-tejo.git
cd sidak-tejo

# Install dependencies via Composer
composer install

# Configure environment (.env)
cp env .env
# Edit database credentials in .env

# Run local development server
php spark serve
```

## Coding & Security Guidelines
- Always use `BaseRepository` safe helpers to avoid chaining `->get()->getResultArray()` on unverified queries.
- Run `php -l` on modified files before committing.
- Ensure all API endpoints pass authentication (`verifyAuth()`) and rate limiting checks.
