import AppLayout from '@/Layouts/AppLayout';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import StatCard from '@/Components/ui/StatCard';
import Button from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Form';
import Badge, { statusColor } from '@/Components/ui/Badge';
import { formatMoney, formatDate } from '@/types/app';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function ReportsIndex({ filters, summary, byStatus, byDepartment, monthly, currency, extra }: any) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    return (
        <AppLayout title="Reports" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Reports' }]}>
            <Head title="Reports" />

            <div className="mb-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <Field label="From">
                        <TextInput type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </Field>
                    <Field label="To">
                        <TextInput type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                    </Field>
                    <div className="flex items-end gap-2">
                        <Button variant="secondary" onClick={() => router.get('/reports', { from, to }, { preserveState: true })}>Apply</Button>
                        <Button variant="ghost" onClick={() => window.print()}>Print</Button>
                    </div>
                </div>
                <p className="mt-2 text-xs text-gray-500">All figures come from real database records. Exports are restricted by permission.</p>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <StatCard label="Requisitions" value={summary.count} accent="blue" />
                <StatCard label="Total requested" value={formatMoney(summary.requested, currency)} accent="gray" />
                <StatCard label="Total approved" value={formatMoney(summary.approved, currency)} accent="green" />
                <StatCard label="Total disbursed" value={formatMoney(summary.disbursed, currency)} accent="green" />
                <StatCard label="Rejected" value={summary.rejected} accent="red" />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Requisitions by status" subtitle={`Period: ${formatDate(filters.from)} – ${formatDate(filters.to)}`} />
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Count</th>
                                    <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Requested</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {(byStatus ?? []).map((row: any) => (
                                    <tr key={row.status}>
                                        <td className="px-4 py-2"><Badge color={statusColor(row.status)}>{row.status.replaceAll('_', ' ')}</Badge></td>
                                        <td className="px-4 py-2 text-right">{row.total}</td>
                                        <td className="px-4 py-2 text-right">{formatMoney(row.amount, currency)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card>
                    <CardHeader title="Departmental overview" />
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Department</th>
                                    <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Employees</th>
                                    <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Requested</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {(byDepartment ?? []).map((row: any) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-2">{row.name}</td>
                                        <td className="px-4 py-2 text-right">{row.employee_count}</td>
                                        <td className="px-4 py-2 text-right">{formatMoney(row.requested_sum ?? '0.00', currency)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Monthly expenditure" subtitle="Completed payments by month" />
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Month</th>
                                    <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Payments</th>
                                    <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {(monthly ?? []).length === 0 && (
                                    <tr><td colSpan={3} className="px-4 py-8 text-center text-gray-500">No payments in this period.</td></tr>
                                )}
                                {(monthly ?? []).map((row: any) => (
                                    <tr key={row.month}>
                                        <td className="px-4 py-2">{row.month}</td>
                                        <td className="px-4 py-2 text-right">{row.count}</td>
                                        <td className="px-4 py-2 text-right">{formatMoney(row.total, currency)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card>
                    <CardHeader title="Operational indicators" />
                    <CardBody>
                        <div className="grid grid-cols-2 gap-4">
                            <Indicator label="Active employees" value={extra.employees} />
                            <Indicator label="Receipts generated" value={extra.receipts} />
                            <Indicator label="Contracts expiring (90 days)" value={extra.contracts_expiring} />
                            <Indicator label="Active projects" value={extra.active_projects} />
                            <Indicator label="Overdue tasks" value={extra.overdue_tasks} />
                        </div>
                    </CardBody>
                </Card>
            </div>
        </AppLayout>
    );
}

function Indicator({ label, value }: { label: string; value: any }) {
    return (
        <div className="rounded-md border border-gray-100 px-4 py-3">
            <p className="text-xs font-semibold uppercase text-gray-500">{label}</p>
            <p className="mt-1 text-xl font-bold text-gray-900">{value}</p>
        </div>
    );
}
