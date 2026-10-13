#!/usr/bin/env bash
set -euo pipefail

readonly BRANCH="feature/inspeksi-kubikel-beyond-kwh"
readonly REPO_HTTPS="https://github.com/dwibagus777-spec/SIDAK-TEJO.git"
readonly REPO_SSH="git@github.com:dwibagus777-spec/SIDAK-TEJO.git"
readonly MAX_BACKUP_AGE_SECONDS=$((24 * 60 * 60))
readonly MAX_RESTORE_EVIDENCE_AGE_SECONDS=$((30 * 24 * 60 * 60))

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

read_env_value() {
    awk -F= -v requested="$2" '
        {
            line = $0
            sub(/^[[:space:]]*/, "", line)
            if (line ~ /^#/ || index(line, "=") == 0) next
            key = line
            sub(/=.*/, "", key)
            sub(/[[:space:]]*$/, "", key)
            if (key != requested) next
            value = line
            sub(/^[^=]*=/, "", value)
            sub(/^[[:space:]]*/, "", value)
            sub(/[[:space:]]*$/, "", value)
            if (value ~ /^".*"$/ || value ~ /^'\''.*'\''$/) {
                value = substr(value, 2, length(value) - 2)
            }
            found = value
        }
        END { if (found != "") print found }
    ' "$1"
}

read_config_value() {
    local env_file="$1"
    shift
    local key value
    for key in "$@"; do
        value="$(read_env_value "$env_file" "$key")"
        if [[ -n "$value" ]]; then
            printf '%s' "$value"
            return 0
        fi
    done
    return 1
}

normalize_host() {
    local host="${1,,}"
    host="${host%.}"
    printf '%s' "$host"
}

manifest_value() {
    read_env_value "$STAGING_BACKUP_MANIFEST" "$1"
}

verify_manifest_fields() {
    local field count
    for field in \
        database_host database_port database_name storage_path created_at \
        database_dump database_sha256 storage_archive storage_sha256 \
        restore_result restore_tested_at restore_target_host restore_target_database \
        restore_evidence restore_evidence_sha256; do
        count="$(awk -F= -v requested="$field" '
        {
            line = $0
            sub(/^[[:space:]]*/, "", line)
            key = line
            sub(/=.*/, "", key)
            sub(/[[:space:]]*$/, "", key)
            if (key == requested) count++
        }
        END { print count + 0 }
        ' "$STAGING_BACKUP_MANIFEST")"
        [[ "$count" -eq 1 ]] || fail "Backup evidence manifest is missing or duplicates a required field."
    done
}

verify_backup_file() {
    local path="$1"
    local expected_hash="$2"
    local actual_hash file_mode canonical_path

    [[ "$path" == /* && -f "$path" && ! -L "$path" ]] || fail "Backup evidence references a missing or invalid artifact."
    canonical_path="$(realpath -e "$path")" || fail "Backup artifact path cannot be resolved."
    [[ "$canonical_path" == "$backup_root/"* ]] || fail "Backup artifact resolves outside the protected backup directory."
    [[ "$canonical_path" != "$doc_root" && "$canonical_path" != "$doc_root/"* ]] || fail "Backup evidence must be stored outside the staging document root."
    file_mode="$(stat -c '%a' "$canonical_path")"
    [[ "$file_mode" =~ ^6[04]0$ ]] || fail "Backup artifacts and restore evidence must have permissions 600 or 640."
    [[ "$expected_hash" =~ ^[0-9a-f]{64}$ ]] || fail "Backup evidence contains an invalid SHA-256 value."
    actual_hash="$(sha256sum -- "$canonical_path" | awk '{print $1}')" || fail "Unable to verify a backup artifact checksum."
    [[ "$actual_hash" == "$expected_hash" ]] || fail "A backup artifact checksum does not match its evidence manifest."
}

verify_timestamp() {
    local timestamp="$1"
    local max_age="$2"
    local label="$3"
    local parsed now

    [[ "$timestamp" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$ ]] || fail "$label timestamp must be UTC RFC 3339."
    parsed="$(date -u -d "$timestamp" +%s 2>/dev/null)" || fail "$label timestamp is invalid."
    [[ "$(date -u -d "$timestamp" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null)" == "$timestamp" ]] || fail "$label timestamp is invalid."
    now="$(date -u +%s)"
    (( parsed <= now )) || fail "$label timestamp must not be in the future."
    (( now - parsed <= max_age )) || fail "$label evidence is too old."
}

verify_backup_manifest() {
    local manifest_mode manifest_host manifest_port manifest_database manifest_storage
    local created_at dump_path dump_hash archive_path archive_hash
    local restore_result restore_tested_at restore_host restore_database evidence_path evidence_hash

    [[ -n "$STAGING_BACKUP_MANIFEST" && -f "$STAGING_BACKUP_MANIFEST" && ! -L "$STAGING_BACKUP_MANIFEST" ]] || fail "A verified staging backup manifest is required."
    manifest_path="$(realpath -e "$STAGING_BACKUP_MANIFEST")" || fail "Backup manifest path cannot be resolved."
    [[ "$manifest_path" == "$backup_root/"* ]] || fail "Backup manifest resolves outside the protected backup directory."
    [[ "$manifest_path" != "$doc_root" && "$manifest_path" != "$doc_root/"* ]] || fail "Backup manifest must be stored outside the staging document root."
    manifest_mode="$(stat -c '%a' "$manifest_path")"
    [[ "$manifest_mode" =~ ^6[04]0$ ]] || fail "Backup manifest permissions must be 600 or 640."
    verify_manifest_fields

    manifest_host="$(manifest_value database_host)"
    manifest_port="$(manifest_value database_port)"
    manifest_database="$(manifest_value database_name)"
    manifest_storage="$(manifest_value storage_path)"
    created_at="$(manifest_value created_at)"
    dump_path="$(manifest_value database_dump)"
    dump_hash="$(manifest_value database_sha256)"
    archive_path="$(manifest_value storage_archive)"
    archive_hash="$(manifest_value storage_sha256)"
    restore_result="$(manifest_value restore_result)"
    restore_tested_at="$(manifest_value restore_tested_at)"
    restore_host="$(manifest_value restore_target_host)"
    restore_database="$(manifest_value restore_target_database)"
    evidence_path="$(manifest_value restore_evidence)"
    evidence_hash="$(manifest_value restore_evidence_sha256)"

    [[ "$(normalize_host "$manifest_host")" == "$(normalize_host "$configured_database_host")" && "$manifest_port" == "$configured_database_port" && "$manifest_database" == "$configured_database" && "$manifest_storage" == "$storage_root" ]] || fail "Backup evidence does not match the configured staging database and storage targets."
    verify_timestamp "$created_at" "$MAX_BACKUP_AGE_SECONDS" "Backup"
    verify_backup_file "$dump_path" "$dump_hash"
    verify_backup_file "$archive_path" "$archive_hash"
    [[ "$restore_result" == "PASS" ]] || fail "A successful isolated restore test is required before deployment."
    verify_timestamp "$restore_tested_at" "$MAX_RESTORE_EVIDENCE_AGE_SECONDS" "Restore test"
    [[ -n "$restore_host" && -n "$restore_database" ]] || fail "Restore evidence must identify its isolated restore target."
    if [[ "$(normalize_host "$restore_host")" == "$(normalize_host "$PRODUCTION_DB_HOST")" ||
          "$(normalize_host "$restore_host")" == "$(normalize_host "$configured_database_host")" ||
          "$restore_database" == "$PRODUCTION_DB_NAME" ||
          "$restore_database" == "$configured_database" ]]; then
        fail "Restore evidence must not target production or the active staging database."
    fi
    verify_backup_file "$evidence_path" "$evidence_hash"
    grep -Fxq 'SIDAK_BACKUP_RESTORE=PASS' "$evidence_path" || fail "Restore evidence must contain the exact successful restore marker."
}

: "${DEPLOY_CONFIRM:?Set DEPLOY_CONFIRM=deploy-staging only after staging is verified}"
: "${DEPLOY_COMMIT:?Set DEPLOY_COMMIT to the reviewed 40-character feature commit SHA}"
: "${STAGING_DOC_ROOT:?Set STAGING_DOC_ROOT to the existing staging application checkout}"
: "${STAGING_STORAGE:?Set STAGING_STORAGE to the existing private photo storage directory}"
: "${STAGING_BASE_URL:?Set STAGING_BASE_URL to the verified staging URL}"
: "${STAGING_DB_NAME:?Set STAGING_DB_NAME to the dedicated staging database name}"
: "${STAGING_BACKUP_DIR:?Set STAGING_BACKUP_DIR to the protected backup directory outside the document root}"
: "${STAGING_BACKUP_MANIFEST:?Set STAGING_BACKUP_MANIFEST to the protected backup evidence manifest}"
: "${PRODUCTION_DB_HOST:?Set PRODUCTION_DB_HOST to the verified production database host}"
: "${PRODUCTION_DB_NAME:?Set PRODUCTION_DB_NAME to the verified production database name}"
: "${PRODUCTION_STORAGE:?Set PRODUCTION_STORAGE to the verified production photo storage directory}"

[[ "$DEPLOY_CONFIRM" == "deploy-staging" ]] || fail "Explicit staging confirmation is required."
[[ "$DEPLOY_COMMIT" =~ ^[0-9a-f]{40}$ ]] || fail "DEPLOY_COMMIT must be a full commit SHA."
[[ "$STAGING_BASE_URL" == "https://staging.sidaktejo.site" ]] || fail "Unexpected staging URL."
[[ "$STAGING_DB_NAME" =~ ^[A-Za-z0-9_]+$ && "$PRODUCTION_DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || fail "Database names must be valid identifiers."
[[ "$PRODUCTION_DB_HOST" != *[[:space:]]* && "$PRODUCTION_DB_HOST" != "" ]] || fail "A verified production database host is required."

[[ -d "$STAGING_DOC_ROOT/.git" ]] || fail "Staging checkout must already exist; no clone or file sync is performed."
[[ -f "$STAGING_DOC_ROOT/.env" && ! -L "$STAGING_DOC_ROOT/.env" ]] || fail "Existing regular staging .env is required and will not be overwritten."
[[ -d "$STAGING_STORAGE" && -d "$PRODUCTION_STORAGE" ]] || fail "Staging and production private photo storage directories must both exist."
[[ -d "$STAGING_BACKUP_DIR" && ! -L "$STAGING_BACKUP_DIR" ]] || fail "Protected backup directory must be an existing directory, not a symlink."

doc_root="$(cd "$STAGING_DOC_ROOT" && pwd -P)"
storage_root="$(cd "$STAGING_STORAGE" && pwd -P)"
production_storage_root="$(cd "$PRODUCTION_STORAGE" && pwd -P)"
backup_root="$(cd "$STAGING_BACKUP_DIR" && pwd -P)"
[[ "$doc_root" == */staging ]] || fail "Document root must be the verified staging directory."
[[ "$storage_root" != "$doc_root" && "$storage_root" != "$doc_root/"* ]] || fail "Photo storage must remain outside the document root."
[[ "$production_storage_root" != "$doc_root" && "$production_storage_root" != "$doc_root/"* ]] || fail "Production photo storage must remain outside the staging document root."
[[ "$storage_root" != "$production_storage_root" && "$storage_root" != "$production_storage_root/"* && "$production_storage_root" != "$storage_root/"* ]] || fail "Staging and production photo storage must be separate directories."
[[ "$backup_root" != "$doc_root" && "$backup_root" != "$doc_root/"* ]] || fail "Protected backup directory must remain outside the document root."
[[ "$backup_root" != "$storage_root" && "$backup_root" != "$storage_root/"* && "$storage_root" != "$backup_root/"* ]] || fail "Backup directory must be separate from staging photo storage."
[[ "$backup_root" != "$production_storage_root" && "$backup_root" != "$production_storage_root/"* && "$production_storage_root" != "$backup_root/"* ]] || fail "Backup directory must be separate from production photo storage."

env_file="$doc_root/.env"
env_mode="$(stat -c '%a' "$env_file")"
[[ "$env_mode" =~ ^6[04]0$ ]] || fail "Staging .env permissions must be 600 or 640."
configured_environment="$(read_config_value "$env_file" CI_ENVIRONMENT || true)"
[[ "$configured_environment" == "staging" ]] || fail "Existing .env must set CI_ENVIRONMENT=staging."
configured_database="$(read_config_value "$env_file" DB_NAME MYSQLDATABASE MYSQL_DATABASE database_default_database database.default.database || true)"
configured_database_host="$(read_config_value "$env_file" DB_HOST MYSQLHOST MYSQL_HOST database_default_hostname database.default.hostname || true)"
configured_database_port="$(read_config_value "$env_file" DB_PORT MYSQLPORT MYSQL_PORT database_default_port database.default.port || true)"
configured_database_port="${configured_database_port:-3306}"
production_database_port="${PRODUCTION_DB_PORT:-3306}"
[[ "$configured_database" == "$STAGING_DB_NAME" && "$configured_database_host" != "" ]] || fail "Existing .env does not identify the requested staging database and host."
[[ "$configured_database_port" =~ ^[0-9]+$ && "$configured_database_port" -ge 1 && "$configured_database_port" -le 65535 ]] || fail "Staging database port is invalid."
[[ "$production_database_port" =~ ^[0-9]+$ && "$production_database_port" -ge 1 && "$production_database_port" -le 65535 ]] || fail "Production database port is invalid."
if [[ "$(normalize_host "$configured_database_host")" == "$(normalize_host "$PRODUCTION_DB_HOST")" && "$configured_database_port" == "$production_database_port" && "$configured_database" == "$PRODUCTION_DB_NAME" ]]; then
    fail "Configured staging database target matches the verified production target."
fi
[[ "$configured_database" != "$PRODUCTION_DB_NAME" ]] || fail "Staging database name must be distinct from the production database name."
runtime_database_name="${DB_NAME:-${MYSQLDATABASE:-${MYSQL_DATABASE:-${database_default_database:-}}}}"
runtime_database_host="${DB_HOST:-${MYSQLHOST:-${MYSQL_HOST:-${database_default_hostname:-}}}}"
runtime_database_port="${DB_PORT:-${MYSQLPORT:-${MYSQL_PORT:-${database_default_port:-}}}}"
if [[ -n "${CI_ENVIRONMENT:-}" && "$CI_ENVIRONMENT" != "$configured_environment" ]] ||
   [[ -n "$runtime_database_name" && "$runtime_database_name" != "$configured_database" ]] ||
   [[ -n "$runtime_database_host" && "$(normalize_host "$runtime_database_host")" != "$(normalize_host "$configured_database_host")" ]] ||
   [[ -n "$runtime_database_port" && "$runtime_database_port" != "$configured_database_port" ]] ||
   [[ -n "${SIDAK_STORAGE_PATH:-}" && "$(realpath -m "$SIDAK_STORAGE_PATH")" != "$storage_root" ]]; then
    fail "Runtime environment overrides conflict with the verified staging .env targets."
fi
if ! read_config_value "$env_file" DB_PASS MYSQLPASSWORD MYSQL_PASSWORD MYSQL_ROOT_PASSWORD database_default_password database.default.password >/dev/null; then
    fail "Existing .env must contain the rotated staging DB secret."
fi
configured_storage="$(read_env_value "$env_file" SIDAK_STORAGE_PATH)"
[[ "$configured_storage" == "$storage_root" ]] || fail "Existing .env must point SIDAK_STORAGE_PATH to the verified private staging storage."
grep -Eq '^[[:space:]]*app\.baseURL[[:space:]]*=[[:space:]]*"?https://staging\.sidaktejo\.site/?["[:space:]]*$' "$env_file" || fail "Existing .env must target the verified staging URL."
verify_backup_manifest

repo_root="$(git -C "$doc_root" rev-parse --show-toplevel)"
remote_url="$(git -C "$repo_root" remote get-url origin)"
[[ "$remote_url" == "$REPO_HTTPS" || "$remote_url" == "$REPO_SSH" ]] || fail "Unexpected origin remote."
export GIT_OPTIONAL_LOCKS=0
repo_status="$(git -C "$repo_root" status --porcelain --untracked-files=all)"
[[ -z "$repo_status" ]] || fail "Worktree changes exist; refusing deployment."

printf 'Checking pinned staging release %s...\n' "$DEPLOY_COMMIT"
if [[ "${DEPLOY_PREFLIGHT_ONLY:-0}" == "1" ]]; then
    git -C "$repo_root" cat-file -e "${DEPLOY_COMMIT}^{commit}" 2>/dev/null || {
        printf 'BLOCKED: pinned commit is not available in local Git metadata; verify it externally without fetching in preflight-only mode.\n' >&2
        exit 3
    }
    git -C "$repo_root" merge-base --is-ancestor "$DEPLOY_COMMIT" "refs/remotes/origin/$BRANCH" 2>/dev/null || {
        printf 'BLOCKED: local origin feature ref does not prove pinned commit ancestry; verify remote metadata externally without fetching in preflight-only mode.\n' >&2
        exit 3
    }
    printf 'Staging preflight passed. No Git metadata, branch, application files, database, or server state was changed.\n'
    exit 0
fi

git -C "$repo_root" fetch --quiet origin "$BRANCH"
git -C "$repo_root" cat-file -e "${DEPLOY_COMMIT}^{commit}" 2>/dev/null || fail "Pinned commit is unavailable in the staging checkout."
git -C "$repo_root" merge-base --is-ancestor "$DEPLOY_COMMIT" FETCH_HEAD || fail "Pinned commit is not contained in the remote feature branch."

previous_commit="$(git -C "$repo_root" rev-parse HEAD)"
git -C "$repo_root" switch --detach "$DEPLOY_COMMIT"
composer --working-dir="$repo_root" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

printf '\nStaging code deployed at %s.\n' "$DEPLOY_COMMIT"
printf 'Previous checkout: %s\n' "$previous_commit"
printf 'Rollback code only with: git -C "%s" switch --detach %s\n' "$repo_root" "$previous_commit"
printf 'Database migrations were NOT run. Inspect pending migrations and apply only the approved migration plan.\n'
