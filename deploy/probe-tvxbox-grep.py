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
    "grep -n 'release_at\\|CURDATE\\|DATE_SUB\\|expires\\|show_on_tv' "
    "domains/pastorxbox.com.br/public_html/includes/tvxbox.php | head -60"
)
print(o.read().decode())
c.close()
