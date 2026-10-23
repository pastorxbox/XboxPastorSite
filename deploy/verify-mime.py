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
cmds = [
    "curl -sI -A Mozilla -H 'Cache-Control: no-cache' "
    "'https://pastorxbox.com.br/uploads/2026/06/amazon-luna-free-pc-games-banner-june-2026-1200x600.avif?v=2' "
    "| tr -d '\\r' | grep -iE 'HTTP/|content-type|cf-cache|age:'",
    "curl -sI -A Mozilla "
    "'https://pastorxbox.com.br/uploads/2026/08/insomniac-dev-wolverine-backlash.webp' "
    "| tr -d '\\r' | grep -iE 'HTTP/|content-type'",
    "cat domains/pastorxbox.com.br/public_html/uploads/.htaccess",
    "httpd -V 2>/dev/null | head -3; which httpd; ls /etc/httpd 2>/dev/null | head; "
    "grep -R \"avif\" ~/domains/pastorxbox.com.br/public_html/.htaccess "
    "~/domains/pastorxbox.com.br/public_html/uploads/.htaccess 2>/dev/null",
]
for cmd in cmds:
    print("===", cmd[:90])
    _, o, e = c.exec_command(cmd, timeout=45)
    print(o.read().decode(errors="replace"))
    err = e.read().decode(errors="replace")
    if err.strip():
        print("ERR", err[:200])
c.close()
