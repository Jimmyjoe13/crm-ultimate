import os
import sys
import subprocess
import paramiko

# Configure stdout/stderr to use UTF-8 and replace unencodable characters
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'reconfigure'):
    sys.stderr.reconfigure(encoding='utf-8', errors='replace')

hostname = "51.38.99.226"
username = "jimmy"
password = "<purge-crm-20260713>\u00f9891234kfp"
remote_base_dir = "/home/jimmy/crm-ultimate"

# Get the list of modified files in the last commit
try:
    files = subprocess.check_output(["git", "diff-tree", "--no-commit-id", "--name-only", "-r", "HEAD"]).decode('utf-8').splitlines()
except Exception as e:
    print(f"Error getting git files: {e}")
    sys.exit(1)

if not files:
    print("No files to deploy.")
    sys.exit(0)

# Connect to SFTP
transport = paramiko.Transport((hostname, 22))
try:
    transport.connect(username=username, password=password)
    sftp = paramiko.SFTPClient.from_transport(transport)
    
    # Helper to create remote dirs
    def ensure_remote_dir(remote_path):
        dirs = []
        path = remote_path
        while path and path != '/' and path != '.':
            # normalize paths
            path = path.replace('\\', '/')
            if path not in dirs:
                dirs.append(path)
            # parent
            parts = path.split('/')
            if len(parts) > 1:
                path = '/'.join(parts[:-1])
            else:
                break
        
        for d in reversed(dirs):
            if not d:
                continue
            try:
                sftp.stat(d)
            except IOError:
                print(f"Creating remote directory: {d}")
                try:
                    sftp.mkdir(d)
                except Exception as mkdir_err:
                    print(f"Warning: could not create directory {d}: {mkdir_err}")

    print(f"Starting upload of {len(files)} files to {hostname}...")
    for f in files:
        if not f:
            continue
        
        # Skip git files, handoff files, docs and scratch directory files
        if f.startswith('crm-ultimate-handoff/') or f.startswith('.claude') or f.startswith('.gemini') or f.endswith('.md'):
            print(f"Skipping documentation/metadata file: {f}")
            continue
            
        local_path = os.path.abspath(f)
        if not os.path.exists(local_path):
            print(f"Skipping non-existent local file: {f}")
            continue
            
        if os.path.isdir(local_path):
            continue
            
        remote_path = remote_base_dir + "/" + f.replace('\\', '/')
        
        ensure_remote_dir(os.path.dirname(remote_path))
        
        print(f"Uploading: {f} -> {remote_path}")
        sftp.put(local_path, remote_path)
        
    print("All files uploaded successfully!")
    
except Exception as e:
    print(f"SFTP Error: {e}")
    sys.exit(1)
finally:
    transport.close()
