#!/usr/bin/env python3
import json
import os
import re
import paramiko

PASSWORD = os.environ["SSHPASS"]
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

sql = r"""
set -a; . domains/pastorxbox.com.br/config/.env; set +a
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
SELECT id, youtube_id, show_on_tv, expires_at, status, gp_release_id, LEFT(youtube_url,80) AS url
FROM tv_videos
WHERE youtube_id='3OG4XeEcYjg' OR youtube_url LIKE '%3OG4XeEcYjg%';
SELECT id, title, show_on_tv, LEFT(trailer_url,90) AS traile
FROM gp_releases WHERE show_on_tv=1 ORDER BY id;
"
"""
_, o, e = c.exec_command(sql, timeout=60)
print("=== DB ===")
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if "ERROR" in err:
    print(err[:500])

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' | tr '\\n' ' ' "
    "| grep -oE 'data-videos=\"[^\"]+\"' | head -1"
)
raw = o.read().decode(errors="replace")
print("=== ATTR LEN ===", len(raw))
m = re.search(r'data-videos="([^"]*)"', raw)
if not m:
    print("NO data-videos")
else:
    html = m.group(1)
    # unescape common entities
    html = (
        html.replace("&quot;", '"')
        .replace("&#039;", "'")
        .replace("&amp;", "&")
        .replace("&lt;", "<")
        .replace("&gt;", ">")
    )
    try:
        videos = json.loads(html)
    except Exception as ex:
        print("JSON fail", ex, html[:200])
        videos = []
    print(f"videos={len(videos)}")
    for v in videos:
        yid = v.get("youtubeId", "")
        mark = " <<<" if yid == "3OG4XeEcYjg" or "Card" in str(v.get("comment", "")) else ""
        print(f"  {v.get('id')}|{yid}|today={v.get('isToday')}|gp={v.get('onGamePass')}|{v.get('comment','')[:60]}{mark}")
    ids = [v.get("youtubeId") for v in videos]
    print("has 3OG4XeEcYjg:", "3OG4XeEcYjg" in ids)

c.close()
