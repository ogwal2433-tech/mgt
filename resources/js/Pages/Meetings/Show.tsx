import AppLayout from '@/Layouts/AppLayout';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Form';
import { formatDateTime } from '@/types/app';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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
    return d > 0 ? `${d}d ${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}` : `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

export default function MeetingShow({ meeting, staffOptions }: any) {
    const { auth } = usePage().props as any;
    const [copied, setCopied] = useState(false);
    const [staffSearch, setStaffSearch] = useState('');
    const [guestEmailInput, setGuestEmailInput] = useState('');
    const expiryCountdown = useCountdown(meeting.status === 'expired' ? null : meeting.link_expires_at);
    const startCountdown = useCountdown(meeting.scheduled_at);

    const inviteForm = useForm({ invitee_ids: [] as number[] });
    const guestForm = useForm({ guest_emails: [] as string[] });

    const canModerate = meeting.is_host || (auth?.user?.permissions ?? []).includes('meetings.manage');
    const isActive = meeting.status !== 'ended' && meeting.status !== 'expired';

    const filteredStaff = (staffOptions ?? []).filter(
        (s: any) =>
            s.name.toLowerCase().includes(staffSearch.toLowerCase()) ||
            s.email.toLowerCase().includes(staffSearch.toLowerCase()),
    );

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(meeting.link);
        } catch {
            const ta = document.createElement('textarea');
            ta.value = meeting.link;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const sendStaffInvites = () => {
        if (inviteForm.data.invitee_ids.length === 0) return;
        inviteForm.post(`/meetings/${meeting.id}/invite`, {
            preserveScroll: true,
            onSuccess: () => inviteForm.reset(),
        });
    };

    const sendGuestInvite = () => {
        const email = guestEmailInput.trim().toLowerCase();
        if (!email) return;
        guestForm.setData('guest_emails', [email]);
        guestForm.post(`/meetings/${meeting.id}/invite`, {
            preserveScroll: true,
            onSuccess: () => {
                setGuestEmailInput('');
                guestForm.reset();
            },
        });
    };

    const removeParticipant = (p: any) => {
        if (!confirm(`Remove ${p.display_name} from this meeting? They will not be able to rejoin.`)) return;
        router.delete(`/meetings/${meeting.id}/participants/${encodeURIComponent(p.identity)}`, { preserveScroll: true });
    };

    const endMeeting = () => {
        if (!confirm('End this meeting for everyone? All participants will be disconnected.')) return;
        router.post(`/meetings/${meeting.id}/end`, { preserveScroll: true });
    };

    return (
        <AppLayout title={meeting.title} breadcrumb={[{ label: 'Meetings', href: '/meetings' }, { label: meeting.title }]}>
            <Head title={meeting.title} />

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Main column */}
                <div className="space-y-6 lg:col-span-2">
                    {/* Hero */}
                    <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-ink-900 via-ink-800 to-brand-700 p-6 text-white shadow-lg">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div className="flex items-center gap-2">
                                    <Badge color={meeting.status === 'live' ? 'green' : meeting.status === 'ended' ? 'gray' : meeting.status === 'expired' ? 'red' : 'blue'}>
                                        {meeting.status === 'live' ? 'Live now' : meeting.status === 'ended' ? 'Ended' : meeting.status === 'expired' ? 'Link expired' : 'Scheduled'}
                                    </Badge>
                                    <span className="rounded-full bg-white/10 px-2.5 py-0.5 text-[11px] font-semibold text-white/80">
                                        {meeting.access_mode === 'staff_only' ? 'Staff only' : 'Anyone with the link'}
                                    </span>
                                </div>
                                <h2 className="mt-3 text-2xl font-extrabold tracking-tight">{meeting.title}</h2>
                                <p className="mt-1 text-sm text-white/70">
                                    Hosted by {meeting.host?.name ?? '—'}
                                    {meeting.host_role ? ` · ${meeting.host_role}` : ''}
                                    {meeting.scheduled_at ? ` · starts ${formatDateTime(meeting.scheduled_at)}` : ''}
                                </p>
                            </div>
                            {isActive && (
                                <Link
                                    href={`/meetings/${meeting.id}/room`}
                                    className="rounded-xl bg-white px-6 py-3 text-sm font-bold text-ink-900 shadow-md transition hover:bg-emerald-50"
                                >
                                    Join meeting →
                                </Link>
                            )}
                        </div>

                        {isActive && (
                            <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                <div className="rounded-xl bg-white/10 px-4 py-3">
                                    <p className="text-[11px] font-semibold uppercase tracking-wider text-white/60">Meeting starts in</p>
                                    <p className="mt-1 font-mono text-xl font-bold tabular-nums">{startCountdown}</p>
                                </div>
                                <div className="rounded-xl bg-white/10 px-4 py-3">
                                    <p className="text-[11px] font-semibold uppercase tracking-wider text-white/60">Link expires in</p>
                                    <p className="mt-1 font-mono text-xl font-bold tabular-nums">{expiryCountdown}</p>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Meeting link */}
                    <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-bold text-ink-900">Meeting link</h3>
                        <p className="mt-0.5 text-xs text-ink-500">
                            {meeting.access_mode === 'staff_only'
                                ? 'Share with Biiteek Global staff — they must sign in to join.'
                                : 'Share with anyone — guests only need to enter a display name.'}
                        </p>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <code className="min-w-0 flex-1 truncate rounded-lg bg-ink-50 px-3 py-2.5 text-xs text-ink-700">{meeting.link}</code>
                            <Button onClick={copyLink} variant={copied ? 'secondary' : 'primary'}>
                                {copied ? 'Copied!' : 'Copy link'}
                            </Button>
                        </div>
                        <p className="mt-2 text-[11px] text-ink-500">
                            Link expires {formatDateTime(meeting.link_expires_at)} — after that it can no longer be used.
                        </p>
                    </div>

                    {/* Invite */}
                    {isActive && canModerate && (
                        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="text-sm font-bold text-ink-900">Invite people</h3>

                            <div className="mt-4">
                                <p className="mb-1.5 text-xs font-semibold text-ink-700">Staff in the system</p>
                                <TextInput
                                    value={staffSearch}
                                    onChange={(e: any) => setStaffSearch(e.target.value)}
                                    placeholder="Search people…"
                                />
                                <div className="mt-2 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-1.5">
                                    {filteredStaff.map((s: any) => {
                                        const checked = inviteForm.data.invitee_ids.includes(s.id);
                                        return (
                                            <label key={s.id} className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-ink-50">
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={() =>
                                                        inviteForm.setData(
                                                            'invitee_ids',
                                                            checked
                                                                ? inviteForm.data.invitee_ids.filter((x) => x !== s.id)
                                                                : [...inviteForm.data.invitee_ids, s.id],
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
                                </div>
                                <div className="mt-2 flex items-center gap-2">
                                    <Button onClick={sendStaffInvites} disabled={inviteForm.data.invitee_ids.length === 0 || inviteForm.processing}>
                                        Send invitations ({inviteForm.data.invitee_ids.length})
                                    </Button>
                                    <span className="text-[11px] text-ink-500">In-app notification + branded email with the join link.</span>
                                </div>
                            </div>

                            <div className="mt-5 border-t border-gray-100 pt-4">
                                <p className="mb-1.5 text-xs font-semibold text-ink-700">Guest by email</p>
                                <div className="flex gap-2">
                                    <TextInput
                                        value={guestEmailInput}
                                        onChange={(e: any) => setGuestEmailInput(e.target.value)}
                                        onKeyDown={(e: any) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                sendGuestInvite();
                                            }
                                        }}
                                        placeholder="guest@company.com"
                                    />
                                    <Button variant="secondary" onClick={sendGuestInvite} disabled={guestForm.processing}>
                                        Email invite
                                    </Button>
                                </div>
                                {(meeting.invitations ?? []).filter((i: any) => i.is_guest).length > 0 && (
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        {(meeting.invitations ?? [])
                                            .filter((i: any) => i.is_guest)
                                            .map((i: any) => (
                                                <span key={i.id} className="rounded-full bg-ink-100 px-2.5 py-1 text-xs font-medium text-ink-700">
                                                    {i.email}
                                                </span>
                                            ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                {/* Side column */}
                <div className="space-y-6">
                    {/* Participants */}
                    <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-bold text-ink-900">Attendance</h3>
                            <span className="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-bold text-brand-700">
                                {meeting.attendee_count ?? 0} attended
                            </span>
                        </div>

                        <div className="mt-3 rounded-xl bg-gradient-to-br from-brand-600 to-emerald-500 px-4 py-3 text-white shadow-sm">
                            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/80">People who attended</p>
                            <p className="mt-0.5 text-3xl font-extrabold leading-none">{meeting.attendee_count ?? 0}</p>
                        </div>

                        <div className="mt-3 space-y-2">
                            <p className="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Invited</p>
                            {(meeting.invitations ?? []).filter((i: any) => !i.is_guest).length === 0 && (
                                <p className="text-xs text-ink-500">No staff invited yet.</p>
                            )}
                            {(meeting.invitations ?? [])
                                .filter((i: any) => !i.is_guest)
                                .map((i: any) => (
                                    <div key={i.id} className="flex items-center gap-2.5 rounded-lg bg-ink-50 px-3 py-2">
                                        <span className="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-[10px] font-bold text-brand-700">
                                            {i.name.split(' ').map((p: string) => p[0]).slice(0, 2).join('')}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate text-xs font-semibold text-ink-800">{i.name}</p>
                                            <p className="truncate text-[10px] text-ink-500">{i.email}</p>
                                        </div>
                                    </div>
                                ))}
                        </div>

                        <div className="mt-4 space-y-2">
                            <p className="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Joined</p>
                            {(meeting.participants ?? []).length === 0 && <p className="text-xs text-ink-500">Nobody has joined yet.</p>}
                            {(meeting.participants ?? []).map((p: any) => (
                                <div key={p.id} className="flex items-center gap-2.5 rounded-lg border border-gray-100 px-3 py-2">
                                    <span
                                        className={`flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-bold ${
                                            p.removed_at ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'
                                        }`}
                                    >
                                        {p.display_name.split(' ').map((x: string) => x[0]).slice(0, 2).join('')}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-xs font-semibold text-ink-800">
                                            {p.display_name}
                                            {p.role === 'host' && <span className="ml-1.5 text-[10px] font-bold text-brand-600">HOST</span>}
                                        </p>
                                        <p className="text-[10px] text-ink-500">{p.removed_at ? 'Removed by host' : p.joined_at ? `joined ${formatDateTime(p.joined_at)}` : 'joined'}</p>
                                    </div>
                                    {canModerate && !p.removed_at && p.role !== 'host' && (
                                        <button
                                            type="button"
                                            onClick={() => removeParticipant(p)}
                                            className="rounded-md px-2 py-1 text-[11px] font-semibold text-red-600 transition hover:bg-red-50"
                                        >
                                            Remove
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Danger zone */}
                    {canModerate && isActive && (
                        <div className="rounded-2xl border border-red-200 bg-red-50/60 p-5">
                            <h3 className="text-sm font-bold text-red-800">End meeting</h3>
                            <p className="mt-1 text-xs text-red-700/80">
                                Ends the meeting for everyone and closes the room. The link will stop working.
                            </p>
                            <button
                                type="button"
                                onClick={endMeeting}
                                className="mt-3 w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700"
                            >
                                End for everyone
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
