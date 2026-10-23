#!/usr/bin/env python3
import os
import paramiko

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

cmds = [
    # parse test
    r"""cd domains/pastorxbox.com.br/public_html && php -r '
require "includes/tvxbox.php";
\$u="https://youtu.be/3OG4XeEcYjg?is=FDt-2puSI2WRml5u";
var_export(tvxbox_parse_youtube_id(\$u));
echo "\n";
\$u2="https://youtu.be/Iz9dEXYRgO8?is=1sKxpDVy8tHqZI4e";
var_export(tvxbox_parse_youtube_id(\$u2));
echo "\n";
'""",
    # does remote have gp_releases loop?
    r"""grep -n "Source of truth for calendar\|show_on_tv = 1\|expires_at" domains/pastorxbox.com.br/public_html/includes/tvxbox.php | head -40""",
    # list player via php with error display
    r"""cd domains/pastorxbox.com.br/public_html && php -d display_errors=1 -r '
\$config=require "../config/config.php";
require "includes/db.php";
require "includes/helpers.php";
require "includes/i18n.php";
require "includes/calendar.php";
require "includes/tvxbox.php";
\$pdo=db_connect(\$config);
try {
  \$list=tvxbox_list_for_player(\$pdo);
  echo "count=".count(\$list)."\n";
  foreach(\$list as \$v){
    if((\$v["youtubeId"]??"")==="3OG4XeEcYjg" || (\$v["youtubeId"]??"")==="Iz9dEXYRgO8"){
      echo "FOUND ".json_encode(\$v, JSON_UNESCAPED_UNICODE)."\n";
    }
  }
  \$ids=array_column(\$list,"youtubeId");
  echo "hasTCG=".((in_array("3OG4XeEcYjg",\$ids,true))?"yes":"no")."\n";
  echo "hasHela=".((in_array("Iz9dEXYRgO8",\$ids,true))?"yes":"no")."\n";
} catch (Throwable \$e) {
  echo "ERR ".$e->getMessage()."\n".$e->getTraceAsString()."\n";
}
'""",
]
for cmd in cmds:
    print(">>>", cmd[:120].replace("\n", " "))
    _, o, e = c.exec_command(cmd, timeout=60)
    print(o.read().decode(errors="replace"))
    err = e.read().decode(errors="replace")
    if err.strip():
        print("STDERR:", err[:1000])
    print("---")

c.close()
