#!/usr/bin/env bash
set -euo pipefail
KEY=/mnt/c/projetos/oracle/ssh-key-2026-06-11.key
HOST=ubuntu@147.15.79.91
ssh -i "$KEY" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=30 "$HOST" bash -s <<'REMOTE'
set -euo pipefail
echo "=== /opt/xbox-manager ==="
ls -la /opt/xbox-manager | head -25
echo "=== compose ps ==="
cd /opt/xbox-manager && docker compose ps
echo "=== grep host files ==="
grep -rn "store_url" /opt/xbox-manager/src/app/calendario /opt/xbox-manager/src/lib/site-api.ts 2>/dev/null | head -20 || echo "no host src matches"
echo "=== container ==="
CID=$(docker compose ps -q app | head -1)
echo "CID=$CID"
if [ -n "$CID" ]; then
  docker exec "$CID" sh -c 'ls /app/src/app/calendario 2>/dev/null || ls /app 2>/dev/null | head; grep -rn store_url /app/src/app/calendario /app/src/lib/site-api.ts 2>/dev/null | head -15 || echo no-store-url-in-container-src'
fi
REMOTE
