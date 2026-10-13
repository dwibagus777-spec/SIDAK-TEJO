# SIDAK TEJO Staging Deployment

This procedure is for an isolated staging environment. It does not authorize production changes.

## Application and web root

- Keep the complete CodeIgniter application, `vendor`, `.env`, and `writable` outside the public web root.
- Point the staging virtual host directly to the release's `public` directory. For the currently reported Hostinger layout, the candidate is `sidak_staging_app/app_release/public`; verify the exact canonical path in hPanel before switching.
- Keep the current staging document root and its default page until the new vhost target is verified and a rollback target is available.
- Deploy from a pinned, reviewed commit using the approved SSH/SFTP or CI mechanism. Do not use public HTTP deployment or migration endpoints.
- Never publish `.env`, database dumps, photo archives, session files, logs, backups, or uploaded runtime data. The public web root rules deny common sensitive artifacts and route photo requests through the authenticated application.

## Staging-only configuration

- Create a staging `.env` on the server; do not copy the production `.env` or put secrets in Git, a ZIP, shell output, or deployment logs.
- Set `CI_ENVIRONMENT=staging`, the confirmed staging base URL, and database connection values supplied by the hosting operator.
- The staging database must be a separately provisioned database and user. Do not infer its identity from production settings.
- Set `SIDAK_STORAGE_PATH` to the confirmed private staging photo directory, currently reported as `/home/u532206332/domains/sidaktejo.site/sidak_storage_staging/foto`. Keep it outside the document root and verify canonical paths and permissions.
- Ensure writable files, sessions, cache, and logs are private and writable only by the application account. The PHP CLI version does not establish the PHP web handler version or enabled web extensions.
- If `AUDIT_SECRET_KEY`, `SIDAK_AUDIT_TOKEN`, or `SIDAK_DEPLOY_MASTER_KEY` are required by an approved non-web operation, configure strong server-side values. The application intentionally has no source-code defaults; missing values must fail closed. Rotate any value that may have matched a credential formerly embedded in source.

## Verification before E2E

1. Verify the deployed source SHA against the reviewed release commit without printing configuration secrets.
2. Verify the staging virtual host serves the CodeIgniter front controller and HTTPS, and that private app files and sensitive artifacts return denied/not found responses.
3. Verify the effective staging database and photo-storage canonical paths are distinct from production; record only sanitized identity evidence.
4. Verify authenticated photo access and rejection without a valid session. Do not infer authorization from an HTTP 200 health check.
5. Back up staging database and private photo storage, then restore both to isolated test targets. Never restore into production or overwrite active staging data.
6. Run staging E2E for authentication/roles, GIS and findings, Kubikel inspections, Beyond KWH boundaries, and private photo upload/view/delete.
7. Review pending migrations and rollback consequences before applying them to staging. Production migrations remain blocked until separate release approval, verified backups, and a rollback plan exist.

## Rollback

Retain the previous staging release and document-root target until smoke and E2E checks pass. If checks fail, switch the staging vhost back to the verified previous target; do not delete the old release, database, or photo storage as part of rollback.
