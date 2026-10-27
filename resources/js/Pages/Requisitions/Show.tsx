import AppLayout from '@/Layouts/AppLayout';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, SelectInput, TextArea, TextInput } from '@/Components/ui/Form';
import { formatMoney, formatDate, formatDateTime } from '@/types/app';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function RequisitionShow({ requisition: r, can, paymentMethods }: any) {
    const [decision, setDecision] = useState<null | 'approve' | 'reject' | 'return'>(null);
    const [showPay, setShowPay] = useState(false);
    const [showAccountability, setShowAccountability] = useState(false);

    const approveForm = useForm({ approved_amount: r.approved_amount ?? r.requested_amount, comments: '' });
    const rejectForm = useForm({ reason: '' });
    const payForm = useForm({
        amount: r.approved_amount ?? r.requested_amount,
        payment_date: new Date().toISOString().slice(0, 10),
        payment_method: 'bank_transfer',
        transaction_reference: '',
        payee_name: r.requester_employee?.full_name ?? '',
        payee_details: '',
        notes: '',
        evidence: null as File | null,
    });
    const accountabilityForm = useForm({
        actual_amount: r.amount_paid ?? '0.00',
        explanation: '',
        items: [{ description: '', amount: '0.00', expense_date: '' }],
        receipts: [] as File[],
    });

    const remaining = (parseFloat(r.approved_amount ?? '0') - parseFloat(r.amount_paid ?? '0')).toFixed(2);

    const submitDecision = () => {
        if (decision === 'approve') {
            approveForm.post(`/requisitions/${r.id}/approve`, { onSuccess: () => setDecision(null) });
        } else if (decision === 'reject') {
            rejectForm.post(`/requisitions/${r.id}/reject`, { onSuccess: () => setDecision(null) });
        } else if (decision === 'return') {
            rejectForm.post(`/requisitions/${r.id}/return`, { onSuccess: () => setDecision(null) });
        }
    };

    const submitPayment = () => {
        const fd = new FormData();
        fd.append('amount', payForm.data.amount);
        fd.append('payment_date', payForm.data.payment_date);
        fd.append('payment_method', payForm.data.payment_method);
        if (payForm.data.transaction_reference) fd.append('transaction_reference', payForm.data.transaction_reference);
        fd.append('payee_name', payForm.data.payee_name);
        if (payForm.data.payee_details) fd.append('payee_details', payForm.data.payee_details);
        if (payForm.data.notes) fd.append('notes', payForm.data.notes);
        if (payForm.data.evidence) fd.append('evidence', payForm.data.evidence);

        payForm.post(`/requisitions/${r.id}/payments`, {
            data: fd,
            forceFormData: true,
            onSuccess: () => setShowPay(false),
        } as any);
    };

    const submitAccountability = () => {
        const fd = new FormData();
        fd.append('actual_amount', accountabilityForm.data.actual_amount);
        fd.append('explanation', accountabilityForm.data.explanation);
        accountabilityForm.data.items.forEach((item, i) => {
            fd.append(`items[${i}][description]`, item.description);
            fd.append(`items[${i}][amount]`, item.amount);
            if (item.expense_date) fd.append(`items[${i}][expense_date]`, item.expense_date);
        });
        accountabilityForm.data.receipts.forEach((file) => fd.append('receipts[]', file));

        accountabilityForm.post(`/requisitions/${r.id}/accountability`, {
            data: fd,
            forceFormData: true,
            onSuccess: () => setShowAccountability(false),
        } as any);
    };

    const stageLabels: Record<string, string> = {
        technical_lead: 'Technical Lead review',
        hr: 'HR review',
        md: 'MD final approval',
        disbursement: 'HR disbursement',
        accountability: 'Accountability & closure',
    };

    const chain: string[] = Array.isArray(r.approval_chain) && r.approval_chain.length
        ? r.approval_chain
        : ['technical_lead', 'hr', 'md'];

    const workflowSteps = [
        ...chain.map((key) => ({ key, label: stageLabels[key] ?? key })),
        { key: 'disbursement', label: stageLabels.disbursement },
        ...(r.accountability_required || r.is_advance
            ? [{ key: 'accountability', label: stageLabels.accountability }]
            : []),
    ];

    return (
        <AppLayout
            title={r.requisition_number ?? 'Requisition'}
            breadcrumb={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Requisitions', href: '/requisitions' },
                { label: r.requisition_number ?? 'Detail' },
            ]}
            actions={
                <div className="flex flex-wrap gap-2">
                    {can.submit && (
                        <Button onClick={() => router.post(`/requisitions/${r.id}/submit`)}>Submit for approval</Button>
                    )}
                    {can.disburse && (
                        <Button onClick={() => setShowPay(true)}>Record payment</Button>
                    )}
                    {can.accountability && (
                        <Button variant="secondary" onClick={() => setShowAccountability(true)}>Submit accountability</Button>
                    )}
                </div>
            }
        >
            <Head title={r.requisition_number} />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p className="text-xs font-semibold uppercase text-gray-500">Status</p>
                    <div className="mt-2"><Badge color={statusColor(r.status)}>{(r.status ?? '').replaceAll('_', ' ')}</Badge></div>
                </div>
                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p className="text-xs font-semibold uppercase text-gray-500">Requested amount</p>
                    <p className="mt-1 text-xl font-bold text-gray-900">{formatMoney(r.requested_amount, r.currency)}</p>
                </div>
                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p className="text-xs font-semibold uppercase text-gray-500">Approved amount</p>
                    <p className="mt-1 text-xl font-bold text-green-700">
                        {r.approved_amount ? formatMoney(r.approved_amount, r.currency) : '—'}
                    </p>
                </div>
                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p className="text-xs font-semibold uppercase text-gray-500">Paid / remaining</p>
                    <p className="mt-1 text-xl font-bold text-gray-900">
                        {formatMoney(r.amount_paid, r.currency)} <span className="text-sm font-medium text-gray-500">/ {formatMoney(remaining, r.currency)}</span>
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader title={r.title} subtitle={`Submitted by ${r.requester_employee?.full_name ?? '—'} · ${formatDate(r.created_at)}`} />
                        <CardBody>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Detail label="Category" value={r.category?.name} />
                                <Detail label="Department" value={r.department?.name} />
                                <Detail label="Priority" value={r.priority} />
                                <Detail label="Required date" value={formatDate(r.required_date)} />
                                <Detail label="Project" value={r.project?.name} />
                                <Detail label="Supplier" value={r.supplier_name} />
                                <Detail label="Advance / accountable" value={r.is_advance ? 'Yes' : 'No'} />
                                <Detail label="Accountability required" value={r.accountability_required ? 'Yes' : 'No'} />
                            </div>
                            {r.purpose && (
                                <div className="mt-4">
                                    <p className="text-xs font-semibold uppercase text-gray-500">Activity description / purpose</p>
                                    <p className="mt-1 whitespace-pre-line text-sm text-gray-800">{r.purpose}</p>
                                </div>
                            )}
                            {r.justification && (
                                <div className="mt-4">
                                    <p className="text-xs font-semibold uppercase text-gray-500">Justification</p>
                                    <p className="mt-1 whitespace-pre-line text-sm text-gray-800">{r.justification}</p>
                                </div>
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title="Items & activities" subtitle="Goods, services (hours x rate) and activity lines (flat amounts)" />
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-100 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Description</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                        <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Basis</th>
                                        <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Rate / unit cost</th>
                                        <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {(r.items ?? []).map((item: any) => {
                                        const basis =
                                            item.line_type === 'service' && item.hours
                                                ? `${item.hours} hrs${item.period ? ` (${item.period})` : ''}`
                                                : item.line_type === 'activity'
                                                  ? item.period || 'Flat amount'
                                                  : `${item.quantity}${item.unit ? ` ${item.unit}` : ''}`;
                                        return (
                                            <tr key={item.id}>
                                                <td className="px-4 py-2 text-gray-800">{item.description}</td>
                                                <td className="px-4 py-2">
                                                    <Badge color={item.line_type === 'goods' ? 'gray' : item.line_type === 'service' ? 'blue' : 'green'}>
                                                        {item.line_type ?? 'goods'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-2 text-right text-gray-600">{basis}</td>
                                                <td className="px-4 py-2 text-right text-gray-800">{formatMoney(item.unit_cost, r.currency)}</td>
                                                <td className="px-4 py-2 text-right font-medium text-gray-900">{formatMoney(item.total_cost, r.currency)}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    {(r.attachments ?? []).length > 0 && (
                        <Card>
                            <CardHeader title="Supporting documents" />
                            <CardBody>
                                <ul className="space-y-2">
                                    {r.attachments.map((a: any) => (
                                        <li key={a.id} className="flex items-center justify-between rounded-md border border-gray-100 px-3 py-2">
                                            <span className="text-sm text-gray-800">{a.document?.original_filename}</span>
                                            <a href={a.document?.preview_url} target="_blank" rel="noreferrer" className="text-sm font-semibold text-green-700 hover:underline">Preview</a><a href={a.document?.download_url} className="text-sm font-semibold text-green-700 hover:underline">
                                                Download
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </CardBody>
                        </Card>
                    )}

                    {(r.payments ?? []).length > 0 && (
                        <Card>
                            <CardHeader title="Payment history" subtitle="Each completed payment has its own receipt" />
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-100 text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Payment</th>
                                            <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Method</th>
                                            <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Reference</th>
                                            <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Amount</th>
                                            <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                            <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {r.payments.map((p: any) => (
                                            <tr key={p.id}>
                                                <td className="px-4 py-2 font-medium text-gray-900">{p.payment_number}</td>
                                                <td className="px-4 py-2 text-gray-600">{(p.payment_method ?? '').replaceAll('_', ' ')}</td>
                                                <td className="px-4 py-2 text-gray-600">{p.transaction_reference ?? '—'}</td>
                                                <td className="px-4 py-2 text-right font-medium text-gray-900">{formatMoney(p.amount, r.currency)}</td>
                                                <td className="px-4 py-2 text-gray-600">{formatDate(p.payment_date)}</td>
                                                <td className="px-4 py-2">
                                                    {(r.receipts ?? []).filter((rec: any) => rec.payment_id === p.id).map((rec: any) => (
                                                        <Link key={rec.id} href={`/receipts/${rec.id}`} className="text-sm font-semibold text-green-700 hover:underline">
                                                            {rec.receipt_number}
                                                        </Link>
                                                    ))}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    )}
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader title="Approval workflow" subtitle="No stage may be skipped" />
                        <CardBody>
                            <ol className="space-y-3">
                                {workflowSteps.map((step, index) => {
                                    const approvals = (r.approvals ?? []).filter((a: any) => a.stage === step.key);
                                    const latest = approvals[approvals.length - 1];
                                    return (
                                        <li key={step.key} className="flex gap-3">
                                            <div className={`mt-1 h-6 w-6 flex-shrink-0 rounded-full text-center text-xs font-bold leading-6 ${latest ? (latest.action === 'approved' ? 'bg-green-600 text-white' : latest.action === 'rejected' ? 'bg-red-600 text-white' : 'bg-amber-500 text-white') : 'bg-gray-200 text-gray-500'}`}>
                                                {index + 1}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-semibold text-gray-900">{step.label}</p>
                                                {latest ? (
                                                    <>
                                                        <p className="text-xs text-gray-600">
                                                            {latest.action} by {latest.user?.username ?? 'system'} · {formatDateTime(latest.decided_at)}
                                                        </p>
                                                        {latest.decision_amount && (
                                                            <p className="text-xs text-gray-600">Amount: {formatMoney(latest.decision_amount, r.currency)}</p>
                                                        )}
                                                        {latest.reason && <p className="mt-1 text-xs italic text-red-600">"{latest.reason}"</p>}
                                                        {latest.comments && <p className="mt-1 text-xs italic text-gray-600">"{latest.comments}"</p>}
                                                    </>
                                                ) : (
                                                    <p className="text-xs text-gray-500">Awaiting action</p>
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>

                            {(can.review_technical || can.review_hr || can.review_md) && (
                                <div className="mt-5 space-y-2 border-t border-gray-100 pt-4">
                                    {can.review_md && (
                                        <Field label="Approved amount (MD may approve a lower amount)">
                                            <TextInput
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={approveForm.data.approved_amount}
                                                onChange={(e) => approveForm.setData('approved_amount', e.target.value)}
                                            />
                                        </Field>
                                    )}
                                    <div className="flex flex-wrap gap-2">
                                        <Button onClick={() => setDecision('approve')}>Approve</Button>
                                        <Button variant="danger" onClick={() => setDecision('reject')}>Reject</Button>
                                        <Button variant="secondary" onClick={() => setDecision('return')}>Return</Button>
                                    </div>
                                    <p className="text-xs text-gray-500">
                                        You cannot approve your own requisition. Every decision is recorded permanently.
                                    </p>
                                </div>
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title="Financial summary" />
                        <CardBody className="space-y-2 text-sm">
                            <Row label="Requested" value={formatMoney(r.requested_amount, r.currency)} />
                            <Row label="Approved" value={formatMoney(r.approved_amount, r.currency)} />
                            <Row label="Paid" value={formatMoney(r.amount_paid, r.currency)} />
                            <Row label="Remaining" value={formatMoney(remaining, r.currency)} />
                            <Row label="Accounted" value={formatMoney(r.amount_accounted, r.currency)} />
                            <Row label="Refunded" value={formatMoney(r.amount_refunded, r.currency)} />
                        </CardBody>
                    </Card>
                </div>
            </div>

            <Modal
                open={!!decision}
                onClose={() => setDecision(null)}
                title={decision === 'approve' ? 'Approve requisition' : decision === 'reject' ? 'Reject requisition' : 'Return for correction'}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setDecision(null)}>Cancel</Button>
                        <Button variant={decision === 'reject' ? 'danger' : 'primary'} onClick={submitDecision}>
                            {decision === 'approve' ? 'Confirm approval' : decision === 'reject' ? 'Confirm rejection' : 'Return to requester'}
                        </Button>
                    </>
                }
            >
                {decision === 'approve' ? (
                    <div className="space-y-3">
                        <p className="text-sm text-gray-700">
                            Approve this requisition at the current stage. The decision is recorded permanently in the approval history.
                        </p>
                        {can.review_md && (
                            <Field label="Approved amount">
                                <TextInput type="number" step="0.01" min="0" value={approveForm.data.approved_amount} onChange={(e) => approveForm.setData('approved_amount', e.target.value)} />
                            </Field>
                        )}
                        <Field label="Comments (optional)">
                            <TextArea rows={3} value={approveForm.data.comments} onChange={(e) => approveForm.setData('comments', e.target.value)} />
                        </Field>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <p className="text-sm text-gray-700">
                            {decision === 'reject'
                                ? 'A rejection reason is mandatory. A rejected requisition cannot proceed to the next stage or be paid.'
                                : 'Explain what the requester must correct. The requisition returns to draft for resubmission.'}
                        </p>
                        <Field label="Reason" required error={rejectForm.errors.reason}>
                            <TextArea rows={4} value={rejectForm.data.reason} onChange={(e) => rejectForm.setData('reason', e.target.value)} />
                        </Field>
                    </div>
                )}
            </Modal>

            <Modal
                open={showPay}
                onClose={() => setShowPay(false)}
                title="Record disbursement"
                size="lg"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowPay(false)}>Cancel</Button>
                        <Button onClick={submitPayment} loading={payForm.processing}>Record payment &amp; generate receipt</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                        Remaining approved amount: <strong>{formatMoney(remaining, r.currency)}</strong>. Partial payments are supported; each
                        completed payment generates its own receipt.
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Payment amount" required error={payForm.errors.amount}>
                            <TextInput type="number" step="0.01" min="0.01" value={payForm.data.amount} onChange={(e) => payForm.setData('amount', e.target.value)} />
                        </Field>
                        <Field label="Payment date" required>
                            <TextInput type="date" value={payForm.data.payment_date} onChange={(e) => payForm.setData('payment_date', e.target.value)} />
                        </Field>
                        <Field label="Payment method" required>
                            <SelectInput value={payForm.data.payment_method} onChange={(e) => payForm.setData('payment_method', e.target.value)}>
                                {(paymentMethods ?? ['cash', 'bank_transfer', 'mobile_money', 'cheque', 'other']).map((m: string) => (
                                    <option key={m} value={m}>{m.replaceAll('_', ' ')}</option>
                                ))}
                            </SelectInput>
                        </Field>
                        <Field label="Transaction reference">
                            <TextInput value={payForm.data.transaction_reference} onChange={(e) => payForm.setData('transaction_reference', e.target.value)} />
                        </Field>
                        <Field label="Payee name" required>
                            <TextInput value={payForm.data.payee_name} onChange={(e) => payForm.setData('payee_name', e.target.value)} />
                        </Field>
                        <Field label="Payee details">
                            <TextInput value={payForm.data.payee_details} onChange={(e) => payForm.setData('payee_details', e.target.value)} />
                        </Field>
                    </div>
                    <Field label="Notes">
                        <TextArea rows={2} value={payForm.data.notes} onChange={(e) => payForm.setData('notes', e.target.value)} />
                    </Field>
                    <Field label="Payment evidence (optional)">
                        <input type="file" accept=".pdf,.png,.jpg,.jpeg" onChange={(e) => payForm.setData('evidence', e.target.files?.[0] ?? null)} className="block w-full text-sm text-gray-600" />
                    </Field>
                    <p className="text-xs text-gray-500">
                        Recording a payment does not independently verify that an external transfer succeeded. The system stores what the
                        authorised HR Finance &amp; Accounts officer records.
                    </p>
                </div>
            </Modal>

            <Modal
                open={showAccountability}
                onClose={() => setShowAccountability(false)}
                title="Submit accountability"
                size="lg"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowAccountability(false)}>Cancel</Button>
                        <Button onClick={submitAccountability} loading={accountabilityForm.processing}>Submit for HR review</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <div className="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
                        Amount disbursed: <strong>{formatMoney(r.amount_paid, r.currency)}</strong>. Report the actual amount spent and any
                        unspent balance to be refunded.
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Actual amount spent" required>
                            <TextInput type="number" step="0.01" min="0" value={accountabilityForm.data.actual_amount} onChange={(e) => accountabilityForm.setData('actual_amount', e.target.value)} />
                        </Field>
                        <Field label="Expense receipts (optional)">
                            <input type="file" multiple onChange={(e) => accountabilityForm.setData('receipts', Array.from(e.target.files ?? []))} className="block w-full text-sm text-gray-600" />
                        </Field>
                    </div>
                    <Field label="Explanation of differences" required>
                        <TextArea rows={3} value={accountabilityForm.data.explanation} onChange={(e) => accountabilityForm.setData('explanation', e.target.value)} />
                    </Field>
                    {accountabilityForm.data.items.map((item, i) => (
                        <div key={i} className="grid grid-cols-1 items-end gap-3 rounded-md border border-gray-100 p-3 sm:grid-cols-3">
                            <Field label="Expense description">
                                <TextInput value={item.description} onChange={(e) => {
                                    const items = accountabilityForm.data.items.map((it, idx) => idx === i ? { ...it, description: e.target.value } : it);
                                    accountabilityForm.setData('items', items);
                                }} />
                            </Field>
                            <Field label="Amount">
                                <TextInput type="number" step="0.01" value={item.amount} onChange={(e) => {
                                    const items = accountabilityForm.data.items.map((it, idx) => idx === i ? { ...it, amount: e.target.value } : it);
                                    accountabilityForm.setData('items', items);
                                }} />
                            </Field>
                            <Field label="Date">
                                <TextInput type="date" value={item.expense_date} onChange={(e) => {
                                    const items = accountabilityForm.data.items.map((it, idx) => idx === i ? { ...it, expense_date: e.target.value } : it);
                                    accountabilityForm.setData('items', items);
                                }} />
                            </Field>
                        </div>
                    ))}
                    <Button type="button" variant="secondary" size="sm" onClick={() => accountabilityForm.setData('items', [...accountabilityForm.data.items, { description: '', amount: '0.00', expense_date: '' }])}>
                        Add expense line
                    </Button>
                </div>
            </Modal>
        </AppLayout>
    );
}

function Detail({ label, value }: { label: string; value?: any }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase text-gray-500">{label}</p>
            <p className="mt-0.5 text-sm text-gray-900">{value ?? '—'}</p>
        </div>
    );
}

function Row({ label, value }: { label: string; value: any }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-gray-600">{label}</span>
            <span className="font-medium text-gray-900">{value}</span>
        </div>
    );
}
