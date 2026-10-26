# Administrator Guide

System administration is a **function**, not a company department. The system
administrator role receives configuration permissions only — it has **no** requisition
approval, disbursement or confidential-contract authority unless those permissions are
explicitly granted (and every grant is audited).

## 1. Users and accounts

**Users & Permissions** lists every account.

### Create an account
1. Create the employee record first (**Employees → Register employee**).
2. **Users & Permissions → Create account**.
3. Enter username, full name, company email, link the employee record and assign at
   least one role.
4. Set an initial password. The user is forced to change it at first login
   (`must_change_password`).
5. Send the credentials to the employee through a secure channel. Accounts are personal
   — sharing is prohibited by policy.

### Deactivate an employee who leaves
Set **Account is active** to off. The account cannot sign in again, but all of the
employee's requisitions, approvals, payments, contracts and audit history are preserved.
Never delete users to "clean up".

### Reset a password
Open the account → **Manage** → enter a new password in *Reset password*. The user must
change it at next login. For self-service recovery, the **Forgot password** flow emails a
reset link to the company address.

## 2. Roles and permissions

**Users & Permissions → Role permission matrix** shows every permission by role and lets
you toggle them per role.

Seeded roles:

| Role slug | Purpose |
| --- | --- |
| `managing_director` | Final approval, executive reports |
| `hr` | Employees, contracts, HR review, disbursement, accountability |
| `hr_finance` | Finance & Accounts officer under HR (disbursement focused) |
| `technical_lead` | First-stage approval, projects, external requisitions |
| `technical_staff` | Own requisitions, tasks, tickets |
| `system_admin` | Settings, users, roles, audit — no financial/HR authority by default |

Guidelines:
- Grant the **least** privilege that a job needs.
- Do not combine `requisitions.create` with `requisitions.approve_md` for the same person
  where segregation of duties matters — the system blocks self-approval regardless.
- Changing role permissions is recorded in the audit log.

## 3. System settings

**System Settings** configures:

- Company name, contact details (shown on receipts and external forms).
- Default currency and company timezone.
- Maximum upload size (kilobytes) and contract reminder days
  (`90,60,30` by default).
- Whether approvers may return requisitions for correction.

The mandatory **Technical Lead → HR → MD → disbursement** sequence cannot be disabled or
reordered through configuration.

## 4. Document numbering

Numbers are generated transactionally and cannot collide:

| Document | Pattern |
| --- | --- |
| Employee | `EMP-0001` |
| Requisition | `REQ-YYYY-00001` (assigned at submission) |
| External requisition | `EXT-YYYY-00001` |
| Contract | `CON-YYYY-00001` |
| Payment | `PAY-YYYY-00001` |
| Receipt | `RCT-YYYY-00001` |

Counters live in the `sequence_counters` table.

## 5. Security operations

- **Audit logs** (`/audit`, permission `audit.view`) are append-only. Rows cannot be
  edited or deleted through the application; database-level care should also prevent it.
- **Login history** records successes, failures and blocked attempts (deactivated
  accounts).
- Login is rate limited to 5 attempts per username+IP window.
- Uploaded files are type- and size-validated and stored on a private disk; downloads
  re-check ownership and confidentiality on every request.
- Ensure HTTPS, `APP_DEBUG=false`, and a restricted database user in production
  (see `DEPLOYMENT.md`).

## 6. Configuration changes you should make after install

1. Set the real company name, address, email and phone in **System Settings**.
2. Set the correct currency and timezone.
3. Create real employee records and accounts, then **remove or disable demo accounts**.
4. Configure the mail transport (`.env`) so receipts and reminders are delivered by email.
5. Start the queue worker and scheduler (see `DEPLOYMENT.md`).
6. Schedule backups (see `BACKUP_AND_RECOVERY.md`).

## 7. Troubleshooting

| Symptom | Likely cause / fix |
| --- | --- |
| Receipt email not arriving | Mail transport not configured; check `email_deliveries` rows and use **Retry email delivery** on the receipt |
| User sees an empty sidebar | Role has no permissions — check the role permission matrix |
| "You are not authorised" on a page | Permission not granted for that module; grant it via the role |
| Upload rejected | File type or size beyond `uploads.max_bytes` |
| Queue not processing | `php artisan queue:work` is not running |
