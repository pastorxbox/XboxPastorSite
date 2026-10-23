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
SELECT id, title, status, show_on_tv, on_gamepass, release_at, LEFT(trailer_url,70) trailer
FROM gp_releases
WHERE title LIKE '%TCG%' OR title LIKE '%Card Shop%' OR release_at >= DATE_SUB(CURDATE(), INTERVAL 3 DAY);
SELECT v.id, v.youtube_id, v.show_on_tv, v.expires_at, v.status, v.gp_release_id, r.title, r.release_at
FROM tv_videos v
LEFT JOIN gp_releases r ON r.id=v.gp_release_id
WHERE r.title LIKE '%TCG%' OR v.youtube_id='3OG4XeEcYjg' OR r.release_at >= DATE_SUB(CURDATE(), INTERVAL 3 DAY);
SELECT CURDATE() AS today;
"
"""
_, o, e = c.exec_command(sql, timeout=60)
print(o.read().decode(errors="replace"))

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' | tr '\\n' ' ' "
    "| grep -oE 'data-videos=\"[^\"]+\"' | head -1"
)
raw = o.read().decode(errors="replace")
m = re.search(r'data-videos="([^"]*)"', raw)
if m:
    html = m.group(1).replace("&quot;", '"').replace("&amp;", "&")
    videos = json.loads(html)
    print(f"player count={len(videos)}")
    for v in videos:
        y = v.get("youtubeId", "")
        if y == "3OG4XeEcYjg" or "15/Setembro" in str(v.get("comment", "")) or "HOJE" in str(v.get("comment", "")):
            print(" HIT", v)
    print("hasTCG", any(v.get("youtubeId") == "3OG4XeEcYjg" for v in videos))

c.close()
