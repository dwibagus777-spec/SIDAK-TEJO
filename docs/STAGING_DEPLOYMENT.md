# Staging deployment gate

The staging deployment script deploys application code only. It does not create
or replace the staging checkout, overwrite `.env`, touch photo storage, or run
database migrations.

## Required preparation by the hosting operator

1. Rotate any database or hosting credential previously embedded in a deployment
   script. Do not put credentials in Git, command history, tickets, or chat.
2. Confirm in Hostinger that staging uses a dedicated database and a private
   photo-storage directory outside the staging document root. Record the
   verified production database host/name/port and photo-storage path as
   non-secret comparison values. Neither staging target may identify production.
3. Update the existing staging `.env` with the rotated secret and verify that it
   has mode `600` or `640`. It must set `CI_ENVIRONMENT=staging`, use
   `https://staging.sidaktejo.site` as `app.baseURL`, set the staging database
   host, port and name, and point `SIDAK_STORAGE_PATH` to the verified private
   directory. Also verify the runtime environment does not override these
   `.env` values; a CLI preflight cannot inspect PHP-FPM or Hostinger account
   settings. The CodeIgniter dotted keys below are read by the application's
   database configuration; supply the actual database values privately in the
   Hostinger file manager:

   ```text
   database.default.hostname=<verified staging database host>
   database.default.database=<dedicated staging database name>
   database.default.username=<staging database user>
   database.default.password=<staging-only secret>
   database.default.port=<verified staging database port>
   CI_ENVIRONMENT=staging
   app.baseURL=https://staging.sidaktejo.site/
   SIDAK_STORAGE_PATH=/home/u532206332/domains/sidaktejo.site/sidak_storage_staging/foto
   ```

   The storage path is the currently reported staging location; verify its
   canonical server path and permissions before using it.
4. Create a staging database dump and storage archive outside the document root.
   Test restoration into a separate, isolated target that is neither production
   nor the active staging database. Keep the database dump, storage archive,
   restore evidence and manifest outside the document root, each with mode
   `600` or `640`. The manifest must contain these exact `key=value` entries:

   ```text
   database_host=<staging database host>
   database_port=<staging database port>
   database_name=<staging database name>
   storage_path=<canonical staging storage path>
   created_at=<UTC timestamp, YYYY-MM-DDTHH:MM:SSZ>
   database_dump=<absolute path to database dump>
   database_sha256=<64-character lowercase SHA-256>
   storage_archive=<absolute path to storage archive>
   storage_sha256=<64-character lowercase SHA-256>
   restore_result=PASS
   restore_tested_at=<UTC timestamp, YYYY-MM-DDTHH:MM:SSZ>
   restore_target_host=<isolated restore-test host>
   restore_target_database=<isolated restore-test database>
   restore_evidence=<absolute path to restore-test evidence>
   restore_evidence_sha256=<64-character lowercase SHA-256>
   ```

   The restore evidence must contain the exact line
   `SIDAK_BACKUP_RESTORE=PASS`. The script checks the permissions on all backup
   artifacts, both artifact checksums, unique manifest fields, the staging
   target identifiers, timestamps, the restore marker, and that the restore
   target is distinct from production and staging. The
   manifest and marker are operator-supplied evidence, not a cryptographic
   attestation that proves a restore actually succeeded; review the restore
   procedure and evidence independently. The backup must be no more than 24
   hours old and the restore test no more than 30 days old.
5. Review the Kubikel migration and current migration status before applying
   schema changes. Do not use `php spark migrate --all` as part of code
   deployment. The Kubikel migration rollback drops all seven inspection tables
   and their data; do not use it as a recovery action without a verified backup
   and an approved data plan. The Temuan FK rollback removes only the constraint
   and intentionally does not narrow or re-sign `temuan.asset_id`.
6. Confirm SSH access and the exact existing staging checkout and storage paths.
   This repository does not verify Hostinger account settings, runtime
   environment overrides, storage ACLs or rotate secrets.

## Pinned code deployment

Run in an SSH shell on the host after confirming the existing staging checkout.
First fetch the branch, then execute the safety script directly from that
branch. Set the environment variables from verified Hostinger configuration;
never pass database passwords as script arguments:

```sh
export DEPLOY_CONFIRM=deploy-staging
export DEPLOY_COMMIT=<reviewed-full-commit-sha>
export STAGING_DOC_ROOT=<existing-staging-document-root-ending-in-staging>
export STAGING_STORAGE=<existing-private-photo-storage-directory>
export STAGING_BASE_URL=https://staging.sidaktejo.site
export STAGING_DB_NAME=<dedicated-staging-database-name>
export PRODUCTION_DB_HOST=<verified-production-database-host>
export PRODUCTION_DB_NAME=<verified-production-database-name>
export PRODUCTION_DB_PORT=<verified-production-database-port>
export PRODUCTION_STORAGE=<verified-production-private-storage-path>
export STAGING_BACKUP_DIR=<protected-backup-directory-outside-document-root>
export STAGING_BACKUP_MANIFEST=<protected-backup-evidence-manifest-path>
git -C "$STAGING_DOC_ROOT" fetch origin feature/inspeksi-kubikel-beyond-kwh
bash <(git -C "$STAGING_DOC_ROOT" show origin/feature/inspeksi-kubikel-beyond-kwh:deploy_staging.sh)
```

The production identifiers are comparison values, not credentials. Do not put
passwords, tokens or full `.env` contents in the manifest or command output.
The script checks the existing checkout, `.env` permissions and database
host/port/name, confirms the canonical staging storage path is separate from
the supplied production storage path, validates the protected backup evidence,
and checks the clean Git worktree, trusted repository remote, and pinned commit
ancestry. Distinct filesystem paths alone do not prove storage ACL isolation;
verify access controls on the server separately.

Before deployment, the same checks can be run without switching revisions or
installing dependencies by setting `DEPLOY_PREFLIGHT_ONLY=1`. The script prints
that preflight passed and exits before any fetch, branch switch, repository
metadata write, or application-file change. It validates ancestry only against
the locally available `origin/feature/inspeksi-kubikel-beyond-kwh` ref; if that
ref or the pinned object is unavailable, it returns `BLOCKED` and requires an
external read-only GitHub verification. `GIT_OPTIONAL_LOCKS=0` is used to avoid
optional Git index refresh writes. The mode does not connect to or write a
database. Local regression tests for the preflight gates run with:

```sh
bash tests/scripts/deploy_staging_preflight_test.sh
```

For an approved code deployment, omit `DEPLOY_PREFLIGHT_ONLY`; the script then
switches the checkout to the selected commit and installs Composer production
dependencies. It prints the previous code revision for code-only rollback.

The script deliberately stops before schema changes. Only after a verified
staging backup and migration review should the hosting operator run the
approved migration procedure against the dedicated staging database.

## Verification gate

After code and the approved schema are deployed, test the staging site before
considering a production release:

- Create, edit, view, and update an inspection and its checklist and findings.
- Upload, view, and delete a Kubikel photo; verify its file remains outside the
  public document root and unauthorized users cannot stream it.
- Verify Beyond KWH users can view and update only recommendations assigned to
  their own account, and cannot access another user's recommendation, inspection,
  or photo.
- Review logs, migration status, and database/storage backups.

Staging reachability alone does not prove that this feature is deployed. A
production release requires a separate review and explicit authorization.
