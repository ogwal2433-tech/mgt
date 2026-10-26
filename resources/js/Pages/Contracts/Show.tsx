import AppLayout from '@/Layouts/AppLayout';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import { Field, TextArea, TextInput } from '@/Components/ui/Form';
import { formatDate, formatDateTime, formatMoney } from '@/types/app';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ContractShow({ contract, canManage }: any) {
    const [showVersion, setShowVersion] = useState(false);
    const versionForm = useForm({ change_summary: '', contract_file: null as File | null });

    return (
        <AppLayout
            title={contract.contract_number}
            breadcrumb={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Contracts', href: '/contracts' },
                { label: contract.contract_number },
            ]}
            actions={canManage ? <Button onClick={() => setShowVersion(true)}>Add new version</Button> : null}
        >
            <Head title={contract.contract_number} />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader
                            title={<span className="flex items-center gap-3">{contract.contract_number} <Badge color={statusColor(contract.status)}>{contract.status}</Badge></span>}
                            subtitle={`${contract.employee?.full_name ?? '—'} · ${contract.contract_type?.replaceAll('_', ' ')}`}
                        />
                        <CardBody>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Item label="Employee" value={`${contract.employee?.employee_code ?? ''} ${contract.employee?.full_name ?? ''}`} />
                                <Item label="Department" value={contract.department?.name} />
                                <Item label="Job title" value={contract.job_title} />
                                <Item label="Working hours" value={contract.working_hours} />
                                <Item label="Start date" value={formatDate(contract.start_date)} />
                                <Item label="End date" value={formatDate(contract.end_date)} />
                                <Item label="Probation ends" value={formatDate(contract.probation_end_date)} />
                                <Item label="Renewal date" value={formatDate(contract.renewal_date)} />
                                <Item label="Salary" value={contract.salary ? formatMoney(contract.salary, contract.currency) : '—'} />
                                <Item label="Signature status" value={contract.signature_status} />
                            </div>
                            {contract.notes && (
                                <div className="mt-4">
                                    <p className="text-xs font-semibold uppercase text-gray-500">Notes</p>
                                    <p className="mt-1 whitespace-pre-line text-sm text-gray-800">{contract.notes}</p>
                                </div>
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title="Document versions" subtitle="Every version is preserved — signed contracts are never overwritten" />
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-100 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Version</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Label</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Summary</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Uploaded</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">File</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {(contract.versions ?? []).length === 0 && (
                                        <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-500">No documents uploaded yet.</td></tr>
                                    )}
                                    {(contract.versions ?? []).map((v: any) => (
                                        <tr key={v.id}>
                                            <td className="px-4 py-2 font-medium text-gray-900">v{v.version_number}</td>
                                            <td className="px-4 py-2">{v.file_label ?? '—'}</td>
                                            <td className="px-4 py-2 text-gray-600">{v.change_summary}</td>
                                            <td className="px-4 py-2 text-gray-600">{formatDateTime(v.created_at)}</td>
                                            <td className="px-4 py-2">
                                                {v.document ? (
                                                    <a href={`/documents/${v.document.uuid}/download`} className="font-semibold text-green-700 hover:underline">
                                                        Download
                                                    </a>
                                                ) : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                <div>
                    <Card>
                        <CardHeader title="Expiry reminders" />
                        <CardBody>
                            {(contract.reminders ?? []).length === 0 && <p className="text-sm text-gray-500">No reminders scheduled.</p>}
                            <ul className="space-y-2">
                                {(contract.reminders ?? []).map((reminder: any) => (
                                    <li key={reminder.id} className="flex items-center justify-between rounded-md border border-gray-100 px-3 py-2 text-sm">
                                        <span>{formatDate(reminder.remind_on)}</span>
                                        <Badge color={statusColor(reminder.status)}>{reminder.status}</Badge>
                                    </li>
                                ))}
                            </ul>
                        </CardBody>
                    </Card>
                </div>
            </div>

            {showVersion && (
                <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 pt-10">
                    <div className="w-full max-w-lg rounded-lg bg-white shadow-xl">
                        <div className="border-b border-gray-100 px-5 py-4">
                            <h2 className="text-lg font-semibold text-gray-900">Add contract version</h2>
                        </div>
                        <div className="space-y-4 px-5 py-4">
                            <Field label="Change summary" required>
                                <TextArea rows={3} value={versionForm.data.change_summary} onChange={(e) => versionForm.setData('change_summary', e.target.value)} />
                            </Field>
                            <Field label="Contract file" required>
                                <input type="file" accept=".pdf,.doc,.docx" onChange={(e) => versionForm.setData('contract_file', e.target.files?.[0] ?? null)} className="block w-full text-sm text-gray-600" />
                            </Field>
                            <p className="text-xs text-gray-500">Previous versions are retained for audit and reference.</p>
                        </div>
                        <div className="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                            <Button variant="secondary" onClick={() => setShowVersion(false)}>Cancel</Button>
                            <Button
                                loading={versionForm.processing}
                                onClick={() => {
                                    const fd = new FormData();
                                    fd.append('change_summary', versionForm.data.change_summary);
                                    if (versionForm.data.contract_file) fd.append('contract_file', versionForm.data.contract_file);
                                    versionForm.post(`/contracts/${contract.id}/versions`, { data: fd, forceFormData: true, onSuccess: () => setShowVersion(false) } as any);
                                }}
                            >
                                Upload version
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

function Item({ label, value }: { label: string; value: any }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase text-gray-500">{label}</p>
            <p className="mt-0.5 text-sm text-gray-900">{value ?? '—'}</p>
        </div>
    );
}
