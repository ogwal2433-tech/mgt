import AppLayout from '@/Layouts/AppLayout';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, TextArea } from '@/Components/ui/Form';
import { formatDate, formatDateTime } from '@/types/app';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function DocumentSubmissionShow({ submission: s, canReview }: any) {
    const [showReview, setShowReview] = useState<null | 'accepted' | 'rejected'>(null);
    const reviewForm = useForm({ review_comments: '' });

    const statusLabel: Record<string, string> = {
        submitted: 'Pending review',
        accepted: 'Accepted',
        rejected: 'Rejected',
    };

    return (
        <AppLayout
            title={s.reference_number}
            breadcrumb={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Document Submissions', href: '/documents-submissions' },
                { label: s.reference_number },
            ]}
            actions={
                canReview && s.status === 'submitted' ? (
                    <div className="flex gap-2">
                        <Button onClick={() => setShowReview('accepted')}>Accept</Button>
                        <Button variant="danger" onClick={() => setShowReview('rejected')}>Reject</Button>
                    </div>
                ) : null
            }
        >
            <Head title={s.reference_number} />

            <div className="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div className="flex flex-wrap items-center gap-3 text-sm">
                <Badge color={statusColor(s.status)}>{statusLabel[s.status] ?? s.status}</Badge>
                <span className="text-ink-500">Submitted {formatDateTime(s.created_at)}</span>
                <span className="font-semibold text-ink-800">To: {s.recipient?.name ?? 'HR (default)'}</span>
                    {s.reviewed_at && (
                        <span className="font-semibold text-ink-800">
                            {s.status} by {s.reviewer?.username ?? 'HR'} · {formatDateTime(s.reviewed_at)}
                        </span>
                    )}
                </div>
                {s.status === 'submitted' && !canReview && (
                    <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        Your documents are with HR for review. You will be notified — and see the outcome here — once they accept or reject.
                    </p>
                )}
                {s.review_comments && (
                    <div className="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <p className="eyebrow">Reviewer comments</p>
                        <p className="mt-1 whitespace-pre-line text-sm text-ink-800">{s.review_comments}</p>
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader title={s.title} subtitle={`Category: ${s.category}`} />
                        <CardBody>
                            {s.notes ? (
                                <div>
                                    <p className="eyebrow">Notes to HR</p>
                                    <p className="mt-1 whitespace-pre-line text-sm text-ink-800">{s.notes}</p>
                                </div>
                            ) : (
                                <p className="text-sm text-ink-500">No notes provided.</p>
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title="Submitted files" subtitle={`${(s.files ?? []).length} file(s)`} />
                        <CardBody>
                            {(s.files ?? []).length === 0 ? (
                                <p className="text-sm text-ink-500">No files were attached to this submission.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {s.files.map((f: any) => (
                                        <li key={f.id} className="flex items-center justify-between rounded-md border border-gray-100 px-3 py-2">
                                            <div>
                                                <p className="text-sm font-medium text-ink-900">{f.document?.original_filename}</p>
                                                <p className="text-xs text-ink-500">{f.document?.human_size ?? ''} · {formatDate(f.created_at)}</p>
                                            </div>
                                            <a href={f.document?.download_url} className="text-sm font-semibold text-brand-700 hover:underline">
                                                Download
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardBody>
                    </Card>
                </div>

                <div>
                    <Card>
                        <CardHeader title="Submission details" />
                        <CardBody className="space-y-3 text-sm">
                            <Row label="Reference" value={s.reference_number} />
                            <Row label="Submitted by" value={s.employee?.full_name ?? s.user?.username} />
                            <Row label="Employee ID" value={s.employee?.employee_code} />
                            <Row label="Category" value={<span className="capitalize">{s.category}</span>} />
                            <Row label="Date" value={formatDateTime(s.created_at)} />
                            <Row label="Status" value={statusLabel[s.status] ?? s.status} />
                        </CardBody>
                    </Card>
                </div>
            </div>

            <Modal
                open={!!showReview}
                onClose={() => setShowReview(null)}
                title={showReview === 'accepted' ? 'Accept these documents' : 'Reject these documents'}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowReview(null)}>Cancel</Button>
                        <Button
                            variant={showReview === 'rejected' ? 'danger' : 'primary'}
                            loading={reviewForm.processing}
                            onClick={() => router.post(
                                `/documents-submissions/${s.id}/review`,
                                { decision: showReview, review_comments: reviewForm.data.review_comments },
                                { onSuccess: () => setShowReview(null) },
                            )}
                        >
                            {showReview === 'accepted' ? 'Confirm acceptance' : 'Confirm rejection'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <p className="text-sm text-ink-600">
                        {showReview === 'accepted'
                            ? 'The employee is notified immediately and the status of their submission becomes Accepted.'
                            : 'A rejection reason is mandatory. The employee is notified with your comments.'}
                    </p>
                    <Field label="Comments to the employee" required={showReview === 'rejected'} error={reviewForm.errors.review_comments}>
                        <TextArea rows={4} value={reviewForm.data.review_comments} onChange={(e) => reviewForm.setData('review_comments', e.target.value)} />
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
