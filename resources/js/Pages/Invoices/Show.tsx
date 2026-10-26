import AppLayout from '@/Layouts/AppLayout';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, SelectInput, TextInput } from '@/Components/ui/Form';
import { Card, CardHeader } from '@/Components/ui/Card';
import { formatMoney, formatDate } from '@/types/app';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { confirmAction } from '@/lib/notify';

export default function InvoiceShow({ invoice, canManage, canPay }: any) {
    const [showPay, setShowPay] = useState(false);
    const payForm = useForm({
        amount: invoice.amount_due,
        payment_date: new Date().toISOString().slice(0, 10),
        payment_method: 'bank_transfer',
        transaction_reference: '',
        received_from: invoice.client_name,
        notes: '',
    });

    return (
        <AppLayout
            title={invoice.invoice_number}
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Invoices', href: '/invoices' }, { label: invoice.invoice_number }]}
            actions={
                <div className="flex flex-wrap gap-2">
                    <a href={`/invoices/${invoice.id}/pdf`}><Button variant="secondary">Download PDF</Button></a>
                    {canManage && invoice.status === 'draft' && (
                        <Button variant="secondary" onClick={() => router.post(`/invoices/${invoice.id}/send`)}>Mark as sent</Button>
                    )}
                    {canPay && invoice.amount_due > 0 && invoice.status !== 'void' && (
                        <Button onClick={() => setShowPay(true)}>Record payment</Button>
                    )}
                    {canPay && invoice.amount_due > 0 && invoice.status !== 'void' && (
                        <Button
                            variant="success"
                            onClick={async () => {
                                const ok = await confirmAction(
                                    'Clear this invoice?',
                                    `Record ${formatMoney(invoice.amount_due, invoice.currency)} as received and mark ${invoice.invoice_number} as paid in full.`,
                                    'Clear invoice',
                                );
                                if (ok) router.post(`/invoices/${invoice.id}/clear`);
                            }}
                        >
                            Clear invoice
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={invoice.invoice_number} />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    ['Status', <Badge key="s" color={statusColor(invoice.status)}>{invoice.status}</Badge>],
                    ['Total', formatMoney(invoice.total_amount, invoice.currency)],
                    ['Paid', formatMoney(invoice.amount_paid, invoice.currency)],
                    ['Balance due', formatMoney(invoice.amount_due, invoice.currency)],
                ].map(([label, value], i) => (
                    <div key={i} className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p className="eyebrow">{label}</p>
                        <p className="mt-1 text-xl font-extrabold tracking-tight text-ink-900 tabular">{value}</p>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader title={invoice.client_name} subtitle={`Issued ${formatDate(invoice.issue_date)} · Due ${formatDate(invoice.due_date)}`} />
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                                <thead>
                                    <tr>
                                        {['Description', 'Qty', 'Unit price', 'Total'].map((h) => (
                                            <th key={h} className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {(invoice.items ?? []).map((item: any) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-2.5">{item.description}</td>
                                            <td className="px-4 py-2.5 tabular">{item.quantity}</td>
                                            <td className="px-4 py-2.5 tabular">{formatMoney(item.unit_price, invoice.currency)}</td>
                                            <td className="px-4 py-2.5 font-medium tabular">{formatMoney(item.total_price, invoice.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    {(invoice.payments ?? []).length > 0 && (
                        <Card>
                            <CardHeader title="Payments received" />
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                                    <thead>
                                        <tr>
                                            {['Receipt', 'Amount', 'Method', 'Reference', 'Date', 'Recorded by'].map((h) => (
                                                <th key={h} className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {invoice.payments.map((p: any) => (
                                            <tr key={p.id}>
                                                <td className="px-4 py-2.5 font-semibold text-ink-900">{p.payment_number}</td>
                                                <td className="px-4 py-2.5 tabular">{formatMoney(p.amount, p.currency)}</td>
                                                <td className="px-4 py-2.5">{p.payment_method?.replaceAll('_', ' ')}</td>
                                                <td className="px-4 py-2.5">{p.transaction_reference ?? '—'}</td>
                                                <td className="px-4 py-2.5">{formatDate(p.payment_date)}</td>
                                                <td className="px-4 py-2.5">{p.recorder?.username ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    )}
                </div>

                <div>
                    <Card>
                        <CardHeader title="Details" />
                        <div className="space-y-3 px-5 py-4 text-sm">
                            <Row label="Project" value={invoice.project?.name ?? '—'} />
                            <Row label="Contact" value={invoice.client_contact} />
                            <Row label="Email" value={invoice.client_email} />
                            <Row label="Subtotal" value={formatMoney(invoice.subtotal, invoice.currency)} />
                            <Row label={`Tax (${invoice.tax_rate}%)`} value={formatMoney(invoice.tax_amount, invoice.currency)} />
                            <Row label="Sent" value={invoice.sent_at ? formatDate(invoice.sent_at) : 'Not sent'} />
                        </div>
                    </Card>
                </div>
            </div>

            <Modal
                open={showPay}
                onClose={() => setShowPay(false)}
                title="Record client payment"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowPay(false)}>Cancel</Button>
                        <Button loading={payForm.processing} onClick={() => payForm.post(`/invoices/${invoice.id}/payments`, { onSuccess: () => setShowPay(false) })}>
                            Record payment
                        </Button>
                    </>
                }
            >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Amount" required error={payForm.errors.amount}>
                        <TextInput type="number" step="0.01" min="0.01" value={payForm.data.amount} onChange={(e) => payForm.setData('amount', e.target.value)} />
                    </Field>
                    <Field label="Payment date" required>
                        <TextInput type="date" value={payForm.data.payment_date} onChange={(e) => payForm.setData('payment_date', e.target.value)} />
                    </Field>
                    <Field label="Method" required>
                        <SelectInput value={payForm.data.payment_method} onChange={(e) => payForm.setData('payment_method', e.target.value)}>
                            {['bank_transfer', 'cash', 'mobile_money', 'cheque', 'other'].map((m) => (
                                <option key={m} value={m}>{m.replaceAll('_', ' ')}</option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label="Reference">
                        <TextInput value={payForm.data.transaction_reference} onChange={(e) => payForm.setData('transaction_reference', e.target.value)} />
                    </Field>
                    <Field label="Received from" className="sm:col-span-2">
                        <TextInput value={payForm.data.received_from} onChange={(e) => payForm.setData('received_from', e.target.value)} />
                    </Field>
                </div>
            </Modal>
        </AppLayout>
    );
}

function Row({ label, value }: { label: string; value: any }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-ink-500">{label}</span>
            <span className="font-medium text-ink-900">{value ?? '—'}</span>
        </div>
    );
}
