import { Head, useForm, usePage } from '@inertiajs/react';
import SignaturePad from '@/Components/SignaturePad';
import { useEffect, useState } from 'react';

function useCountdown(targetIso: string | null) {
    const [remaining, setRemaining] = useState(() =>
        targetIso ? Math.max(0, Math.floor((new Date(targetIso).getTime() - Date.now()) / 1000)) : 0,
    );
    useEffect(() => {
        if (!targetIso) return;
        const t = setInterval(
            () => setRemaining(Math.max(0, Math.floor((new Date(targetIso).getTime() - Date.now()) / 1000))),
            1000,
        );
        return () => clearInterval(t);
    }, [targetIso]);
    const d = Math.floor(remaining / 86400);
    const h = Math.floor((remaining % 86400) / 3600);
    const m = Math.floor((remaining % 3600) / 60);
    const s = remaining % 60;
    return {
        remaining,
        label: d > 0 ? `${d}d ${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}` : `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`,
    };
}

function formatBytes(n: number) {
    if (!n) return '0 B';
    if (n >= 1048576) return `${(n / 1048576).toFixed(1)} MB`;
    return `${Math.max(1, Math.round(n / 1024))} KB`;
}

export default function ExternalAssignment({ assignment, company, token }: any) {
    const { errors, flash } = usePage().props as any;
    const { label: countdown, remaining } = useCountdown(assignment.is_past_due ? null : assignment.due_at);
    const form = useForm({
        signer_name: '',
        signature: '' as string | null,
        note: '',
        files: [] as File[],
    });

    const addFiles = (list: FileList | null) => {
        if (!list) return;
        form.setData('files', [...form.data.files, ...Array.from(list)].slice(0, 10));
    };

    const submit = () => {
        form.post(`/assignment/${token}`, {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    return (
        <div className="min-h-screen bg-ink-50">
            <Head title={`${assignment.title} · Assignment`} />

            {/* brand bar */}
            <header className="bg-ink-900 text-white">
                <div className="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4 sm:px-6">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-emerald-500 text-sm font-extrabold text-white">
                        B
                    </span>
                    <div>
                        <p className="text-sm font-extrabold leading-tight">Biiteek Global</p>
                        <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-300">Assignments &amp; Tasks</p>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-10">
                {/* status banner */}
                {assignment.submitted_at ? (
                    <div className="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                        <svg className="mt-0.5 h-6 w-6 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p className="text-sm font-bold text-emerald-800">Your submission has been received</p>
                            <p className="mt-0.5 text-xs text-emerald-700">
                                Submitted {new Date(assignment.submitted_at).toLocaleString()}
                                {assignment.can_submit ? ' — you can still upload additional files until the deadline.' : ''}
                            </p>
                        </div>
                    </div>
                ) : assignment.is_past_due ? (
                    <div className="mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4">
                        <svg className="mt-0.5 h-6 w-6 shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M4.93 4.93l14.14 14.14" />
                        </svg>
                        <div>
                            <p className="text-sm font-bold text-red-800">The deadline has passed</p>
                            <p className="mt-0.5 text-xs text-red-700">Submissions for this assignment are closed. Please contact the sender if you still need to submit.</p>
                        </div>
                    </div>
                ) : assignment.is_closed ? (
                    <div className="mb-6 rounded-2xl border border-gray-200 bg-white px-5 py-4 text-sm font-semibold text-ink-600">
                        This assignment has been closed by the sender.
                    </div>
                ) : null}

                {/* assignment card */}
                <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div className="border-b border-gray-100 px-6 py-6 sm:px-8">
                        <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-brand-600">
                            Assignment {assignment.reference_number ? `· ${assignment.reference_number}` : ''}
                        </p>
                        <h1 className="mt-2 text-2xl font-extrabold leading-snug tracking-tight text-ink-900 sm:text-3xl">
                            {assignment.title}
                        </h1>
                        <p className="mt-2 text-sm text-ink-500">
                            {assignment.sender_name ? `Sent by ${assignment.sender_name}` : 'Sent via Biiteek Global Management System'}
                        </p>
                    </div>

                    {/* due + countdown */}
                    <div className="px-6 py-5 sm:px-8">
                        <div
                            className={`rounded-2xl border px-5 py-4 ${
                                assignment.is_past_due
                                    ? 'border-red-200 bg-red-50'
                                    : remaining <= 3600
                                      ? 'border-amber-300 bg-amber-100'
                                      : 'border-amber-200 bg-amber-50'
                            }`}
                        >
                            <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-amber-700">Due date — submit before this time</p>
                            <div className="mt-1.5 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                                <p className="text-lg font-extrabold text-amber-900">{assignment.due_label}</p>
                                {!assignment.is_past_due && (
                                    <p className="font-mono text-2xl font-bold tabular-nums text-amber-800">
                                        {countdown}
                                        <span className="ml-1.5 text-xs font-semibold text-amber-600">remaining</span>
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="mt-6 whitespace-pre-wrap text-[15px] leading-7 text-ink-700">{assignment.body}</div>
                    </div>
                </div>

                {/* submission form */}
                {assignment.can_submit && (
                    <div className="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-100 px-6 py-5 sm:px-8">
                            <h2 className="text-lg font-extrabold text-ink-900">{assignment.submitted_at ? 'Upload additional deliverables' : 'Submit your work'}</h2>
                            <p className="mt-1 text-sm text-ink-500">
                                {assignment.require_deliverables
                                    ? 'This assignment requires deliverable files — attach at least one document, then sign and submit.'
                                    : 'Attach your deliverable files (optional), sign the form and submit.'}
                            </p>
                        </div>

                        <div className="space-y-5 px-6 py-6 sm:px-8">
                            {(Object.keys(errors ?? {}).length > 0 || flash?.error) && (
                                <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                    {flash?.error && <p className="font-semibold">{flash.error}</p>}
                                    {Object.values(errors ?? {}).map((e: any, i: number) => (
                                        <p key={i}>{e}</p>
                                    ))}
                                </div>
                            )}

                            <div>
                                <label className="text-sm font-semibold text-ink-700">
                                    Your full name <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.signer_name}
                                    onChange={(e) => form.setData('signer_name', e.target.value)}
                                    className="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-ink-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                    placeholder="e.g. John Doe"
                                />
                            </div>

                            <div>
                                <label className="text-sm font-semibold text-ink-700">Attach deliverables {assignment.require_deliverables ? <span className="text-red-500">*</span> : <span className="font-normal text-ink-400">(optional)</span>}</label>
                                <input
                                    type="file"
                                    multiple
                                    onChange={(e) => addFiles(e.target.files)}
                                    className="mt-1.5 block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.png,.jpg,.jpeg,.txt,.md,.csv"
                                />
                                <p className="mt-1 text-xs text-ink-400">PDF, Word, Excel, PowerPoint, ZIP or images — up to 20 MB each, max 10 files.</p>
                                {form.data.files.length > 0 && (
                                    <ul className="mt-2 space-y-1.5">
                                        {form.data.files.map((f: File, i: number) => (
                                            <li key={i} className="flex items-center justify-between rounded-lg bg-ink-50 px-3 py-2 text-xs">
                                                <span className="min-w-0 truncate font-medium text-ink-700">
                                                    {f.name} <span className="text-ink-400">({formatBytes(f.size)})</span>
                                                </span>
                                                <button type="button" onClick={() => form.setData('files', form.data.files.filter((_: File, x: number) => x !== i))} className="ml-3 font-bold text-red-500 hover:text-red-700">
                                                    Remove
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>

                            <div>
                                <label className="text-sm font-semibold text-ink-700">Note (optional)</label>
                                <textarea
                                    rows={3}
                                    value={form.data.note}
                                    onChange={(e) => form.setData('note', e.target.value)}
                                    className="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-ink-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                    placeholder="Any notes about your submission…"
                                />
                            </div>

                            <div>
                                <SignaturePad
                                    label="Your signature"
                                    onChange={(dataUrl) => form.setData('signature', dataUrl)}
                                />
                            </div>

                            <button
                                type="button"
                                onClick={submit}
                                disabled={form.processing}
                                className="w-full rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60 sm:w-auto sm:px-10"
                            >
                                {form.processing ? 'Submitting…' : assignment.submitted_at ? 'Submit additional files' : 'Sign & submit'}
                            </button>
                        </div>
                    </div>
                )}

                {/* submitted files */}
                {(assignment.deliverables ?? []).length > 0 && (
                    <div className="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-100 px-6 py-5 sm:px-8">
                            <h2 className="text-lg font-extrabold text-ink-900">Your submitted files</h2>
                            {assignment.signer_name && (
                                <p className="mt-1 text-sm text-ink-500">
                                    Signed by <span className="font-semibold text-ink-700">{assignment.signer_name}</span>
                                    {assignment.signed_at ? ` on ${new Date(assignment.signed_at).toLocaleString()}` : ''}
                                </p>
                            )}
                        </div>
                        <ul className="divide-y divide-gray-100 px-6 sm:px-8">
                            {assignment.deliverables.map((d: any, i: number) => (
                                <li key={i} className="flex items-center gap-3 py-3">
                                    <svg className="h-5 w-5 shrink-0 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-ink-800">{d.filename}</p>
                                        <p className="text-xs text-ink-400">
                                            {formatBytes(d.size)} · uploaded {d.uploaded_at ? new Date(d.uploaded_at).toLocaleString() : ''}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </main>

            <footer className="border-t border-gray-200 bg-white px-4 py-6 text-center text-xs text-ink-400">
                {company?.name ?? 'Biiteek Global'} Management System · Secure assignment delivery
            </footer>
        </div>
    );
}
