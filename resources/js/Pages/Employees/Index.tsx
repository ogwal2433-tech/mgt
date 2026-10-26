import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, SelectInput, TextInput } from '@/Components/ui/Form';
import { formatDate } from '@/types/app';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function EmployeesIndex({ employees, filters, departments, positions, canManage }: any) {
    const [search, setSearch] = useState(filters?.search ?? '');
    const [showCreate, setShowCreate] = useState(false);
    const form = useForm({
        first_name: '', last_name: '', other_names: '', email: '', phone: '',
        department_id: '', position_id: '', employment_status: 'active', employment_type: 'full_time',
        hire_date: new Date().toISOString().slice(0, 10), job_title: '', gender: '',
    });

    const columns: Column<any>[] = [
        {
            key: 'employee_code',
            header: 'Employee ID',
            render: (row) => (
                <Link href={`/employees/${row.id}`} className="font-semibold text-green-700 hover:underline">{row.employee_code}</Link>
            ),
        },
        { key: 'name', header: 'Name', render: (row) => <span className="font-medium text-gray-900">{row.full_name}</span> },
        { key: 'job_title', header: 'Job title' },
        { key: 'department', header: 'Department', render: (row) => row.department?.name ?? '—' },
        { key: 'email', header: 'Email', render: (row) => row.email ?? '—' },
        {
            key: 'employment_status',
            header: 'Status',
            render: (row) => <Badge color={statusColor(row.employment_status)}>{row.employment_status}</Badge>,
        },
        { key: 'hire_date', header: 'Hired', render: (row) => formatDate(row.hire_date) },
        {
            key: 'account',
            header: 'Account',
            render: (row) => (row.user ? <Badge color={row.user.is_active ? 'green' : 'red'}>{row.user.is_active ? 'Active' : 'Inactive'}</Badge> : <span className="text-xs text-gray-400">No account</span>),
        },
    ];

    return (
        <AppLayout
            title="Employees"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Employees' }]}
            actions={canManage ? <Button onClick={() => setShowCreate(true)}>Register employee</Button> : null}
        >
            <Head title="Employees" />

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <Field label="Search">
                        <TextInput
                            placeholder="Name, employee ID, email…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && router.get('/employees', { search }, { preserveState: true })}
                        />
                    </Field>
                    <Field label="Department">
                        <SelectInput value={filters?.department_id ?? ''} onChange={(e) => router.get('/employees', { search, department_id: e.target.value }, { preserveState: true })}>
                            <option value="">All departments</option>
                            {(departments ?? []).map((d: any) => <option key={d.id} value={d.id}>{d.name}</option>)}
                        </SelectInput>
                    </Field>
                    <Field label="Status">
                        <SelectInput value={filters?.status ?? ''} onChange={(e) => router.get('/employees', { search, status: e.target.value }, { preserveState: true })}>
                            <option value="">All</option>
                            {['active', 'probation', 'suspended', 'terminated', 'resigned'].map((s) => <option key={s} value={s}>{s}</option>)}
                        </SelectInput>
                    </Field>
                    <div className="flex items-end">
                        <Button variant="secondary" onClick={() => router.get('/employees', { search }, { preserveState: true })}>Search</Button>
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <DataTable columns={columns} rows={employees?.data ?? []} keyExtractor={(row) => row.id} emptyMessage="No employees found." />
                <Pagination meta={employees} onGoTo={(page) => router.get('/employees', { search, page }, { preserveState: true })} />
            </div>

            <Modal
                open={showCreate}
                onClose={() => setShowCreate(false)}
                title="Register employee"
                size="lg"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowCreate(false)}>Cancel</Button>
                        <Button
                            loading={form.processing}
                            onClick={() => form.post('/employees', { onSuccess: () => setShowCreate(false) })}
                        >
                            Register
                        </Button>
                    </>
                }
            >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="First name" required><TextInput value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} /></Field>
                    <Field label="Last name" required><TextInput value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} /></Field>
                    <Field label="Other names"><TextInput value={form.data.other_names} onChange={(e) => form.setData('other_names', e.target.value)} /></Field>
                    <Field label="Email"><TextInput type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></Field>
                    <Field label="Phone"><TextInput value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} /></Field>
                    <Field label="Job title"><TextInput value={form.data.job_title} onChange={(e) => form.setData('job_title', e.target.value)} /></Field>
                    <Field label="Department">
                        <SelectInput value={form.data.department_id} onChange={(e) => form.setData('department_id', e.target.value)}>
                            <option value="">Select</option>
                            {(departments ?? []).map((d: any) => <option key={d.id} value={d.id}>{d.name}</option>)}
                        </SelectInput>
                    </Field>
                    <Field label="Position">
                        <SelectInput value={form.data.position_id} onChange={(e) => form.setData('position_id', e.target.value)}>
                            <option value="">Select</option>
                            {(positions ?? []).map((p: any) => <option key={p.id} value={p.id}>{p.title}</option>)}
                        </SelectInput>
                    </Field>
                    <Field label="Employment status">
                        <SelectInput value={form.data.employment_status} onChange={(e) => form.setData('employment_status', e.target.value)}>
                            {['active', 'probation', 'suspended', 'terminated', 'resigned'].map((s) => <option key={s} value={s}>{s}</option>)}
                        </SelectInput>
                    </Field>
                    <Field label="Employment type">
                        <SelectInput value={form.data.employment_type} onChange={(e) => form.setData('employment_type', e.target.value)}>
                            {['full_time', 'part_time', 'contract', 'intern'].map((s) => <option key={s} value={s}>{s.replaceAll('_', ' ')}</option>)}
                        </SelectInput>
                    </Field>
                    <Field label="Hire date"><TextInput type="date" value={form.data.hire_date} onChange={(e) => form.setData('hire_date', e.target.value)} /></Field>
                </div>
                <p className="mt-3 text-xs text-gray-500">A unique employee ID (EMP-0001 …) is generated automatically.</p>
            </Modal>
        </AppLayout>
    );
}
