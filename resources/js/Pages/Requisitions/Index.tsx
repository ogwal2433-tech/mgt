import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import { Field, SelectInput, TextInput } from '@/Components/ui/Form';
import Button from '@/Components/ui/Button';
import { formatMoney, formatDate } from '@/types/app';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function RequisitionIndex({ requisitions, filters, categories, statusOptions, canCreate }: any) {
    const [search, setSearch] = useState(filters?.search ?? '');
    const [status, setStatus] = useState(filters?.status ?? '');
    const [categoryId, setCategoryId] = useState(filters?.category_id ?? '');

    const apply = (extra: Record<string, any> = {}) => {
        router.get('/requisitions', {
            search,
            status,
            category_id: categoryId,
            ...extra,
        }, { preserveState: true, preserveScroll: true });
    };

    const columns: Column<any>[] = [
        {
            key: 'requisition_number',
            header: 'Number',
            render: (row) => (
                <Link href={`/requisitions/${row.id}`} className="font-semibold text-green-700 hover:underline">
                    {row.requisition_number ?? '—'}
                </Link>
            ),
        },
        { key: 'title', header: 'Title', render: (row) => <span className="font-medium text-gray-900">{row.title}</span> },
        {
            key: 'requester',
            header: 'Requester',
            render: (row) => row.requester_employee?.full_name ?? '—',
        },
        { key: 'category', header: 'Category', render: (row) => row.category?.name ?? '—' },
        {
            key: 'requested_amount',
            header: 'Amount',
            className: 'text-right',
            render: (row) => formatMoney(row.requested_amount, row.currency),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <Badge color={statusColor(row.status)}>{row.status_label ?? row.status}</Badge>,
        },
        { key: 'created_at', header: 'Created', render: (row) => formatDate(row.created_at) },
        {
            key: 'actions',
            header: '',
            render: (row) => (
                <Link href={`/requisitions/${row.id}`} className="text-sm font-semibold text-green-700 hover:underline">
                    Open
                </Link>
            ),
        },
    ];

    return (
        <AppLayout
            title="Internal Requisitions"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Internal Requisitions' }]}
            actions={canCreate ? (
                <Link href="/requisitions/create">
                    <Button>New requisition</Button>
                </Link>
            ) : null}
        >
            <Head title="Internal Requisitions" />

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Search">
                        <TextInput
                            placeholder="Number, title, purpose…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && apply()}
                        />
                    </Field>
                    <Field label="Status">
                        <SelectInput value={status} onChange={(e) => { setStatus(e.target.value); apply({ status: e.target.value }); }}>
                            <option value="">All statuses</option>
                            {Object.entries(statusOptions ?? {}).map(([value, label]) => (
                                <option key={value} value={value}>{label as string}</option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label="Category">
                        <SelectInput value={categoryId} onChange={(e) => { setCategoryId(e.target.value); apply({ category_id: e.target.value }); }}>
                            <option value="">All categories</option>
                            {(categories ?? []).map((c: any) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </SelectInput>
                    </Field>
                    <div className="flex items-end gap-2">
                        <Button variant="secondary" onClick={() => apply()}>Apply</Button>
                        <Button variant="ghost" onClick={() => { setSearch(''); setStatus(''); setCategoryId(''); router.get('/requisitions'); }}>Reset</Button>
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <DataTable columns={columns} rows={requisitions?.data ?? []} keyExtractor={(row) => row.id} emptyMessage="No requisitions found." />
                <Pagination meta={requisitions} onGoTo={(page) => router.get('/requisitions', { search, status, category_id: categoryId, page }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
