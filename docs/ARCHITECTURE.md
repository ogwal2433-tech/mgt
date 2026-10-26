# MGT System — Architecture

Company Management System for a technology company.
Organisational structure: **Managing Director (MD)**, **Human Resources (HR — including Finance & Accounts)**, **Technical Department**, and **System Administration** as a system function (not a department).

## 1. Technology stack

| Layer | Choice | Notes |
| --- | --- | --- |
| Backend | Laravel 12 (PHP 8.2) | REST-less Inertia monolith, server-driven pages |
| Frontend | React 18 + TypeScript, Inertia.js | Single Vite build, code-split pages |
| UI | Tailwind CSS 3 + Headless UI | White surfaces, black text, green (`#16a34a` family) primary actions |
| Database | MySQL / MariaDB (XAMPP) | `mgt_system`, utf8mb4, strict mode, FK enforced |
| Auth | Laravel session auth (cookie, encrypted) | Server-managed session, rotation, expiry, optional TOTP 2FA |
| PDF | barryvdh/laravel-dompdf | Receipts, external requisition forms, reports |
| Email | Laravel mail (log/smtp) | Transactional receipts, contract reminders |
| Queue | Database driver | Notification/reminder jobs, retry on transient failure |
| Storage | Local private disk `storage/app/private` | Never served through public URLs; authorised download routes only |
| Money | `DECIMAL(18,2)` + `bcmath` | No floating point in financial code paths |
| Tests | Pest (PHPUnit) | Workflow order, permissions, payment limits, receipts, contracts |

Secrets (mail credentials, app key) come from `.env`; nothing secret is shipped to the client bundle.

## 2. High-level request flow

```
Browser (React/Inertia)  --POST/GET-->  routes/web.php
        ^                                   |
        |                            Middleware stack
        |                     auth -> active account -> permission
        |                                   |
        |                            Controller (thin)
        |                                   |
        |                    Service layer (transactions, workflow,
        |                     audit, notifications, PDF, storage)
        |                                   |
        +---- Inertia props ----------  Eloquent / Query Builder  -->  MySQL
```

Authorisation is **always** enforced server-side (middleware + policy checks inside
services). Frontend hiding of buttons is convenience only, never a security control.

## 3. Domain modules

1. **Identity & access** — users, roles, permissions, login history, 2FA, deactivation.
2. **People (HR)** — employees, departments, positions, reporting lines, contracts + versions + reminders, attendance, leave, performance.
3. **Work (Technical)** — projects, project members, tasks, task updates, support tickets.
4. **Requisitions** — internal requisitions (categories, items, attachments) with the mandatory sequential approval workflow; external requisitions (parties, items, PDF form).
5. **Finance & Accounts (under HR)** — payments, receipts, accountability, refunds, budgets.
6. **Procurement & stock** — suppliers, quotations, purchase orders, GRNs, inventory, assets.
7. **Platform** — documents, notifications, audit logs, settings, reports.

## 4. Database relationships (core)

```
departments 1---* employees *---1 positions
employees   1---* employee_contracts 1---* contract_versions 1---1 documents
employees   1---0..1 users            (account linked to employee record)
users       *---* roles *---* permissions
employees   1---* reporting_relationships (manager)

requisitions *---1 requisition_categories
requisitions *---1 employees (requester)
requisitions *---1 departments
requisitions *---0..1 projects
requisitions 1---* requisition_items
requisitions 1---* requisition_attachments *---1 documents
requisitions 1---* requisition_approvals   (append-only history)
requisitions 1---* payments 1---* payment_receipts
requisitions 1---* accountability_submissions 1---* accountability_items
accountability_submissions 1---* refunds

external_requisitions *---1 external_parties
external_requisitions 1---* external_requisition_items

projects 1---* project_members *---1 employees
projects 1---* tasks 1---* task_updates
projects 1---* requisitions (link)

inventory_items 1---* inventory_movements
assets 1---* asset_assignments *---1 employees
purchase_orders *---1 suppliers 1---* supplier_quotations
purchase_orders 1---* purchase_order_items 1---* goods_received_notes

documents   (polymorphic owner/linked record, private storage)
notifications *---1 users
audit_logs  (append-only, no FK delete cascade)
```

Rules:
- Requisitions keep `requested_amount`, `approved_amount`, `amount_paid`,
  `amount_accounted`, `amount_refunded` as separate `DECIMAL(18,2)` columns.
- `requisition_approvals` is the authoritative approval history; the requisition
  `status` column is a denormalised projection updated only inside transactions.
- Financial history is never overwritten: corrections create reversal rows.
- Audit rows and payment rows are insert-only; no update/delete endpoints.

## 5. Role–permission matrix

Permissions are granular slugs grouped by module; roles are bundles of permissions.
The system administrator role receives **only** `system.*` / `audit.view` permissions
by default — it has no financial or HR authority unless explicitly granted.

| Capability (permission) | MD | HR | Tech Lead | Tech Staff | Sys Admin |
| --- | :-: | :-: | :-: | :-: | :-: |
| `dashboard.md` / `dashboard.hr` / `dashboard.technical` | ✓ | ✓ | ✓ | own | — |
| `employees.view` / `employees.manage` | ✓ | ✓ | dept view | own profile | — |
| `contracts.view` (own) | — | ✓ | — | ✓ | — |
| `contracts.manage` (upload, versions) | — | ✓ | — | — | — |
| `contracts.view_confidential` | policy | ✓ | — | — | — |
| `requisitions.create` | ✓ | ✓ | ✓ | ✓ | — |
| `requisitions.review_technical` | — | — | ✓ | — | — |
| `requisitions.review_hr` | — | ✓ | — | — | — |
| `requisitions.approve_md` | ✓ | — | — | — | — |
| `requisitions.view_department` | ✓ | ✓ | ✓ | own | — |
| `finance.disburse` | — | ✓ | — | — | — |
| `finance.view` | ✓ | ✓ | dept summary | own | — |
| `finance.accountability_review` | — | ✓ | — | — | — |
| `external_requisitions.manage` | — | ✓ | ✓ | — | — |
| `projects.manage` | — | — | ✓ | — | — |
| `tasks.update` | — | — | ✓ | ✓ | — |
| `hr.operations` (attendance, leave, performance) | — | ✓ | — | — | — |
| `procurement.manage` / `inventory.manage` / `assets.manage` | — | granted | granted | — | — |
| `reports.executive` / `reports.financial` / `reports.hr` | ✓ | ✓ | dept | own | — |
| `audit.view` | ✓ | — | — | — | ✓ |
| `system.settings` / `system.users` / `system.roles` | — | — | — | — | ✓ |
| `documents.download_confidential` | policy | ✓ | — | — | — |

Additional rules enforced in code:
- A user can never approve their own requisition (any stage).
- `finance.disburse` requires all three approvals to exist (see §6).
- System admin cannot approve, disburse, or open confidential contracts unless the
  permission is explicitly granted through the roles UI (and that grant is audited).
- Employee accounts are deactivated, never deleted; history is preserved.

## 6. Mandatory internal requisition workflow

```
SUBMIT  →  TECHNICAL LEAD  →  HR REVIEW  →  MD APPROVAL
        →  HR DISBURSEMENT  →  RECEIPT  →  REQUESTER NOTIFICATION
        →  ACCOUNTABILITY  →  CLOSURE
```

Status projection on `requisitions.status`:

| Stage | Allowed actions | Resulting status |
| --- | --- | --- |
| 1 Submission | submit / save draft | `PENDING_TECHNICAL_LEAD` |
| 2 Technical Lead | approve / reject (reason) / return | `PENDING_HR_REVIEW`, `REJECTED_BY_TECHNICAL_LEAD`, `RETURNED_FOR_CORRECTION` |
| 3 HR review | approve / reject (reason) / return | `PENDING_MD_APPROVAL`, `REJECTED_BY_HR`, `RETURNED_FOR_CORRECTION` |
| 4 MD | approve full / approve lower / reject / return | `APPROVED_AWAITING_DISBURSEMENT`, `REJECTED_BY_MD`, `RETURNED_FOR_CORRECTION` |
| 5 HR disbursement | record payment (partial allowed) | `PARTIALLY_DISBURSED` → `FULLY_DISBURSED` |
| 6 Receipt | automatic on completed payment | receipt + notification |
| 7 Accountability | submit / HR review / refund | `PENDING_ACCOUNTABILITY` → `ACCOUNTABILITY_UNDER_REVIEW` → `CLOSED` |

Non-negotiables (enforced inside `RequisitionWorkflowService`, in a DB transaction
with row locks):
- No stage may be skipped; each stage validates the previous approvals exist.
- Users cannot approve their own requests.
- Rejection requires a reason.
- Every decision appends a `requisition_approvals` row (user, timestamp, action,
  comments, previous/new status) and an `audit_logs` row.
- HR cannot disburse before MD approval; cannot exceed the remaining approved amount.
- Rejected/cancelled requisitions cannot be paid.
- Payments are idempotent per `(requisition_id, transaction_reference)` unique key.
- Corrections create reversal records; history is never silently overwritten.

External requisitions use the same document/PDF services but a separate table and
do **not** bypass internal approval: an external form can only be marked `APPROVED`
after its linked internal requisition reaches `APPROVED_AWAITING_DISBURSEMENT` or
an explicit internal approval record exists.

## 7. Documents & PDFs

- Uploaded files land on a private disk under `documents/{uuid}/…`.
- Every download goes through `GET /documents/{uuid}/download` which re-checks
  ownership, role and confidentiality flags before streaming.
- PDFs (receipts, external requisition forms, report exports) are generated
  server-side and stored as `documents` rows linked to their business record.

## 8. Notifications

`NotificationService` writes an in-app `notifications` row for every business event
and dispatches an email job when the mail transport is configured. Failed email
deliveries are recorded on `payment_notifications` / `email_deliveries` with a retry
counter so authorised users can retry.

## 9. Audit

`AuditLogger` writes append-only rows (user, action, entity, old/new values, IP,
request id). Database triggers/permissions and the model layer (`$guarded` +
disallowing update/delete) keep rows immutable.

## 10. Frontend structure

```
resources/js/
  Layouts/AppLayout.tsx        sidebar + topbar + notifications + flash
  Components/                  tables, badges, modals, forms, money, PDF links
  Pages/
    Dashboard/                 per-role dashboards (real counts only)
    Employees, Contracts, Requisitions, Approvals, Payments, Receipts,
    Accountability, External, Projects, Tasks, Procurement, Inventory,
    Assets, Attendance, Leave, Reports, Notifications, Users, Audit, Settings
  lib/                         api helpers, formatters, permission checks
```

Sidebar items are filtered by the permissions shared with the page via Inertia.

## 11. Money handling

- Stored as `DECIMAL(18,2)`.
- Arithmetic uses `bcmath` (`Money` helper: add/sub/mul/compare/allocate).
- Templates format with the configured currency and thousands separator.
- Sum of partial payments can never exceed `approved_amount` (checked under a
  `SELECT … FOR UPDATE` row lock inside the payment transaction).
