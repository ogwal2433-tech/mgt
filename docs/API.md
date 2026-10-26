# API / Route Reference

The application is a server-driven Inertia.js app: pages are rendered by controllers and
mutations are plain HTTP `POST/PUT/PATCH/DELETE` endpoints that redirect back with flash
messages or validation errors. Every protected route is guarded by `auth`, an active-account
check, and a permission middleware on the server — never by frontend visibility alone.

All routes below require an authenticated, active session unless stated otherwise.

## Authentication

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/login` | Login page (guests only) |
| POST | `/login` | Authenticate with `login` (username **or** email) + `password` + optional `remember` |
| POST | `/logout` | End the session, record logout in login history |
| GET/POST | `/forgot-password`, `/reset-password/*` | Password recovery |
| GET/POST | `/confirm-password` | Confirm password for sensitive actions |
| GET/POST | `/email/verification/*` | Email verification (optional) |

Login is rate limited (5 attempts) and records successes, failures and blocked
(deactivated) attempts in `login_history`. Self-registration is disabled.

## Dashboards

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/dashboard` | any authenticated user (content depends on role) |

## Employees

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/employees` | `employees.view` or `employees.manage` |
| POST | `/employees` | `employees.manage` |
| GET | `/employees/{id}` | `employees.view` or `employees.manage` |
| PUT | `/employees/{id}` | `employees.manage` |

Employee codes are generated sequentially (`EMP-0001`, …).

## Employment contracts

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/contracts` | `contracts.view` or `contracts.manage` (others see only their own) |
| POST | `/contracts` | `contracts.manage` |
| GET | `/contracts/{id}` | owner, `contracts.manage`, or `contracts.view_confidential` |
| POST | `/contracts/{id}/versions` | `contracts.manage` |
| PATCH | `/contracts/{id}/status` | `contracts.manage` |

Contract uploads accept `pdf,doc,docx`. Each upload creates a **new version**; previous
versions are preserved. Reminders are scheduled from `contracts.reminder_days`
(default `90,60,30`).

## Internal requisitions

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/requisitions` | `requisitions.create` / `requisitions.view_department` / `requisitions.view_all` |
| GET | `/requisitions/create` | `requisitions.create` |
| POST | `/requisitions` | `requisitions.create` (optionally `submit=1`) |
| GET | `/requisitions/{id}` | requester, department viewer, or `requisitions.view_all` |
| PUT | `/requisitions/{id}` | requester (draft/returned only) |
| POST | `/requisitions/{id}/submit` | requester |
| POST | `/requisitions/{id}/approve` | `requisitions.review_technical` / `review_hr` / `approve_md` (stage-dependent) |
| POST | `/requisitions/{id}/reject` | as above (mandatory `reason`) |
| POST | `/requisitions/{id}/return` | as above (mandatory `reason`) |
| DELETE | `/requisitions/{id}` | requester (draft only) |

### Create payload (multipart/form-data)

```
title*, category_id*, currency*, requested_amount*, priority*
purpose, justification, project_id, required_date, supplier_name,
supplier_contact, budget_reference, is_advance, accountability_required
items[n][description]*, items[n][quantity]*, items[n][unit_cost]*, items[n][unit]
attachments[] (pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,txt,zip — max 20MB)
```

### Approval decisions

`POST /requisitions/{id}/approve` accepts `approved_amount` (MD may approve a lower
amount) and `comments`. `reject`/`return` require `reason`. Status transitions:

```
DRAFT / RETURNED_FOR_CORRECTION --submit--> PENDING_TECHNICAL_LEAD
PENDING_TECHNICAL_LEAD --approve--> PENDING_HR_REVIEW
PENDING_HR_REVIEW      --approve--> PENDING_MD_APPROVAL
PENDING_MD_APPROVAL    --approve--> APPROVED_AWAITING_DISBURSEMENT
any pending stage      --reject-->  REJECTED_BY_<STAGE>
any pending stage      --return-->  RETURNED_FOR_CORRECTION
```

## Payments / disbursement

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/disbursements` | `finance.view` or `finance.disburse` |
| GET | `/finance` | `finance.view` or `finance.disburse` |
| POST | `/requisitions/{id}/payments` | `finance.disburse` |
| POST | `/payments/{id}/reverse` | `finance.disburse` |

`POST /requisitions/{id}/payments` payload:

```
amount*, payment_date*, payment_method*, payee_name*
transaction_reference, payee_details, notes, evidence (pdf/png/jpg)
```

Server-side validation before a payment is accepted:

1. All three approvals (technical_lead, hr, md) exist.
2. The requisition is `APPROVED_AWAITING_DISBURSEMENT` or `PARTIALLY_DISBURSED`.
3. `amount > 0` and `amount <= approved_amount - amount_paid` (checked under a row lock).
4. No completed payment with the same `transaction_reference` for the requisition.

A completed payment triggers receipt generation, an in-app notification and an email
attempt automatically.

## Receipts

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/receipts` | own receipts, or `finance.view` |
| GET | `/receipts/{id}` | requester or `finance.view` |
| GET | `/receipts/{id}/pdf` | requester or `finance.view` (PDF download) |
| POST | `/receipts/{id}/retry-delivery` | `finance.disburse` |

## Accountability

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/accountability` | own submissions or `finance.accountability_review` |
| POST | `/requisitions/{id}/accountability` | requester |
| POST | `/accountability/{id}/review` | `finance.accountability_review` |

## External requisitions

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/external-requisitions` | `external_requisitions.manage` / `.view` |
| POST | `/external-requisitions` | `external_requisitions.manage` |
| GET | `/external-requisitions/{id}` | as above |
| POST | `/external-requisitions/{id}/approve` | `external_requisitions.manage` |
| GET | `/external-requisitions/{id}/pdf` | `external_requisitions.view` |

## Users, roles and permissions

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/users` | `system.users` |
| POST | `/users` | `system.users` |
| PUT | `/users/{id}` | `system.users` |
| GET | `/users/permissions/matrix` | `system.roles` |
| PUT | `/roles/{id}/permissions` | `system.roles` |

## Other modules

| Route | Purpose | Permission |
| --- | --- | --- |
| `/hr` | HR hub (employees, contracts, leave) | `employees.*`, `hr.operations` |
| `/technical` | Technical department hub | `dashboard.technical`, `projects.*` |
| `/projects` | Projects & tasks | `projects.view` / `projects.manage` |
| `/procurement` | Suppliers & purchase orders | `procurement.manage` |
| `/inventory` | Inventory & assets | `inventory.manage` / `assets.manage` |
| `/attendance` | Attendance & leave | `hr.operations` |
| `/reports` | Reports with date filters | `reports.*` |
| `/notifications` | Notification centre | authenticated |
| `/audit` | Audit logs | `audit.view` |
| `/settings` | System settings | `system.settings` |
| `/documents/{uuid}/download` | Private document download | ownership or `documents.download_confidential` |

## Response conventions

- Successful mutations redirect back (`302`) with `success` flash message.
- Validation failures redirect back with `errors` (Inertia shares them to the form).
- Authorisation failures return `403`.
- Missing records return `404`.
- All monetary values are transferred as fixed-precision decimal strings (`"1000.00"`).
