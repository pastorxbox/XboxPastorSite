#!/usr/bin/env python3
"""Probe why a calendar game may be missing from TV Xbox player."""
import os
import re
import paramiko

PASSWORD = os.environ["SSHPASS"]
TITLE = "TCG Card Shop"

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
DBN="${DB_NAME:-$DB_DATABASE}"
DBU="${DB_USER:-$DB_USERNAME}"
DBP="${DB_PASSWORD:-$DB_PASS}"
DBH="${DB_HOST:-localhost}"
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "
SELECT id, title, status, show_on_tv, on_gamepass, release_at, trailer_url
FROM gp_releases
WHERE title LIKE '%TCG Card Shop%' OR title LIKE '%Card Shop Simulator%';
SELECT v.id, v.youtube_id, v.show_on_tv, v.expires_at, v.status, v.gp_release_id, v.youtube_url, r.title
FROM tv_videos v
LEFT JOIN gp_releases r ON r.id = v.gp_release_id
WHERE r.title LIKE '%TCG Card Shop%' OR r.title LIKE '%Card Shop%'
   OR v.youtube_url LIKE '%3OG4XeEcYjg%' OR v.youtube_id = '3OG4XeEcYjg';
SELECT youtube_id, COUNT(*) c FROM tv_videos GROUP BY youtube_id HAVING c > 1;
SELECT COUNT(*) AS tv_on FROM tv_videos WHERE show_on_tv=1 AND status='published' AND (expires_at IS NULL OR expires_at >= CURDATE());
SELECT COUNT(*) AS gp_on FROM gp_releases WHERE show_on_tv=1 AND status='published' AND trailer_url IS NOT NULL AND trailer_url <> '';
"
"""
_, o, e = c.exec_command(sql, timeout=60)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    # hide password noise
    print("ERR:", re.sub(r"-p\S+", "-p***", err)[:800])

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' "
    "| grep -oE '3OG4XeEcYjg|TCG Card Shop|Disponível HOJE' | sort | uniq -c"
)
print("HOME HITS:")
print(o.read().decode(errors="replace"))

_, o, _ = c.exec_command(
    "php -r "
    "'require \"domains/pastorxbox.com.br/config/config.php\"; "
    "\$c=require \"domains/pastorxbox.com.br/config/config.php\";' "
    "2>/dev/null; "
    "cd domains/pastorxbox.com.br/public_html && php -r '"
    "\\$config=require \"../config/config.php\";"
    "require \"includes/db.php\";"
    "require \"includes/helpers.php\";"
    "require \"includes/i18n.php\";"
    "require \"includes/calendar.php\";"
    "require \"includes/tvxbox.php\";"
    "\\$pdo=db_connect(\\$config);"
    "\\$list=tvxbox_list_for_player(\\$pdo);"
    "echo count(\\$list).\" videos\\n\";"
    "foreach(\\$list as \\$v){"
    "  echo (\\$v[\"id\"]??\"\").\"|\".(\\$v[\"youtubeId\"]??\"\").\"|\".(\\$v[\"comment\"]??\"\").\"|\".((\\$v[\"isToday\"]??false)?\"TODAY\":\"\").\"\\n\";"
    "}"
    "'"
)
print("PLAYER LIST:")
print(o.read().decode(errors="replace")[:4000])
c.close()
