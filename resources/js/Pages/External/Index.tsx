import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Form';
import { formatMoney, formatDate } from '@/types/app';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function ExternalIndex({ requisitions, filters, canManage }: any) {
    const [search, setSearch] = useState(filters?.search ?? '');

    const columns: Column<any>[] = [
        {
            key: 'requisition_number',
            header: 'Number',
            render: (row) => (
                <Link href={`/external-requisitions/${row.id}`} className="font-semibold text-green-700 hover:underline">
                    {row.requisition_number}
                </Link>
            ),
        },
        { key: 'external_party_name', header: 'External party' },
        { key: 'purpose', header: 'Purpose', render: (row) => <span className="line-clamp-2 max-w-xs">{row.purpose}</span> },
        { key: 'total_amount', header: 'Amount', className: 'text-right', render: (row) => formatMoney(row.total_amount, row.currency) },
        { key: 'date', header: 'Date', render: (row) => formatDate(row.date) },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <Badge color={row.is_approved ? 'green' : statusColor(row.status)}>{row.status}</Badge>,
        },
        {
            key: 'actions',
            header: '',
            render: (row) => (
                <a href={`/external-requisitions/${row.id}/pdf`} className="text-sm font-semibold text-green-700 hover:underline">PDF</a>
            ),
        },
    ];

    return (
        <AppLayout
            title="External Requisitions"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'External Requisitions' }]}
            actions={canManage ? (
                <Link href="/external-requisitions/create"><Button>New external requisition</Button></Link>
            ) : null}
        >
            <Head title="External Requisitions" />

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Search">
                        <TextInput
                            placeholder="Number, party, purpose…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && router.get('/external-requisitions', { search }, { preserveState: true })}
                        />
                    </Field>
                    <div className="flex items-end">
                        <Button variant="secondary" onClick={() => router.get('/external-requisitions', { search }, { preserveState: true })}>Search</Button>
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <DataTable columns={columns} rows={requisitions?.data ?? []} keyExtractor={(row) => row.id} emptyMessage="No external requisitions found." />
                <Pagination meta={requisitions} onGoTo={(page) => router.get('/external-requisitions', { search, page }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
