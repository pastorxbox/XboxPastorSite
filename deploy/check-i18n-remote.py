#!/usr/bin/env python3
"""Inspect Hostinger i18n deployment state."""
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

cmd = r"""
grep -n '20260909i18n1\|lang-switcher\|require __DIR__ .*/i18n.php' \
  domains/pastorxbox.com.br/public_html/includes/vitrine-layout.php \
  domains/pastorxbox.com.br/public_html/index.php || true
echo '---'
grep -n 'post_translations\|site_settings' \
  domains/pastorxbox.com.br/public_html/includes/posts.php \
  domains/pastorxbox.com.br/public_html/includes/i18n.php || true
"""
_, o, e = c.exec_command(cmd, timeout=60)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR:", err[:800])
c.close()
