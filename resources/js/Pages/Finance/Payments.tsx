import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Form';
import { formatMoney, formatDate } from '@/types/app';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function PaymentsIndex({ payments, filters }: any) {
    const [search, setSearch] = useState(filters?.search ?? '');

    const columns: Column<any>[] = [
        { key: 'payment_number', header: 'Payment', render: (row) => <span className="font-semibold text-gray-900">{row.payment_number}</span> },
        {
            key: 'requisition',
            header: 'Requisition',
            render: (row) => (
                <Link href={`/requisitions/${row.requisition?.id}`} className="text-green-700 hover:underline">
                    {row.requisition?.requisition_number}
                </Link>
            ),
        },
        { key: 'payee_name', header: 'Payee' },
        { key: 'payment_method', header: 'Method', render: (row) => row.payment_method?.replaceAll('_', ' ') },
        { key: 'transaction_reference', header: 'Reference', render: (row) => row.transaction_reference ?? '—' },
        { key: 'amount', header: 'Amount', className: 'text-right', render: (row) => formatMoney(row.amount, row.requisition?.currency) },
        { key: 'payment_date', header: 'Date', render: (row) => formatDate(row.payment_date) },
        { key: 'status', header: 'Status', render: (row) => <Badge color={statusColor(row.status)}>{row.status}</Badge> },
    ];

    return (
        <AppLayout
            title="Finance & Accounts"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Finance & Accounts' }]}
        >
            <Head title="Finance & Accounts" />

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Search">
                        <TextInput
                            placeholder="Payment number, payee, reference…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && router.get('/finance', { search }, { preserveState: true })}
                        />
                    </Field>
                    <div className="flex items-end gap-2">
                        <Button variant="secondary" onClick={() => router.get('/finance', { search }, { preserveState: true })}>Search</Button>
                        <Link href="/disbursements"><Button variant="ghost">Disbursement queue</Button></Link>
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <DataTable columns={columns} rows={payments?.data ?? []} keyExtractor={(row) => row.id} emptyMessage="No payments recorded yet." />
                <Pagination meta={payments} onGoTo={(page) => router.get('/finance', { search, page }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
