#!/usr/bin/env python3
"""TV Xbox: leave air the day after launch; resync expiries."""
import json
import os
import re
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
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
}
$list = tvxbox_list_for_player($pdo);
echo "player=".count($list)."\n";
$ids = array_column($list, "youtubeId");
echo "hasTCG=".(in_array("3OG4XeEcYjg", $ids, true) ? "yes" : "no")."\n";
echo "hasAniimo=".(in_array("LMHKfDQfDi4", $ids, true) ? "yes" : "no")."\n";
foreach ($list as $v) {
  if (in_array($v["youtubeId"] ?? "", ["3OG4XeEcYjg","LMHKfDQfDi4"], true)) {
    echo json_encode($v, JSON_UNESCAPED_UNICODE)."\n";
  }
}
$rows = $pdo->query("SELECT v.id, v.youtube_id, v.expires_at, r.title, DATE(r.release_at) rel FROM tv_videos v JOIN gp_releases r ON r.id=v.gp_release_id WHERE r.show_on_tv=1 ORDER BY r.release_at")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
  echo "exp ".$r["id"]." ".$r["expires_at"]." rel=".$r["rel"]." ".$r["title"]."\n";
}
'
"""
_, o, e = c.exec_command(sync, timeout=90)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:600])

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' | tr '\\n' ' ' "
    "| grep -oE 'data-videos=\"[^\"]+\"' | head -1"
)
raw = o.read().decode(errors="replace")
m = re.search(r'data-videos="([^"]*)"', raw)
if m:
    html = m.group(1).replace("&quot;", '"').replace("&amp;", "&")
    videos = json.loads(html)
    ids = [v.get("youtubeId") for v in videos]
    print(f"homepage={len(videos)} TCG={'3OG4XeEcYjg' in ids} Aniimo={'LMHKfDQfDi4' in ids}")

c.close()
print("DONE")
