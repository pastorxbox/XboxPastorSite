#!/usr/bin/env python3
"""Check AVIF/WebP support and live image formats on Hostinger."""
import os
import re
import urllib.request

import paramiko

HOST = "147.93.39.20"
PORT = 65002
USER = "u285071835"
PASSWORD = os.environ["SSHPASS"]
REMOTE_BASE = "domains/pastorxbox.com.br/public_html"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(
    HOST,
    port=PORT,
    username=USER,
    password=PASSWORD,
    allow_agent=False,
    look_for_keys=False,
    timeout=45,
)

php = r"""php -r 'echo "imageavif=".(function_exists("imageavif")?"yes":"no")."\n"; echo "imagecreatefromavif=".(function_exists("imagecreatefromavif")?"yes":"no")."\n"; echo "imagewebp=".(function_exists("imagewebp")?"yes":"no")."\n"; echo "gd=".phpversion("gd")."\n";'"""
_, o, e = client.exec_command(php, timeout=30)
print("PHP GD:")
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"), end="")

ext_cmd = (
    f"find {REMOTE_BASE}/uploads -type f "
    r"\( -iname '*.avif' -o -iname '*.webp' -o -iname '*.jpg' -o -iname '*.jpeg' "
    r"-o -iname '*.png' -o -iname '*.gif' \) 2>/dev/null "
    r"| sed 's/.*\.//' | tr 'A-Z' 'a-z' | sort | uniq -c | sort -nr"
)
_, o, e = client.exec_command(ext_cmd, timeout=60)
print("Upload extensions:")
print(o.read().decode(errors="replace"))

_, o, e = client.exec_command(
    f"find {REMOTE_BASE}/uploads -type f -iname '*.avif' 2>/dev/null | head -5",
    timeout=30,
)
print("Sample AVIF files:")
print(o.read().decode(errors="replace") or "(none)\n")

client.close()

# Probe via Hostinger curl (local urllib gets 403)
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(
    HOST,
    port=PORT,
    username=USER,
    password=PASSWORD,
    allow_agent=False,
    look_for_keys=False,
    timeout=45,
)
_, o, _ = client.exec_command(
    "curl -sL -A 'Mozilla/5.0' 'https://pastorxbox.com.br/' "
    "| grep -oE 'src=\"[^\"]+\"' | sed 's/^src=\"//;s/\"$//' | head -25",
    timeout=45,
)
srcs = [line.strip() for line in o.read().decode(errors="replace").splitlines() if line.strip()]
print(f"Homepage img srcs ({len(srcs)}):")
avif_refs = webp_refs = other = 0
for src in srcs:
    low = src.lower()
    if ".avif" in low:
        avif_refs += 1
        kind = "avif"
    elif ".webp" in low:
        webp_refs += 1
        kind = "webp"
    else:
        other += 1
        kind = "other"
    print(f"  [{kind}] {src[:120]}")

_, o, _ = client.exec_command(
    "curl -sI -A 'Mozilla/5.0' "
    "'https://pastorxbox.com.br/uploads/2026/06/amazon-luna-free-pc-games-banner-june-2026-1200x600.avif' "
    "| tr -d '\\r' | head -12",
    timeout=30,
)
print("Sample AVIF response headers:")
print(o.read().decode(errors="replace"))
client.close()
print(f"summary homepage: avif={avif_refs} webp={webp_refs} other={other}")
