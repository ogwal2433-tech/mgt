# MGT System — Company Management System

A complete, secure, responsive management system for a technology company with the
organisational structure **Managing Director (MD)**, **Human Resources (HR — including
Finance & Accounts)**, **Technical Department**, and **System Administration** as a
system function.

Built with **Laravel 12 + Inertia.js + React (TypeScript) + Tailwind CSS + MySQL/MariaDB**.

## What the system does

- Individual employee accounts with usernames, role- and department-based permissions,
  login history and account deactivation (history is never deleted).
- Role dashboards for the MD, HR, Technical Lead and technical staff, with real database
  figures only (no invented statistics).
- **Internal requisitions** with the mandatory sequential approval workflow:

  ```
  Technical Staff → Technical Lead → HR Review → MD Final Approval
    → HR Disbursement → Automatic Receipt → Requester Notification → Accountability
  ```

  No stage can be skipped. Users cannot approve their own requisitions. Rejection
  requires a reason. Every decision is appended to the approval history and the audit log.
- **Payments & Finance (under HR)**: partial payments, remaining-balance tracking,
  duplicate-payment prevention, decimal-safe money arithmetic (`DECIMAL(18,2)` + bcmath),
  automatic unique PDF receipts per completed payment, in-app + email delivery with
  retry, accountability submissions and refunds. Corrections are reversals — financial
  history is never overwritten.
- **External requisitions** (suppliers/contractors/clients) with a professional printable
  PDF form that never bypasses internal approval requirements.
- **Employment contracts** with secure storage, version history (signed contracts are
  never overwritten), expiry reminders at configurable intervals, and per-employee access.
- **Technical department**: projects, tasks, support tickets.
- **Procurement, inventory and assets** as operational modules (not departments).
- **Attendance, leave and performance** records.
- Centralised private document storage with authorised download routes only.
- Notification centre, reports with date filters, append-only audit logs, and a system
  settings module (the approval sequence cannot be bypassed by configuration).

## Requirements

- PHP 8.2+ with `pdo_mysql`, `bcmath`, `openssl`, `mbstring`, `fileinfo`, `gd` (XAMPP is fine)
- Composer 2
- Node.js 20+ and npm
- MySQL 8 / MariaDB 10.4+ (XAMPP's MariaDB is fine)

## Development setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure the environment
cp .env.example .env
php artisan key:generate

#    Edit .env and set the database:
#      DB_CONNECTION=mysql
#      DB_HOST=127.0.0.1
#      DB_PORT=3306
#      DB_DATABASE=mgt_system
#      DB_USERNAME=root
#      DB_PASSWORD=

# 3. Create the database
mysql -u root -e "CREATE DATABASE mgt_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Migrate and seed (roles, permissions, departments, categories, settings)
php artisan migrate --seed

# 5. Build the frontend (or run the dev server)
npm run dev          # Vite dev server (hot reload)
# npm run build      # production build

# 6. Start the application (backend runs on port 8080)
composer serve         # php artisan serve --port=8080  →  http://127.0.0.1:8080
# or: npm run serve
# or everything at once (server + queue + logs + vite):
composer dev
```

### XAMPP notes

XAMPP's Apache can serve the app directly. Point a virtual host (or a `mgt-system`
alias inside `htdocs`) at the `public/` directory of this project, enable `mod_rewrite`,
and set `AllowOverride All`. Run `npm run build` so the compiled assets in
`public/build` are used. Alternatively keep using `composer serve` (port 8080) for development.

### Demo accounts (development only)

`php artisan db:seed` creates development accounts when `APP_ENV` is not `production`.
The default password for all demo accounts is `Password123!` (override with
`DEMO_PASSWORD` in `.env`).

| Username | Role |
| --- | --- |
| `md` | Managing Director |
| `hr` | Human Resources |
| `finance` | HR Finance & Accounts |
| `lead` | Technical Lead |
| `dev` | Technical Staff |
| `sysadmin` | System Administrator (no financial/HR authority by default) |

Sign in with the **username** (or the company email). Self-registration is disabled:
accounts are provisioned by authorised HR personnel or system administrators.

## Tests

```bash
php artisan test          # 84 tests: workflow order, permissions, payments,
                          # receipts, contracts, notifications, smoke tests
```

## Documentation

| Document | Contents |
| --- | --- |
| `docs/ARCHITECTURE.md` | Stack, database relationships, role-permission matrix, approval workflow |
| `docs/DEPLOYMENT.md` | Production deployment guide |
| `docs/API.md` | HTTP routes and request/response contract |
| `docs/USER_MANUAL.md` | Manuals for MD, HR, Technical Lead and technical staff |
| `docs/ADMINISTRATOR_GUIDE.md` | Users, roles, settings, security operations |
| `docs/BACKUP_AND_RECOVERY.md` | Backup and tested recovery procedures |

## Security highlights

- Server-side authorisation on every protected operation (role + permission middleware
  *and* service-level checks). Hiding a button is never a security control.
- Passwords hashed with bcrypt; sessions are server-managed, regenerated on login and
  invalidated on logout; rate-limited login with account lockout.
- Private document storage — contracts, payment evidence and receipts are only reachable
  through authorised download routes; there are no public URLs.
- Append-only audit logs (updates/deletes are blocked in the model layer).
- Duplicate-payment protection (unique constraint + transactional checks), and
  over-payment protection (remaining approved amount checked under a row lock).
- Secrets live in `.env`; nothing secret is sent to the client bundle.

## License

Proprietary — internal company use.
