import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import { formatMoney } from '@/types/app';
import { Head, Link, router } from '@inertiajs/react';

export default function Disbursements({ requisitions, canDisburse }: any) {
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
        { key: 'requester', header: 'Requester' },
        { key: 'department', header: 'Department' },
        { key: 'approved_amount', header: 'Approved', className: 'text-right', render: (row) => formatMoney(row.approved_amount, row.currency) },
        { key: 'amount_paid', header: 'Paid', className: 'text-right', render: (row) => formatMoney(row.amount_paid, row.currency) },
        {
            key: 'remaining',
            header: 'Remaining',
            className: 'text-right',
            render: (row) => <span className="font-semibold text-gray-900">{formatMoney(row.remaining, row.currency)}</span>,
        },
        { key: 'status', header: 'Status', render: (row) => <Badge color={statusColor(row.status)}>{row.status_label}</Badge> },
        {
            key: 'actions',
            header: '',
            render: (row) => (
                <Link href={`/requisitions/${row.id}`}>
                    <Button size="sm" variant={canDisburse ? 'primary' : 'secondary'}>
                        {canDisburse ? 'Disburse' : 'View'}
                    </Button>
                </Link>
            ),
        },
    ];

    return (
        <AppLayout
            title="Disbursements"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Disbursements' }]}
        >
            <Head title="Disbursements" />

            <div className="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                Only requisitions with completed <strong>Technical Lead</strong>, <strong>HR</strong> and <strong>MD</strong> approvals appear
                here. Payments cannot exceed the remaining approved amount, and rejected requisitions cannot be paid.
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <DataTable
                    columns={columns}
                    rows={requisitions?.data ?? []}
                    keyExtractor={(row) => row.id}
                    emptyMessage="No requisitions are awaiting disbursement."
                />
                <Pagination meta={requisitions} onGoTo={(page) => router.get('/disbursements', { page }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
