#!/usr/bin/env bash
# Persistent terminal: follow the compose stack logs so the agent can watch
# the web, mysql, and phpmyadmin services.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

"$REPO_ROOT/.cursor/dockerd-up.sh"
exec sudo docker compose logs -f
