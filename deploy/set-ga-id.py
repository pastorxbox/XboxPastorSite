#!/usr/bin/env python3
"""Set GA_MEASUREMENT_ID on Hostinger and verify data-ga-id on pages."""
import os
import re

import paramiko

PASSWORD = os.environ["SSHPASS"]
GA_ID = os.environ.get("GA_MEASUREMENT_ID", "G-KWYLNG7W65")
ENV_PATH = "domains/pastorxbox.com.br/config/.env"

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

_, o, e = c.exec_command(f"cat {ENV_PATH}", timeout=30)
env_text = o.read().decode(errors="replace")
err = e.read().decode(errors="replace")
if err.strip() and "No such file" in err:
    raise SystemExit(f"Missing .env: {err}")

if re.search(r"^GA_MEASUREMENT_ID=", env_text, re.M):
    new_env = re.sub(
        r"^GA_MEASUREMENT_ID=.*$",
        f"GA_MEASUREMENT_ID={GA_ID}",
        env_text,
        count=1,
        flags=re.M,
    )
else:
    if not env_text.endswith("\n"):
        env_text += "\n"
    new_env = env_text + f"GA_MEASUREMENT_ID={GA_ID}\n"

sftp = c.open_sftp()
with sftp.file(ENV_PATH, "w") as f:
    f.write(new_env)
sftp.close()
print(f"Updated {ENV_PATH} -> GA_MEASUREMENT_ID={GA_ID}")

# Confirm key present without dumping secrets
_, o, _ = c.exec_command(
    f"grep -E '^GA_MEASUREMENT_ID=' {ENV_PATH}; "
    "php -r '$c=require \"domains/pastorxbox.com.br/config/config.php\"; "
    "echo \"config:\".($c[\"ga_measurement_id\"]??\"\").PHP_EOL;'",
    timeout=30,
)
print(o.read().decode(errors="replace"))

pages = ["/", "/noticias", "/artigos", "/calendario"]
for path in pages:
    _, o, _ = c.exec_command(
        f"curl -sL -A Mozilla 'https://pastorxbox.com.br{path}' "
        f"| grep -oE 'data-ga-id=\"[^\"]*\"' | head -1",
        timeout=45,
    )
    print(f"{path}: {o.read().decode(errors='replace').strip()}")

c.close()
print("DONE")
