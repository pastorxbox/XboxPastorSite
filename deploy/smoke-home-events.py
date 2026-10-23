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
SELECT @@session.time_zone tz_s, @@global.time_zone tz_g, NOW() nowx, CURDATE() cd;
SELECT id, title, starts_at, ends_at FROM calendar_events
 WHERE status='published'
   AND starts_at >= DATE_SUB(CURDATE(), INTERVAL 10 DAY)
   AND starts_at < DATE_ADD(CURDATE(), INTERVAL 3 DAY)
 ORDER BY starts_at;
"
php -r 'date_default_timezone_set("America/Sao_Paulo"); echo "php=".date("Y-m-d H:i:s T")."\n";'
"""
_, o, e = c.exec_command(probe, timeout=60)
print("DB/PHP:")
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR:", err)

# Download homepage and analyze locally on remote with simpler python
script = r'''
import urllib.request, re
req = urllib.request.Request("https://pastorxbox.com.br/?lang=pt", headers={"User-Agent": "Mozilla"})
h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8", "replace")
print("asset", h.count("20260916evcar1"))
print("titles", re.findall(r"Hoje é Dia de[^<]{0,80}", h))
print("event_pills", len(re.findall(r"cat--events", h)))
print("below_blocks", len(re.findall(r"home-events-below", h)))
print("mais_links", len(re.findall(r"calendario#eventos", h)))
print("carousel_slides", len(re.findall(r"news-carousel__slide", h)))
print("news_cards_in_carousel", len(re.findall(r'class="card featured news-carousel__slide"', h)))
print("event_cards_in_carousel", len(re.findall(r'class="card card--event featured news-carousel__slide', h)))
print("event_cards_below", len(re.findall(r'class="card card--event"', h)))
# show first event card snippet
m = re.search(r'card--event.{0,500}', h)
print("snippet", (m.group(0)[:400] if m else "none"))
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_home_events.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_home_events.py", timeout=60)
print("HOME:")
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR:", err)
c.close()
