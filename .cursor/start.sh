#!/usr/bin/env bash
# Cloud Agent start phase: per-boot reconciliation.
# Ensures the Docker daemon is up and the compose stack is running, then returns.
# Safe to run on every boot and to re-run.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

# Re-assert nested-VM Docker settings in case this boots from a fresh base.
sudo update-alternatives --set iptables /usr/sbin/iptables-legacy >/dev/null 2>&1 || true
sudo update-alternatives --set ip6tables /usr/sbin/ip6tables-legacy >/dev/null 2>&1 || true
sudo mkdir -p /etc/docker
echo '{"storage-driver":"fuse-overlayfs"}' | sudo tee /etc/docker/daemon.json >/dev/null

[ -f .env ] || cp .env.example .env

"$REPO_ROOT/.cursor/dockerd-up.sh"

# Bring up the stack and wait until MySQL is healthy and services are running.
sudo docker compose up -d --wait
sudo docker compose ps

echo "Site:       http://localhost:8080"
echo "phpMyAdmin: http://localhost:8081"
