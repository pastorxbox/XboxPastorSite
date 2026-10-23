#!/usr/bin/env python3
"""Fix Eventos jump icon CSS + TV Xbox post-launch retention."""
import json
import os
import re
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/tvxbox.php", f"{REMOTE}/includes/tvxbox.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
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
    print("put", local)
    sftp.put(str(ROOT / local), remote)
sftp.close()

# Backfill linked TV rows for calendar games with TV on (creates expires = release+14)
sync = r"""
set -a; . domains/pastorxbox.com.br/config/.env; set +a
cd domains/pastorxbox.com.br/public_html
php -r '
$config = require "../config/config.php";
require_once "includes/db.php";
require_once "includes/helpers.php";
require_once "includes/i18n.php";
require_once "includes/calendar.php";
require_once "includes/tvxbox.php";
$pdo = db_connect($config);
$rows = $pdo->query("SELECT id, trailer_url, show_on_tv FROM gp_releases WHERE show_on_tv=1 AND trailer_url IS NOT NULL AND trailer_url<>\"\"")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
  tvxbox_sync_release_trailer($pdo, (int)$row["id"], (string)$row["trailer_url"], true);
  echo "synced #".$row["id"]."\n";
}
$list = tvxbox_list_for_player($pdo);
echo "player=".count($list)."\n";
foreach ($list as $v) {
  if (($v["youtubeId"] ?? "") === "3OG4XeEcYjg") {
    echo "TCG ".json_encode($v, JSON_UNESCAPED_UNICODE)."\n";
  }
}
'
"""
_, o, e = c.exec_command(sync, timeout=90)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:800])

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' | tr '\\n' ' ' "
    "| grep -oE 'data-videos=\"[^\"]+\"' | head -1"
)
raw = o.read().decode(errors="replace")
m = re.search(r'data-videos="([^"]*)"', raw)
if m:
    html = m.group(1).replace("&quot;", '"').replace("&amp;", "&")
    videos = json.loads(html)
    tcg = [v for v in videos if v.get("youtubeId") == "3OG4XeEcYjg"]
    print(f"homepage player={len(videos)} tcg={tcg}")

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' "
    "| grep -oE 'calendar-section-jumps__btn .lucide|widget-head .calendar-section-jumps__btn|20260916tvfix1' "
    "| sort | uniq -c; "
    "grep -n 'widget-head .calendar-section-jumps__btn' domains/pastorxbox.com.br/public_html/assets/css/vitrine.css"
)
print(o.read().decode(errors="replace"))
c.close()
print("DONE")
