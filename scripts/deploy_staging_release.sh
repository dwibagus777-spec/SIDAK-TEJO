#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

readonly EXPECTED_APP_ROOT="/home/u532206332/domains/sidaktejo.site/sidak_staging_app/app_release"
readonly EXPECTED_STAGING_STORAGE="/home/u532206332/domains/sidaktejo.site/sidak_storage_staging/foto"
readonly EXPECTED_PRODUCTION_STORAGE="/home/u532206332/domains/sidaktejo.site/sidak_storage/foto"
readonly STAGING_URL="https://staging.sidaktejo.site"

fail() {
    printf 'BLOCKED: %s\n' "$1" >&2
    exit 1
}

read_env_value() {
    local file="$1"
    local key="$2"

    awk -F= -v requested="$key" '
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
    ' "$file"
}

read_config_value() {
    local file="$1"
    shift
    local key value
    for key in "$@"; do
        value="$(read_env_value "$file" "$key")"
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

verify_backup_file() {
    local path="$1"
    local expected_hash="$2"
    local canonical_path file_mode actual_hash

    [[ "$path" == /* && -f "$path" && ! -L "$path" ]] || fail "Backup evidence references an invalid file."
    canonical_path="$(realpath -e "$path")" || fail "Backup evidence path cannot be resolved."
    [[ "$canonical_path" == "$backup_root/"* ]] || fail "Backup evidence file is outside the protected backup directory."
    [[ "$canonical_path" != "$web_root" && "$canonical_path" != "$web_root/"* ]] || fail "Backup evidence must remain outside the document root."
    file_mode="$(stat -c '%a' "$canonical_path")"
    [[ "$file_mode" =~ ^6[04]0$ ]] || fail "Backup evidence file permissions must be 600 or 640."
    [[ "$expected_hash" =~ ^[0-9a-f]{64}$ ]] || fail "Backup evidence has an invalid SHA-256 value."
    actual_hash="$(sha256sum -- "$canonical_path" | awk '{print $1}')" || fail "Unable to verify a backup evidence checksum."
    [[ "$actual_hash" == "$expected_hash" ]] || fail "A backup evidence checksum does not match."
}

verify_backup_manifest() {
    local field count
    local manifest_mode manifest_host manifest_port manifest_database manifest_storage
    local created_at dump_path dump_hash archive_path archive_hash
    local restore_result restore_tested_at restore_host restore_database evidence_path evidence_hash

    [[ -n "${STAGING_BACKUP_MANIFEST:-}" && -f "$STAGING_BACKUP_MANIFEST" && ! -L "$STAGING_BACKUP_MANIFEST" ]] || fail "Verified staging backup and restore evidence is required."
    local manifest_path
    manifest_path="$(realpath -e "$STAGING_BACKUP_MANIFEST")" || fail "Backup manifest path cannot be resolved."
    [[ "$manifest_path" == "$backup_root/"* ]] || fail "Backup manifest is outside the protected backup directory."
    [[ "$manifest_path" != "$web_root" && "$manifest_path" != "$web_root/"* ]] || fail "Backup manifest must remain outside the document root."
    manifest_mode="$(stat -c '%a' "$manifest_path")"
    [[ "$manifest_mode" =~ ^6[04]0$ ]] || fail "Backup manifest permissions must be 600 or 640."

    for field in \
        database_host database_port database_name storage_path created_at \
        database_dump database_sha256 storage_archive storage_sha256 \
        restore_result restore_tested_at restore_target_host restore_target_database \
        restore_evidence restore_evidence_sha256; do
        count="$(awk -F= -v requested="$field" '
            {
                key = $0
                sub(/=.*/, "", key)
                sub(/^[[:space:]]*/, "", key)
                sub(/[[:space:]]*$/, "", key)
                if (key == requested) count++
            }
            END { print count + 0 }
        ' "$manifest_path")"
        [[ "$count" -eq 1 ]] || fail "Backup manifest is missing or duplicates a required field."
    done

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

    [[ "$(normalize_host "$manifest_host")" == "$(normalize_host "$configured_database_host")" &&
       "$manifest_port" == "$configured_database_port" &&
       "$manifest_database" == "$configured_database" &&
       "$manifest_storage" == "$storage_root" ]] || fail "Backup evidence targets do not match the staging configuration."
    [[ "$restore_result" == "PASS" ]] || fail "An isolated database and storage restore test is required."

    local timestamp
    for timestamp in "$created_at" "$restore_tested_at"; do
        [[ "$timestamp" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$ ]] || fail "Backup or restore evidence timestamp is invalid."
        date -u -d "$timestamp" +%s >/dev/null 2>&1 || fail "Backup or restore evidence timestamp is invalid."
        [[ "$(date -u -d "$timestamp" +%Y-%m-%dT%H:%M:%SZ)" == "$timestamp" ]] || fail "Backup or restore evidence timestamp is invalid."
    done
    local now created_epoch restore_epoch
    now="$(date -u +%s)"
    created_epoch="$(date -u -d "$created_at" +%s)"
    restore_epoch="$(date -u -d "$restore_tested_at" +%s)"
    (( created_epoch <= now && now - created_epoch <= 86400 )) || fail "Staging backup evidence is invalid or older than 24 hours."
    (( restore_epoch <= now && now - restore_epoch <= 2592000 )) || fail "Isolated restore evidence is invalid or older than 30 days."

    [[ -n "$restore_host" && -n "$restore_database" ]] || fail "Restore evidence does not identify its isolated target."
    if { [[ "$(normalize_host "$restore_host")" == "$(normalize_host "$PRODUCTION_DB_HOST")" ]] &&
         [[ "$restore_database" == "$PRODUCTION_DB_NAME" ]]; } ||
       { [[ "$(normalize_host "$restore_host")" == "$(normalize_host "$configured_database_host")" ]] &&
         [[ "$restore_database" == "$configured_database" ]]; }; then
        fail "Restore target must be distinct from production and active staging."
    fi

    verify_backup_file "$dump_path" "$dump_hash"
    verify_backup_file "$archive_path" "$archive_hash"
    verify_backup_file "$evidence_path" "$evidence_hash"
    grep -Fxq 'SIDAK_BACKUP_RESTORE=PASS' "$evidence_path" || fail "Restore evidence lacks the required success marker."
}

preflight() {
    local env_file env_mode configured_environment configured_base_url
    local configured_database_user configured_database_password
    local configured_storage configured_web_root
    local runtime_database runtime_host runtime_port
    local runtime_dotted_database runtime_dotted_host runtime_dotted_port

    [[ "${STAGING_APP_ROOT:-}" == "$EXPECTED_APP_ROOT" ]] || fail "Staging application root does not match the approved private release directory."
    [[ "${STAGING_STORAGE:-}" == "$EXPECTED_STAGING_STORAGE" ]] || fail "Staging photo storage does not match the approved private storage directory."
    [[ "${PRODUCTION_STORAGE:-}" == "$EXPECTED_PRODUCTION_STORAGE" ]] || fail "Production photo storage comparison path is not the verified production directory."
    [[ "${STAGING_BASE_URL:-}" == "$STAGING_URL" ]] || fail "Unexpected staging URL."
    [[ "${STAGING_DB_NAME:-}" =~ ^[A-Za-z0-9_]+$ && "${PRODUCTION_DB_NAME:-}" =~ ^[A-Za-z0-9_]+$ ]] || fail "Database identifiers are missing or invalid."
    [[ "${STAGING_DB_PORT:-}" =~ ^[0-9]+$ && "${PRODUCTION_DB_PORT:-}" =~ ^[0-9]+$ ]] || fail "Database port configuration is missing or invalid."
    [[ -n "${STAGING_DB_HOST:-}" && -n "${PRODUCTION_DB_HOST:-}" ]] || fail "Verified staging and production database hosts are required."
    [[ "$STAGING_DB_PORT" -ge 1 && "$STAGING_DB_PORT" -le 65535 &&
       "$PRODUCTION_DB_PORT" -ge 1 && "$PRODUCTION_DB_PORT" -le 65535 ]] || fail "Database port is outside the valid range."

    [[ -d "$STAGING_APP_ROOT" && ! -L "$STAGING_APP_ROOT" ]] || fail "Existing extracted staging application directory is missing or is a symlink."
    [[ -f "$STAGING_APP_ROOT/public/index.php" && -f "$STAGING_APP_ROOT/vendor/autoload.php" ]] || fail "Existing CodeIgniter public entry point or Composer dependencies are missing."
    env_file="$STAGING_APP_ROOT/.env"
    [[ -f "$env_file" && ! -L "$env_file" ]] || fail "A regular staging-only .env must already exist; deployment will not create or replace it."
    env_mode="$(stat -c '%a' "$env_file")"
    [[ "$env_mode" =~ ^6[04]0$ ]] || fail "Staging .env permissions must be 600 or 640."

    [[ -d "$STAGING_WEB_ROOT" ]] || fail "Configured staging document root does not exist."
    configured_web_root="$(realpath -e "$STAGING_WEB_ROOT")" || fail "Staging document root cannot be resolved."
    [[ "$configured_web_root" == "$(realpath -e "$STAGING_APP_ROOT/public")" ]] || fail "Staging document root must resolve exactly to the existing app_release/public directory."
    web_root="$configured_web_root"

    [[ -d "$STAGING_STORAGE" && -w "$STAGING_STORAGE" && -d "$PRODUCTION_STORAGE" ]] || fail "Private staging/production photo storage directories are unavailable."
    storage_root="$(realpath -e "$STAGING_STORAGE")" || fail "Staging photo storage cannot be resolved."
    production_storage_root="$(realpath -e "$PRODUCTION_STORAGE")" || fail "Production photo storage cannot be resolved."
    [[ "$storage_root" == "$EXPECTED_STAGING_STORAGE" ]] || fail "Staging storage canonical path does not match the approved private location."
    [[ "$storage_root" != "$production_storage_root" ]] || fail "Staging and production photo storage resolve to the same directory."
    [[ "$storage_root" != "$web_root" && "$storage_root" != "$web_root/"* ]] || fail "Staging photo storage is inside the web document root."
    [[ "$production_storage_root" != "$web_root" && "$production_storage_root" != "$web_root/"* ]] || fail "Production photo storage is inside the staging document root."

    env_file="$(realpath -e "$env_file")"
    configured_environment="$(read_env_value "$env_file" CI_ENVIRONMENT)"
    configured_base_url="$(read_env_value "$env_file" app.baseURL)"
    configured_database_host="$(read_config_value "$env_file" DB_HOST MYSQLHOST MYSQL_HOST database.default.hostname database_default_hostname || true)"
    configured_database_port="$(read_config_value "$env_file" DB_PORT MYSQLPORT MYSQL_PORT database.default.port database_default_port || true)"
    configured_database_name="$(read_config_value "$env_file" DB_NAME MYSQLDATABASE MYSQL_DATABASE database.default.database database_default_database || true)"
    configured_database_user="$(read_config_value "$env_file" DB_USER MYSQLUSER MYSQL_USER database.default.username database_default_username || true)"
    configured_database_password="$(read_config_value "$env_file" DB_PASS MYSQLPASSWORD MYSQL_PASSWORD MYSQL_ROOT_PASSWORD database.default.password database_default_password || true)"
    configured_database_port="${configured_database_port:-3306}"
    configured_storage="$(read_env_value "$env_file" SIDAK_STORAGE_PATH)"

    [[ "$configured_environment" == "staging" && "$configured_base_url" == "$STAGING_URL/" ]] || fail "Existing .env must identify the staging environment and HTTPS base URL."
    [[ -n "$configured_database_user" && -n "$configured_database_password" ]] || fail "Existing .env must contain staging-only database credentials."
    [[ "$(normalize_host "$configured_database_host")" == "$(normalize_host "$STAGING_DB_HOST")" &&
       "$configured_database_port" == "$STAGING_DB_PORT" &&
       "$configured_database_name" == "$STAGING_DB_NAME" ]] || fail "Existing .env database target does not match the verified staging database."
    [[ "$configured_database_name" != "$PRODUCTION_DB_NAME" ]] || fail "Staging database name must be distinct from production."
    if [[ "$(normalize_host "$configured_database_host")" == "$(normalize_host "$PRODUCTION_DB_HOST")" &&
          "$configured_database_port" == "$PRODUCTION_DB_PORT" &&
          "$configured_database_name" == "$PRODUCTION_DB_NAME" ]]; then
        fail "Staging database target matches production."
    fi
    [[ "$(realpath -e "$configured_storage")" == "$storage_root" ]] || fail "Existing .env must point SIDAK_STORAGE_PATH to the private staging photo directory."

    runtime_database="${DB_NAME:-${MYSQLDATABASE:-${MYSQL_DATABASE:-${database_default_database:-}}}}"
    runtime_host="${DB_HOST:-${MYSQLHOST:-${MYSQL_HOST:-${database_default_hostname:-}}}}"
    runtime_port="${DB_PORT:-${MYSQLPORT:-${MYSQL_PORT:-${database_default_port:-}}}}"
    runtime_dotted_database="$(printenv 'database.default.database' || true)"
    runtime_dotted_host="$(printenv 'database.default.hostname' || true)"
    runtime_dotted_port="$(printenv 'database.default.port' || true)"
    if [[ -n "${CI_ENVIRONMENT:-}" && "$CI_ENVIRONMENT" != "staging" ]] ||
       [[ -n "$runtime_database" && "$runtime_database" != "$configured_database_name" ]] ||
       [[ -n "$runtime_host" && "$(normalize_host "$runtime_host")" != "$(normalize_host "$configured_database_host")" ]] ||
       [[ -n "$runtime_port" && "$runtime_port" != "$configured_database_port" ]] ||
       [[ -n "$runtime_dotted_database" && "$runtime_dotted_database" != "$configured_database_name" ]] ||
       [[ -n "$runtime_dotted_host" && "$(normalize_host "$runtime_dotted_host")" != "$(normalize_host "$configured_database_host")" ]] ||
       [[ -n "$runtime_dotted_port" && "$runtime_dotted_port" != "$configured_database_port" ]] ||
       [[ -n "${SIDAK_STORAGE_PATH:-}" && "$(realpath -m "$SIDAK_STORAGE_PATH")" != "$storage_root" ]]; then
        fail "Runtime environment overrides conflict with the staging .env."
    fi

    [[ -d "${STAGING_BACKUP_DIR:-}" && ! -L "$STAGING_BACKUP_DIR" ]] || fail "Protected backup directory must already exist and must not be a symlink."
    backup_root="$(realpath -e "$STAGING_BACKUP_DIR")" || fail "Protected backup directory cannot be resolved."
    [[ "$backup_root" != "$STAGING_APP_ROOT" && "$backup_root" != "$STAGING_APP_ROOT/"* &&
       "$backup_root" != "$web_root" && "$backup_root" != "$web_root/"* &&
       "$backup_root" != "$storage_root" && "$backup_root" != "$storage_root/"* &&
       "$backup_root" != "$production_storage_root" && "$backup_root" != "$production_storage_root/"* ]] || fail "Protected backup directory overlaps application, web, or photo storage."

    command -v rsync >/dev/null || fail "Hostinger staging account must provide rsync before deployment."
    command -v tar >/dev/null || fail "Hostinger staging account must provide tar before deployment."
    command -v sha256sum >/dev/null || fail "Hostinger staging account must provide sha256sum before deployment."
    verify_backup_manifest
}

rollback_release() {
    local backup_archive="$1"
    local rollback_dir
    rollback_dir="$(mktemp -d "$(dirname "$STAGING_APP_ROOT")/.sidak-rollback.XXXXXX")" || return 1
    tar -xzf "$backup_archive" -C "$rollback_dir" || { rmdir "$rollback_dir"; return 1; }
    umask 022
    rsync -rlt --delete --no-perms --omit-dir-times \
        --exclude='/.env' \
        --exclude='/writable/***' \
        --exclude='/.git/***' \
        --exclude='/.sidak-release' \
        --exclude='/public/foto/***' \
        --exclude='/public/uploads/***' \
        "$rollback_dir/" "$STAGING_APP_ROOT/"
    local result=$?
    rm -rf -- "$rollback_dir"
    umask 077
    return "$result"
}

main() {
    local mode="${1:-}"
    local commit="${2:-}"
    local artifact="${3:-}"
    local backup_name="" temp_dir="" deploy_started=0

    [[ "$commit" =~ ^[0-9a-f]{40}$ ]] || fail "A full pinned commit SHA is required."
    preflight

    if [[ "$mode" == "preflight" ]]; then
        printf 'Staging deployment preflight passed for %s; no server files were changed.\n' "$commit"
        return 0
    fi
    [[ "$mode" == "deploy" ]] || fail "Use preflight or deploy mode."
    [[ -n "$artifact" && -f "$artifact" && ! -L "$artifact" ]] || fail "Pinned release archive is missing or invalid."
    [[ "$(dirname "$(realpath -e "$artifact")")" == "$backup_root" ]] || fail "Release archive must be staged in the protected backup directory."
    [[ "$(basename "$artifact")" == ".release-$commit.tar.gz" ]] || fail "Release archive name does not match the pinned commit."

    local archive_listing
    archive_listing="$(tar -tzf "$artifact")" || fail "Release archive is unreadable."
    if grep -Eq '(^|/)(\.env|writable)(/|$)|(^|/)\.git(/|$)|^(\./)?public/(foto|uploads)(/|$)' <<<"$archive_listing"; then
        fail "Release archive contains protected configuration, runtime, Git, or photo data."
    fi
    grep -Eq '^(\./)?public/index\.php$' <<<"$archive_listing" || fail "Release archive has no CodeIgniter public entry point."
    grep -Eq '^(\./)?vendor/autoload\.php$' <<<"$archive_listing" || fail "Release archive has no Composer autoloader."

    backup_name="$backup_root/app_release_before_${commit}_$(date -u +%Y%m%dT%H%M%SZ).tar.gz"
    [[ ! -e "$backup_name" ]] || fail "A code rollback archive already exists at the selected path."
    temp_dir="$(mktemp -d "$(dirname "$STAGING_APP_ROOT")/.sidak-release.XXXXXX")" || fail "Cannot create private extraction directory."
    trap 'status=$?; if [[ -n "$temp_dir" && -d "$temp_dir" ]]; then rm -rf -- "$temp_dir"; fi; if [[ -n "$artifact" && -f "$artifact" ]]; then rm -f -- "$artifact"; fi; if (( status != 0 && deploy_started == 1 )); then if rollback_release "$backup_name"; then printf "Deployment failed; prior code restored from %s.\\n" "$backup_name" >&2; else printf "CRITICAL: deployment and automatic code rollback both failed; preserve the server and recover from %s.\\n" "$backup_name" >&2; fi; fi; exit "$status"' EXIT

    tar -czf "$backup_name" \
        --exclude='./.env' \
        --exclude='./writable' \
        --exclude='./.git' \
        --exclude='./public/foto' \
        --exclude='./public/uploads' \
        -C "$STAGING_APP_ROOT" .
    chmod 600 "$backup_name"
    umask 022
    tar -xzf "$artifact" -C "$temp_dir"
    [[ -f "$temp_dir/public/index.php" && -f "$temp_dir/vendor/autoload.php" ]] || fail "Extracted release is incomplete."

    deploy_started=1
    rsync -rlt --delete --no-perms --omit-dir-times \
        --exclude='/.env' \
        --exclude='/writable/***' \
        --exclude='/.git/***' \
        --exclude='/.sidak-release' \
        --exclude='/public/foto/***' \
        --exclude='/public/uploads/***' \
        "$temp_dir/" "$STAGING_APP_ROOT/"
    umask 077
    printf '%s\n' "$commit" > "$STAGING_APP_ROOT/.sidak-release"
    chmod 600 "$STAGING_APP_ROOT/.sidak-release"
    deploy_started=0
    trap - EXIT
    rm -rf -- "$temp_dir"
    rm -f -- "$artifact"
    printf 'Staging release deployed: %s\n' "$commit"
    printf 'Private code rollback archive: %s\n' "$backup_name"
    printf 'The existing .env, writable directory, and private storage were preserved; no migrations were run.\n'
}

[[ -n "${STAGING_APP_ROOT:-}" && -n "${STAGING_WEB_ROOT:-}" && -n "${STAGING_STORAGE:-}" &&
   -n "${PRODUCTION_STORAGE:-}" && -n "${STAGING_BASE_URL:-}" && -n "${STAGING_DB_HOST:-}" &&
   -n "${STAGING_DB_PORT:-}" && -n "${STAGING_DB_NAME:-}" && -n "${PRODUCTION_DB_HOST:-}" &&
   -n "${PRODUCTION_DB_PORT:-}" && -n "${PRODUCTION_DB_NAME:-}" && -n "${STAGING_BACKUP_DIR:-}" &&
   -n "${STAGING_BACKUP_MANIFEST:-}" ]] || fail "Required staging-only deployment configuration is missing."

main "$@"
