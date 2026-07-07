import paramiko, sys, os

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

# Secrets lus depuis l'environnement — ne JAMAIS coder en dur (le repo est versionné).
host = os.environ.get('VPS_SSH_HOST', '51.38.99.226')
user = os.environ.get('VPS_SSH_USER', 'jimmy')
pwd  = os.environ.get('VPS_SSH_PASSWORD')
if not pwd:
    sys.exit('VPS_SSH_PASSWORD non défini — exporte la variable avant de lancer ce script.')

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(host, username=user, password=pwd, timeout=15)

def run(cmd, timeout=30):
    print(f'$ {cmd}')
    _, stdout, _ = client.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode('utf-8', errors='replace').strip()
    print(out[:600])
    print()

# Trouver où est le code dans le conteneur
run("docker inspect crm-app --format '{{range .Mounts}}{{.Source}} -> {{.Destination}}\\n{{end}}'")
run("docker exec crm-app ls /var/www/html/database/migrations/ | grep emelia")
run("docker exec crm-app php artisan migrate:status 2>&1 | grep emelia | head -10")
run("docker exec crm-app cat /var/www/html/.env | grep APP_ENV")

client.close()
