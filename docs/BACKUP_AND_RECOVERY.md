# Backup and Recovery

Financial history, contracts and audit records must survive any incident. Back up both
the **database** and the **private document storage**.

## 1. What must be backed up

| Data | Location |
| --- | --- |
| All business data (users, requisitions, payments, receipts, contracts, audit logs) | MySQL database `mgt_system` |
| Uploaded and generated documents (contracts, receipts, evidence, PDFs) | `storage/app/private/documents/` (disk `local`) |
| Environment configuration | `.env` (store separately, securely — it contains `APP_KEY`) |
| Application code | Version control repository |

Session/cache/job tables can be regenerated and are optional to restore.

## 2. Nightly backup script

`/usr/local/sbin/mgt-backup.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

STAMP=$(date +%F_%H%M)
DEST=/var/backups/mgt-system
KEEP_DAYS=30
APP=/var/www/mgt-system

mkdir -p "$DEST"

# Load DB credentials from the application environment
DB_NAME=$(grep '^DB_DATABASE=' "$APP/.env" | cut -d= -f2-)
DB_USER=$(grep '^DB_USERNAME=' "$APP/.env" | cut -d= -f2-)
DB_PASS=$(grep '^DB_PASSWORD=' "$APP/.env" | cut -d= -f2-)

# 1) Database dump
MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --routines --triggers \
    -u "$DB_USER" "$DB_NAME" | gzip > "$DEST/db-$STAMP.sql.gz"

# 2) Private documents
tar -czf "$DEST/documents-$STAMP.tar.gz" -C "$APP/storage/app/private" documents

# 3) Environment file
cp "$APP/.env" "$DEST/env-$STAMP"

# Prune old backups
find "$DEST" -type f -mtime +"$KEEP_DAYS" -delete

echo "Backup completed: $STAMP"
```

```bash
chmod 700 /usr/local/sbin/mgt-backup.sh
crontab -e
# 02:00 every night
0 2 * * * /usr/local/sbin/mgt-backup.sh >> /var/log/mgt-backup.log 2>&1
```

Copy the backup directory off the server (object storage, another host, or tape) —
backups that live only on the production server are not backups.

XAMPP/Windows equivalent (Task Scheduler):

```bat
"C:\xampps\mysql\bin\mysqldump.exe" -u root mgt_system > E:\backups\mgt\db-%DATE%.sql
"C:\xampps\php\php.exe" artisan tinker --execute="..."   # or simply copy storage\app\private\documents
```

## 3. Restore procedure (test this quarterly)

```bash
# 1. Create a clean database
mysql -u root -p -e "CREATE DATABASE mgt_system_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Restore the dump
gunzip < /var/backups/mgt-system/db-2026-10-25_0200.sql.gz | mysql -u root -p mgt_system_restore

# 3. Restore the documents
tar -xzf /var/backups/mgt-system/documents-2026-10-25_0200.tar.gz -C /var/www/mgt-system/storage/app/private/

# 4. Point the app at the restored database (or rename the databases)
#    and restore .env from the backup copy if needed.

# 5. Clear caches
cd /var/www/mgt-system
php artisan config:clear && php artisan cache:clear
```

Verify after restore:

1. Log in as each role and open the dashboards.
2. Open a known requisition and confirm its approval history is intact.
3. Download a known receipt PDF and a contract document.
4. Check `audit_logs` continuity (no gaps in `id`/timestamps).
5. Confirm `SELECT COUNT(*) FROM payments;` matches the pre-incident figure.

## 4. Recovery-time and recovery-point objectives (suggested)

| Objective | Target |
| --- | --- |
| RPO (maximum data loss) | 24 hours with nightly dumps; 1 hour if binary logging / point-in-time recovery is enabled |
| RTO (maximum downtime) | 2 hours for a full restore on a prepared server |

## 5. Additional resilience

- Enable MySQL binary logging (`log_bin`) and take nightly dumps for point-in-time recovery.
- Keep at least 30 daily and 12 monthly backups.
- Encrypt backup archives at rest and restrict access to the backup directory (`chmod 700`).
- Rehearse the restore into a staging environment at least quarterly and record the result.
- Because `APP_KEY` encrypts sessions and cookies, keep the `.env` backup as securely as
  the database backup.
