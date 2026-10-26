import AppLayout from '@/Layouts/AppLayout';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, SelectInput, TextArea, TextInput } from '@/Components/ui/Form';
import { formatMoney, formatDate, formatDateTime } from '@/types/app';
import { Head, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';
import { toastSuccess, toastError } from '@/lib/notify';

export default function ExternalShow({ requisition: r, canManage }: any) {
    const [showSend, setShowSend] = useState(false);
    const [showDecision, setShowDecision] = useState<null | 'approved' | 'rejected'>(null);
    const [copying, setCopying] = useState(false);

    const sendForm = useForm({ email: r.external_email ?? '', contact_name: r.external_contact ?? '' });
    const decisionForm = useForm({ comments: '' });

    const copyLink = async () => {
        setCopying(true);
        try {
            const response = await axios.post(`/external-requisitions/${r.id}/share-link`);
            await navigator.clipboard.writeText(response.data.url);
            toastSuccess('Copied!');
        } catch (error: any) {
            toastError(error?.response?.data?.message ?? 'Could not copy the link');
        } finally {
            setCopying(false);
        }
    };

    const stageLabels: Record<string, string> = {
        technical_lead: 'Technical Lead (confirm all)',
        hr: 'HR',
        md: 'Managing Director',
    };

    return (
        <AppLayout
            title={r.requisition_number}
            breadcrumb={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'External Requisitions', href: '/external-requisitions' },
                { label: r.requisition_number },
            ]}
            actions={
                <div className="flex flex-wrap gap-2">
                    <a href={r.pdf_url}><Button variant="secondary">Download PDF form</Button></a>
                    {canManage && !r.signed_at && (
                        <>
                            <Button variant="secondary" loading={copying} onClick={copyLink}>
                                Copy link
                            </Button>
                            <Button variant="secondary" onClick={() => setShowSend(true)}>Send to external party</Button>
                        </>
                    )}
                    {canManage && r.signed_at && ['submitted', 'awaiting_hr', 'awaiting_md'].includes(r.status) && (
                        <>
                            <Button onClick={() => setShowDecision('approved')}>Approve</Button>
                            <Button variant="danger" onClick={() => setShowDecision('rejected')}>Reject</Button>
                        </>
                    )}
                </div>
            }
        >
            <Head title={r.requisition_number} />

            {/* Status strip */}
            <div className="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <Badge color={statusColor(r.status)}>{r.status?.replaceAll('_', ' ')}</Badge>
                    {r.sent_to_external_at && (
                        <span className="text-ink-500">Sent to external party {formatDateTime(r.sent_to_external_at)}</span>
                    )}
                    {r.signed_at ? (
                        <span className="font-semibold text-brand-700">Signed by {r.signed_by_name} · {formatDateTime(r.signed_at)}</span>
                    ) : r.sent_to_external_at ? (
                        <span className="font-semibold text-amber-600">Awaiting signature from external party</span>
                    ) : null}
                    {r.feedback_sent_at && (
                        <span className="text-ink-500">Feedback sent to external party {formatDateTime(r.feedback_sent_at)}</span>
                    )}
                </div>
                {r.signed_at && !r.feedback_sent_at && (
                    <p className="mt-2 rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-900">
                        Approval feedback is only emailed to the external party after <strong>Technical Lead, HR and the Managing
                        Director</strong> have all approved — or immediately if the form is rejected.
                    </p>
                )}
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader
                            title={<span className="flex items-center gap-3">{r.requisition_number} <Badge color={r.is_approved ? 'green' : statusColor(r.status)}>{r.status?.replaceAll('_', ' ')}</Badge></span>}
                            subtitle={`Date: ${formatDate(r.date)} · Mode of payment: ${r.mode_of_payment?.replaceAll('_', ' ') ?? '—'}`}
                        />
                        <CardBody>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Item label="External organisation" value={r.external_party_name} />
                                <Item label="Requested by" value={r.requested_by_name} />
                                <Item label="Payee" value={r.payee_name} />
                                <Item label="Contact" value={r.external_contact} />
                                <Item label="Email" value={r.external_email} />
                                <Item label="Mode of payment" value={r.mode_of_payment?.replaceAll('_', ' ')} />
                                <Item label="Date of activity" value={formatDate(r.date_of_activity)} />
                                <Item label="Activity budget" value={r.activity_budget ? formatMoney(r.activity_budget, r.currency) : null} />
                            </div>
                            <div className="mt-4">
                                <p className="eyebrow">Activity description</p>
                                <p className="mt-1 whitespace-pre-line text-sm text-gray-800">{r.purpose}</p>
                            </div>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title="Items & accountability" subtitle="Estimated vs actual costs as filled by the external party" />
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-100 text-[13px]">
                                <thead>
                                    <tr>
                                        {['Description', 'Estimated', 'Actual', 'Balance'].map((h) => (
                                            <th key={h} className="px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {(r.items ?? []).map((item: any) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-2.5">{item.description}</td>
                                            <td className="px-4 py-2.5 tabular">{formatMoney(item.total_price, r.currency)}</td>
                                            <td className="px-4 py-2.5 tabular">{item.actual_cost != null ? formatMoney(item.actual_cost, r.currency) : '—'}</td>
                                            <td className="px-4 py-2.5 tabular">
                                                {item.actual_cost != null ? formatMoney((parseFloat(item.total_price) - parseFloat(item.actual_cost)).toFixed(2), r.currency) : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                    <tr className="bg-brand-50 font-semibold">
                                        <td className="px-4 py-2.5">TOTAL</td>
                                        <td className="px-4 py-2.5 tabular">{formatMoney(r.total_amount, r.currency)}</td>
                                        <td className="px-4 py-2.5 tabular">{formatMoney(r.actual_total, r.currency)}</td>
                                        <td className="px-4 py-2.5 tabular">{formatMoney(r.unspent_funds, r.currency)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                <div className="space-y-6">
                    {/* Approval trail */}
                    <Card>
                        <CardHeader title="Internal approval" subtitle="Lead → HR → MD (feedback sent to the external party only at the end)" />
                        <CardBody>
                            <ol className="space-y-3">
                                {['technical_lead', 'hr', 'md'].map((stage, index) => {
                                    const approval = (r.approvals ?? []).filter((a: any) => a.stage === stage).slice(-1)[0];
                                    return (
                                        <li key={stage} className="flex gap-3">
                                            <div className={`mt-1 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                                approval ? (approval.action === 'approved' ? 'bg-brand-600 text-white' : 'bg-red-600 text-white') : 'bg-ink-100 text-ink-500'
                                            }`}>
                                                {index + 1}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-semibold text-ink-900">{stageLabels[stage]}</p>
                                                {approval ? (
                                                    <p className="text-xs text-ink-500">
                                                        {approval.action} by {approval.user?.username ?? '—'} · {formatDateTime(approval.decided_at)}
                                                        {approval.comments ? ` — “${approval.comments}”` : ''}
                                                    </p>
                                                ) : (
                                                    <p className="text-xs text-ink-400">Awaiting decision</p>
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                            {r.approval_feedback && (
                                <div className="mt-4 rounded-lg border border-brand-200 bg-brand-50 p-3">
                                    <p className="eyebrow">Feedback {r.feedback_sent_at ? 'sent to external party' : 'recorded'}</p>
                                    <p className="mt-1 whitespace-pre-line text-xs text-brand-900">{r.approval_feedback}</p>
                                </div>
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title="Signature" />
                        <CardBody>
                            {r.signature ? (
                                <div>
                                    <img src={`/documents/${r.signature.uuid}/download`} alt="signature" className="max-h-24 rounded border border-gray-200" />
                                    <p className="mt-2 text-xs text-ink-500">{r.signed_by_name} · {formatDateTime(r.signed_at)}</p>
                                </div>
                            ) : (
                                <p className="text-sm text-ink-500">Not signed yet.</p>
                            )}
                        </CardBody>
                    </Card>
                </div>
            </div>

            {/* Send to external */}
            <Modal
                open={showSend}
                onClose={() => setShowSend(false)}
                title="Send form to external party"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowSend(false)}>Cancel</Button>
                        <Button loading={sendForm.processing} onClick={() => sendForm.post(`/external-requisitions/${r.id}/send`, { onSuccess: () => setShowSend(false) })}>
                            Send form link
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <div className="rounded-lg border border-brand-200 bg-brand-50 p-3 text-sm text-brand-900">
                        The external party receives a secure, tokenized link (valid 7 days). They fill in the actual
                        costs, choose the mode of payment, sign digitally and submit — no account needed.
                    </div>
                    <div className="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-ink-800">Or copy the link and send it yourself</p>
                            <p className="text-xs text-ink-500">Works with WhatsApp, Slack, SMS — any channel.</p>
                        </div>
                        <Button variant="secondary" size="sm" loading={copying} onClick={copyLink}>Copy link</Button>
                    </div>
                    <Field label="External email" required error={sendForm.errors.email}>
                        <TextInput type="email" value={sendForm.data.email} onChange={(e) => sendForm.setData('email', e.target.value)} />
                    </Field>
                    <Field label="Contact name">
                        <TextInput value={sendForm.data.contact_name} onChange={(e) => sendForm.setData('contact_name', e.target.value)} />
                    </Field>
                </div>
            </Modal>

            {/* Decision */}
            <Modal
                open={!!showDecision}
                onClose={() => setShowDecision(null)}
                title={showDecision === 'approved' ? 'Approve at your stage' : 'Reject form'}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowDecision(null)}>Cancel</Button>
                        <Button
                            variant={showDecision === 'rejected' ? 'danger' : 'primary'}
                            loading={decisionForm.processing}
                            onClick={() => decisionForm.post(`/external-requisitions/${r.id}/${showDecision}`, { onSuccess: () => setShowDecision(null) })}
                        >
                            {showDecision === 'approved' ? 'Confirm & approve' : 'Confirm rejection'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <p className="text-sm text-ink-600">
                        {showDecision === 'approved'
                            ? 'Your approval is recorded at your stage. The external party is only notified with the outcome once Technical Lead, HR and the Managing Director have all approved.'
                            : 'A rejection reason is mandatory. The external party will be notified with your feedback.'}
                    </p>
                    <Field label="Comments / feedback to the external party" required={showDecision === 'rejected'} error={decisionForm.errors.comments}>
                        <TextArea rows={4} value={decisionForm.data.comments} onChange={(e) => decisionForm.setData('comments', e.target.value)} />
                    </Field>
                </div>
            </Modal>
        </AppLayout>
    );
}

function Item({ label, value }: { label: string; value: any }) {
    return (
        <div>
            <p className="eyebrow">{label}</p>
            <p className="mt-0.5 text-sm text-ink-900">{value ?? '—'}</p>
        </div>
    );
}
