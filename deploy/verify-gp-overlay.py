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
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' "
    "| grep -oE 'No Xbox|Fora do serviço|20260904gpoverlay|cal-card__gp-badge' "
    "| sort | uniq -c"
)
print(o.read().decode(errors="replace"))
_, o, _ = c.exec_command(
    "grep -n 'No Xbox\\|cal-card__gp-badge\\|position: absolute' "
    "domains/pastorxbox.com.br/public_html/includes/calendar.php "
    "domains/pastorxbox.com.br/public_html/assets/css/vitrine.css "
    "| head -20"
)
print(o.read().decode(errors="replace"))
c.close()
