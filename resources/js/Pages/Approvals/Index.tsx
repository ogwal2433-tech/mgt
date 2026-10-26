import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import { Card, CardHeader } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Form';
import { formatMoney, formatDate } from '@/types/app';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function ApprovalsIndex({ queue, decided, filters }: any) {
    const [search, setSearch] = useState(filters?.search ?? '');

    const columns: Column<any>[] = [
        {
            key: 'requisition_number',
            header: 'Number',
            render: (row) => (
                <Link href={`/requisitions/${row.id}`} className="font-semibold text-green-700 hover:underline">
                    {row.requisition_number}
                </Link>
            ),
        },
        { key: 'title', header: 'Title', render: (row) => <span className="font-medium text-gray-900">{row.title}</span> },
        {
            key: 'requester',
            header: 'Requester',
            render: (row) => row.requester_employee?.full_name ?? '—',
        },
        { key: 'department', header: 'Department', render: (row) => row.department?.name ?? '—' },
        {
            key: 'requested_amount',
            header: 'Amount',
            className: 'text-right',
            render: (row) => formatMoney(row.requested_amount, row.currency),
        },
        {
            key: 'status',
            header: 'Awaiting',
            render: (row) => <Badge color={statusColor(row.status)}>{row.status_label ?? row.status}</Badge>,
        },
        { key: 'required_date', header: 'Required by', render: (row) => formatDate(row.required_date) },
        {
            key: 'actions',
            header: '',
            render: (row) => (
                <Link href={`/requisitions/${row.id}`}>
                    <Button size="sm">Review</Button>
                </Link>
            ),
        },
    ];

    return (
        <AppLayout
            title="Approval Center"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Approval Center' }]}
        >
            <Head title="Approval Center" />

            <div className="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                <strong>Mandatory sequence:</strong> Technical Lead → HR Review → Managing Director → HR Disbursement → Receipt → Accountability.
                You cannot approve your own requisition, and every decision is recorded in the permanent approval history.
            </div>

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Search">
                        <TextInput
                            placeholder="Number or title…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && router.get('/approvals', { search }, { preserveState: true })}
                        />
                    </Field>
                    <div className="flex items-end gap-2">
                        <Button variant="secondary" onClick={() => router.get('/approvals', { search }, { preserveState: true })}>Search</Button>
                        <Button variant="ghost" onClick={() => { setSearch(''); router.get('/approvals'); }}>Reset</Button>
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <CardHeader title="Awaiting your decision" subtitle="Requisitions you are authorised to act on" />
                <DataTable columns={columns} rows={queue?.data ?? []} keyExtractor={(row) => row.id} emptyMessage="Nothing is awaiting your decision." />
                <Pagination meta={queue} onGoTo={(page) => router.get('/approvals', { search, page }, { preserveState: true })} />
            </div>

            <div className="mt-6 rounded-lg border border-gray-200 bg-white shadow-sm">
                <CardHeader title="Your recent decisions" />
                <DataTable
                    columns={[
                        { key: 'requisition_number', header: 'Number', render: (row: any) => <Link href={`/requisitions/${row.id}`} className="font-semibold text-green-700 hover:underline">{row.requisition_number}</Link> },
                        { key: 'title', header: 'Title' },
                        { key: 'requester', header: 'Requester' },
                        { key: 'requested_amount', header: 'Amount', className: 'text-right', render: (row: any) => formatMoney(row.requested_amount, row.currency) },
                        { key: 'status', header: 'Status', render: (row: any) => <Badge color={statusColor(row.status)}>{row.status_label}</Badge> },
                    ]}
                    rows={decided ?? []}
                    keyExtractor={(row: any) => row.id}
                    emptyMessage="You have not taken any approval decisions yet."
                />
            </div>
        </AppLayout>
    );
}
