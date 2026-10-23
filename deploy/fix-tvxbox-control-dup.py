#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

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
sftp.put(str(ROOT / "public/includes/tvxbox.php"), f"{REMOTE}/includes/tvxbox.php")
sftp.put(str(ROOT / "deploy/_probe_tvxbox_meta.php"), f"{REMOTE}/_probe_tvxbox_meta.php")
sftp.close()

sql = r"""
UPDATE tv_videos v
JOIN gp_releases r ON r.id = v.gp_release_id
SET v.youtube_url = r.trailer_url,
    v.youtube_id = 'WhQm-ExRz60'
WHERE v.id = 35 AND r.id = 17;

DELETE FROM tv_videos WHERE id = 6 AND youtube_id = 'WhQm-ExRz60' AND gp_release_id IS NULL;
"""
# write sql remotely and run via php config credentials
php = r'''<?php
$config = require dirname(__DIR__) . "/config/config.php";
$pdo = new PDO(
  sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4", $config["db"]["host"], $config["db"]["name"]),
  $config["db"]["user"],
  $config["db"]["pass"],
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("UPDATE tv_videos v JOIN gp_releases r ON r.id = v.gp_release_id SET v.youtube_url = r.trailer_url, v.youtube_id = 'WhQm-ExRz60' WHERE v.id = 35 AND r.id = 17");
echo "updated35=" . $pdo->query("SELECT youtube_id FROM tv_videos WHERE id=35")->fetchColumn() . "\n";
$del = $pdo->exec("DELETE FROM tv_videos WHERE id = 6 AND youtube_id = 'WhQm-ExRz60' AND gp_release_id IS NULL");
echo "deleted6=$del\n";
'''
fix_path = ROOT / "deploy" / "_fix_control_dup.php"
fix_path.write_text(php, encoding="utf-8")
sftp = c.open_sftp()
sftp.put(str(fix_path), f"{REMOTE}/_fix_control_dup.php")
sftp.close()

_, o, e = c.exec_command(
    f"cd {REMOTE} && php _fix_control_dup.php && php _probe_tvxbox_meta.php | head -40; "
    f"rm -f _fix_control_dup.php _probe_tvxbox_meta.php",
    timeout=90,
)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR", err[:2000])
c.close()
print("DONE")
