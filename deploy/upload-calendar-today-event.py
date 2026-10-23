#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]
files = [
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect("147.93.39.20", port=65002, username="u285071835", password=PASSWORD,
          allow_agent=False, look_for_keys=False, timeout=45)
sftp = c.open_sftp()
for local, remote in files:
    print("put", local)
    sftp.put(str(ROOT / local), remote)
sftp.close()

probe = r'''
set -a; . domains/pastorxbox.com.br/config/.env; set +a
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -N -e "
SELECT id, title, starts_at, DATE(starts_at)=CURDATE() is_today
  FROM calendar_events WHERE status='published' AND DATE(starts_at)>=CURDATE() ORDER BY starts_at;
"
'''
_, o, e = c.exec_command(probe, timeout=60)
print("DB upcoming:")
print(o.read().decode(errors="replace"))

script = r'''
import urllib.request, re
for path in ["/calendario?lang=pt", "/?lang=pt"]:
    req = urllib.request.Request("https://pastorxbox.com.br"+path, headers={"User-Agent":"Mozilla"})
    h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8","replace")
    titles = re.findall(r"Hoje é Dia de[^<]+", h)
    no = "Nenhum evento futuro" in h or "no future events" in h.lower()
    print(path, "titles=", titles, "empty=", no, "tokyo=", "Tokyo" in h or "tokyo" in h.lower())
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_cal_today.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_cal_today.py", timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
