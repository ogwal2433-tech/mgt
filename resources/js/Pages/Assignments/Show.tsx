import AppLayout from '@/Layouts/AppLayout';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { TextInput } from '@/Components/ui/Form';
import { formatDateTime } from '@/types/app';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const toLocalInput = (iso: string | null) => {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

function useCountdown(targetIso: string | null, active: boolean) {
    const [remaining, setRemaining] = useState(() =>
        targetIso ? Math.max(0, Math.floor((new Date(targetIso).getTime() - Date.now()) / 1000)) : 0,
    );
    useEffect(() => {
        if (!targetIso || !active) return;
        const t = setInterval(
            () => setRemaining(Math.max(0, Math.floor((new Date(targetIso).getTime() - Date.now()) / 1000))),
            1000,
        );
        return () => clearInterval(t);
    }, [targetIso, active]);
    const d = Math.floor(remaining / 86400);
    const h = Math.floor((remaining % 86400) / 3600);
    const m = Math.floor((remaining % 3600) / 60);
    const s = remaining % 60;
    return d > 0 ? `${d}d ${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}` : `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function formatBytes(n: number) {
    if (!n) return '0 B';
    if (n >= 1048576) return `${(n / 1048576).toFixed(1)} MB`;
    return `${Math.max(1, Math.round(n / 1024))} KB`;
}

export default function AssignmentShow({ assignment }: any) {
    const { flash } = usePage().props as any;
    const [copied, setCopied] = useState<number | null>(null);
    const [showParts, setShowParts] = useState(false);
    const [partsDraft, setPartsDraft] = useState<any[]>([]);
    const partsForm = useForm({ parts: [] as any[] });
    const recipientForm = useForm({ email: '' });
    const countdown = useCountdown(assignment.due_at, !assignment.is_past_due);

    const openPartsEditor = () => {
        setPartsDraft(
            (assignment.parts ?? []).map((p: any) => ({
                id: p.id,
                title: p.title,
                due_at: toLocalInput(p.due_at),
            })),
        );
        setShowParts(true);
    };

    const saveParts = () => {
        partsForm.setData('parts', partsDraft);
        partsForm.patch(`/assignments/${assignment.id}/parts`, {
            preserveScroll: true,
            onSuccess: () => setShowParts(false),
        });
    };

    const copyLink = async (id: number, link: string) => {
        try {
            await navigator.clipboard.writeText(link);
        } catch {
            const ta = document.createElement('textarea');
            ta.value = link;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        setCopied(id);
        setTimeout(() => setCopied(null), 1800);
    };

    const closeAssignment = () => {
        if (!confirm('Close this assignment? External submission links will be disabled.')) return;
        router.post(`/assignments/${assignment.id}/close`, { preserveScroll: true });
    };

    const toList = assignment.recipients.filter((r: any) => r.kind === 'to');
    const ccList = assignment.recipients.filter((r: any) => r.kind === 'cc');

    return (
        <AppLayout
            title={assignment.title}
            breadcrumb={[{ label: 'Assignments', href: '/assignments' }, { label: assignment.title }]}
        >
            <Head title={assignment.title} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {/* hero */}
                    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                        <div className="bg-gradient-to-br from-ink-900 via-ink-800 to-brand-700 px-6 py-6 text-white">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <Badge color={assignment.status === 'overdue' ? 'red' : assignment.status === 'closed' ? 'gray' : 'green'}>
                                            {assignment.status === 'overdue' ? 'Overdue' : assignment.status === 'closed' ? 'Closed' : 'Open'}
                                        </Badge>
                                        <span className="rounded-full bg-white/10 px-2.5 py-0.5 text-[11px] font-semibold text-white/80">
                                            {assignment.reference_number}
                                        </span>
                                        {assignment.require_deliverables && (
                                            <span className="rounded-full bg-amber-400/20 px-2.5 py-0.5 text-[11px] font-semibold text-amber-200">
                                                Deliverables required
                                            </span>
                                        )}
                                    </div>
                                    <h2 className="mt-3 text-2xl font-extrabold tracking-tight">{assignment.title}</h2>
                                    <p className="mt-1 text-sm text-white/70">
                                        Sent by {assignment.created_by?.name ?? '—'} · {assignment.submitted_count} of {assignment.recipient_count} submitted
                                    </p>
                                </div>
                                {!assignment.is_past_due && assignment.status !== 'closed' && (
                                    <div className="rounded-xl bg-white/10 px-4 py-3 text-right">
                                        <p className="text-[10px] font-semibold uppercase tracking-wider text-white/60">Time remaining</p>
                                        <p className="mt-0.5 font-mono text-xl font-bold tabular-nums">{countdown}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="border-b border-gray-100 px-6 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">Due date</p>
                            <p className="mt-0.5 text-sm font-bold text-ink-800">{formatDateTime(assignment.due_at)}</p>
                        </div>

                        <div className="whitespace-pre-wrap px-6 py-5 text-sm leading-7 text-ink-700">{assignment.body}</div>
                    </div>

                    {/* sender attachments */}
                    {(assignment.attachments ?? []).length > 0 && (
                        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-gray-100 px-5 py-4">
                                <h3 className="text-sm font-bold text-ink-900">Attached documents</h3>
                            </div>
                            <ul className="divide-y divide-gray-100 px-5">
                                {assignment.attachments.map((a: any) => (
                                    <li key={a.id} className="flex items-center gap-3 py-3">
                                        <svg className="h-5 w-5 shrink-0 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-xs font-semibold text-ink-800">{a.filename}</p>
                                            <p className="text-[11px] text-ink-400">{formatBytes(a.size)}</p>
                                        </div>
                                        <a
                                            href={`/assignments/${assignment.id}/attachments/${a.id}`}
                                            className="rounded-lg bg-brand-600 px-3 py-1.5 text-[11px] font-semibold text-white transition hover:bg-brand-700"
                                        >
                                            Download
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* recipients TO + deliverables */}
                    <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                            <h3 className="text-sm font-bold text-ink-900">Recipients & submissions</h3>
                            <div className="flex flex-wrap items-center gap-2">
                                <input
                                    type="email"
                                    value={recipientForm.data.email}
                                    onChange={(e) => recipientForm.setData('email', e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            recipientForm.post(`/assignments/${assignment.id}/recipients`, {
                                                preserveScroll: true,
                                                onSuccess: () => recipientForm.reset(),
                                            });
                                        }
                                    }}
                                    placeholder="Send to another email…"
                                    className="rounded-lg border border-gray-200 px-3 py-2 text-xs text-ink-800 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                />
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        recipientForm.post(`/assignments/${assignment.id}/recipients`, {
                                            preserveScroll: true,
                                            onSuccess: () => recipientForm.reset(),
                                        })
                                    }
                                    disabled={recipientForm.processing || recipientForm.data.email.trim() === ''}
                                >
                                    {recipientForm.processing ? 'Sending…' : 'Send'}
                                </Button>
                            </div>
                        </div>
                        {recipientForm.errors?.email && (
                            <p className="border-b border-gray-100 px-5 py-2 text-xs font-medium text-red-600">{recipientForm.errors.email}</p>
                        )}

                        <div className="divide-y divide-gray-100">
                            {toList.map((r: any) => (
                                <div key={r.id} className="px-5 py-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-bold text-ink-900">{r.name || r.email}</p>
                                            <p className="truncate text-xs text-ink-500">{r.email}</p>
                                            <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                                {r.submitted_at ? (
                                                    <Badge color="green">Submitted {formatDateTime(r.submitted_at)}</Badge>
                                                ) : assignment.is_past_due ? (
                                                    <Badge color="red">No submission</Badge>
                                                ) : (
                                                    <Badge color="blue">Awaiting submission</Badge>
                                                )}
                                                {r.viewed_at && <Badge color="gray">Opened {formatDateTime(r.viewed_at)}</Badge>}
                                                {r.has_signature && <Badge color="green">Signed by {r.signer_name}</Badge>}
                                            </div>
                                        </div>
                                        {r.external_link && (
                                            <button
                                                type="button"
                                                onClick={() => copyLink(r.id, r.external_link)}
                                                className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-ink-700 transition hover:bg-ink-50"
                                            >
                                                {copied === r.id ? 'Link copied!' : 'Copy submission link'}
                                            </button>
                                        )}
                                    </div>

                                    {/* signature */}
                                    {r.has_signature && (
                                        <div className="mt-3">
                                            <a
                                                href={`/assignments/${assignment.id}/signatures/${r.id}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50"
                                            >
                                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M15.232 5.232l3.536 3.536M4 20h4l10.5-10.5a2.121 2.121 0 10-3-3L5 17v3z" />
                                                </svg>
                                                View signature{r.signed_at ? ` (signed ${formatDateTime(r.signed_at)})` : ''}
                                            </a>
                                        </div>
                                    )}

                                    {/* deliverables grouped by part */}
                                    {r.deliverables.length > 0 && (
                                        <div className="mt-3 space-y-3">
                                            {Object.entries(
                                                r.deliverables.reduce((acc: Record<string, any[]>, d: any) => {
                                                    const key = d.part_title || 'Other files';
                                                    (acc[key] = acc[key] || []).push(d);
                                                    return acc;
                                                }, {} as Record<string, any[]>),
                                            ).map(([groupTitle, grouped]) => {
                                                const items = grouped as any[];
                                                return (
                                                <div key={groupTitle}>
                                                    <p className="text-[11px] font-semibold uppercase tracking-wider text-ink-400">{groupTitle}</p>
                                                    <div className="mt-1.5 space-y-2">
                                                        {items.map((d: any) => (
                                                            <div key={d.id} className="flex flex-wrap items-center gap-3 rounded-xl border border-gray-100 bg-ink-50 px-3 py-2.5">
                                                                <svg className="h-5 w-5 shrink-0 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                </svg>
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="truncate text-xs font-semibold text-ink-800">{d.filename}</p>
                                                                    <p className="text-[11px] text-ink-400">
                                                                        {formatBytes(d.size)} · {formatDateTime(d.uploaded_at)}
                                                                        {d.note ? ` · ${d.note}` : ''}
                                                                    </p>
                                                                </div>
                                                                <a
                                                                    href={`/assignments/${assignment.id}/deliverables/${d.id}/view`}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-ink-700 transition hover:bg-ink-50"
                                                                >
                                                                    View
                                                                </a>
                                                                <a
                                                                    href={`/assignments/${assignment.id}/deliverables/${d.id}/download`}
                                                                    className="rounded-lg bg-brand-600 px-3 py-1.5 text-[11px] font-semibold text-white transition hover:bg-brand-700"
                                                                >
                                                                    Download
                                                                </a>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            ))}

                            {toList.length === 0 && <p className="px-5 py-6 text-sm text-ink-500">No recipients.</p>}
                        </div>
                    </div>
                </div>

                {/* side */}
                <div className="space-y-6">
                    {/* what to submit */}
                    <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-bold text-ink-900">What to submit</h3>
                            <button
                                type="button"
                                onClick={openPartsEditor}
                                className="rounded-lg border border-gray-200 px-2.5 py-1 text-[11px] font-semibold text-ink-700 transition hover:bg-ink-50"
                            >
                                Edit
                            </button>
                        </div>
                        {(assignment.parts ?? []).length > 0 ? (
                            <ol className="mt-2 space-y-2">
                                {assignment.parts.map((p: any, i: number) => (
                                    <li key={p.id} className="flex items-start gap-2 text-xs text-ink-700">
                                        <span className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[9px] font-bold text-brand-700">
                                            {i + 1}
                                        </span>
                                        <span>
                                            {p.title}
                                            {p.due_label && (
                                                <span className="ml-1.5 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">
                                                    Due {p.due_label}
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <p className="mt-2 text-xs text-ink-500">No named parts — recipients can upload any deliverables.</p>
                        )}
                    </div>

                    {/* CC */}
                    <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-bold text-ink-900">Copied in the system</h3>
                        {ccList.length === 0 ? (
                            <p className="mt-2 text-xs text-ink-500">Nobody was copied on this assignment.</p>
                        ) : (
                            <div className="mt-3 space-y-2">
                                {ccList.map((r: any) => (
                                    <div key={r.id} className="flex items-center gap-2.5 rounded-lg bg-ink-50 px-3 py-2">
                                        <span className="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-[10px] font-bold text-brand-700">
                                            {(r.name || r.email).split(' ').map((p: string) => p[0]).slice(0, 2).join('')}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate text-xs font-semibold text-ink-800">{r.name || r.email}</p>
                                            <p className="truncate text-[10px] text-ink-500">{r.email}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* info */}
                    <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-bold text-ink-900">Submission rules</h3>
                        <ul className="mt-2 space-y-1.5 text-xs text-ink-600">
                            <li>· Deliverable files: {assignment.require_deliverables ? 'required (at least one)' : 'optional'}</li>
                            <li>· Digital signature: required on every submission</li>
                            <li>· Deadline: {formatDateTime(assignment.due_at)} — submissions blocked after</li>
                        </ul>
                        <a
                            href={`/assignments/${assignment.id}/pdf`}
                            className="mt-4 flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-xs font-bold text-ink-700 transition hover:bg-ink-50"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download brief PDF
                        </a>
                    </div>

                    {/* danger */}
                    {assignment.status !== 'closed' && (
                        <div className="rounded-2xl border border-red-200 bg-red-50/60 p-5">
                            <h3 className="text-sm font-bold text-red-800">Close assignment</h3>
                            <p className="mt-1 text-xs text-red-700/80">Disables all external submission links immediately.</p>
                            <button
                                type="button"
                                onClick={closeAssignment}
                                className="mt-3 w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700"
                            >
                                Close assignment
                            </button>
                        </div>
                    )}
                </div>
            </div>
            {/* edit what-to-submit */}
            <Modal open={showParts} onClose={() => setShowParts(false)} title="Edit what to submit" size="lg">
                <div className="space-y-3">
                    <p className="text-xs text-ink-500">
                        Changes apply immediately — recipients see the updated list on their submission page. Removing a part does not delete files already submitted for it.
                    </p>
                    <div className="space-y-2">
                        {partsDraft.map((p: any, i: number) => (
                            <div key={i} className="flex flex-wrap items-center gap-2">
                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-700">{i + 1}</span>
                                <TextInput
                                    value={p.title}
                                    onChange={(e: any) => {
                                        const next = [...partsDraft];
                                        next[i] = { ...next[i], title: e.target.value };
                                        setPartsDraft(next);
                                    }}
                                    placeholder={`Part ${i + 1} name`}
                                />
                                <TextInput
                                    type="datetime-local"
                                    value={p.due_at}
                                    onChange={(e: any) => {
                                        const next = [...partsDraft];
                                        next[i] = { ...next[i], due_at: e.target.value };
                                        setPartsDraft(next);
                                    }}
                                />
                                <button
                                    type="button"
                                    onClick={() => setPartsDraft(partsDraft.filter((_, x) => x !== i))}
                                    className="shrink-0 rounded-lg px-2 py-2 text-sm font-bold text-red-500 hover:bg-red-50"
                                >
                                    ×
                                </button>
                            </div>
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={() => setPartsDraft([...partsDraft, { id: null, title: '', due_at: '' }])}
                        className="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs font-semibold text-ink-600 transition hover:border-brand-400 hover:text-brand-700"
                    >
                        + Add part
                    </button>

                    {partsForm.errors && Object.keys(partsForm.errors).length > 0 && (
                        <div className="rounded-lg bg-red-50 p-3 text-xs text-red-700">
                            {Object.values(partsForm.errors).map((err: any, i) => (
                                <p key={i}>{err}</p>
                            ))}
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="secondary" onClick={() => setShowParts(false)}>
                            Cancel
                        </Button>
                        <Button onClick={saveParts} disabled={partsForm.processing}>
                            {partsForm.processing ? 'Saving…' : 'Save changes'}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}
