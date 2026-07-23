#!/bin/sh
# Nightly PostgreSQL backup to a restic repository on S3.
#
# Dumps plain SQL to a temp file FIRST, then backs that file up with restic. Dumping to a file (not
# piping pg_dump straight into `restic backup --stdin`) is deliberate: a failing pg_dump writes an
# empty/partial stream that restic would happily commit as a valid (empty) snapshot, so a bad night
# would silently poison the repo and a later `restic dump latest` could restore nothing. With a file,
# `set -e` aborts before restic ever runs if pg_dump fails. Plain SQL (not gzip/custom) lets restic
# dedup + compress across days. Retention is restic's Grandfather-Father-Son forget policy.
#
# Runs in the `backup` image (postgres:18-alpine + restic). Driven by env from the Helm CronJob:
#   RESTIC_REPOSITORY, RESTIC_PASSWORD, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY
#   PGHOST, PGPORT, PGUSER, PGDATABASE, PGPASSWORD  (pg_dump reads these, so it takes no args)
#   BACKUP_ENV (restic --host label), KEEP_DAILY, KEEP_WEEKLY, KEEP_MONTHLY
set -eu
# busybox ash (alpine) supports pipefail; harmless belt-and-braces even without a pipe.
set -o pipefail

: "${RESTIC_REPOSITORY:?}" "${RESTIC_PASSWORD:?}" "${AWS_ACCESS_KEY_ID:?}" "${AWS_SECRET_ACCESS_KEY:?}"
: "${PGHOST:?}" "${PGDATABASE:?}"

DUMP="${HOME:-/tmp}/${PGDATABASE}.sql"
trap 'rm -f "$DUMP"' EXIT

# Initialise the repository on first run (idempotent — no-op once it exists).
if ! restic cat config >/dev/null 2>&1; then
    echo "Initialising restic repository at ${RESTIC_REPOSITORY}"
    restic init
fi

echo "Dumping ${PGDATABASE} from ${PGHOST}"
# If pg_dump fails, set -e aborts here — restic is never called, so no empty/partial snapshot lands.
pg_dump --no-owner --no-privileges --clean --if-exists --file "$DUMP"

echo "Backing up to restic"
# Pipe the completed file to --stdin so the snapshot keeps a stable filename (not the temp path).
restic backup --stdin --stdin-filename "${PGDATABASE}.sql" --host "${BACKUP_ENV:-$PGHOST}" --tag db < "$DUMP"

echo "Applying retention (daily=${KEEP_DAILY:-8} weekly=${KEEP_WEEKLY:-4} monthly=${KEEP_MONTHLY:-12})"
restic forget \
    --keep-daily "${KEEP_DAILY:-8}" \
    --keep-weekly "${KEEP_WEEKLY:-4}" \
    --keep-monthly "${KEEP_MONTHLY:-12}" \
    --prune

restic snapshots --tag db
