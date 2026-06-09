#!/usr/bin/env python3
"""
Déploiement v4.0 — Sécurité + cohérence
- Rate limiting login, webhook HMAC obligatoire, owner scope, retry, tests
"""
import paramiko
import os
import sys
import time

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')

HOSTNAME = "51.38.99.226"
USERNAME = "jimmy"
PASSWORD = "<purge-crm-20260713>ù891234kfp"
REMOTE_BASE = "/home/jimmy/crm-ultimate"
LOCAL_BASE  = r"C:\Users\jimmy\Projet\crm_ultimate"

# ── Fichiers modifiés dans ce déploiement ────────────────────────────────────
FILES = [
    # Sécurité critique
    "routes/api.php",
    "app/Http/Controllers/Api/Concerns/CrudActions.php",
    "app/Http/Controllers/Api/ExportController.php",
    "app/Http/Controllers/Api/ImportController.php",
    "app/Http/Controllers/Webhook/EmeliaWebhookController.php",
    "app/Http/Controllers/Webhook/EmeliaIntentWebhookController.php",
    # Services externes — retry/timeout
    "app/Services/EmeliaService.php",
    "app/Services/LlmService.php",
    "app/Jobs/SyncEmeliaCampaignJob.php",
    # Cohérence entités
    "app/Http/Controllers/Api/CompanyController.php",
    "app/Http/Controllers/Web/ContactController.php",
    "app/Http/Controllers/Web/CompanyController.php",
    "resources/views/pages/companies/show.blade.php",
    # Migration index
    "database/migrations/2026_05_24_100001_add_performance_indexes.php",
    # Env config
    ".env.example",
    ".env.production.example",
]

ESSENTIAL_DIRS = ["app", "routes", "config", "database", "tests", "resources"]

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

def sftp_put_dir(sftp, local_dir, remote_dir):
    """Upload récursif d'un dossier."""
    uploaded = 0
    for root, dirs, files in os.walk(local_dir):
        rel = os.path.relpath(root, local_dir)
        remote_root = remote_dir if rel == "." else f"{remote_dir}/{rel.replace(os.sep, '/')}"
        ensure_remote_dir(sftp, remote_root)
        for fname in files:
            local_file = os.path.join(root, fname)
            remote_file = f"{remote_root}/{fname}"
            sftp.put(local_file, remote_file)
            uploaded += 1
            print(f"  OK  {rel}/{fname}")
    return uploaded

print("=" * 60)
print("Déploiement v4.0 — Sécurité + Cohérence")
print("=" * 60)

# ── 1. Upload via SFTP ───────────────────────────────────────────────────────
print("\n[1/4] Upload des fichiers modifiés...\n")

transport = paramiko.Transport((HOSTNAME, 22))
transport.connect(username=USERNAME, password=PASSWORD)
sftp = paramiko.SFTPClient.from_transport(transport)

uploaded = 0
for rel_path in FILES:
    local  = os.path.join(LOCAL_BASE, rel_path.replace("/", os.sep))
    remote = f"{REMOTE_BASE}/{rel_path}"
    remote_dir = "/".join(remote.split("/")[:-1])

    if not os.path.exists(local):
        print(f"  SKIP (introuvable): {rel_path}")
        continue

    ensure_remote_dir(sftp, remote_dir)
    sftp.put(local, remote)
    uploaded += 1
    print(f"  OK  {rel_path}")

sftp.close()
transport.close()
print(f"\n  => {uploaded} fichiers uploadés")

# ── 2. Rebuild Docker ────────────────────────────────────────────────────────
print("\n[2/4] Rebuild Docker + migrations...\n")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOSTNAME, username=USERNAME, password=PASSWORD)

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
    print(f"--- {label} ---")
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=600)
    exit_status = stdout.channel.recv_exit_status()
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    lines = out.strip().splitlines()
    if len(lines) > 30:
        print(f"  ... ({len(lines)-30} lignes masquées) ...")
        for l in lines[-30:]:
            print(f"  {l}")
    elif lines:
        for l in lines:
            print(f"  {l}")
    if err.strip():
        for l in err.strip().splitlines()[:10]:
            print(f"  ERR: {l}")
    if exit_status != 0:
        print(f"  ⚠️  Exit code: {exit_status}")
    print()
    time.sleep(2)

# ── 3. Smoke tests HTTP ──────────────────────────────────────────────────────
print("[3/4] Smoke tests HTTP...\n")

import urllib.request
import json

BASE = "https://crm.nana-intelligence.fr"
results = []

def test(name, url, method="GET", data=None, expected_status=None):
    try:
        body = json.dumps(data).encode() if data else None
        headers = {"Content-Type": "application/json"}
        req = urllib.request.Request(url, data=body, headers=headers, method=method)
        resp = urllib.request.urlopen(req, timeout=15)
        status = resp.read(0) and resp.status
        ok = expected_status is None or resp.status == expected_status
        icon = "✅" if ok else "❌"
        results.append((icon, name, resp.status, url))
        print(f"  {icon} [{resp.status}] {name}")
    except urllib.error.HTTPError as e:
        ok = expected_status is None or e.code == expected_status
        icon = "✅" if ok else "❌"
        results.append((icon, name, e.code, url))
        print(f"  {icon} [{e.code}] {name}")
    except Exception as e:
        results.append(("❌", name, "ERR", url))
        print(f"  ❌ [ERR] {name}: {str(e)[:80]}")

# Health checks
test("Home page (200)", f"{BASE}/")
test("Login page (200)", f"{BASE}/login")

# API — login public
test("API login wrong creds (422)", f"{BASE}/api/v1/auth/login",
     method="POST", data={"email":"wrong@test.com","password":"wrong"}, expected_status=422)

# API — endpoints protégés sans auth
test("API contacts sans auth (401)", f"{BASE}/api/v1/contacts", expected_status=401)
test("API exports sans auth (401)", f"{BASE}/api/v1/exports", expected_status=401)

# Webhooks — sans signature
test("Webhook emelia sans sig (401)", f"{BASE}/api/webhooks/emelia",
     method="POST", data={"event":"opened"}, expected_status=401)
test("Webhook intent sans sig (401)", f"{BASE}/api/webhooks/emelia-intent",
     method="POST", data={"intent":"stop"}, expected_status=401)

# Rate limiting (fire 11 rapid requests)
print("\n  → Test rate limiting login (11 requêtes rapides)...")
rate_ok = False
for i in range(11):
    try:
        req = urllib.request.Request(
            f"{BASE}/api/v1/auth/login",
            data=json.dumps({"email":"ratelimit@test.com","password":"wrong"}).encode(),
            headers={"Content-Type": "application/json"},
            method="POST"
        )
        resp = urllib.request.urlopen(req, timeout=10)
        status = resp.status
    except urllib.error.HTTPError as e:
        status = e.code
    if status == 429:
        rate_ok = True
        break
    time.sleep(0.1)

icon = "✅" if rate_ok else "❌"
results.append((icon, "Rate limit login (429)", "OK" if rate_ok else "MISSING", f"{BASE}/api/v1/auth/login"))
print(f"  {icon} Rate limit déclenché: {rate_ok}")

print()

# ── 4. Résumé ────────────────────────────────────────────────────────────────
print("=" * 60)
print("RÉSUMÉ")
print("=" * 60)
passed = sum(1 for r in results if r[0] == "✅")
failed = sum(1 for r in results if r[0] == "❌")
print(f"  Tests OK: {passed}/{len(results)} | Échecs: {failed}")
for icon, name, status, url in results:
    print(f"  {icon} [{status}] {name}")
print()

ssh.close()
print("=== Déploiement v4.0 terminé ===")
