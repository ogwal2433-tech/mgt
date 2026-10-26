import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/ui/StatCard';
import { Card, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import { formatDate } from '@/types/app';
import { Head } from '@inertiajs/react';

export default function OperationsAttendance({ attendance, leave, stats }: any) {
    return (
        <AppLayout title="Attendance & Leave" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Attendance & Leave' }]}>
            <Head title="Attendance & Leave" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Present today" value={stats.present_today} accent="green" />
                <StatCard label="Leave requests pending" value={stats.leave_pending} accent="amber" />
                <StatCard label="Leave approved" value={stats.leave_approved} accent="blue" />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Attendance (last 30 days)" />
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Employee</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">In / Out</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {(attendance ?? []).length === 0 && (
                                    <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-500">No attendance records.</td></tr>
                                )}
                                {(attendance ?? []).map((a: any) => (
                                    <tr key={a.id}>
                                        <td className="px-4 py-2">{formatDate(a.date)}</td>
                                        <td className="px-4 py-2">{a.employee?.full_name}</td>
                                        <td className="px-4 py-2"><Badge color={statusColor(a.status)}>{a.status}</Badge></td>
                                        <td className="px-4 py-2 text-gray-600">{a.check_in ?? '—'} / {a.check_out ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card>
                    <CardHeader title="Leave requests" />
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Employee</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Dates</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {(leave ?? []).length === 0 && (
                                    <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-500">No leave requests.</td></tr>
                                )}
                                {(leave ?? []).map((l: any) => (
                                    <tr key={l.id}>
                                        <td className="px-4 py-2">{l.employee?.full_name}</td>
                                        <td className="px-4 py-2">{l.leave_type?.replaceAll('_', ' ')}</td>
                                        <td className="px-4 py-2">{formatDate(l.start_date)} → {formatDate(l.end_date)}</td>
                                        <td className="px-4 py-2"><Badge color={statusColor(l.status)}>{l.status}</Badge></td>
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
