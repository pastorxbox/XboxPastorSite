#!/usr/bin/env bash
# Cloud Agent install phase: prepare durable, source-derived state.
# Installs Docker tooling, configures it for this nested VM, creates the local
# .env, and pre-builds/pulls the compose images so the first boot is fast.
# Must be idempotent: it may run repeatedly and against cached/snapshot state.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

export DEBIAN_FRONTEND=noninteractive

# 1. System packages (Docker engine + compose plugin + nested-container deps).
if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  sudo apt-get update -qq
  # --force-confold keeps the pre-existing /etc/fuse.conf instead of prompting.
  sudo apt-get install -y -qq -o Dpkg::Options::=--force-confold \
    docker.io docker-compose-v2 fuse-overlayfs iptables
fi

# 2. Docker bridge networking needs legacy iptables inside the nested VM.
sudo update-alternatives --set iptables /usr/sbin/iptables-legacy >/dev/null 2>&1 || true
sudo update-alternatives --set ip6tables /usr/sbin/ip6tables-legacy >/dev/null 2>&1 || true

# 3. overlay2 is unavailable in the nested container; use fuse-overlayfs.
sudo mkdir -p /etc/docker
echo '{"storage-driver":"fuse-overlayfs"}' | sudo tee /etc/docker/daemon.json >/dev/null

# 4. Local, non-committed environment file (see .env.example / .gitignore).
if [ ! -f .env ]; then
  cp .env.example .env
  echo "created .env from .env.example"
fi

# 5. Pre-build the web image and pull third-party images into the snapshot.
"$REPO_ROOT/.cursor/dockerd-up.sh"
sudo docker compose build
sudo docker compose pull --ignore-buildable

echo "install complete"
