# User Manual

Sign in at the application URL with your **username** (or company email) and password.
Your sidebar only shows the modules you are allowed to use.

---

## 1. Managing Director (MD)

### Dashboard
Shows company-wide figures pulled from the live database: active employees by
department, active projects, task completion, the four requisition queues
(Technical Lead, HR, MD, disbursement), total requested / approved / disbursed amounts,
outstanding advances, accountability awaiting review, contracts approaching expiry and
monthly expenditure. Every card links to the underlying list.

### Reviewing requisitions
1. Open **Approval Center**. The queue contains only requisitions that have already been
   approved by both the Technical Lead and HR — the system never offers a requisition
   out of sequence.
2. Open a requisition to see items, supporting documents, prior decisions and financial
   detail.
3. Choose **Approve**, **Reject** or **Return**:
   - *Approve*: you may approve the full requested amount or enter a **lower amount**.
     The requested amount and the final approved amount are stored separately.
   - *Reject*: a reason is mandatory. The requisition cannot be paid afterwards.
   - *Return*: sends the requisition back to the requester for correction.

Your decision, amount, comments and timestamp are recorded permanently in the approval
history and the audit log. You cannot approve your own requisition.

### Financial oversight
Use **Finance & Accounts**, **Disbursements**, **Payment Receipts** and **Reports** to
monitor approved expenditure, payments made, remaining balances and outstanding
advances. Reports accept date ranges and can be printed.

---

## 2. Human Resources (HR — including Finance & Accounts)

### Employees
**Employees → Register employee** creates an employee record. The system assigns a unique
employee ID (`EMP-0001`, …). To give the employee access, open **Users & Permissions →
Create account**, link the employee record and assign roles. Deactivating an account
preserves every historical record.

### Employment contracts
**Employment Contracts → Upload contract** stores the signed PDF securely. Every upload
creates a new version; old versions are kept. Contracts with end dates automatically get
expiry reminders (default 90, 60 and 30 days before) and appear in the expiry banner.
Employees can only open their own contract.

### Requisition review (stage 2)
When a requisition reaches **Pending HR Review**, it appears in **Approval Center**.
Review the items, purpose, justification and supporting documents, then **Approve**,
**Reject** (reason required) or **Return**. HR cannot skip or precede the Technical Lead
stage.

### Disbursement (stage 5)
After MD approval the requisition appears in **Disbursements** with its approved amount,
amount paid and remaining balance.
1. Open the requisition and choose **Record payment**.
2. Enter the amount (partial payments are allowed), date, method, transaction reference,
   payee and optional payment evidence.
3. Save. The system validates that all three approvals exist and that the payment does
   not exceed the remaining approved amount, then generates a **unique receipt** and
   notifies the requester.

Receipts are listed under **Payment Receipts**; you can download the PDF and retry failed
email delivery. To correct a payment, reverse it — the original record is preserved.

### Accountability (stage 7)
Advances and accountable expenditure appear under **Accountability**. The requester
submits actual spend and explanations; HR reviews the submission, records the refund of
the unspent balance and closes the requisition.

---

## 3. Technical Lead

### Department dashboard
Shows team size, active projects, open and overdue tasks, requisitions awaiting your
action, rejected requisitions, external forms and support tickets.

### Requisition review (stage 1)
You complete the first approval stage for **all Technical Department requisitions**.
You cannot approve your own requisition — it is routed to another authorised approver.
Approve, reject (reason required) or return for correction.

### External requisition forms
**External Requisitions → New external requisition** creates a formal form for a
supplier, contractor or client: organisation details, itemised goods/services with
quantities and prices, delivery date, terms and attachments. Use **Download PDF form**
to produce the printable document with company details, totals and signature sections.
Preparing or printing the form never bypasses internal approval — the form is only
marked approved after an authorised internal approval decision.

### Projects & tasks
**Projects & Tasks** lists projects with progress and the task board (To Do, In Progress,
Blocked, In Review, Completed, Cancelled). Overdue tasks are flagged.

---

## 4. Technical Staff

### Your dashboard
Shows your requisitions (pending, approved, rejected), your receipts and your open tasks.

### Creating an internal requisition
1. **Internal Requisitions → New requisition**.
2. Fill in title, category, priority and required date; add itemised goods/services
   (quantity × unit cost is calculated automatically).
3. Add purpose, justification, supplier details and supporting documents.
4. Tick **advance / accountable expenditure** if you will need to account for the funds.
5. Save as a draft or **Submit for approval**.

After submission the requisition follows: Technical Lead → HR → MD → HR disbursement →
receipt → accountability. You receive a notification at every decision and can download
the payment receipt as soon as the payment is completed.

You can only edit a draft or a returned requisition, and only your own.

---

## Common tasks

| Task | Where |
| --- | --- |
| Change your password | **Profile** (top-right) |
| See notifications | Bell icon or **Notifications** |
| Download a receipt | **Payment Receipts → PDF** |
| Download a document | Attachment **Download** link (authorised users only) |
| Print a form or report | Open the PDF, or use the browser print dialog |
