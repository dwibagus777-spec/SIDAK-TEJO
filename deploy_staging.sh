#!/usr/bin/env bash
set -euo pipefail

readonly BRANCH="feature/inspeksi-kubikel-beyond-kwh"
readonly REPO_HTTPS="https://github.com/dwibagus777-spec/SIDAK-TEJO.git"
readonly REPO_SSH="git@github.com:dwibagus777-spec/SIDAK-TEJO.git"
readonly PRODUCTION_DATABASE="u532206332_sidaktejo"

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

: "${DEPLOY_CONFIRM:?Set DEPLOY_CONFIRM=deploy-staging only after staging is verified}"
: "${DEPLOY_COMMIT:?Set DEPLOY_COMMIT to the reviewed 40-character feature commit SHA}"
: "${STAGING_DOC_ROOT:?Set STAGING_DOC_ROOT to the existing staging application checkout}"
: "${STAGING_STORAGE:?Set STAGING_STORAGE to the existing private photo storage directory}"
: "${STAGING_BASE_URL:?Set STAGING_BASE_URL to the verified staging URL}"
: "${STAGING_DB_NAME:?Set STAGING_DB_NAME to the dedicated staging database name}"

[[ "$DEPLOY_CONFIRM" == "deploy-staging" ]] || fail "Explicit staging confirmation is required."
[[ "$DEPLOY_COMMIT" =~ ^[0-9a-f]{40}$ ]] || fail "DEPLOY_COMMIT must be a full commit SHA."
[[ "$STAGING_BASE_URL" == "https://staging.sidaktejo.site" ]] || fail "Unexpected staging URL."
[[ "$STAGING_DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || fail "STAGING_DB_NAME must be a database identifier."
[[ "$STAGING_DB_NAME" != "$PRODUCTION_DATABASE" ]] || fail "Production database is forbidden."

[[ -d "$STAGING_DOC_ROOT/.git" ]] || fail "Staging checkout must already exist; no clone or file sync is performed."
[[ -f "$STAGING_DOC_ROOT/.env" ]] || fail "Existing staging .env is required and will not be overwritten."
[[ -d "$STAGING_STORAGE" ]] || fail "Private staging photo storage must already exist."

doc_root="$(cd "$STAGING_DOC_ROOT" && pwd -P)"
storage_root="$(cd "$STAGING_STORAGE" && pwd -P)"
[[ "$doc_root" == */staging ]] || fail "Document root must be the verified staging directory."
[[ "$storage_root" != "$doc_root" && "$storage_root" != "$doc_root/"* ]] || fail "Photo storage must remain outside the document root."

env_file="$doc_root/.env"
env_mode="$(stat -c '%a' "$env_file")"
[[ "$env_mode" =~ ^6[04]0$ ]] || fail "Staging .env permissions must be 600 or 640."
grep -Eq '^[[:space:]]*CI_ENVIRONMENT[[:space:]]*=[[:space:]]*"?staging"?[[:space:]]*$' "$env_file" || fail "Existing .env must set CI_ENVIRONMENT=staging."
configured_database="$(sed -n 's/^[[:space:]]*database\.default\.database[[:space:]]*=[[:space:]]*"\{0,1\}\([^"#]*\)"\{0,1\}[[:space:]]*$/\1/p' "$env_file" | tail -n 1)"
configured_database="${configured_database//[[:space:]]/}"
[[ -n "$configured_database" && "$configured_database" == "$STAGING_DB_NAME" ]] || fail "Existing .env does not identify the requested staging database."
grep -Eq '^[[:space:]]*database\.default\.password[[:space:]]*=[[:space:]]*[^[:space:]#]' "$env_file" || fail "Existing .env must contain the rotated staging DB secret."
awk -v expected="$storage_root" '
    /^[[:space:]]*SIDAK_STORAGE_PATH[[:space:]]*=/ {
        value = $0
        sub(/^[^=]*=[[:space:]]*/, "", value)
        gsub(/^["[:space:]]+|["[:space:]]+$/, "", value)
        if (value == expected) found = 1
    }
    END { exit !found }
' "$env_file" || fail "Existing .env must point SIDAK_STORAGE_PATH to the verified private storage."
grep -Eq '^[[:space:]]*app\.baseURL[[:space:]]*=[[:space:]]*"?https://staging\.sidaktejo\.site/?["[:space:]]*$' "$env_file" || fail "Existing .env must target the verified staging URL."

repo_root="$(git -C "$doc_root" rev-parse --show-toplevel)"
remote_url="$(git -C "$repo_root" remote get-url origin)"
[[ "$remote_url" == "$REPO_HTTPS" || "$remote_url" == "$REPO_SSH" ]] || fail "Unexpected origin remote."
git -C "$repo_root" diff --quiet || fail "Tracked worktree changes exist; refusing deployment."
git -C "$repo_root" diff --cached --quiet || fail "Staged worktree changes exist; refusing deployment."

printf 'Checking pinned staging release %s...\n' "$DEPLOY_COMMIT"
git -C "$repo_root" fetch --quiet origin "$BRANCH"
git -C "$repo_root" cat-file -e "${DEPLOY_COMMIT}^{commit}" 2>/dev/null || fail "Pinned commit is unavailable in the staging checkout."
git -C "$repo_root" merge-base --is-ancestor "$DEPLOY_COMMIT" FETCH_HEAD || fail "Pinned commit is not contained in the remote feature branch."

previous_commit="$(git -C "$repo_root" rev-parse HEAD)"
git -C "$repo_root" switch --detach "$DEPLOY_COMMIT"
composer --working-dir="$repo_root" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

printf '\nStaging code deployed at %s.\n' "$DEPLOY_COMMIT"
printf 'Previous checkout: %s\n' "$previous_commit"
printf 'Rollback code only with: git -C "%s" switch --detach %s\n' "$repo_root" "$previous_commit"
printf 'Database migrations were NOT run. Back up and verify the dedicated staging database, inspect pending migrations, then apply only the approved migration plan.\n'
