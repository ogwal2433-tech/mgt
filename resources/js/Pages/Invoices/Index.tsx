import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Form';
import { formatMoney, formatDate } from '@/types/app';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function InvoiceIndex({ invoices, summary, filters, canManage }: any) {
    const [search, setSearch] = useState(filters?.search ?? '');

    const columns: Column<any>[] = [
        {
            key: 'invoice_number',
            header: 'Invoice',
            render: (row) => <Link href={`/invoices/${row.id}`} className="font-semibold text-brand-700 hover:underline">{row.invoice_number}</Link>,
        },
        { key: 'client_name', header: 'Client', render: (row) => <span className="font-medium text-ink-900">{row.client_name}</span> },
        { key: 'project', header: 'Project', render: (row) => row.project?.name ?? '—' },
        { key: 'issue_date', header: 'Issued', render: (row) => formatDate(row.issue_date) },
        { key: 'due_date', header: 'Due', render: (row) => formatDate(row.due_date) },
        { key: 'total_amount', header: 'Total', className: 'text-right', render: (row) => formatMoney(row.total_amount, row.currency) },
        { key: 'amount_due', header: 'Balance', className: 'text-right', render: (row) => <span className="font-semibold">{formatMoney(row.amount_due, row.currency)}</span> },
        { key: 'status', header: 'Status', render: (row) => <Badge color={statusColor(row.status)}>{row.status}</Badge> },
    ];

    return (
        <AppLayout
            title="Client Invoicing"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Client Invoicing' }]}
            actions={canManage ? <Link href="/invoices/create"><Button>New invoice</Button></Link> : null}
        >
            <Head title="Client Invoicing" />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p className="eyebrow">Total invoiced</p>
                    <p className="mt-2 text-2xl font-extrabold tracking-tight text-ink-900 tabular">{formatMoney(summary.total)}</p>
                </div>
                <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p className="eyebrow">Collected</p>
                    <p className="mt-2 text-2xl font-extrabold tracking-tight text-brand-700 tabular">{formatMoney(summary.paid)}</p>
                </div>
                <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p className="eyebrow">Outstanding</p>
                    <p className="mt-2 text-2xl font-extrabold tracking-tight text-amber-600 tabular">{formatMoney(summary.due)}</p>
                </div>
            </div>

            <div className="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Search">
                        <TextInput
                            placeholder="Invoice number or client…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && router.get('/invoices', { search }, { preserveState: true })}
                        />
                    </Field>
                    <div className="flex items-end">
                        <Button variant="secondary" onClick={() => router.get('/invoices', { search }, { preserveState: true })}>Search</Button>
                    </div>
                </div>
            </div>

            <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <DataTable columns={columns} rows={invoices?.data ?? []} keyExtractor={(row) => row.id} emptyMessage="No invoices yet." />
                <Pagination meta={invoices} onGoTo={(page) => router.get('/invoices', { search, page }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
