#!/usr/bin/env python3
"""Execute the public site entrypoint via remote PHP CLI for debugging."""
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

php = r"""cd domains/pastorxbox.com.br/public_html && php -d display_errors=1 -r '
$_GET = ["secao" => "noticias", "lang" => "en"];
$_SERVER["REQUEST_URI"] = "/noticias?lang=en";
$_SERVER["HTTP_HOST"] = "pastorxbox.com.br";
$_SERVER["HTTPS"] = "on";
include "index.php";
'"""
_, o, e = c.exec_command(php, timeout=120)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR:", err[:1200])
c.close()
