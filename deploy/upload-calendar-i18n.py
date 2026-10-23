#!/usr/bin/env python3
"""Upload calendar UI i18n to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/helpers.php", f"{REMOTE}/includes/helpers.php"),
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
    ("public/index.php", f"{REMOTE}/index.php"),
]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "147.93.39.20",
    port=65002,
    username="u285071835",
    password=PASSWORD,
    allow_agent=False,
    look_for_keys=False,
    timeout=45,
)
sftp = c.open_sftp()
for local, remote in files:
    path = ROOT / local
    print(f"put {path.name} -> {remote}")
    sftp.put(str(path), remote)
sftp.close()

checks = [
    (
        "EN",
        "curl -sL -A Mozilla -b 'px_lang=en' 'https://pastorxbox.com.br/calendario?lang=en' "
        "| grep -oE 'Xbox Calendar|Xbox Releases|Subscriptions|Devices|Cloud|Clear filters|Arrives |Xbox Store|more…|Historical Events|All|On Xbox|January|September' "
        "| sort | uniq -c",
    ),
    (
        "ES",
        "curl -sL -A Mozilla -b 'px_lang=es' 'https://pastorxbox.com.br/calendario?lang=es' "
        "| grep -oE 'Calendario Xbox|Lanzamientos Xbox|Suscripciones|Dispositivos|Nube|Limpiar filtros|Llega el|Tienda de Xbox|más…|Eventos Históricos|Todos|En Xbox|Septiembre' "
        "| sort | uniq -c",
    ),
    (
        "PT",
        "curl -sL -A Mozilla -b 'px_lang=pt' 'https://pastorxbox.com.br/calendario?lang=pt' "
        "| grep -oE 'Calendário Xbox|Lançamentos Xbox|Assinaturas|Nuvem|Chega dia|Loja do Xbox|mais…|Setembro' "
        "| sort | uniq -c",
    ),
]

for label, cmd in checks:
    print(f"\nSMOKE {label}:")
    _, o, e = c.exec_command(cmd, timeout=60)
    print(o.read().decode(errors="replace"))
    err = e.read().decode(errors="replace")
    if err.strip():
        print("ERR:", err[:400])

c.close()
print("DONE")
