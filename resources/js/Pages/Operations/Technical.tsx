import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/ui/StatCard';
import { Card, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import { formatDate } from '@/types/app';
import { Head, Link } from '@inertiajs/react';

export default function OperationsTechnical({ stats, projects, recentTasks, tickets }: any) {
    return (
        <AppLayout title="Technical Department" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Technical Department' }]}>
            <Head title="Technical Department" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <StatCard label="Team members" value={stats.team} href="/employees" />
                <StatCard label="Active projects" value={stats.projects} accent="blue" href="/projects" />
                <StatCard label="Open tasks" value={stats.tasks_open} accent="blue" href="/projects" />
                <StatCard label="Overdue tasks" value={stats.tasks_overdue} accent="red" href="/projects" />
                <StatCard label="Open support tickets" value={stats.tickets} accent="amber" />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Projects" />
                    <ul className="divide-y divide-gray-100">
                        {(projects ?? []).length === 0 && <li className="px-5 py-8 text-center text-sm text-gray-500">No projects yet.</li>}
                        {(projects ?? []).map((p: any) => (
                            <li key={p.id} className="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">{p.name}</p>
                                    <p className="text-xs text-gray-500">{p.project_code} · Lead: {p.lead?.full_name ?? '—'}</p>
                                </div>
                                <div className="text-right">
                                    <Badge color={statusColor(p.status)}>{p.status}</Badge>
                                    <p className="mt-1 text-xs text-gray-500">{p.progress_percentage}% complete</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card>
                    <CardHeader title="Recent tasks" />
                    <ul className="divide-y divide-gray-100">
                        {(recentTasks ?? []).length === 0 && <li className="px-5 py-8 text-center text-sm text-gray-500">No tasks.</li>}
                        {(recentTasks ?? []).map((t: any) => (
                            <li key={t.id} className="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{t.title}</p>
                                    <p className="text-xs text-gray-500">{t.task_code} · {t.assignee?.full_name ?? 'Unassigned'}</p>
                                </div>
                                <div className="text-right">
                                    <Badge color={statusColor(t.status)}>{t.status}</Badge>
                                    <p className="mt-1 text-xs text-gray-500">Due {formatDate(t.due_date)}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader title="Support tickets" />
                <ul className="divide-y divide-gray-100">
                    {(tickets ?? []).length === 0 && <li className="px-5 py-8 text-center text-sm text-gray-500">No support tickets.</li>}
                    {(tickets ?? []).map((t: any) => (
                        <li key={t.id} className="flex items-center justify-between px-5 py-3">
                            <div>
                                <p className="text-sm font-medium text-gray-900">{t.title}</p>
                                <p className="text-xs text-gray-500">{t.ticket_number} · {t.category} · {t.priority}</p>
                            </div>
                            <Badge color={statusColor(t.status)}>{t.status}</Badge>
                        </li>
                    ))}
                </ul>
            </Card>
        </AppLayout>
    );
}
