import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/ui/StatCard';
import { Card, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import { formatDate } from '@/types/app';
import { Head, Link } from '@inertiajs/react';

export default function OperationsHR({ stats, expiringContracts, recentLeave, recentHires }: any) {
    return (
        <AppLayout title="HR Management" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'HR Management' }]}>
            <Head title="HR Management" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard label="Active employees" value={stats.employees} href="/employees" />
                <StatCard label="On probation" value={stats.probation} accent="amber" href="/employees" />
                <StatCard label="Active contracts" value={stats.contracts_active} href="/contracts" />
                <StatCard label="Contracts expiring (90d)" value={stats.contracts_expiring} accent="red" href="/contracts" />
                <StatCard label="Leave requests pending" value={stats.leave_pending} accent="blue" href="/attendance" />
                <StatCard label="Performance reviews in draft" value={stats.reviews_due} accent="gray" />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Contracts approaching expiry" />
                    <ul className="divide-y divide-gray-100">
                        {(expiringContracts ?? []).length === 0 && <li className="px-5 py-8 text-center text-sm text-gray-500">No contracts expiring soon.</li>}
                        {(expiringContracts ?? []).map((c: any) => (
                            <li key={c.id} className="flex items-center justify-between px-5 py-3">
                                <div>
                                    <Link href={`/contracts/${c.id}`} className="text-sm font-semibold text-green-700 hover:underline">{c.contract_number}</Link>
                                    <p className="text-xs text-gray-500">{c.employee?.full_name}</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm text-gray-800">{formatDate(c.end_date)}</p>
                                    <Badge color={statusColor(c.status)}>{c.status}</Badge>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card>
                    <CardHeader title="Recent leave requests" />
                    <ul className="divide-y divide-gray-100">
                        {(recentLeave ?? []).length === 0 && <li className="px-5 py-8 text-center text-sm text-gray-500">No leave requests.</li>}
                        {(recentLeave ?? []).map((l: any) => (
                            <li key={l.id} className="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{l.employee?.full_name}</p>
                                    <p className="text-xs text-gray-500">{l.leave_type?.replaceAll('_', ' ')} · {formatDate(l.start_date)} → {formatDate(l.end_date)}</p>
                                </div>
                                <Badge color={statusColor(l.status)}>{l.status}</Badge>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader title="Recent hires" />
                <ul className="divide-y divide-gray-100">
                    {(recentHires ?? []).map((e: any) => (
                        <li key={e.id} className="flex items-center justify-between px-5 py-3">
                            <div>
                                <Link href={`/employees/${e.id}`} className="text-sm font-semibold text-green-700 hover:underline">{e.full_name}</Link>
                                <p className="text-xs text-gray-500">{e.employee_code} · {e.job_title ?? '—'}</p>
                            </div>
                            <p className="text-sm text-gray-600">{formatDate(e.hire_date)}</p>
                        </li>
                    ))}
                </ul>
            </Card>
        </AppLayout>
    );
}
