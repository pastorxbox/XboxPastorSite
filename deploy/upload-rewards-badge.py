#!/usr/bin/env python3
"""Upload rewards/price changes + migrate price_brl + rebuild Manager."""
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
MANAGER_ROOT = Path("/mnt/c/Projetos/Xbox Manager")
MANAGER_KEY = Path("/mnt/c/Projetos/oracle/ssh-key-2026-06-11.key")
MANAGER_REMOTE = "/opt/xbox-manager"

SITE_FILES = [
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/api-calendar.php", f"{REMOTE}/includes/api-calendar.php"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/tvxbox.php", f"{REMOTE}/includes/tvxbox.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
    ("sql/018_releases_price_brl.sql", "domains/pastorxbox.com.br/sql/018_releases_price_brl.sql"),
]

MANAGER_FILES = [
    "src/app/calendario/actions.ts",
    "src/lib/site-api.ts",
    "src/components/calendario/GamePassPanel.tsx",
]


def hostinger():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(
        "147.93.39.20",
        port=65002,
        username="u285071835",
        password=os.environ["SSHPASS"],
        allow_agent=False,
        look_for_keys=False,
        timeout=45,
    )
    return c


def oracle():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        pkey = paramiko.Ed25519Key.from_private_key_file(str(MANAGER_KEY))
    except Exception:
        pkey = paramiko.RSAKey.from_private_key_file(str(MANAGER_KEY))
    c.connect("147.15.79.91", username="ubuntu", pkey=pkey, timeout=45, allow_agent=False, look_for_keys=False)
    return c


def run(c, cmd, timeout=120):
    print(">>>", cmd[:160])
    _, o, e = c.exec_command(cmd, timeout=timeout)
    out = o.read().decode(errors="replace")
    err = e.read().decode(errors="replace")
    code = o.channel.recv_exit_status()
    if out.strip():
        print(out.rstrip())
    if err.strip():
        print("ERR:", err[:800])
    print("exit=", code)
    return code, out


# --- Hostinger ---
c = hostinger()
sftp = c.open_sftp()
try:
    sftp.mkdir("domains/pastorxbox.com.br/sql")
except OSError:
    pass
for local, remote in SITE_FILES:
    print("put", local)
    sftp.put(str(ROOT / local), remote)
sftp.close()

migrate_cmd = r'''
set -e
cd ~
export $(grep -E '^(DB_HOST|DB_NAME|DB_USER|DB_PASS|DB_PASSWORD)=' domains/pastorxbox.com.br/config/.env 2>/dev/null | xargs) || true
DBH="${DB_HOST:-localhost}"
DBN="${DB_NAME}"
DBU="${DB_USER}"
DBP="${DB_PASS:-${DB_PASSWORD}}"
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "ALTER TABLE gp_releases ADD COLUMN price_brl DECIMAL(10,2) NULL AFTER store_url;" 2>&1 | tee /tmp/mig_price.out || true
grep -E "Duplicate|OK|price_brl" /tmp/mig_price.out || true
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -N -e "SHOW COLUMNS FROM gp_releases LIKE 'price_brl';"
'''
run(c, migrate_cmd, timeout=60)

smoke = r'''
python3 - <<'PY'
import urllib.request
req=urllib.request.Request("https://pastorxbox.com.br/calendario?lang=pt", headers={"User-Agent":"Mozilla"})
h=urllib.request.urlopen(req, timeout=45).read().decode("utf-8","replace")
css=open("domains/pastorxbox.com.br/public_html/assets/css/vitrine.css",encoding="utf-8",errors="replace").read().replace("\r\n","\n")
print("asset", "20260917rewards1" in h)
print("blurb_gap_fixed", "margin-top: 0;" in css and ".cal-card__blurb" in css)
print("rewards_css", ".cal-rewards--card" in css)
print("tv_rewards", "data-tvxbox-rewards" in open("domains/pastorxbox.com.br/public_html/includes/tvxbox.php",encoding="utf-8",errors="replace").read())
PY
'''
run(c, smoke, timeout=60)
c.close()

# --- Manager Oracle ---
m = oracle()
sftp = m.open_sftp()
for rel in MANAGER_FILES:
    print("put manager", rel)
    sftp.put(str(MANAGER_ROOT / rel), f"{MANAGER_REMOTE}/{rel}")
sftp.close()
run(
    m,
    "grep -n 'on_pc\\|price_brl\\|PC Game Pass' /opt/xbox-manager/src/components/calendario/GamePassPanel.tsx | head -40",
)
code, _ = run(
    m,
    "cd /opt/xbox-manager && docker compose build app && docker compose up -d app && sleep 8 && curl -sS -o /dev/null -w 'health:%{http_code}\\n' http://127.0.0.1:3000/api/health",
    timeout=1200,
)
m.close()
raise SystemExit(code)
