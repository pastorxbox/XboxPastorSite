#!/usr/bin/env bash
# Idempotently ensure the Docker daemon is running in this nested Cloud Agent VM.
# Cloud Agent VMs have no systemd, so dockerd is launched manually.
set -euo pipefail

if sudo docker info >/dev/null 2>&1; then
  echo "dockerd already running"
  exit 0
fi

# Clear a stale pid file left over from a previous boot/snapshot.
sudo rm -f /var/run/docker.pid

sudo bash -c 'nohup dockerd >/var/log/dockerd.log 2>&1 &'

for _ in $(seq 1 60); do
  if sudo docker info >/dev/null 2>&1; then
    echo "dockerd ready"
    exit 0
  fi
  sleep 1
done

echo "ERROR: dockerd did not become ready in time" >&2
sudo tail -n 40 /var/log/dockerd.log >&2 || true
exit 1
