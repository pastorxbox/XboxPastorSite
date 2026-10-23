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
_, o, _ = c.exec_command(
    "grep -n 'cal-month-legend\\|legend_game\\|Jogos\\|Fato' "
    "domains/pastorxbox.com.br/public_html/includes/calendar.php | head -30",
    timeout=30,
)
print("CAL PHP:")
print(o.read().decode(errors="replace"))
_, o, _ = c.exec_command(
    "grep -n 'legend_game\\|legend_history\\|Fato Hist' "
    "domains/pastorxbox.com.br/public_html/includes/i18n.php | head -20",
    timeout=30,
)
print("I18N:")
print(o.read().decode(errors="replace"))
script = r'''
import urllib.request
req = urllib.request.Request("https://pastorxbox.com.br/calendario?lang=pt", headers={"User-Agent":"Mozilla"})
h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8", "replace")
i = h.find("cal-month-legend")
print("SNIP:")
print(h[i:i+600] if i >= 0 else "NOT FOUND")
'''
sftp = c.open_sftp()
with sftp.file("/tmp/sm_leg.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/sm_leg.py", timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
