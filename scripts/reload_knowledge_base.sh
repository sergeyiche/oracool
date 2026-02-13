#!/usr/bin/env bash
set -euo pipefail

GLOBAL_USER_ID="${1:-858361483}"
RELOAD_MODE="${2:-global-only}" # global-only | full
DRY_RUN="${3:-false}" # true | false

if [[ "${RELOAD_MODE}" != "global-only" && "${RELOAD_MODE}" != "full" ]]; then
  echo "Usage: $0 [global_user_id] [reload_mode:global-only|full] [dry_run:true|false]"
  echo "Example (safe): $0 858361483 global-only false"
  echo "Example (destructive): $0 858361483 full false"
  exit 1
fi

if [[ "${DRY_RUN}" != "true" && "${DRY_RUN}" != "false" ]]; then
  echo "Usage: $0 [global_user_id] [reload_mode:global-only|full] [dry_run:true|false]"
  echo "Example: $0 858361483 global-only false"
  exit 1
fi

ROOT_DIR="/www/oracool"
KB_DIR_REL="kb"
KB_DIR_ABS="${ROOT_DIR}/${KB_DIR_REL}"

echo "=== Full Knowledge Base Reload ==="
echo "Global user id: ${GLOBAL_USER_ID}"
echo "Reload mode: ${RELOAD_MODE}"
echo "Dry run: ${DRY_RUN}"
echo "Knowledge directory: ${KB_DIR_ABS}"
echo

if [[ ! -d "${KB_DIR_ABS}" ]]; then
  echo "ERROR: knowledge directory not found: ${KB_DIR_ABS}"
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -q '^oracool-app$'; then
  echo "ERROR: container oracool-app is not running"
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -q '^oracool-postgres$'; then
  echo "ERROR: container oracool-postgres is not running"
  exit 1
fi

mapfile -t KB_FILES < <(
  find "${KB_DIR_ABS}" -type f -name '*.txt' \
    | sed "s#^${ROOT_DIR}/##" \
    | sort
)

if [[ ${#KB_FILES[@]} -eq 0 ]]; then
  echo "ERROR: no .txt files found under ${KB_DIR_ABS}"
  exit 1
fi

echo "Files to import (${#KB_FILES[@]}):"
for rel in "${KB_FILES[@]}"; do
  echo "  - ${rel}"
done
echo

if [[ "${DRY_RUN}" == "true" ]]; then
  echo "[DRY RUN] No changes will be applied."
  if [[ "${RELOAD_MODE}" == "full" ]]; then
    echo "[DRY RUN] Would execute: TRUNCATE TABLE knowledge_base;"
  else
    echo "[DRY RUN] Would execute: DELETE FROM knowledge_base WHERE user_id = ${GLOBAL_USER_ID};"
  fi
  for rel in "${KB_FILES[@]}"; do
    echo "[DRY RUN] Would import: /app/${rel} -> user ${GLOBAL_USER_ID}"
  done
  exit 0
fi

if [[ "${RELOAD_MODE}" == "full" ]]; then
  echo "Step 1/3: Truncating knowledge_base (FULL RESET)..."
  docker exec oracool-postgres psql -U oracool -d oracool -c "TRUNCATE TABLE knowledge_base;"
else
  echo "Step 1/3: Cleaning global KB only (preserve user overlays)..."
  docker exec oracool-postgres psql -U oracool -d oracool -c "DELETE FROM knowledge_base WHERE user_id = ${GLOBAL_USER_ID};"
fi

echo
echo "Step 2/3: Importing discovered .txt files into global KB..."
for rel in "${KB_FILES[@]}"; do
  echo "  -> ${rel}"
  docker exec oracool-app php bin/console knowledge:import "/app/${rel}" "${GLOBAL_USER_ID}" >/dev/null
done

echo
echo "Step 3/3: Result snapshot..."
docker exec oracool-postgres psql -U oracool -d oracool -c "
SELECT
  user_id,
  source,
  embedding_model,
  COUNT(*) AS entries,
  MIN(created_at) AS first_added,
  MAX(created_at) AS last_added
FROM knowledge_base
GROUP BY user_id, source, embedding_model
ORDER BY user_id, entries DESC;
"

echo
echo "Reload completed."
