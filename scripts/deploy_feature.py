import os
import sys
import time
import subprocess
import paramiko
import urllib.request
import json

# Configuration
HOSTNAME = "51.38.99.226"
USERNAME = "jimmy"
PASSWORD = "<purge-crm-20260713>ù891234kfp"
REMOTE_BASE = "/home/jimmy/crm-ultimate"
LOCAL_BASE = r"C:\Users\jimmy\Projet\crm_ultimate"

# Force output to be unbuffered
sys.stdout.reconfigure(line_buffering=True)
sys.stderr.reconfigure(line_buffering=True)

# 1. Récupérer les fichiers modifiés dans le dernier commit git
try:
    files = subprocess.check_output(["git", "diff-tree", "--no-commit-id", "--name-only", "-r", "HEAD"]).decode('utf-8').splitlines()
except Exception as e:
    print(f"Error getting git files: {e}", flush=True)
    sys.exit(1)

if not files:
    print("No files to deploy.", flush=True)
    sys.exit(0)

# Helper pour créer les répertoires distants
def ensure_remote_dir(sftp, remote_dir):
    parts = remote_dir.replace("\\", "/").split("/")
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

print("=" * 60, flush=True)
print("Déploiement des modifications de la branche Feature (avec timeouts)", flush=True)
print("=" * 60, flush=True)

# ── 1. Upload via SFTP ───────────────────────────────────────────────────────
print("\n[1/3] Connexion SFTP & Upload des fichiers modifiés...\n", flush=True)

try:
    transport = paramiko.Transport((HOSTNAME, 22))
    transport.banner_timeout = 30
    print("Connexion au transport SSH...", flush=True)
    transport.connect(username=USERNAME, password=PASSWORD)
    print("Connexion SFTP...", flush=True)
    sftp = paramiko.SFTPClient.from_transport(transport)

    uploaded = 0
    for f in files:
        if not f:
            continue
        
        # Ignorer la doc et les métadonnées de l'agent
        if f.startswith('crm-ultimate-handoff/') or f.startswith('.claude') or f.startswith('.gemini') or f.endswith('.md') or f.startswith('.git'):
            print(f"  SKIP (meta/doc): {f}", flush=True)
            continue
            
        local = os.path.join(LOCAL_BASE, f.replace("/", os.sep))
        f_normalized = f.replace('\\', '/')
        remote = f"{REMOTE_BASE}/{f_normalized}"
        remote_dir = "/".join(remote.split("/")[:-1])

        if not os.path.exists(local):
            print(f"  SKIP (introuvable): {f}", flush=True)
            continue

        if os.path.isdir(local):
            continue

        ensure_remote_dir(sftp, remote_dir)
        print(f"Uploading: {f} -> {remote}", flush=True)
        sftp.put(local, remote)
        uploaded += 1
        print(f"  OK  {f}", flush=True)

    sftp.close()
    transport.close()
    print(f"\n  => {uploaded} fichiers uploadés", flush=True)

except Exception as e:
    print(f"Erreur SFTP : {e}", flush=True)
    sys.exit(1)

# ── 2. Rebuild Docker ────────────────────────────────────────────────────────
print("\n[2/3] Connexion SSH & Rebuild Docker + migrations...\n", flush=True)

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    print("Connexion SSH au VPS...", flush=True)
    ssh.connect(HOSTNAME, username=USERNAME, password=PASSWORD, timeout=30, banner_timeout=30)
    print("Connecté au VPS via SSH. Exécution des commandes...", flush=True)

    COMMANDS = [
        ("Build images",
         "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml build app queue"),
        ("Restart services",
         "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml up -d app queue"),
        ("Migrations",
         "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force"),
        ("Config cache",
         "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache"),
        ("View cache",
         "cd /home/jimmy/crm-ultimate && docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache"),
    ]

    for label, cmd in COMMANDS:
        print(f"--- {label} ---", flush=True)
        stdin, stdout, stderr = ssh.exec_command(cmd, timeout=300)
        exit_status = stdout.channel.recv_exit_status()
        out = stdout.read().decode("utf-8", "replace")
        err = stderr.read().decode("utf-8", "replace")
        lines = out.strip().splitlines()
        if len(lines) > 30:
            print(f"  ... ({len(lines)-30} lignes masquées) ...", flush=True)
            for l in lines[-30:]:
                print(f"  {l}", flush=True)
        elif lines:
            for l in lines:
                print(f"  {l}", flush=True)
        if err.strip():
            for l in err.strip().splitlines()[:10]:
                print(f"  ERR: {l}", flush=True)
        if exit_status != 0:
            print(f"  ⚠️  Exit code: {exit_status}", flush=True)
        print("", flush=True)
        time.sleep(2)

    ssh.close()

except Exception as e:
    print(f"Erreur SSH : {e}", flush=True)
    sys.exit(1)

# ── 3. Smoke tests HTTP ──────────────────────────────────────────────────────
print("[3/3] Smoke tests HTTP...\n", flush=True)

BASE = "https://crm.nana-intelligence.fr"
results = []

def test(name, url, method="GET", data=None, expected_status=None):
    try:
        body = json.dumps(data).encode() if data else None
        headers = {"Content-Type": "application/json"}
        req = urllib.request.Request(url, data=body, headers=headers, method=method)
        resp = urllib.request.urlopen(req, timeout=15)
        ok = expected_status is None or resp.status == expected_status
        icon = "✅" if ok else "❌"
        results.append((icon, name, resp.status, url))
        print(f"  {icon} [{resp.status}] {name}", flush=True)
    except urllib.error.HTTPError as e:
        ok = expected_status is None or e.code == expected_status
        icon = "✅" if ok else "❌"
        results.append((icon, name, e.code, url))
        print(f"  {icon} [{e.code}] {name}", flush=True)
    except Exception as e:
        results.append(("❌", name, "ERR", url))
        print(f"  ❌ [ERR] {name}: {str(e)[:80]}", flush=True)

# Health checks
test("Home page (200)", f"{BASE}/")
test("Login page (200)", f"{BASE}/login")
test("Contacts page (Redirect to login/401/200)", f"{BASE}/contacts")
test("Activities page (Redirect to login/401/200)", f"{BASE}/activities")

print("", flush=True)
print("=== Déploiement terminé ===", flush=True)
