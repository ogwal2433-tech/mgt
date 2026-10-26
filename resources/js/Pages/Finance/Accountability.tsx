import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Card, CardHeader, CardBody } from '@/Components/ui/Card';
import { Field, TextArea, TextInput } from '@/Components/ui/Form';
import { formatMoney, formatDate, formatDateTime } from '@/types/app';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Accountability({ submissions, outstanding, filters }: any) {
    const [reviewTarget, setReviewTarget] = useState<any>(null);
    const [submitTarget, setSubmitTarget] = useState<any>(null);
    const reviewForm = useForm({ decision: 'accepted', review_comments: '', refund_amount: '' });
    const submitForm = useForm({ actual_amount: '', explanation: '' });

    const columns: Column<any>[] = [
        {
            key: 'requisition',
            header: 'Requisition',
            render: (row) => (
                <Link href={`/requisitions/${row.requisition?.id}`} className="font-semibold text-green-700 hover:underline">
                    {row.requisition?.requisition_number}
                </Link>
            ),
        },
        { key: 'submitter', header: 'Submitted by', render: (row) => row.submitter?.username ?? '—' },
        { key: 'advance_amount', header: 'Advance', className: 'text-right', render: (row) => formatMoney(row.advance_amount, row.requisition?.currency) },
        { key: 'actual_amount', header: 'Actual', className: 'text-right', render: (row) => formatMoney(row.actual_amount, row.requisition?.currency) },
        { key: 'unspent_balance', header: 'Unspent', className: 'text-right', render: (row) => formatMoney(row.unspent_balance, row.requisition?.currency) },
        { key: 'status', header: 'Status', render: (row) => <Badge color={statusColor(row.status)}>{row.status}</Badge> },
        {
            key: 'actions',
            header: '',
            render: (row) => (
                row.status === 'pending' ? (
                    <Button size="sm" variant="secondary" onClick={() => { setReviewTarget(row); reviewForm.setData('refund_amount', row.unspent_balance); }}>
                        Review
                    </Button>
                ) : (
                    <span className="text-xs text-gray-500">{row.review_comments ? 'Reviewed' : '—'}</span>
                )
            ),
        },
    ];

    return (
        <AppLayout
            title="Accountability"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Accountability' }]}
        >
            <Head title="Accountability" />

            <div className="mb-6 rounded-lg border border-gray-200 bg-white shadow-sm">
                <CardHeader title="Requisitions awaiting accountability" subtitle="Advances and accountable expenditure that must be reported" />
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Number</th>
                                <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Requester</th>
                                <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Amount paid</th>
                                <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {(outstanding ?? []).length === 0 && (
                                <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-500">No outstanding accountability.</td></tr>
                            )}
                            {(outstanding ?? []).map((row: any) => (
                                <tr key={row.id}>
                                    <td className="px-4 py-2">
                                        <Link href={`/requisitions/${row.id}`} className="font-semibold text-green-700 hover:underline">{row.requisition_number}</Link>
                                    </td>
                                    <td className="px-4 py-2">{row.title}</td>
                                    <td className="px-4 py-2">{row.requester}</td>
                                    <td className="px-4 py-2 text-right">{formatMoney(row.amount_paid, row.currency)}</td>
                                    <td className="px-4 py-2 text-right">
                                        {row.can_submit ? (
                                            <Button size="sm" onClick={() => setSubmitTarget(row)}>Submit</Button>
                                        ) : (
                                            <span className="text-xs text-gray-500">Awaiting requester</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <CardHeader title="Accountability submissions" />
                <DataTable columns={columns} rows={submissions?.data ?? []} keyExtractor={(row) => row.id} emptyMessage="No accountability submissions yet." />
                <Pagination meta={submissions} onGoTo={(page) => router.get('/accountability', { page }, { preserveState: true })} />
            </div>

            <Modal
                open={!!reviewTarget}
                onClose={() => setReviewTarget(null)}
                title="Review accountability submission"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setReviewTarget(null)}>Cancel</Button>
                        <Button onClick={() => reviewForm.post(`/accountability/${reviewTarget?.id}/review`, { onSuccess: () => setReviewTarget(null) })}>
                            Record decision
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <div className="rounded-md bg-gray-50 p-3 text-sm text-gray-700">
                        Advance: <strong>{formatMoney(reviewTarget?.advance_amount, reviewTarget?.requisition?.currency)}</strong> · Actual:{' '}
                        <strong>{formatMoney(reviewTarget?.actual_amount, reviewTarget?.requisition?.currency)}</strong> · Unspent:{' '}
                        <strong>{formatMoney(reviewTarget?.unspent_balance, reviewTarget?.requisition?.currency)}</strong>
                        {reviewTarget?.explanation && <p className="mt-2 whitespace-pre-line">{reviewTarget.explanation}</p>}
                    </div>
                    <Field label="Decision">
                        <select
                            className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            value={reviewForm.data.decision}
                            onChange={(e) => reviewForm.setData('decision', e.target.value)}
                        >
                            <option value="accepted">Accept and close requisition</option>
                            <option value="rejected">Reject — requester must resubmit</option>
                        </select>
                    </Field>
                    <Field label="Refund amount (unspent balance)">
                        <TextInput type="number" step="0.01" min="0" value={reviewForm.data.refund_amount} onChange={(e) => reviewForm.setData('refund_amount', e.target.value)} />
                    </Field>
                    <Field label="Review comments">
                        <TextArea rows={3} value={reviewForm.data.review_comments} onChange={(e) => reviewForm.setData('review_comments', e.target.value)} />
                    </Field>
                </div>
            </Modal>

            <Modal
                open={!!submitTarget}
                onClose={() => setSubmitTarget(null)}
                title="Submit accountability"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setSubmitTarget(null)}>Cancel</Button>
                        <Button onClick={() => submitForm.post(`/requisitions/${submitTarget?.id}/accountability`, { onSuccess: () => setSubmitTarget(null) })}>
                            Submit for review
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Field label="Actual amount spent" required>
                        <TextInput type="number" step="0.01" min="0" value={submitForm.data.actual_amount} onChange={(e) => submitForm.setData('actual_amount', e.target.value)} />
                    </Field>
                    <Field label="Explanation" required>
                        <TextArea rows={3} value={submitForm.data.explanation} onChange={(e) => submitForm.setData('explanation', e.target.value)} />
                    </Field>
                    <p className="text-xs text-gray-500">
                        The unspent balance is calculated automatically and must be refunded after HR review.
                    </p>
                </div>
            </Modal>
        </AppLayout>
    );
}
