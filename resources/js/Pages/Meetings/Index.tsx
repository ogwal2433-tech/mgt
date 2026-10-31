import AppLayout from '@/Layouts/AppLayout';
import Badge, { statusColor } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { Field, SelectInput, TextArea, TextInput } from '@/Components/ui/Form';
import { formatDateTime } from '@/types/app';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const EXPIRY_OPTIONS = [
    { value: '60', label: '1 hour' },
    { value: '240', label: '4 hours' },
    { value: '1440', label: '24 hours' },
    { value: '10080', label: '7 days' },
    { value: '43200', label: '30 days' },
];

export default function MeetingsIndex({ meetings }: any) {
    const { flash, auth } = usePage().props as any;
    const [showCreate, setShowCreate] = useState(false);
    const [staffSearch, setStaffSearch] = useState('');
    const [guestEmails, setGuestEmails] = useState<string[]>([]);
    const [guestEmailInput, setGuestEmailInput] = useState('');

    const form = useForm({
        title: '',
        access_mode: 'guest',
        show_host_role: false,
        scheduled_at: '',
        expires_in: 1440,
        invitee_ids: [] as number[],
        guest_emails: [] as string[],
    });

    const staffOptions: any[] = (usePage().props as any).staffOptions ?? [];
    const filteredStaff = staffOptions.filter(
        (s) =>
            s.name.toLowerCase().includes(staffSearch.toLowerCase()) ||
            s.email.toLowerCase().includes(staffSearch.toLowerCase()),
    );

    const toggleInvitee = (id: number) => {
        form.setData(
            'invitee_ids',
            form.data.invitee_ids.includes(id)
                ? form.data.invitee_ids.filter((x) => x !== id)
                : [...form.data.invitee_ids, id],
        );
    };

    const addGuestEmail = () => {
        const email = guestEmailInput.trim().toLowerCase();
        if (!email || guestEmails.includes(email)) return;
        const next = [...guestEmails, email];
        setGuestEmails(next);
        form.setData('guest_emails', next);
        setGuestEmailInput('');
    };

    const removeGuestEmail = (email: string) => {
        const next = guestEmails.filter((e) => e !== email);
        setGuestEmails(next);
        form.setData('guest_emails', next);
    };

    const submit = () => {
        form.post('/meetings', {
            preserveScroll: true,
            onSuccess: () => {
                setShowCreate(false);
                form.reset();
                setGuestEmails([]);
            },
        });
    };

    return (
        <AppLayout
            title="Meetings"
            actions={
                <Button onClick={() => setShowCreate(true)}>
                    <span className="flex items-center gap-1.5">
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        New meeting
                    </span>
                </Button>
            }
        >
            <Head title="Meetings" />

            <div className="space-y-4">
                {meetings.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-gray-300 bg-white px-8 py-16 text-center">
                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                            <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 className="mt-4 text-lg font-bold text-ink-900">No meetings yet</h3>
                        <p className="mt-1 text-sm text-ink-500">Create a video meeting and share the link — guests join with just a display name.</p>
                        <div className="mt-5">
                            <Button onClick={() => setShowCreate(true)}>Create your first meeting</Button>
                        </div>
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {meetings.map((m: any) => (
                            <div key={m.id} className="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow-md">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h3 className="truncate text-[15px] font-bold text-ink-900">{m.title}</h3>
                                        <p className="mt-0.5 text-xs text-ink-500">
                                            {m.is_host ? 'Hosted by you' : `Hosted by ${m.host?.name ?? '—'}`}
                                        </p>
                                    </div>
                                    <Badge color={m.status === 'live' ? 'green' : m.status === 'ended' ? 'gray' : m.status === 'expired' ? 'red' : 'blue'}>
                                        {m.status === 'live' ? 'Live' : m.status === 'ended' ? 'Ended' : m.status === 'expired' ? 'Link expired' : 'Scheduled'}
                                    </Badge>
                                </div>

                                <div className="mt-4 space-y-1.5 text-xs text-ink-600">
                                    <p className="flex items-center gap-2">
                                        <svg className="h-3.5 w-3.5 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        {m.scheduled_at ? formatDateTime(m.scheduled_at) : 'Starting now'}
                                    </p>
                                    <p className="flex items-center gap-2">
                                        <svg className="h-3.5 w-3.5 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Link expires {formatDateTime(m.link_expires_at)}
                                    </p>
                                    <p className="flex items-center gap-2">
                                        <svg className="h-3.5 w-3.5 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                        </svg>
                                        {m.access_mode === 'staff_only' ? 'Staff only' : 'Anyone with the link'}
                                    </p>
                                </div>

                                <div className="mt-5 flex items-center gap-2">
                                    {m.status === 'ended' || m.status === 'expired' ? (
                                        <>
                                            <span className="flex-1 cursor-not-allowed rounded-lg border border-gray-200 bg-ink-50 px-3 py-2 text-center text-xs font-semibold text-ink-400">
                                                Closed
                                            </span>
                                            <Link
                                                href={`/meetings/${m.id}`}
                                                className="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-ink-700 transition hover:bg-ink-50"
                                            >
                                                Attendees ({m.attendee_count ?? 0})
                                            </Link>
                                        </>
                                    ) : (
                                        <>
                                            <Link
                                                href={`/meetings/${m.id}/room`}
                                                className="flex-1 rounded-lg bg-brand-600 px-3 py-2 text-center text-xs font-bold text-white transition hover:bg-brand-700"
                                            >
                                                Join
                                            </Link>
                                            <Link
                                                href={`/meetings/${m.id}`}
                                                className="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-ink-700 transition hover:bg-ink-50"
                                            >
                                                Open
                                            </Link>
                                        </>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Create meeting */}
            <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Create a meeting" size="lg">
                <div className="space-y-4">
                    <Field label="Meeting title" required>
                        <TextInput
                            value={form.data.title}
                            onChange={(e: any) => form.setData('title', e.target.value)}
                            placeholder="e.g. Q4 Strategy Review"
                        />
                    </Field>

                    <div>
                        <p className="mb-1.5 text-xs font-semibold text-ink-700">
                            Who can join? <span className="text-red-500">*</span>
                        </p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {[
                                { key: 'guest', title: 'Anyone with the link', desc: 'Guests join with just a display name — no account needed.' },
                                { key: 'staff_only', title: 'Biiteek Global staff only', desc: 'Requires signing in to the company system.' },
                            ].map((opt) => (
                                <button
                                    key={opt.key}
                                    type="button"
                                    onClick={() => form.setData('access_mode', opt.key)}
                                    className={`rounded-xl border p-3 text-left transition ${
                                        form.data.access_mode === opt.key
                                            ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500'
                                            : 'border-gray-200 bg-white hover:border-gray-300'
                                    }`}
                                >
                                    <p className="text-sm font-bold text-ink-900">{opt.title}</p>
                                    <p className="mt-0.5 text-xs text-ink-500">{opt.desc}</p>
                                </button>
                            ))}
                        </div>
                    </div>

                    <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-gray-300">
                        <input
                            type="checkbox"
                            checked={!!form.data.show_host_role}
                            onChange={(e: any) => form.setData('show_host_role', e.target.checked)}
                            className="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600"
                        />
                        <span>
                            <span className="block text-sm font-semibold text-ink-800">Show my role to participants</span>
                            <span className="mt-0.5 block text-xs text-ink-500">
                                Participants see you as{' '}
                                <span className="font-semibold text-ink-700">“Your name — Staff at Biiteek”</span> by default. Enable this
                                to show your actual role instead (e.g. Managing Director).
                            </span>
                        </span>
                    </label>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Starts at (optional)">
                            <TextInput
                                type="datetime-local"
                                value={form.data.scheduled_at}
                                onChange={(e: any) => form.setData('scheduled_at', e.target.value)}
                            />
                        </Field>
                        <Field label="Link expires after" required>
                            <SelectInput
                                value={String(form.data.expires_in)}
                                onChange={(e: any) => form.setData('expires_in', Number(e.target.value))}
                            >
                                {EXPIRY_OPTIONS.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </SelectInput>
                        </Field>
                    </div>

                    <div>
                        <p className="mb-1.5 text-xs font-semibold text-ink-700">Invite staff from the system</p>
                        <TextInput
                            value={staffSearch}
                            onChange={(e: any) => setStaffSearch(e.target.value)}
                            placeholder="Search people by name or email…"
                        />
                        <div className="mt-2 max-h-44 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-1.5">
                            {filteredStaff.map((s: any) => (
                                <label
                                    key={s.id}
                                    className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-ink-50"
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.invitee_ids.includes(s.id)}
                                        onChange={() => toggleInvitee(s.id)}
                                        className="h-4 w-4 rounded border-gray-300 text-brand-600"
                                    />
                                    <span className="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-[10px] font-bold text-brand-700">
                                        {s.name.split(' ').map((p: string) => p[0]).slice(0, 2).join('')}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-semibold text-ink-800">{s.name}</span>
                                        <span className="block truncate text-[11px] text-ink-500">{s.roles.join(' · ') || 'Staff'}</span>
                                    </span>
                                </label>
                            ))}
                            {filteredStaff.length === 0 && <p className="px-2 py-3 text-center text-xs text-ink-500">No staff found.</p>}
                        </div>
                        {form.data.invitee_ids.length > 0 && (
                            <p className="mt-1 text-[11px] text-ink-500">{form.data.invitee_ids.length} staff member(s) will be notified in-app and by email.</p>
                        )}
                    </div>

                    <div>
                        <p className="mb-1.5 text-xs font-semibold text-ink-700">Invite guests by email (optional)</p>
                        <div className="flex gap-2">
                            <TextInput
                                value={guestEmailInput}
                                onChange={(e: any) => setGuestEmailInput(e.target.value)}
                                onKeyDown={(e: any) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        addGuestEmail();
                                    }
                                }}
                                placeholder="guest@company.com"
                            />
                            <Button type="button" variant="secondary" onClick={addGuestEmail}>
                                Add
                            </Button>
                        </div>
                        {guestEmails.length > 0 && (
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {guestEmails.map((email) => (
                                    <span key={email} className="flex items-center gap-1.5 rounded-full bg-ink-100 px-2.5 py-1 text-xs font-medium text-ink-700">
                                        {email}
                                        <button type="button" onClick={() => removeGuestEmail(email)} className="text-ink-400 hover:text-red-600">
                                            ×
                                        </button>
                                    </span>
                                ))}
                            </div>
                        )}
                        <p className="mt-1 text-[11px] text-ink-500">Guests receive a branded email invitation with the join link.</p>
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
                            {form.processing ? 'Creating…' : 'Create meeting'}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}
