import paramiko
import os
import sys

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')

HOSTNAME = "51.38.99.226"
USERNAME = "jimmy"
PASSWORD = "<purge-crm-20260713>ù891234kfp"
REMOTE_BASE = "/home/jimmy/crm-ultimate"
LOCAL_BASE   = r"C:\Users\jimmy\Projet\crm_ultimate"

FILES = [
    "database/migrations/2026_05_25_000001_add_blacklist_to_contacts.php",
    "app/Models/Contact.php",
    "app/Http/Controllers/Webhook/EmeliaIntentWebhookController.php",
    "app/Jobs/RemoveFromEmeliaCampaign.php",
    "app/Services/EmeliaService.php",
    "app/Console/Commands/EmeliaSyncCampaign.php",
    "app/Http/Controllers/Web/EmeliaController.php",
    "routes/api.php",
]

def ensure_remote_dir(sftp, remote_dir):
    parts = remote_dir.split("/")
    path = ""
    for part in parts:
        if not part:
            path = "/"
            continue
        path = path.rstrip("/") + "/" + part
        try:
            sftp.stat(path)
        except FileNotFoundError:
            sftp.mkdir(path)

print("=== Deploiement v3.5 — Blacklist + Intent Emelia ===\n")

transport = paramiko.Transport((HOSTNAME, 22))
transport.connect(username=USERNAME, password=PASSWORD)
sftp = paramiko.SFTPClient.from_transport(transport)

for rel_path in FILES:
    local  = os.path.join(LOCAL_BASE, rel_path.replace("/", os.sep))
    remote = f"{REMOTE_BASE}/{rel_path}"
    remote_dir = "/".join(remote.split("/")[:-1])

    if not os.path.exists(local):
        print(f"  SKIP (not found): {rel_path}")
        continue

    ensure_remote_dir(sftp, remote_dir)
    sftp.put(local, remote)
    print(f"  OK  {rel_path}")

sftp.close()
transport.close()
print("\nFichiers uploades. Lancement du rebuild Docker...\n")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOSTNAME, username=USERNAME, password=PASSWORD)

COMMANDS = [
    ("Build image app + queue",
     "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml build app queue 2>&1"),
    ("Up app + queue",
     "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml up -d app queue 2>&1"),
    ("Migrate",
     "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force 2>&1"),
    ("Route cache",
     "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache 2>&1"),
    ("Config clear",
     "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml exec -T app php artisan config:clear 2>&1"),
    ("Cache clear",
     "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml exec -T app php artisan cache:clear 2>&1"),
]

for label, cmd in COMMANDS:
    print(f"--- {label} ---")
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=300)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    if out.strip():
        # Only show last 20 lines to keep output readable
        lines = out.strip().splitlines()
        if len(lines) > 20:
            print(f"  ... ({len(lines)-20} lignes masquees) ...")
            print("\n".join("  " + l for l in lines[-20:]))
        else:
            print("\n".join("  " + l for l in lines))
    if err.strip():
        print("  STDERR:", err.strip()[:500])
    print()

ssh.close()
print("=== Deploiement v3.5 termine ===")
