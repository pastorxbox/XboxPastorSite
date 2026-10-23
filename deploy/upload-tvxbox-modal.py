#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
files = [
    ("public/includes/tvxbox.php", f"{REMOTE}/includes/tvxbox.php"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
]
probe = r'''<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", "1");
$config = require dirname(__DIR__) . "/config/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/i18n.php";
require_once __DIR__ . "/includes/calendar.php";
require_once __DIR__ . "/includes/tvxbox.php";
$pdo = db_connect($config);
$items = tvxbox_list_for_player($pdo);
echo "COUNT=" . count($items) . "\n";
foreach ($items as $it) {
    if (($it["youtubeId"] ?? "") === "WhQm-ExRz60" || (is_array($it["game"] ?? null) && str_contains((string)($it["game"]["title"] ?? ""), "CONTROL"))) {
        echo json_encode($it, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
$withGame = 0;
$without = 0;
foreach ($items as $it) {
    if (!empty($it["game"]["id"])) { $withGame++; } else { $without++; }
}
echo "withGame=$withGame withoutGame=$without\n";
echo "php_ok\n";
'''
probe_path = ROOT / "deploy" / "_probe_tvxbox_modal.php"
probe_path.write_text(probe, encoding="utf-8")

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
sftp = c.open_sftp()
for local, remote in files:
    print("put", local)
    sftp.put(str(ROOT / local), remote)
sftp.put(str(probe_path), f"{REMOTE}/_probe_tvxbox_modal.php")
sftp.close()
_, o, e = c.exec_command(
    f"cd {REMOTE} && php -l includes/tvxbox.php && php _probe_tvxbox_modal.php; "
    f"curl -sL -A Mozilla 'https://pastorxbox.com.br/?lang=pt' | grep -c 20260918tvmodal1; "
    f"rm -f _probe_tvxbox_modal.php",
    timeout=90,
)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR", err[:2500])
c.close()
print("DONE")
