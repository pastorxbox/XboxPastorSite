#!/usr/bin/env python3
"""Temporarily move BlizzCon to today to verify carousel day-0, then restore."""
import os
import re
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

def mysql(sql: str) -> str:
    cmd = f'''set -a; . domains/pastorxbox.com.br/config/.env; set +a
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -N -e "{sql}"
'''
    _, o, e = c.exec_command(cmd, timeout=60)
    out = o.read().decode(errors="replace")
    err = e.read().decode(errors="replace")
    if err.strip() and "Using a password" not in err:
        print("MYSQL ERR", err)
    return out

orig = mysql("SELECT starts_at FROM calendar_events WHERE id=4").strip()
print("orig", orig)
mysql("UPDATE calendar_events SET starts_at=CONCAT(CURDATE(), ' 12:00:00') WHERE id=4")
print("now", mysql("SELECT id, title, starts_at, DATE(starts_at) FROM calendar_events WHERE id=4").strip())

script = r'''
import urllib.request, re
req = urllib.request.Request("https://pastorxbox.com.br/?lang=pt", headers={"User-Agent":"Mozilla/5.0"})
h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8","replace")
arts = re.findall(r'<article class="([^"]+)"', re.search(r'<div class="news-carousel".*?<div class="news-carousel__dots"', h, re.S).group(0))
print("slides", len(arts))
for i,a in enumerate(arts):
    print(i, a)
print("event_in_carousel", any("card--event" in a for a in arts))
print("titles", re.findall(r"Hoje é Dia de[^<]+", h)[:3])
print("news_kept", sum(1 for a in arts if "card--event" not in a))
print("pill", "EVENTOS" in h)
print("mais", "calendario#eventos" in h)
print("below_after_move", "home-events-below" in h)
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_day0.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_day0.py", timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))

# restore
mysql(f"UPDATE calendar_events SET starts_at='{orig}' WHERE id=4")
print("restored", mysql("SELECT starts_at FROM calendar_events WHERE id=4").strip())
c.close()
print("DONE")
