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
probe = r"""
set -a; . domains/pastorxbox.com.br/config/.env; set +a
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
SELECT id, title, starts_at, DATE(starts_at) d,
       DATEDIFF(CURDATE(), DATE(starts_at)) age_days
  FROM calendar_events
 WHERE status='published'
   AND DATE(starts_at) <= CURDATE()
   AND DATE(starts_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
 ORDER BY starts_at;
"
# PHP web timezone
php -r 'echo "default=".date_default_timezone_get()." now=".date("Y-m-d H:i:s")."\n";'
grep -n "date_default_timezone\|America/Sao_Paulo\|timezone" domains/pastorxbox.com.br/public_html/includes/*.php domains/pastorxbox.com.br/config/*.php 2>/dev/null | head -40
"""
_, o, e = c.exec_command(probe, timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))

script = r'''
import urllib.request, re
req = urllib.request.Request("https://pastorxbox.com.br/?lang=pt", headers={"User-Agent": "Mozilla"})
h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8", "replace")
# extract carousel block
m = re.search(r'<div class="news-carousel".*?</div>\s*</div>\s*(?:<div class="ad-slot| <div class="home-events-below)', h, re.S)
block = m.group(0) if m else ""
print("carousel_len", len(block))
print("slides_in_block", block.count("news-carousel__slide"))
for i, art in enumerate(re.findall(r"<article class=\"([^\"]+)\"", block)):
    print(i, art)
# below
mb = re.search(r'<div class="home-events-below".*?</div>\s*(?:<article|</div>)', h, re.S)
print("BELOW:")
print((mb.group(0)[:600] if mb else "none"))
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_home_events2.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_home_events2.py", timeout=60)
print("PARSE:")
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
