import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, SelectInput, TextArea, TextInput } from '@/Components/ui/Form';
import { Card, CardHeader, CardBody } from '@/Components/ui/Card';
import { formatDate, formatMoney } from '@/types/app';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ContractsIndex({ contracts, expiring, filters, canManage, employees, departments }: any) {
    const [search, setSearch] = useState(filters?.search ?? '');
    const [showCreate, setShowCreate] = useState(false);
    const form = useForm({
        employee_id: '',
        contract_type: 'permanent',
        job_title: '',
        department_id: '',
        start_date: new Date().toISOString().slice(0, 10),
        end_date: '',
        probation_end_date: '',
        renewal_date: '',
        status: 'draft',
        salary: '',
        currency: 'UGX',
        working_hours: '',
        signature_status: 'pending',
        notes: '',
        contract_file: null as File | null,
    });

    const columns: Column<any>[] = [
        {
            key: 'contract_number',
            header: 'Contract',
            render: (row) => (
                <Link href={`/contracts/${row.id}`} className="font-semibold text-green-700 hover:underline">{row.contract_number}</Link>
            ),
        },
        { key: 'employee', header: 'Employee', render: (row) => row.employee?.full_name ?? '—' },
        { key: 'contract_type', header: 'Type', render: (row) => row.contract_type?.replaceAll('_', ' ') },
        { key: 'job_title', header: 'Job title' },
        { key: 'start_date', header: 'Start', render: (row) => formatDate(row.start_date) },
        { key: 'end_date', header: 'End', render: (row) => formatDate(row.end_date) },
        { key: 'status', header: 'Status', render: (row) => <Badge color={statusColor(row.status)}>{row.status}</Badge> },
        {
            key: 'actions',
            header: '',
            render: (row) => <Link href={`/contracts/${row.id}`} className="text-sm font-semibold text-green-700 hover:underline">Open</Link>,
        },
    ];

    return (
        <AppLayout
            title="Employment Contracts"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Employment Contracts' }]}
            actions={canManage ? <Button onClick={() => setShowCreate(true)}>Upload contract</Button> : null}
        >
            <Head title="Employment Contracts" />

            {(expiring ?? []).length > 0 && (
                <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p className="text-sm font-semibold text-amber-900">Contracts approaching expiry (90 days)</p>
                    <ul className="mt-2 space-y-1 text-sm text-amber-800">
                        {expiring.map((c: any) => (
                            <li key={c.id} className="flex justify-between">
                                <span>{c.contract_number} — {c.employee?.full_name}</span>
                                <span>Ends {formatDate(c.end_date)}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Search">
                        <TextInput
                            placeholder="Contract number, employee, job title…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && router.get('/contracts', { search }, { preserveState: true })}
                        />
                    </Field>
                    <div className="flex items-end">
                        <Button variant="secondary" onClick={() => router.get('/contracts', { search }, { preserveState: true })}>Search</Button>
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <DataTable columns={columns} rows={contracts?.data ?? []} keyExtractor={(row) => row.id} emptyMessage="No contracts found." />
                <Pagination meta={contracts} onGoTo={(page) => router.get('/contracts', { search, page }, { preserveState: true })} />
            </div>

            <Modal
                open={showCreate}
                onClose={() => setShowCreate(false)}
                title="Upload employment contract"
                size="lg"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowCreate(false)}>Cancel</Button>
                        <Button
                            loading={form.processing}
                            onClick={() => {
                                const fd = new FormData();
                                (Object.keys(form.data) as (keyof typeof form.data)[]).forEach((key) => {
                                    const value = form.data[key];
                                    if (value === null || value === '') return;
                                    if (value instanceof File) fd.append(key, value);
                                    else fd.append(key, String(value));
                                });
                                form.post('/contracts', { data: fd, forceFormData: true, onSuccess: () => setShowCreate(false) } as any);
                            }}
                        >
                            Save contract
                        </Button>
                    </>
                }
            >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Employee" required>
                        <SelectInput value={form.data.employee_id} onChange={(e) => form.setData('employee_id', e.target.value)}>
                            <option value="">Select employee</option>
                            {(employees ?? []).map((emp: any) => (
                                <option key={emp.id} value={emp.id}>{emp.employee_code} — {emp.first_name} {emp.last_name}</option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label="Contract type" required>
                        <SelectInput value={form.data.contract_type} onChange={(e) => form.setData('contract_type', e.target.value)}>
                            {['permanent', 'fixed_term', 'probation', 'consulting', 'internship'].map((t) => (
                                <option key={t} value={t}>{t.replaceAll('_', ' ')}</option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label="Job title">
                        <TextInput value={form.data.job_title} onChange={(e) => form.setData('job_title', e.target.value)} />
                    </Field>
                    <Field label="Department">
                        <SelectInput value={form.data.department_id} onChange={(e) => form.setData('department_id', e.target.value)}>
                            <option value="">None</option>
                            {(departments ?? []).map((d: any) => <option key={d.id} value={d.id}>{d.name}</option>)}
                        </SelectInput>
                    </Field>
                    <Field label="Start date" required>
                        <TextInput type="date" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                    </Field>
                    <Field label="End date">
                        <TextInput type="date" value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} />
                    </Field>
                    <Field label="Probation end">
                        <TextInput type="date" value={form.data.probation_end_date} onChange={(e) => form.setData('probation_end_date', e.target.value)} />
                    </Field>
                    <Field label="Renewal date">
                        <TextInput type="date" value={form.data.renewal_date} onChange={(e) => form.setData('renewal_date', e.target.value)} />
                    </Field>
                    <Field label="Status">
                        <SelectInput value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                            {['draft', 'pending_signature', 'active', 'expiring', 'expired', 'renewed', 'terminated'].map((s) => (
                                <option key={s} value={s}>{s.replaceAll('_', ' ')}</option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label="Salary">
                        <TextInput type="number" step="0.01" value={form.data.salary} onChange={(e) => form.setData('salary', e.target.value)} />
                    </Field>
                    <Field label="Signed contract file">
                        <input type="file" accept=".pdf,.doc,.docx" onChange={(e) => form.setData('contract_file', e.target.files?.[0] ?? null)} className="block w-full text-sm text-gray-600" />
                    </Field>
                    <Field label="Notes" className="sm:col-span-2">
                        <TextArea rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </Field>
                </div>
                <p className="mt-3 text-xs text-gray-500">
                    Contracts are stored securely and versioned. Signed contracts are never overwritten; each upload creates a new version.
                </p>
            </Modal>
        </AppLayout>
    );
}
