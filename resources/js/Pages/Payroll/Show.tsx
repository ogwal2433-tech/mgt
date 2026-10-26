import AppLayout from '@/Layouts/AppLayout';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, SelectInput, TextInput } from '@/Components/ui/Form';
import { Card, CardHeader, CardBody } from '@/Components/ui/Card';
import { formatMoney, formatDate, formatDateTime } from '@/types/app';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function PayrollShow({ run, can }: any) {
    const [showPay, setShowPay] = useState(false);
    const payForm = useForm({
        payment_date: new Date().toISOString().slice(0, 10),
        payment_method: 'bank_transfer',
        transaction_reference: '',
        notes: '',
    });

    return (
        <AppLayout
            title={`Payroll ${run.run_number}`}
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Payroll', href: '/payroll' }, { label: run.run_number }]}
            actions={
                <div className="flex flex-wrap gap-2">
                    {can.run && run.status !== 'paid' && run.status !== 'closed' && (
                        <Button variant="secondary" onClick={() => router.post(`/payroll/periods/${run.pay_period_id}/compute`)}>
                            {run.status === 'draft' ? 'Compute payroll' : 'Recompute'}
                        </Button>
                    )}
                    {can.approve && (run.status === 'computed' || run.status === 'draft') && (
                        <Button onClick={() => router.post(`/payroll/runs/${run.id}/approve`)}>Approve payroll</Button>
                    )}
                    {can.pay && run.status === 'approved' && (
                        <Button onClick={() => setShowPay(true)}>Record salary payments</Button>
                    )}
                    {can.approve && run.status === 'paid' && (
                        <Button variant="secondary" onClick={() => router.post(`/payroll/runs/${run.id}/close`)}>Close period</Button>
                    )}
                </div>
            }
        >
            <Head title={`Payroll ${run.run_number}`} />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {[
                    ['Status', <Badge key="s" color={statusColor(run.status)}>{run.status}</Badge>],
                    ['Total basic', formatMoney(run.total_basic)],
                    ['Gross pay', formatMoney(run.total_gross)],
                    ['Deductions', formatMoney(run.total_deductions)],
                    ['Net pay', formatMoney(run.total_net)],
                ].map(([label, value], i) => (
                    <div key={i} className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p className="eyebrow">{label}</p>
                        <p className="mt-1 text-xl font-extrabold tracking-tight text-ink-900 tabular">{value}</p>
                    </div>
                ))}
            </div>

            <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <CardHeader
                    title={`Employee payroll — ${run.period?.name ?? ''}`}
                    subtitle={`${(run.lines ?? []).length} employees · computed ${run.computed_at ? formatDateTime(run.computed_at) : '—'}`}
                />
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                        <thead>
                            <tr>
                                {['Employee', 'Basic', 'Gross', 'Tax', 'Advance', 'Other', 'Net pay', 'Status', 'Payslip'].map((h) => (
                                    <th key={h} className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {(run.lines ?? []).map((line: any) => (
                                <tr key={line.id} className="hover:bg-brand-50/40">
                                    <td className="px-4 py-2.5">
                                        <p className="font-semibold text-ink-900">{line.employee_name}</p>
                                        <p className="text-xs text-ink-500">{line.employee_code}</p>
                                    </td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(line.basic_salary)}</td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(line.gross_amount)}</td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(line.tax_amount)}</td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(line.advance_amount)}</td>
                                    <td className="px-4 py-2.5 tabular">{formatMoney(line.other_deduction_amount)}</td>
                                    <td className="px-4 py-2.5 font-semibold tabular">{formatMoney(line.net_amount)}</td>
                                    <td className="px-4 py-2.5"><Badge color={statusColor(line.status)}>{line.status}</Badge></td>
                                    <td className="px-4 py-2.5">
                                        {line.payslip_number ? (
                                            <div className="flex flex-wrap items-center gap-2">
                                                <a href={`/my-payslips/${line.id}/pdf`} className="font-semibold text-brand-700 hover:underline">
                                                    {line.payslip_number}
                                                </a>
                                                <Badge color={statusColor(line.payslip_delivery_status)}>{line.payslip_delivery_status}</Badge>
                                                {can.pay && line.payslip_delivery_status === 'failed' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => router.post(`/payroll/lines/${line.id}/retry-email`)}
                                                        className="text-xs font-semibold text-ink-500 underline hover:text-brand-700"
                                                    >
                                                        Retry
                                                    </button>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-xs text-ink-400">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {(run.payments ?? []).length > 0 && (
                <Card className="mt-6">
                    <CardHeader title="Salary payments" subtitle="Every payment is tracked with method, reference and recorder" />
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                            <thead>
                                <tr>
                                    {['Payment', 'Employee', 'Amount', 'Method', 'Reference', 'Date', 'Recorded by'].map((h) => (
                                        <th key={h} className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {run.payments.map((p: any) => (
                                    <tr key={p.id}>
                                        <td className="px-4 py-2.5 font-semibold text-ink-900">{p.payment_number}</td>
                                        <td className="px-4 py-2.5">{p.employee?.full_name}</td>
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

            <Modal
                open={showPay}
                onClose={() => setShowPay(false)}
                title="Record salary payments"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowPay(false)}>Cancel</Button>
                        <Button loading={payForm.processing} onClick={() => payForm.post(`/payroll/runs/${run.id}/pay`, { onSuccess: () => setShowPay(false) })}>
                            Pay all salaries &amp; issue payslips
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <div className="rounded-lg border border-brand-200 bg-brand-50 p-3 text-sm text-brand-900">
                        This records a salary payment for every employee line, generates payslips and delivers them
                        by email and inside each employee's account. Total: <strong>{formatMoney(run.total_net)}</strong>.
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Payment date" required>
                            <TextInput type="date" value={payForm.data.payment_date} onChange={(e) => payForm.setData('payment_date', e.target.value)} />
                        </Field>
                        <Field label="Payment method" required>
                            <SelectInput value={payForm.data.payment_method} onChange={(e) => payForm.setData('payment_method', e.target.value)}>
                                {['bank_transfer', 'cash', 'mobile_money', 'cheque', 'other'].map((m) => (
                                    <option key={m} value={m}>{m.replaceAll('_', ' ')}</option>
                                ))}
                            </SelectInput>
                        </Field>
                        <Field label="Batch / transaction reference">
                            <TextInput value={payForm.data.transaction_reference} onChange={(e) => payForm.setData('transaction_reference', e.target.value)} />
                        </Field>
                        <Field label="Notes">
                            <TextInput value={payForm.data.notes} onChange={(e) => payForm.setData('notes', e.target.value)} />
                        </Field>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}
