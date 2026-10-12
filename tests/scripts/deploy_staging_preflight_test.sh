#!/usr/bin/env bash
set -euo pipefail

unset CI_ENVIRONMENT DB_NAME DB_HOST DB_PORT SIDAK_STORAGE_PATH
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
deploy_script="$repo_root/deploy_staging.sh"
realpath_bin="$(command -v realpath)"
work_root="$(mktemp -d)"
trap 'rm -rf -- "$work_root"' EXIT

assert_contains() {
    [[ "$1" == *"$2"* ]] || {
        printf 'Expected output to contain: %s\n' "$2" >&2
        exit 1
    }
}

assert_not_contains() {
    [[ "$1" != *"$2"* ]] || {
        printf 'Output unexpectedly contained sensitive or forbidden text.\n' >&2
        exit 1
    }
}

prepare_fixture() {
    fixture="$work_root/$1"
    staging_root="$fixture/home/staging"
    repo_fixture="$staging_root"
    staging_storage="$fixture/private/staging-photos"
    production_storage="$fixture/private/production-photos"
    backup_root="$fixture/backup"
    fake_bin="$fixture/bin"
    git_call_log="$fixture/git-calls.log"

    mkdir -p "$repo_fixture/.git" "$staging_storage" "$production_storage" "$backup_root" "$fake_bin"
    staging_root_posix="$(cd "$staging_root" && pwd -P)"
    staging_storage_posix="$(cd "$staging_storage" && pwd -P)"
    production_storage_posix="$(cd "$production_storage" && pwd -P)"
    backup_root_posix="$(cd "$backup_root" && pwd -P)"
    fake_bin_posix="$(cd "$fake_bin" && pwd -P)"

    cat > "$repo_fixture/.env" <<'EOF'
CI_ENVIRONMENT=staging
app.baseURL="https://staging.sidaktejo.site"
DB_HOST=staging-db.internal
DB_PORT=3306
DB_NAME=sidaktejo_staging
DB_PASS=do-not-print-this-secret
SIDAK_STORAGE_PATH=
EOF
    chmod 600 "$repo_fixture/.env"
    sed -i "s|^SIDAK_STORAGE_PATH=.*|SIDAK_STORAGE_PATH=$staging_storage_posix|" "$repo_fixture/.env"

    printf 'isolated staging database dump\n' > "$backup_root/database.sql"
    printf 'isolated staging storage archive\n' > "$backup_root/photos.tar"
    printf 'SIDAK_BACKUP_RESTORE=PASS\n' > "$backup_root/restore-evidence.txt"

    cat > "$fake_bin/git" <<'EOF'
#!/usr/bin/env bash
case "$*" in
    *) printf '%s\n' "$*" >> "$GIT_CALL_LOG" ;;
esac
case "$*" in
    *"rev-parse --show-toplevel"*) printf '%s\n' "$FAKE_REPO_ROOT" ;;
    *"remote get-url origin"*) printf '%s\n' "https://github.com/dwibagus777-spec/SIDAK-TEJO.git" ;;
    *"status --porcelain"*) exit 0 ;;
    *"cat-file -e"*) [[ "${FAKE_MISSING_COMMIT:-0}" != "1" ]] ;;
    *"merge-base --is-ancestor"*) [[ "${FAKE_MISSING_REMOTE_REF:-0}" != "1" ]] ;;
    *) exit 0 ;;
esac
EOF
    chmod +x "$fake_bin/git"
    cat > "$fake_bin/stat" <<'EOF'
#!/usr/bin/env bash
case "$*" in
    *"/.env") printf '600\n' ;;
    *"/manifest.txt") printf '600\n' ;;
    *"/database.sql") printf '600\n' ;;
    *"/photos.tar") printf '600\n' ;;
    *"/restore-evidence.txt") printf '600\n' ;;
    *) exec /usr/bin/stat "$@" ;;
esac
EOF
    chmod +x "$fake_bin/stat"
    cat > "$fake_bin/realpath" <<'EOF'
#!/usr/bin/env bash
if [[ "$1" == "-e" && -n "${FAKE_REALPATH_ESCAPE_PATH:-}" && "$2" == "$FAKE_REALPATH_ESCAPE_PATH" ]]; then
    printf '%s\n' "$FAKE_REALPATH_ESCAPE_TARGET"
else
    exec "$REALPATH_BIN" "$@"
fi
EOF
    chmod +x "$fake_bin/realpath"

    backup_created="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    database_hash="$(sha256sum "$backup_root/database.sql" | awk '{print $1}')"
    storage_hash="$(sha256sum "$backup_root/photos.tar" | awk '{print $1}')"
    evidence_hash="$(sha256sum "$backup_root/restore-evidence.txt" | awk '{print $1}')"
    cat > "$backup_root/manifest.txt" <<EOF
database_host=staging-db.internal
database_port=3306
database_name=sidaktejo_staging
storage_path=$staging_storage_posix
created_at=$backup_created
database_dump=$backup_root_posix/database.sql
database_sha256=$database_hash
storage_archive=$backup_root_posix/photos.tar
storage_sha256=$storage_hash
restore_result=PASS
restore_tested_at=$backup_created
restore_target_host=isolated-restore.internal
restore_target_database=sidaktejo_restore_test
restore_evidence=$backup_root_posix/restore-evidence.txt
restore_evidence_sha256=$evidence_hash
EOF
    chmod 600 "$backup_root/manifest.txt" "$backup_root/restore-evidence.txt"

    export FAKE_REPO_ROOT="$staging_root_posix"
    export DEPLOY_CONFIRM=deploy-staging
    export DEPLOY_COMMIT=0f1e431f0597d4898a590f2097ed780e6a01ea99
    export STAGING_DOC_ROOT="$staging_root_posix"
    export STAGING_STORAGE="$staging_storage_posix"
    export STAGING_BASE_URL=https://staging.sidaktejo.site
    export STAGING_DB_NAME=sidaktejo_staging
    export STAGING_BACKUP_DIR="$backup_root_posix"
    export STAGING_BACKUP_MANIFEST="$backup_root_posix/manifest.txt"
    export PRODUCTION_DB_HOST=production-db.internal
    export PRODUCTION_DB_NAME=sidaktejo_production
    export PRODUCTION_STORAGE="$production_storage_posix"
    export DEPLOY_PREFLIGHT_ONLY=1
    export PATH="$fake_bin_posix:$PATH"
    export GIT_CALL_LOG="$git_call_log"
    export REALPATH_BIN="$realpath_bin"
    unset FAKE_REALPATH_ESCAPE_PATH FAKE_REALPATH_ESCAPE_TARGET
    unset FAKE_MISSING_COMMIT FAKE_MISSING_REMOTE_REF
}

run_preflight() {
    if output="$(bash "$deploy_script" 2>&1)"; then
        result=0
    else
        result=$?
    fi
}

prepare_fixture valid
env_hash_before="$(sha256sum "$STAGING_DOC_ROOT/.env" | awk '{print $1}')"
manifest_hash_before="$(sha256sum "$STAGING_BACKUP_MANIFEST" | awk '{print $1}')"
run_preflight
[[ "$result" -eq 0 ]] || { printf '%s\n' "$output" >&2; exit 1; }
assert_contains "$output" "Staging preflight passed."
assert_not_contains "$output" "do-not-print-this-secret"
[[ "$(sha256sum "$STAGING_DOC_ROOT/.env" | awk '{print $1}')" == "$env_hash_before" ]] || {
    printf 'Preflight-only modified the fixture .env.\n' >&2
    exit 1
}
[[ "$(sha256sum "$STAGING_BACKUP_MANIFEST" | awk '{print $1}')" == "$manifest_hash_before" ]] || {
    printf 'Preflight-only modified its backup manifest.\n' >&2
    exit 1
}
if grep -Eq '(^|[[:space:]])(fetch|checkout|switch|reset|pull|clean|commit|push)([[:space:]]|$)' "$GIT_CALL_LOG"; then
    printf 'Preflight-only invoked a Git command that can write repository metadata.\n' >&2
    exit 1
fi
if [[ -e "$STAGING_DOC_ROOT/.preflight-write" ]]; then
    printf 'Preflight-only unexpectedly wrote into its checkout fixture.\n' >&2
    exit 1
fi

prepare_fixture production-db
export PRODUCTION_DB_HOST=staging-db.internal
export PRODUCTION_DB_NAME=sidaktejo_staging
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Production database target was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "matches the verified production target"

prepare_fixture production-storage
export PRODUCTION_STORAGE="$STAGING_STORAGE"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Production storage target was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "must be separate directories"

prepare_fixture unknown-environment
sed -i 's/^CI_ENVIRONMENT=staging$/CI_ENVIRONMENT=stagng/' "$STAGING_DOC_ROOT/.env"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Unknown environment was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "CI_ENVIRONMENT=staging"

prepare_fixture corrupted-backup
printf 'tampered\n' >> "$backup_root/database.sql"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Corrupted backup artifact was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "checksum does not match"

prepare_fixture missing-restore-pass
sed -i 's/^restore_result=PASS$/restore_result=FAIL/' "$backup_root/manifest.txt"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Unverified restore evidence was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "successful isolated restore test is required"

prepare_fixture restore-on-production-host
sed -i 's/^restore_target_host=isolated-restore.internal$/restore_target_host=production-db.internal/' "$backup_root/manifest.txt"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Restore on the production DB host was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "must not target production"

prepare_fixture duplicate-manifest-field
printf 'database_name=production_database\n' >> "$backup_root/manifest.txt"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Duplicate manifest fields were not rejected.\n' >&2; exit 1; }
assert_contains "$output" "missing or duplicates a required field"

prepare_fixture canonical-backup-escape
outside_backup="$fixture/outside"
mkdir -p "$outside_backup" "$backup_root/linked"
printf 'external dump\n' > "$outside_backup/database.sql"
outside_hash="$(sha256sum "$outside_backup/database.sql" | awk '{print $1}')"
printf 'untrusted link target placeholder\n' > "$backup_root/linked/database.sql"
export FAKE_REALPATH_ESCAPE_PATH="$backup_root_posix/linked/database.sql"
export FAKE_REALPATH_ESCAPE_TARGET="$(cd "$outside_backup" && pwd -P)/database.sql"
sed -i "s|^database_dump=.*|database_dump=$FAKE_REALPATH_ESCAPE_PATH|; s|^database_sha256=.*|database_sha256=$outside_hash|" "$backup_root/manifest.txt"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Backup artifact resolving outside the safe directory was not rejected: %s\n' "$output" >&2; exit 1; }
assert_contains "$output" "resolves outside the protected backup directory"

prepare_fixture missing-local-remote-metadata
export FAKE_MISSING_COMMIT=1
run_preflight
[[ "$result" -eq 3 ]] || { printf 'Unavailable local metadata did not produce BLOCKED status.\n' >&2; exit 1; }
assert_contains "$output" "BLOCKED:"
if grep -q 'fetch' "$GIT_CALL_LOG"; then
    printf 'Preflight-only fetched missing remote metadata instead of blocking.\n' >&2
    exit 1
fi

prepare_fixture missing-local-branch-ref
export FAKE_MISSING_REMOTE_REF=1
run_preflight
[[ "$result" -eq 3 ]] || { printf 'Unavailable local branch ref did not produce BLOCKED status.\n' >&2; exit 1; }
assert_contains "$output" "BLOCKED:"
if grep -Eq '(^|[[:space:]])fetch([[:space:]]|$)' "$GIT_CALL_LOG"; then
    printf 'Preflight-only fetched a missing branch ref instead of blocking.\n' >&2
    exit 1
fi

prepare_fixture missing-backup-manifest
export STAGING_BACKUP_MANIFEST="$backup_root_posix/not-present.manifest"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Missing backup manifest was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "verified staging backup manifest is required"

prepare_fixture missing-restore-marker
printf 'SIDAK_BACKUP_RESTORE=FAIL\n' > "$backup_root/restore-evidence.txt"
evidence_hash="$(sha256sum "$backup_root/restore-evidence.txt" | awk '{print $1}')"
sed -i "s|^restore_evidence_sha256=.*|restore_evidence_sha256=$evidence_hash|" "$backup_root/manifest.txt"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Missing successful restore evidence marker was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "exact successful restore marker"

prepare_fixture invalid-checksum
sed -i 's/^database_sha256=.*/database_sha256=invalid/' "$backup_root/manifest.txt"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Invalid backup checksum was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "invalid SHA-256"

prepare_fixture stale-backup-time
sed -i 's/^created_at=.*/created_at=2020-01-01T00:00:00Z/' "$backup_root/manifest.txt"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Stale backup evidence was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "Backup evidence is too old"

prepare_fixture runtime-storage-override
export SIDAK_STORAGE_PATH="$PRODUCTION_STORAGE"
run_preflight
[[ "$result" -ne 0 ]] || { printf 'Runtime production storage override was not rejected.\n' >&2; exit 1; }
assert_contains "$output" "Runtime environment overrides conflict"

printf 'All staging preflight tests passed (16 scenarios).\n'
