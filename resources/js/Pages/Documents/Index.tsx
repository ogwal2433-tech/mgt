import AppLayout from '@/Layouts/AppLayout';
import DataTable, { Column } from '@/Components/ui/DataTable';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, SelectInput, TextArea, TextInput } from '@/Components/ui/Form';
import { formatDate, formatDateTime } from '@/types/app';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function DocumentSubmissionsIndex({ submissions, filters, canReview, pendingCount, recipients }: any) {
const { flash, auth } = usePage().props as any;
const [search, setSearch] = useState(filters?.search ?? '');
const [showCreate, setShowCreate] = useState(false);
const createForm = useForm({
title: '',
category: 'general',
recipient_id: '',
notes: '',
files: [] as File[],
});

    const statusLabel: Record<string, string> = {
        submitted: 'Pending review',
        accepted: 'Accepted',
        rejected: 'Rejected',
    };

    const columns: Column<any>[] = [
        {
            key: 'reference_number',
            header: 'Reference',
            render: (row) => (
                <Link href={`/documents-submissions/${row.id}`} className="font-semibold text-brand-700 hover:underline">
                    {row.reference_number}
                </Link>
            ),
        },
        { key: 'title', header: 'Title', render: (row) => <span className="font-medium text-ink-900">{row.title}</span> },
        { key: 'category', header: 'Category', render: (row) => <span className="capitalize">{row.category}</span> },
{ key: 'recipient', header: 'To', render: (row: any) => row.recipient?.name ?? <span className="text-ink-500">HR (default)</span> },
        ...(canReview ? [{ key: 'employee', header: 'Submitted by', render: (row: any) => row.employee?.full_name ?? row.user?.username ?? '—' } as Column<any>] : []),
        {
            key: 'files',
            header: 'Files',
            render: (row) => <span className="text-ink-600">{(row.files ?? []).length} file(s)</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <Badge color={statusColor(row.status)}>
                    {statusLabel[row.status] ?? row.status}
                </Badge>
            ),
        },
{ key: 'created_at', header: 'Submitted', render: (row) => formatDate(row.created_at) },
{
    key: 'actions',
    header: 'Actions',
    render: (row) => (
        <div className="flex flex-wrap items-center gap-2">
            <Link
                href={`/documents-submissions/${row.id}`}
                className="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-ink-700 transition-colors hover:border-brand-500 hover:text-brand-700"
            >
                View
            </Link>
            {(canReview || row.recipient?.id === (auth as any)?.user?.id) && row.status === 'submitted' && (
                <Link
                    href={`/documents-submissions/${row.id}`}
                    className="rounded-md border border-green-600 bg-green-600 px-2.5 py-1 text-xs font-semibold text-white transition-colors hover:bg-green-700"
                >
                    Review
                </Link>
            )}
            {row.status === 'accepted' && (
                <span className="rounded-md bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">Done</span>
            )}
        </div>
    ),
},
];

    return (
        <AppLayout
            title={canReview ? 'Document Submissions' : 'My Documents'}
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Document Submissions' }]}
            actions={<Button onClick={() => setShowCreate(true)}>Submit documents</Button>}
        >
            <Head title="Document Submissions" />

            <div className="mb-4 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
                {canReview ? (
                    <>
                        <strong>{pendingCount} submission(s) pending review.</strong> Open a submission to view the files, then
                        accept or reject it — the employee is notified immediately and can see the status and your comments.
                    </>
                ) : (
                    <>
                        Submit documents to HR — certificates, claims, signed forms, anything that needs review. You will see
                        the status here: <strong>Pending review → Accepted or Rejected</strong>, with the reviewer's comments.
                    </>
                )}
            </div>

            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Search">
                        <TextInput
                            placeholder="Reference or title…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && router.get('/documents-submissions', { search }, { preserveState: true })}
                        />
                    </Field>
                    <Field label="Status">
                        <SelectInput value={filters?.status ?? ''} onChange={(e) => router.get('/documents-submissions', { search, status: e.target.value }, { preserveState: true })}>
                            <option value="">All</option>
                            <option value="submitted">Pending review</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                        </SelectInput>
                    </Field>
                    <div className="flex items-end">
                        <Button variant="secondary" onClick={() => router.get('/documents-submissions', { search }, { preserveState: true })}>Search</Button>
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <DataTable
                    columns={columns}
                    rows={submissions?.data ?? []}
                    keyExtractor={(row) => row.id}
                    emptyMessage="No document submissions yet."
                />
                <Pagination meta={submissions} onGoTo={(page) => router.get('/documents-submissions', { search, page }, { preserveState: true })} />
            </div>

            <Modal
                open={showCreate}
                onClose={() => setShowCreate(false)}
                title="Submit documents"
                size="lg"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowCreate(false)}>Cancel</Button>
                        <Button
                            loading={createForm.processing}
                            onClick={() => {
                                const fd = new FormData();
                                fd.append('title', createForm.data.title);
                                fd.append('category', createForm.data.category);
            fd.append('recipient_id', createForm.data.recipient_id);
                                if (createForm.data.notes) fd.append('notes', createForm.data.notes);
                                createForm.data.files.forEach((file) => fd.append('files[]', file));
                                createForm.post('/documents-submissions', {
                                    data: fd,
                                    forceFormData: true,
                                    onSuccess: () => setShowCreate(false),
                                } as any);
                            }}
                        >
                            Send document
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    {Object.keys(createForm.errors).length > 0 && (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                            <p className="font-semibold">Please fix the following:</p>
                            <ul className="mt-1 list-inside list-disc space-y-0.5">
                                {Object.entries(createForm.errors).map(([key, message]) => (
                                    <li key={key}>{message as string}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <Field label="Title" required error={createForm.errors.title}>
                        <TextInput
                            placeholder="e.g. Academic certificates, medical claim, signed contract"
                            value={createForm.data.title}
                            onChange={(e) => createForm.setData('title', e.target.value)}
                        />
                    </Field>
                    <Field label="Send to (intended reviewer)" required error={createForm.errors.recipient_id}>
                <SelectInput value={createForm.data.recipient_id} onChange={(e) => createForm.setData('recipient_id', e.target.value)}>
                    <option value="">Select who this document is going to...</option>
                    {(recipients ?? []).map((r: any) => (
                        <option key={r.id} value={r.id}>{r.name} ({r.role})</option>
                    ))}
                </SelectInput>
            </Field>
            <Field label="Category" required>
                        <SelectInput value={createForm.data.category} onChange={(e) => createForm.setData('category', e.target.value)}>
                            {[
                                ['general', 'General document'],
                                ['certificate', 'Certificate'],
                                ['identification', 'Identification'],
                                ['claim', 'Claim / reimbursement'],
                                ['contract', 'Contract / agreement'],
                                ['leave', 'Leave document'],
                                ['other', 'Other'],
                            ].map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label="Notes to HR (optional)">
                        <TextArea rows={3} value={createForm.data.notes} onChange={(e) => createForm.setData('notes', e.target.value)} />
                    </Field>
                    <Field label="Files (optional — you can also submit without attachments)">
                        <input
                            type="file"
                            multiple
                            onChange={(e) => createForm.setData('files', Array.from(e.target.files ?? []))}
                            className="block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-green-700 hover:file:bg-green-100"
                        />
                    </Field>
                </div>
            </Modal>
        </AppLayout>
    );
}
