import AppLayout from '@/Layouts/AppLayout';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, TextArea, TextInput } from '@/Components/ui/Form';
import { formatDateTime } from '@/types/app';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AssignmentsIndex({ assignments, filters }: any) {
    const staffOptions: any[] = (usePage().props as any).staffOptions ?? [];
    const [showCreate, setShowCreate] = useState(false);
    const [search, setSearch] = useState(filters?.search ?? '');
    const [staffSearch, setStaffSearch] = useState('');
    const [toEmails, setToEmails] = useState<string[]>([]);
    const [toInput, setToInput] = useState('');
    const [parts, setParts] = useState<{ title: string; due_at: string }[]>([]);
    const [attachments, setAttachments] = useState<File[]>([]);

    const form = useForm({
        title: '',
        body: '',
        due_at: '',
        require_deliverables: false,
        recipient_emails: [] as string[],
        cc_user_ids: [] as number[],
        parts: [] as { title: string; due_at: string }[],
        attachments: [] as File[],
    });

    const applySearch = (value: string) => {
        setSearch(value);
        router.get('/assignments', { search: value }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const addToEmail = () => {
        const email = toInput.trim().toLowerCase();
        if (!email || toEmails.includes(email)) return;
        const next = [...toEmails, email];
        setToEmails(next);
        form.setData('recipient_emails', next);
        setToInput('');
    };

    const filteredStaff = staffOptions.filter(
        (s) => s.name.toLowerCase().includes(staffSearch.toLowerCase()) || s.email.toLowerCase().includes(staffSearch.toLowerCase()),
    );

    const submit = () => {
        form.post('/assignments', {
            preserveScroll: true,
            onSuccess: () => {
                setShowCreate(false);
                form.reset();
                setToEmails([]);
                setParts([]);
            },
        });
    };

    return (
        <AppLayout
            title="Assignments"
            actions={
                <Button onClick={() => setShowCreate(true)}>
                    <span className="flex items-center gap-1.5">
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        New assignment
                    </span>
                </Button>
            }
        >
            <Head title="Assignments" />

            {/* search */}
            <div className="mb-4">
                <div className="relative max-w-md">
                    <svg className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => applySearch(e.target.value)}
                        placeholder="Search by task name, reference, recipient or submitter…"
                        className="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-9 pr-4 text-sm text-ink-800 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                    />
                </div>
                {search !== '' && (
                    <p className="mt-1.5 text-xs text-ink-500">
                        {assignments.length} result(s) for “{search}” ·{' '}
                        <button type="button" onClick={() => applySearch('')} className="font-semibold text-brand-700 hover:underline">
                            clear
                        </button>
                    </p>
                )}
            </div>

            {assignments.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-gray-300 bg-white px-8 py-16 text-center">
                    <h3 className="text-lg font-bold text-ink-900">{search ? 'No assignments match your search' : 'No assignments yet'}</h3>
                    <p className="mt-1 text-sm text-ink-500">
                        {search ? 'Try a different name, email or task title.' : 'Send tasks to interns or external recipients and collect their deliverables here.'}
                    </p>
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {assignments.map((a: any) => (
                        <div key={a.id} className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow-md">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-[11px] font-semibold text-ink-400">{a.reference_number}</p>
                                    <h3 className="mt-0.5 truncate text-[15px] font-bold text-ink-900">{a.title}</h3>
                                </div>
                                <Badge color={a.status === 'overdue' ? 'red' : a.status === 'closed' ? 'gray' : 'blue'}>
                                    {a.status === 'overdue' ? 'Overdue' : a.status === 'closed' ? 'Closed' : 'Open'}
                                </Badge>
                            </div>

                            <div className="mt-3 space-y-1 text-xs text-ink-600">
                                <p>Due {formatDateTime(a.due_at)}</p>
                                <p>
                                    {a.submitted_count}/{a.recipient_count} submitted
                                    {a.require_deliverables ? ' · files required' : ''}
                                </p>
                            </div>

                            <div className="mt-4 flex gap-2">
                                <Link href={`/assignments/${a.id}`} className="flex-1 rounded-lg bg-brand-600 px-3 py-2 text-center text-xs font-bold text-white transition hover:bg-brand-700">
                                    Open
                                </Link>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* create modal */}
            <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Send an assignment" size="lg">
                <div className="space-y-4">
                    <Field label="Task name / title" required>
                        <TextInput value={form.data.title} onChange={(e: any) => form.setData('title', e.target.value)} placeholder="e.g. Internship Assignment – IBMS Development" />
                    </Field>

                    <Field label="Assignment details" required>
                        <TextArea rows={10} value={form.data.body} onChange={(e: any) => form.setData('body', e.target.value)} placeholder="Paste or write the full assignment brief — it is delivered by email exactly as written…" />
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Due date & time" required>
                            <TextInput type="datetime-local" value={form.data.due_at} onChange={(e: any) => form.setData('due_at', e.target.value)} />
                        </Field>
                        <div className="flex items-end">
                            <label className="flex w-full cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-gray-300">
                                <input
                                    type="checkbox"
                                    checked={form.data.require_deliverables}
                                    onChange={(e: any) => form.setData('require_deliverables', e.target.checked)}
                                    className="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600"
                                />
                                <span>
                                    <span className="block text-sm font-semibold text-ink-800">Require deliverables</span>
                                    <span className="mt-0.5 block text-xs text-ink-500">Recipients must upload at least one file before submitting.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <p className="mb-1.5 text-xs font-semibold text-ink-700">
                            What they should submit (named parts) <span className="font-normal text-ink-400">(optional)</span>
                        </p>
                        <p className="mb-2 text-[11px] text-ink-500">
                            Name each deliverable part (e.g. “ERD Diagram”, “Data Dictionary”). Recipients upload files per part and can update each part separately.
                        </p>
                        <div className="space-y-2">
                            {parts.map((p, i) => (
                                <div key={i} className="flex flex-wrap items-center gap-2">
                                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-700">{i + 1}</span>
                                    <TextInput
                                        value={p.title}
                                        onChange={(e: any) => {
                                            const next = [...parts];
                                            next[i] = { ...next[i], title: e.target.value };
                                            setParts(next);
                                            form.setData('parts', next);
                                        }}
                                        placeholder={`Part ${i + 1} name`}
                                    />
                                    <TextInput
                                        type="datetime-local"
                                        value={p.due_at}
                                        onChange={(e: any) => {
                                            const next = [...parts];
                                            next[i] = { ...next[i], due_at: e.target.value };
                                            setParts(next);
                                            form.setData('parts', next);
                                        }}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => {
                                            const next = parts.filter((_, x) => x !== i);
                                            setParts(next);
                                            form.setData('parts', next);
                                        }}
                                        className="shrink-0 rounded-lg px-2 py-2 text-sm font-bold text-red-500 hover:bg-red-50"
                                    >
                                        ×
                                    </button>
                                </div>
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={() => {
                                const next = [...parts, { title: '', due_at: '' }];
                                setParts(next);
                                form.setData('parts', next);
                            }}
                            className="mt-2 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs font-semibold text-ink-600 transition hover:border-brand-400 hover:text-brand-700"
                        >
                            + Add part
                        </button>
                    </div>

                    <div>
                        <p className="mb-1.5 text-xs font-semibold text-ink-700">
                            Attach documents (requirements PDF etc.) <span className="font-normal text-ink-400">(optional)</span>
                        </p>
                        <input
                            type="file"
                            multiple
                            onChange={(e: any) => {
                                const next = [...attachments, ...(Array.from(e.target.files ?? []) as File[])].slice(0, 5);
                                setAttachments(next);
                                form.setData('attachments', next);
                            }}
                            className="block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.png,.jpg,.jpeg,.txt,.md,.csv"
                        />
                        <p className="mt-1 text-[11px] text-ink-500">Attached to the invitation email and downloadable from the assignment page — up to 5 files, 20 MB each.</p>
                        {attachments.length > 0 && (
                            <ul className="mt-2 space-y-1">
                                {attachments.map((f, i) => (
                                    <li key={i} className="flex items-center justify-between rounded-lg bg-ink-50 px-3 py-2 text-xs">
                                        <span className="min-w-0 truncate font-medium text-ink-700">{f.name}</span>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const next = attachments.filter((_, x) => x !== i);
                                                setAttachments(next);
                                                form.setData('attachments', next);
                                            }}
                                            className="ml-3 font-bold text-red-500 hover:text-red-700"
                                        >
                                            Remove
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div>
                        <p className="mb-1.5 text-xs font-semibold text-ink-700">
                            Send to (external emails) <span className="text-red-500">*</span>
                        </p>
                        <div className="flex gap-2">
                            <TextInput
                                value={toInput}
                                onChange={(e: any) => setToInput(e.target.value)}
                                onKeyDown={(e: any) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        addToEmail();
                                    }
                                }}
                                placeholder="intern@company.com"
                            />
                            <Button type="button" variant="secondary" onClick={addToEmail}>
                                Add
                            </Button>
                        </div>
                        {toEmails.length > 0 && (
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {toEmails.map((email) => (
                                    <span key={email} className="flex items-center gap-1.5 rounded-full bg-ink-100 px-2.5 py-1 text-xs font-medium text-ink-700">
                                        {email}
                                        <button type="button" onClick={() => { const next = toEmails.filter((e) => e !== email); setToEmails(next); form.setData('recipient_emails', next); }} className="text-ink-400 hover:text-red-600">
                                            ×
                                        </button>
                                    </span>
                                ))}
                            </div>
                        )}
                        <p className="mt-1 text-[11px] text-ink-500">Each recipient receives the assignment by email with their own private link to view and upload deliverables.</p>
                    </div>

                    <div>
                        <p className="mb-1.5 text-xs font-semibold text-ink-700">Copy people in the system (CC) <span className="font-normal text-ink-400">(optional)</span></p>
                        <TextInput value={staffSearch} onChange={(e: any) => setStaffSearch(e.target.value)} placeholder="Search staff…" />
                        <div className="mt-2 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-1.5">
                            {filteredStaff.map((s: any) => {
                                const checked = form.data.cc_user_ids.includes(s.id);
                                return (
                                    <label key={s.id} className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-ink-50">
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={() =>
                                                form.setData(
                                                    'cc_user_ids',
                                                    checked ? form.data.cc_user_ids.filter((x) => x !== s.id) : [...form.data.cc_user_ids, s.id],
                                                )
                                            }
                                            className="h-4 w-4 rounded border-gray-300 text-brand-600"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-semibold text-ink-800">{s.name}</span>
                                            <span className="block truncate text-[11px] text-ink-500">{s.roles.join(' · ') || 'Staff'}</span>
                                        </span>
                                    </label>
                                );
                            })}
                            {filteredStaff.length === 0 && <p className="px-2 py-3 text-center text-xs text-ink-500">No staff found.</p>}
                        </div>
                    </div>

                    {form.errors && Object.keys(form.errors).length > 0 && (
                        <div className="rounded-lg bg-red-50 p-3 text-xs text-red-700">
                            {Object.values(form.errors).map((err: any, i) => (
                                <p key={i}>{err}</p>
                            ))}
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="secondary" onClick={() => setShowCreate(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} disabled={form.processing}>
                            {form.processing ? 'Sending…' : 'Send assignment'}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}
