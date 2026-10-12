# Staging deployment gate

The staging deployment script deploys application code only. It does not create
or replace the staging checkout, overwrite `.env`, touch photo storage, or run
database migrations.

## Required preparation by the hosting operator

1. Rotate any database or hosting credential previously embedded in a deployment
   script. Do not put credentials in Git, command history, tickets, or chat.
2. Confirm in Hostinger that staging uses a dedicated database and a private
   photo-storage directory outside the staging document root. Neither may point
   to production.
3. Update the existing staging `.env` with the rotated secret and verify that it
   has mode `600` or `640`. It must set `CI_ENVIRONMENT=staging`, use
   `https://staging.sidaktejo.site` as `app.baseURL`, set the staging database
   name, and point `SIDAK_STORAGE_PATH` to the verified private directory.
4. Back up the staging database and storage. Review the Kubikel migration and
   the current migration status before applying any schema changes. Do not use
   `php spark migrate --all` as part of this deployment. The Kubikel migration
   rollback drops all seven inspection tables and their data; do not use it as
   a recovery action without a verified backup and an approved data plan. The
   Temuan FK rollback removes only the constraint and intentionally does not
   narrow or re-sign `temuan.asset_id`.
5. Confirm SSH access and the exact existing staging checkout and storage paths.
   This repository does not verify Hostinger account settings or rotate secrets.

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
git -C "$STAGING_DOC_ROOT" fetch origin feature/inspeksi-kubikel-beyond-kwh
bash <(git -C "$STAGING_DOC_ROOT" show origin/feature/inspeksi-kubikel-beyond-kwh:deploy_staging.sh)
```

The script checks the existing checkout, `.env` permissions and target
configuration, private-storage location, clean Git worktree, trusted repository
remote, and that the pinned commit belongs to the feature branch. It switches
the checkout to the selected commit and installs Composer production
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
