import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Form';
import { formatMoney, formatDate } from '@/types/app';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function ReceiptsIndex({ receipts, filters }: any) {
    const [search, setSearch] = useState(filters?.search ?? '');

    const columns: Column<any>[] = [
        {
            key: 'receipt_number',
            header: 'Receipt',
            render: (row) => (
                <Link href={`/receipts/${row.id}`} className="font-semibold text-green-700 hover:underline">
                    {row.receipt_number}
                </Link>
            ),
        },
        {
            key: 'requisition',
            header: 'Requisition',
            render: (row) => row.requisition?.requisition_number ?? '—',
        },
        { key: 'payee_name', header: 'Payee' },
        { key: 'amount', header: 'Amount', className: 'text-right', render: (row) => formatMoney(row.amount, row.currency) },
        { key: 'remaining_balance', header: 'Remaining', className: 'text-right', render: (row) => formatMoney(row.remaining_balance, row.currency) },
        { key: 'payment_date', header: 'Payment date', render: (row) => formatDate(row.payment_date) },
        {
            key: 'delivery_status',
            header: 'Delivery',
            render: (row) => <Badge color={statusColor(row.delivery_status)}>{row.delivery_status}</Badge>,
        },
        {
            key: 'actions',
            header: '',
            render: (row) => (
                <a href={`/receipts/${row.id}/pdf`} className="inline-flex items-center gap-1 text-sm font-semibold text-green-700 hover:underline">
                    PDF
                </a>
            ),
        },
    ];

    return (
        <AppLayout
            title="Payment Receipts"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Payment Receipts' }]}
        >
            <Head title="Payment Receipts" />

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Search">
                        <TextInput
                            placeholder="Receipt number or payee…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && router.get('/receipts', { search }, { preserveState: true })}
                        />
                    </Field>
                    <div className="flex items-end">
                        <Button variant="secondary" onClick={() => router.get('/receipts', { search }, { preserveState: true })}>Search</Button>
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <DataTable columns={columns} rows={receipts?.data ?? []} keyExtractor={(row) => row.id} emptyMessage="No receipts generated yet." />
                <Pagination meta={receipts} onGoTo={(page) => router.get('/receipts', { search, page }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
