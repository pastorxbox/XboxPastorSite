#!/usr/bin/env python3
import os
import paramiko

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
sql = r"""
set -a; . domains/pastorxbox.com.br/config/.env; set +a
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
SELECT v.id, v.youtube_id, v.show_on_tv, v.expires_at, r.title, r.release_at, r.show_on_tv AS gp_tv
FROM tv_videos v
LEFT JOIN gp_releases r ON r.id=v.gp_release_id
ORDER BY v.id;
SELECT id, title, release_at, show_on_tv FROM gp_releases
WHERE show_on_tv=1 AND release_at < CURDATE() ORDER BY release_at;
"
"""
_, o, _ = c.exec_command(sql, timeout=60)
print(o.read().decode(errors="replace"))
c.close()
