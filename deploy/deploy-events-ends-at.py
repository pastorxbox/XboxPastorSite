#!/usr/bin/env python3
"""Deploy multi-day events (ends_at) to Hostinger. Requires SSHPASS."""
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ.get("SSHPASS", "")
if not PASSWORD:
    raise SystemExit("SSHPASS required")

DB_NAME = os.environ.get("DB_NAME", "u285071835_PastorXbox")
DB_USER = os.environ.get("DB_USER", "u285071835_pastorxbox")
DB_PASS = os.environ.get("DB_PASSWORD", PASSWORD)

files = [
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/api-calendar.php", f"{REMOTE}/includes/api-calendar.php"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("sql/017_events_ends_at.sql", "domains/pastorxbox.com.br/sql/017_events_ends_at.sql"),
]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "147.93.39.20",
    port=65002,
    username="u285071835",
    password=PASSWORD,
    allow_agent=False,
    look_for_keys=False,
    timeout=45,
)
sftp = c.open_sftp()
for local, remote in files:
    path = ROOT / local
    print(f"put {path.name} ({path.stat().st_size} bytes)")
    sftp.put(str(path), remote)
sftp.close()

sql_remote = "domains/pastorxbox.com.br/sql/017_events_ends_at.sql"
# Apply migration idempotently: ignore "Duplicate column" if re-run
cmd = (
    f"mysql -u {DB_USER} -p'{DB_PASS}' {DB_NAME} < {sql_remote} 2>&1 | "
    "grep -v 'Using a password' || true; "
    f"mysql -u {DB_USER} -p'{DB_PASS}' {DB_NAME} -N -e "
    "\"SHOW COLUMNS FROM calendar_events LIKE 'ends_at'; "
    "SELECT COUNT(*) FROM calendar_events WHERE ends_at IS NULL;\" 2>&1 | grep -v 'Using a password'"
)
_, o, e = c.exec_command(cmd)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
