import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/ui/Pagination';
import Badge, { statusColor } from '@/Components/ui/Badge';
import { Field, TextInput } from '@/Components/ui/Form';
import Button from '@/Components/ui/Button';
import { formatDate } from '@/types/app';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function OperationsProjects({ projects, tasks, filters, canManage }: any) {
    const [search, setSearch] = useState(filters?.search ?? '');

    return (
        <AppLayout title="Projects & Tasks" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Projects & Tasks' }]}>
            <Head title="Projects & Tasks" />

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Search projects">
                        <TextInput value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && router.get('/projects', { search }, { preserveState: true })} />
                    </Field>
                    <div className="flex items-end">
                        <Button variant="secondary" onClick={() => router.get('/projects', { search }, { preserveState: true })}>Search</Button>
                    </div>
                </div>
            </div>

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {(projects?.data ?? []).map((p: any) => (
                    <div key={p.id} className="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase text-gray-500">{p.project_code}</p>
                                <h3 className="mt-1 text-base font-semibold text-gray-900">{p.name}</h3>
                            </div>
                            <Badge color={statusColor(p.status)}>{p.status}</Badge>
                        </div>
                        <p className="mt-2 line-clamp-2 text-sm text-gray-600">{p.description ?? 'No description.'}</p>
                        <div className="mt-4">
                            <div className="flex items-center justify-between text-xs text-gray-500">
                                <span>Progress</span>
                                <span>{p.progress_percentage}%</span>
                            </div>
                            <div className="mt-1 h-2 w-full rounded-full bg-gray-100">
                                <div className="h-2 rounded-full bg-green-600" style={{ width: `${p.progress_percentage}%` }} />
                            </div>
                        </div>
                        <div className="mt-4 space-y-1 text-xs text-gray-500">
                            <p>Lead: <span className="text-gray-800">{p.lead?.full_name ?? '—'}</span></p>
                            <p>Target: <span className="text-gray-800">{formatDate(p.target_completion_date)}</span></p>
                        </div>
                    </div>
                ))}
            </div>

            <Pagination meta={projects} onGoTo={(page) => router.get('/projects', { search, page }, { preserveState: true })} />

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-100 px-5 py-4">
                    <h3 className="text-base font-semibold text-gray-900">Tasks</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Code</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Assignee</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Due</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {(tasks ?? []).length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-12 text-center text-gray-500">No tasks found.</td></tr>
                            )}
                            {(tasks ?? []).map((t: any) => (
                                <tr key={t.id} className="hover:bg-green-50/40">
                                    <td className="px-4 py-2 font-mono text-xs text-gray-600">{t.task_code}</td>
                                    <td className="px-4 py-2 font-medium text-gray-900">{t.title}</td>
                                    <td className="px-4 py-2">{t.assignee?.full_name ?? '—'}</td>
                                    <td className="px-4 py-2">{t.project?.name ?? '—'}</td>
                                    <td className="px-4 py-2">{formatDate(t.due_date)}</td>
                                    <td className="px-4 py-2"><Badge color={statusColor(t.status)}>{t.status}</Badge></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
