# GitHub Automated Deployment for PHP Applications

## Goal

Automatically deploy a PHP application to a production server whenever code is pushed to the `prod` or `deploy` branch.

This replaces manual uploads via FileZilla and provides a more reliable deployment process.

---

# Recommended Architecture

```text
Local Development
       |
       | git push prod
       v
GitHub Repository
       |
       | GitHub Action
       v
Production Server (SSH + rsync)
       |
       v
Updated Website
```

---

# Why Use SSH + rsync?

Advantages:

- Fully automated deployment
- Only changed files are transferred
- Faster than FTP/FileZilla
- Easy rollback using Git
- Secure authentication with SSH keys
- Ability to exclude files from deployment

---

# Step 1: Generate SSH Key Pair

On your local machine:

```bash
ssh-keygen -t ed25519 -C "github-actions"
```

This creates:

```text
~/.ssh/id_ed25519
~/.ssh/id_ed25519.pub
```

---

# Step 2: Configure Server Access

Copy the public key:

```bash
cat ~/.ssh/id_ed25519.pub
```

Add it to:

```text
~/.ssh/authorized_keys
```

on the production server.

Verify access:

```bash
ssh user@your-server
```

---

# Step 3: Add GitHub Secrets

Repository → Settings → Secrets and Variables → Actions

Create the following secrets:

| Secret | Value |
|----------|----------|
| HOST | Server IP or hostname |
| USERNAME | SSH username |
| SSH_PRIVATE_KEY | Contents of id_ed25519 |

Example:

```text
HOST=123.45.67.89
USERNAME=deploy
SSH_PRIVATE_KEY=-----BEGIN OPENSSH PRIVATE KEY-----
...
```

---

# Step 4: Create Deployment Workflow

Create:

```text
.github/workflows/deploy.yml
```

```yaml
name: Deploy Production

on:
  push:
    branches:
      - prod
      - deploy

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: Deploy via rsync
        uses: burnett01/rsync-deployments@7.0.2
        with:
          switches: -avzr --delete --exclude-from='.deployignore'
          path: ./
          remote_path: /var/www/myapp
          remote_host: ${{ secrets.HOST }}
          remote_user: ${{ secrets.USERNAME }}
          remote_key: ${{ secrets.SSH_PRIVATE_KEY }}
```

Deployment now occurs automatically when:

```bash
git push origin prod
```

or

```bash
git push origin deploy
```

---

# Step 5: Exclude Files from Production

Create:

```text
.deployignore
```

Example:

```text
.git/
.github/
.vscode/

tests/
docs/

README.md

.env
.env.*

docker-compose.yml

node_modules/
```

Files matching these patterns will not be copied to the server.

This works similarly to `.gitignore`, but specifically for production deployments.

---

# Protecting Environment Files

Never deploy local environment files.

Example `.deployignore`:

```text
.env
.env.*
```

Production should already contain:

```text
/var/www/myapp/.env
```

Example:

```env
DB_HOST=prod-db
DB_NAME=mydatabase
DB_USER=prod-user
DB_PASSWORD=super-secret
```

This prevents local development credentials from overwriting production settings.

---

# Optional: Run Composer After Deployment

If the server has Composer installed:

```yaml
- name: Run deployment commands
  uses: appleboy/ssh-action@v1.0.3
  with:
    host: ${{ secrets.HOST }}
    username: ${{ secrets.USERNAME }}
    key: ${{ secrets.SSH_PRIVATE_KEY }}
    script: |
      cd /var/www/myapp
      composer install --no-dev --optimize-autoloader
```

---

# Alternative: Git Pull on Server

Instead of uploading files, the server can pull directly from GitHub.

```yaml
- name: Deploy
  uses: appleboy/ssh-action@v1.0.3
  with:
    host: ${{ secrets.HOST }}
    username: ${{ secrets.USERNAME }}
    key: ${{ secrets.SSH_PRIVATE_KEY }}
    script: |
      cd /var/www/myapp
      git pull origin prod
```

Advantages:

- Smaller deployments
- Cleaner workflow
- Full Git history on server

Disadvantages:

- Requires Git access on the production server

---

# Recommended Setup for Small-to-Medium PHP Projects

Use:

- GitHub repository
- GitHub Actions
- SSH authentication
- rsync deployment
- `.deployignore`
- Production `.env` stored directly on the server

Deployment command becomes:

```bash
git add .
git commit -m "Feature completed"
git push origin prod
```

GitHub automatically deploys the changes to production.