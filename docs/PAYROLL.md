# Payroll — Design & Operating Model

A payroll engine for the HR (Finance & Accounts) department that computes salaries from
each employee's **basic salary** plus **stipends/allowances**, less **advances**, **taxes**
and statutory deductions — and tracks every payment through to a delivered payslip.

## 1. Objectives

1. Compute a complete payroll for a pay period from real employee records — never
   hard-coded figures.
2. Include in the computation: basic salary, additional stipends/allowances, outstanding
   **advance recoveries**, **income tax** and statutory deductions.
3. Track every paid salary: who was paid, how much, when, by which method and reference,
   recorded by whom — with an audit trail.
4. Deliver every payslip to the employee **by email and inside their account**.
5. Enforce segregation of duties: payroll is prepared by HR, approved before payment, and
   every step is audited. Corrections are reversals, never overwrites.

## 2. Roles and permissions

| Permission | HR | HR Finance | Managing Director | Others |
| --- | :-: | :-: | :-: | :-: |
| `payroll.view` | ✓ | ✓ | ✓ | — |
| `payroll.run` (compute periods) | ✓ | ✓ | — | — |
| `payroll.approve` | ✓ | — | ✓ | — |
| `payroll.pay` (record salary payments) | ✓ | ✓ | — | — |

Employees see **only their own payslips** (`My Payslips`), with no payroll controls.

## 3. Data model

```
pay_periods            one row per calendar month (2026-10), status-driven
salary_components      configurable earning/deduction definitions
                       (e.g. Housing stipend, Transport stipend, PAYE, Pension, Advance recovery)
employee_compensation  recurring per-employee component amounts (stipends on top of basic)
tax_brackets           progressive monthly tax bands (configurable, inactive = no tax)
payroll_runs           one computed run per pay period (totals, status, approvals)
payroll_lines          one row per employee per run (basic, gross, deductions, net, breakdown)
payroll_line_items     every component applied to a line (amount + source reference)
payroll_payments       salary payments: amount, date, method, reference, recorded-by, status
```

`payroll_lines.breakdown` keeps a JSON snapshot of the computation so historical payslips
remain correct even after configuration changes. `payroll_line_items.source_type/source_id`
links advance recoveries to the originating requisition for traceability.

## 4. Computation model

For each active employee with a basic salary, per pay period:

```
EARNINGS
  basic               = employees.basic_salary
  + stipends          = Σ active employee_compensation (earning type, within effective dates)
  + one-off earnings  = Σ salary_components flagged for the run
  ────────────────────────────────────────────────────────────
  gross               = basic + stipends + one-off earnings

DEDUCTIONS
  taxable pay         = gross − non-taxable earnings
  tax                 = Σ tax_brackets applied to taxable pay (progressive, monthly bands)
  advance recovery    = min(outstanding advance balance, recovery cap)
                        outstanding = Σ(requisition.amount_paid − amount_accounted − amount_refunded)
                        for the employee's accountable/advance requisitions not yet closed
  statutory           = pension % of basic (configurable), other statutory components
  other               = one-off deductions flagged for the run
  ────────────────────────────────────────────────────────────
  total deductions    = tax + advance recovery + statutory + other

NET PAY              = gross − total deductions   (bcmath, DECIMAL(18,2), never floats)
```

Rules:
- Every amount is `DECIMAL(18,2)` computed with bcmath (`App\Support\Money`).
- `net_pay` can never be negative: recoveries are capped at the remaining net pay and the
  remainder carries to the next period (the advance balance stays outstanding).
- A line records `basic`, `gross`, `tax`, `advance`, `statutory`, `other`, `net` plus the
  itemised breakdown — the payslip renders exactly these numbers.

## 5. Run lifecycle and controls

```
OPEN (pay period) → COMPUTED → APPROVED → PAID → CLOSED
```

| Step | Who | What happens |
| --- | --- | --- |
| Compute | `payroll.run` | Snapshot all employees/components into `payroll_lines`; run is immutable afterwards without recomputing |
| Review / Approve | `payroll.approve` | Locks the run; approver ≠ computer where possible (audited either way) |
| Mark as paid | `payroll.pay` | Records a `payroll_payments` row per line (method, reference, date), generates payslips, delivers them |
| Close | `payroll.approve` | Period closed; further changes require a **reversal run** (new run referencing the original) |

Controls:
- A run can only be paid after approval.
- Paying twice for the same period is prevented (one run per period; payments unique per line).
- Recomputing an unpaid run replaces its lines; recomputing a paid run is blocked — use
  reversals/adjustments in the next period.
- Every transition writes an `audit_logs` row (actor, totals before/after).

## 6. Payment tracking

Every salary payment is recorded individually:

- `payroll_payments`: employee, line, amount, payment date, method (bank transfer, cash,
  mobile money), transaction reference, recorded-by, status.
- The run totals (`total_paid`) and the per-employee status (Unpaid / Paid) reflect these
  rows only — never the approval itself.
- Salary payments are fully searchable in **Finance & Accounts**-style listings and in the
  payroll register report.

## 7. Payslips — generation and delivery

When a payroll is marked as paid:

1. A payslip PDF is generated per employee (company-branded, like receipts): period,
   earnings breakdown (basic + each stipend), deductions (tax, advance recovery, statutory),
   net pay, payment reference and date.
2. The PDF is stored in private document storage and linked to the payroll line
   (`payslip_number` like `PAYSLIP-2026-10-0001`).
3. The payslip is **emailed** to the employee's company address (delivery status tracked,
   retryable).
4. The payslip appears in **My Payslips** inside the employee's account, where they can
   view/download it at any time (authorised download route, own payslips only).

Delivery failures are recorded and retriable; an employee can always fetch the payslip
from their account even if email fails.

## 8. Audit and compliance

- Audit rows for: run created/computed, approvals, payments recorded, payslip generation,
  payslip download, reversals.
- Payslips are append-only; a corrected salary is issued as a new adjustment line in the
  next run, never by editing a paid line.
- Access to payroll data is restricted to the roles above; payslips are private documents.

## 9. Configuration (System Settings)

| Setting | Default | Purpose |
| --- | --- | --- |
| `payroll.tax_enabled` | true | Apply tax brackets |
| `payroll.pension_percent` | 0 | Statutory pension % of basic salary |
| `payroll.advance_recovery` | `percent` | `percent` (recover X% of outstanding) or `full` |
| `payroll.advance_recovery_percent` | 50 | % of outstanding advance recovered per run |
| `payroll.pay_frequency` | `monthly` | Pay frequency label |

Tax brackets are fully configurable (`tax_brackets`): band, lower/upper monthly bounds,
rate. Seeded example brackets are generic and must be adjusted to local law.

## 10. Test plan

1. Computation: gross = basic + stipends; tax follows the bands; advance recovery is
   capped; net = gross − deductions (decimal-exact).
2. Lifecycle: compute → approve → pay; payment blocked before approval; double payment
   blocked; recomputing a paid run blocked.
3. Payslips: generated per paid line, delivered by email (delivery status recorded) and
   available in the employee's account (own payslips only).
4. Permissions: only HR roles can run/approve/pay; employees see only their payslips.
5. Tracking: payment rows carry method/reference/recorder; totals reflect payments only.
6. Audit: every transition and payment is logged.
