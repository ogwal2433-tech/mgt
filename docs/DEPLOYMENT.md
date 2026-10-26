# Production Deployment Guide

## 1. Server requirements

| Component | Version |
| --- | --- |
| PHP | 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `fileinfo`, `gd`, `curl`, `zip` |
| Composer | 2.x |
| Node.js | 20+ (build-time only) |
| Database | MySQL 8+ or MariaDB 10.4+ |
| Web server | Nginx (recommended) or Apache 2.4 with `mod_rewrite` |
| HTTPS | Required in production (Let's Encrypt or corporate certificate) |

## 2. Release build

```bash
git clone <repository> /var/www/mgt-system
cd /var/www/mgt-system
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

## 3. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Production `.env` essentials:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mgt.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mgt_system
DB_USERNAME=mgt_app
DB_PASSWORD=<strong password>

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true   # add to .env when serving over HTTPS

CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="mgt@example.com"
MAIL_FROM_NAME="MGT System"
```

Never commit `.env`. Restrict its permissions (`chmod 640 .env`) and use a secret
manager for the database password and `APP_KEY` where possible.

## 4. Database

```bash
mysql -u root -p -e "CREATE DATABASE mgt_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'mgt_app'@'localhost' IDENTIFIED BY '<strong password>';"
mysql -u root -p -e "GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,REFERENCES ON mgt_system.* TO 'mgt_app'@'localhost';"

php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force   # roles + permissions
php artisan db:seed --class=CompanyDataSeeder --force      # departments, categories, settings
```

`DemoDataSeeder` refuses to run when `APP_ENV=production`. Create real employee records
and accounts through **Users & Permissions** after deployment.

## 5. Web server (Nginx)

```nginx
server {
    listen 443 ssl http2;
    server_name mgt.example.com;

    root /var/www/mgt-system/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/mgt.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mgt.example.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "same-origin";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    client_max_body_size 25M;   # keep in sync with the "uploads.max_bytes" setting

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* ^/storage { deny all; }        # never serve private storage
    location ~ /\.(?!well-known) { deny all; }
}
```

Apache alternative: point a `<VirtualHost>` at `public/`, enable `mod_rewrite` and set
`AllowOverride All` so the shipped `.htaccess` is honoured.

## 6. Permissions and background workers

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Queue workers (notifications, receipt email retries, contract reminders):

```bash
# /etc/systemd/system/mgt-worker.service
[Unit]
Description=MGT System queue worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/mgt-system
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now mgt-worker
```

Scheduler (contract expiry reminders, overdue-task alerts) — one cron entry:

```cron
* * * * * www-data cd /var/www/mgt-system && php artisan schedule:run >> /dev/null 2>&1
```

## 7. Optimisation after every release

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

## 8. Deployment checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] HTTPS enabled and HTTP→HTTPS redirect in place
- [ ] Database user has only the privileges listed above
- [ ] `storage/` and `bootstrap/cache` writable by the web server
- [ ] Queue worker and scheduler running
- [ ] Mail transport tested (send a receipt to a test account)
- [ ] Backup job configured and a restore rehearsed (see `BACKUP_AND_RECOVERY.md`)
- [ ] Demo seed data absent; real employees and accounts created
- [ ] Log rotation configured for `storage/logs`
