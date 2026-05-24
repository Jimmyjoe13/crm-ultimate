import paramiko, sys
sys.stdout.reconfigure(encoding='utf-8', errors='replace')

host = '51.38.99.226'; user = 'jimmy'; pwd = '<purge-crm-20260713>ù891234kfp'
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(host, username=user, password=pwd, timeout=15)

def run(cmd, timeout=30):
    _, stdout, _ = client.exec_command(cmd, timeout=timeout)
    return stdout.read().decode('utf-8', errors='replace').strip()

# Check show.blade.php lines around the emelia modal
out = run("docker exec crm-app sed -n '450,510p' /var/www/html/resources/views/pages/contacts/show.blade.php")
print(out)
client.close()
