import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/ui/StatCard';
import Badge, { statusColor } from '@/Components/ui/Badge';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { formatMoney, formatDate, formatDateTime, AuthUser } from '@/types/app';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard({ stats, recent }: any) {
    const { auth } = usePage().props as any;
    const user = auth.user as AuthUser;
    const currency = stats?.currency ?? 'UGX';
    const isMd = user.roles.some((r) => r.slug === 'managing_director');
    const isHr = user.roles.some((r) => r.slug === 'hr' || r.slug === 'hr_finance');
    const isTech = user.roles.some((r) => r.slug === 'technical_lead');
    const isStaff = !isMd && !isHr && !isTech;

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="mb-6">
                <h2 className="text-xl font-bold text-gray-900">
                    Welcome back, {user.employee?.full_name ?? user.name}
                </h2>
                <p className="mt-1 text-sm text-gray-500">
                    {isMd && 'Company-wide executive overview'}
                    {isHr && 'Human resources and finance overview'}
                    {isTech && 'Technical department overview'}
                    {isStaff && 'Your requisitions, tasks and receipts'}
                </p>
            </div>

            {isMd && (
                <>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label="Active employees" value={stats.active_employees} accent="green" href="/employees" />
                        <StatCard label="Active projects" value={stats.active_projects} accent="blue" href="/projects" />
                        <StatCard label="Awaiting MD approval" value={stats.pending_md} accent="amber" href="/approvals" />
                        <StatCard label="Awaiting disbursement" value={stats.awaiting_disbursement} accent="blue" href="/disbursements" />
                        <StatCard label="Total requested" value={formatMoney(stats.total_requested, currency)} hint="All submitted requisitions" accent="gray" href="/requisitions" />
                        <StatCard label="Total approved" value={formatMoney(stats.total_approved, currency)} hint="MD-approved amounts" accent="green" href="/requisitions" />
                        <StatCard label="Total disbursed" value={formatMoney(stats.total_disbursed, currency)} hint="Completed payments" accent="green" href="/finance" />
                        <StatCard label="Monthly expenditure" value={formatMoney(stats.monthly_expenditure, currency)} hint="Current month" accent="blue" href="/reports/finance" />
                        <StatCard label="Outstanding advances" value={formatMoney(stats.outstanding_advances, currency)} accent="amber" href="/accountability" />
                        <StatCard label="Accountability pending" value={stats.accountability_pending} accent="amber" href="/accountability" />
                        <StatCard label="Contracts expiring (90d)" value={stats.contracts_expiring} accent="red" href="/contracts" />
                        <StatCard label="Overdue tasks" value={stats.overdue_tasks} accent="red" href="/projects" />
                    </div>

                    <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader title="Approval pipeline" subtitle="Live requisition queues" />
                            <CardBody>
                                <div className="space-y-3">
                                    {[
                                        ['Pending Technical Lead review', stats.pending_technical_lead, '/approvals'],
                                        ['Pending HR review', stats.pending_hr, '/approvals'],
                                        ['Pending MD approval', stats.pending_md, '/approvals'],
                                        ['Approved, awaiting disbursement', stats.awaiting_disbursement, '/disbursements'],
                                    ].map(([label, value, href]) => (
                                        <Link key={label as string} href={href as string} className="flex items-center justify-between rounded-md border border-gray-100 px-4 py-3 hover:border-green-300 hover:bg-green-50/40">
                                            <span className="text-sm text-gray-700">{label}</span>
                                            <span className="text-lg font-bold text-gray-900">{value as number}</span>
                                        </Link>
                                    ))}
                                </div>
                            </CardBody>
                        </Card>

                        <Card>
                            <CardHeader title="Employees by department" />
                            <CardBody>
                                <div className="space-y-3">
                                    {(stats.employees_by_department ?? []).map((d: any) => (
                                        <div key={d.id} className="flex items-center justify-between rounded-md border border-gray-100 px-4 py-3">
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{d.name}</p>
                                                <p className="text-xs text-gray-500">{d.code}</p>
                                            </div>
                                            <span className="text-lg font-bold text-gray-900">{d.total}</span>
                                        </div>
                                    ))}
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </>
            )}

            {isHr && (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Active employees" value={stats.active_employees} href="/employees" />
                    <StatCard label="Contracts expiring (90d)" value={stats.contracts_expiring} accent="red" href="/contracts" />
                    <StatCard label="Requisitions for HR review" value={stats.pending_hr_review} accent="amber" href="/approvals" />
                    <StatCard label="Awaiting disbursement" value={stats.awaiting_disbursement} accent="blue" href="/disbursements" />
                    <StatCard label="Payments made" value={stats.payments_made} href="/finance" />
                    <StatCard label="Receipts generated" value={stats.receipts_generated} href="/receipts" />
                    <StatCard label="Total disbursed" value={formatMoney(stats.total_disbursed, currency)} accent="green" href="/finance" />
                    <StatCard label="Approved expenses" value={formatMoney(stats.approved_expenses, currency)} accent="blue" href="/reports/finance" />
                    <StatCard label="Outstanding advances" value={formatMoney(stats.outstanding_advances, currency)} accent="amber" href="/accountability" />
                    <StatCard label="Accountability outstanding" value={stats.accountability_outstanding} accent="amber" href="/accountability" />
                    <StatCard label="Leave requests pending" value={stats.pending_leave} accent="blue" href="/attendance" />
                    <StatCard label="Accounts pending activation" value={stats.accounts_pending_activation} accent="gray" href="/users" />
                </div>
            )}

            {isTech && (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Technical team" value={stats.technical_employees} href="/employees" />
                    <StatCard label="Active projects" value={stats.active_projects} href="/projects" />
                    <StatCard label="Open tasks" value={stats.tasks_assigned} href="/projects" />
                    <StatCard label="Overdue tasks" value={stats.overdue_tasks} accent="red" href="/projects" />
                    <StatCard label="Requisitions for Lead review" value={stats.pending_lead_action} accent="amber" href="/approvals" />
                    <StatCard label="Awaiting HR review" value={stats.awaiting_hr} accent="blue" href="/requisitions" />
                    <StatCard label="Rejected requisitions" value={stats.rejected} accent="red" href="/requisitions" />
                    <StatCard label="External requisition forms" value={stats.external_forms} href="/external-requisitions" />
                    <StatCard label="Open support tickets" value={stats.support_tickets} accent="amber" href="/technical" />
                    <StatCard label="Completed tasks" value={stats.completed_tasks} accent="green" href="/projects" />
                </div>
            )}

            {isStaff && (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="My requisitions" value={stats.my_requisitions} href="/requisitions" />
                    <StatCard label="Pending decisions" value={stats.my_pending} accent="amber" href="/requisitions" />
                    <StatCard label="Approved / disbursed" value={stats.my_approved} accent="green" href="/requisitions" />
                    <StatCard label="Rejected" value={stats.my_rejected} accent="red" href="/requisitions" />
                    <StatCard label="My receipts" value={stats.my_receipts} href="/receipts" />
                    <StatCard label="My open tasks" value={stats.my_tasks} accent="blue" href="/projects" />
                </div>
            )}

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader
                        title="Recent requisitions"
                        subtitle="Latest activity on your account"
                        actions={<Link href="/requisitions" className="text-sm font-semibold text-green-700 hover:text-green-800">View all</Link>}
                    />
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Number</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Amount</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {(recent?.requisitions ?? []).length === 0 && (
                                    <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-500">No requisitions yet.</td></tr>
                                )}
                                {(recent?.requisitions ?? []).map((r: any) => (
                                    <tr key={r.id} className="hover:bg-green-50/40">
                                        <td className="px-4 py-2">
                                            <Link href={`/requisitions/${r.id}`} className="font-medium text-green-700 hover:underline">
                                                {r.requisition_number}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-2 text-gray-800">{r.title}</td>
                                        <td className="px-4 py-2 text-gray-800">{formatMoney(r.requested_amount, r.currency)}</td>
                                        <td className="px-4 py-2"><Badge color={statusColor(r.status)}>{r.status_label}</Badge></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card>
                    <CardHeader title="Recent payments" subtitle="Latest disbursements recorded" />
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Payment</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Payee</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Amount</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {(recent?.payments ?? []).length === 0 && (
                                    <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-500">No payments recorded yet.</td></tr>
                                )}
                                {(recent?.payments ?? []).map((p: any) => (
                                    <tr key={p.id} className="hover:bg-green-50/40">
                                        <td className="px-4 py-2 font-medium text-gray-900">{p.payment_number}</td>
                                        <td className="px-4 py-2 text-gray-800">{p.payee_name}</td>
                                        <td className="px-4 py-2 text-gray-800">{formatMoney(p.amount, p.currency)}</td>
                                        <td className="px-4 py-2 text-gray-600">{formatDate(p.payment_date)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
