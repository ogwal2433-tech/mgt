import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import { Field, SelectInput, TextInput } from '@/Components/ui/Form';
import { formatDateTime } from '@/types/app';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function AuditIndex({ logs, filters, actions }: any) {
    const [search, setSearch] = useState(filters?.search ?? '');
    const [action, setAction] = useState(filters?.action ?? '');

    return (
        <AppLayout title="Audit Logs" breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Audit Logs' }]}>
            <Head title="Audit Logs" />

            <div className="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                Audit records are append-only. They cannot be edited or deleted through the application, and financial corrections are
                recorded as reversals rather than overwrites.
            </div>

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <Field label="Search">
                        <TextInput
                            placeholder="Action, user, description…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && router.get('/audit', { search, action }, { preserveState: true })}
                        />
                    </Field>
                    <Field label="Action">
                        <SelectInput value={action} onChange={(e) => { setAction(e.target.value); router.get('/audit', { search, action: e.target.value }, { preserveState: true }); }}>
                            <option value="">All actions</option>
                            {(actions ?? []).map((a: string) => <option key={a} value={a}>{a}</option>)}
                        </SelectInput>
                    </Field>
                    <div className="flex items-end gap-2">
                        <Button variant="secondary" onClick={() => router.get('/audit', { search, action }, { preserveState: true })}>Filter</Button>
                        <Button variant="ghost" onClick={() => { setSearch(''); setAction(''); router.get('/audit'); }}>Reset</Button>
                    </div>
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table className="min-w-full divide-y divide-gray-100 text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Timestamp</th>
                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">User</th>
                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Action</th>
                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Entity</th>
                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Description</th>
                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">IP</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {(logs?.data ?? []).length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-12 text-center text-gray-500">No audit records found.</td></tr>
                        )}
                        {(logs?.data ?? []).map((log: any) => (
                            <tr key={log.id} className="hover:bg-green-50/40">
                                <td className="px-4 py-2 text-gray-600">{formatDateTime(log.created_at)}</td>
                                <td className="px-4 py-2 font-medium text-gray-900">{log.user_name ?? 'system'}</td>
                                <td className="px-4 py-2"><span className="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700">{log.action}</span></td>
                                <td className="px-4 py-2 text-gray-600">{log.entity_type ? `${log.entity_type.split('\\').pop()}#${log.entity_id}` : '—'}</td>
                                <td className="px-4 py-2 text-gray-800">{log.description ?? '—'}</td>
                                <td className="px-4 py-2 text-gray-500">{log.ip_address ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination meta={logs} onGoTo={(page) => router.get('/audit', { search, action, page }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
