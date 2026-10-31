import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const STATE_COPY: Record<string, { title: string; body: string }> = {
    invalid: {
        title: 'Meeting not found',
        body: 'This meeting link is not valid. Please check the link you were sent or contact the host.',
    },
    ended: {
        title: 'This meeting has ended',
        body: 'The host ended the meeting for everyone. If you need to meet again, ask the host for a new link.',
    },
    expired: {
        title: 'This link has expired',
        body: 'Meeting links have a limited lifetime set by the host. Please ask for a fresh link.',
    },
    staff_only: {
        title: 'Biiteek Global staff only',
        body: 'This meeting requires signing in to the company system. If you are staff, open the link while signed in.',
    },
};

export default function MeetingJoin({ state, meeting, guestName, publicId }: any) {
    const { flash } = usePage().props as any;
    const [error, setError] = useState('');
    const form = useForm({ display_name: guestName ?? '' });

    const submit = () => {
        if (!form.data.display_name.trim()) {
            setError('Please enter a display name to join.');
            return;
        }
        setError('');
        form.post(`/meet/${publicId}/join`);
    };

    return (
        <div className="meeting-page">
            <Head title={meeting ? `${meeting.title} · Join` : 'Join meeting'} />

            <div className="flex min-h-full items-center justify-center px-4 py-10">
                <div className="w-full max-w-md">
                    {/* brand */}
                    <div className="mb-8 flex flex-col items-center text-center">
                        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-emerald-500 text-2xl font-extrabold text-white shadow-lg shadow-emerald-500/20">
                            B
                        </span>
                        <p className="mt-3 text-sm font-bold uppercase tracking-[0.2em] text-white/50">Biiteek Global</p>
                        <p className="text-xs text-white/30">Video Conference</p>
                    </div>

                    <div className="rounded-3xl border border-white/10 bg-white/[0.06] p-8 shadow-2xl backdrop-blur-xl">
                        {state !== 'ok' || !meeting ? (
                            <div className="text-center">
                                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10">
                                    <svg className="h-6 w-6 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 9v2m0 4h.01M4.93 4.93l14.14 14.14" />
                                    </svg>
                                </div>
                                <h1 className="mt-4 text-xl font-extrabold text-white">
                                    {STATE_COPY[state]?.title ?? 'Unable to join'}
                                </h1>
                                <p className="mt-2 text-sm leading-relaxed text-white/60">
                                    {STATE_COPY[state]?.body ?? 'Something went wrong with this meeting link.'}
                                </p>
                            </div>
                        ) : (
                            <>
                                <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-300/80">You're invited to</p>
                                <h1 className="mt-2 text-2xl font-extrabold leading-tight text-white">{meeting.title}</h1>
                                <p className="mt-2 text-sm text-white/55">
                                    {meeting.host ? `Hosted by ${meeting.host}` : 'Video conference'}
                                    {meeting.link_expires_at && (
                                        <>
                                            {' · link valid until '}
                                            {new Date(meeting.link_expires_at).toLocaleString([], {
                                                day: 'numeric',
                                                month: 'short',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </>
                                    )}
                                </p>

                                {flash?.error && (
                                    <div className="mt-4 rounded-xl border border-red-400/30 bg-red-500/10 px-4 py-2.5 text-xs font-medium text-red-200">
                                        {flash.error}
                                    </div>
                                )}

                                <div className="mt-6">
                                    <label className="text-xs font-bold text-white/70">Your display name</label>
                                    <input
                                        type="text"
                                        value={form.data.display_name}
                                        onChange={(e) => {
                                            form.setData('display_name', e.target.value);
                                            setError('');
                                        }}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') submit();
                                        }}
                                        maxLength={80}
                                        autoFocus
                                        placeholder="e.g. Sarah Nakato"
                                        className="mt-1.5 w-full rounded-xl border border-white/12 bg-white/[0.07] px-4 py-3 text-sm text-white placeholder-white/25 outline-none transition focus:border-emerald-400/60"
                                    />
                                    {error && <p className="mt-1.5 text-xs font-medium text-red-300">{error}</p>}
                                    <p className="mt-1.5 text-[11px] text-white/40">This is how others in the meeting will see you.</p>
                                </div>

                                <button
                                    type="button"
                                    onClick={submit}
                                    disabled={form.processing}
                                    className="mt-6 w-full rounded-xl bg-gradient-to-r from-brand-600 to-emerald-500 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-500/25 transition hover:brightness-110 disabled:opacity-60"
                                >
                                    {form.processing ? 'Joining…' : 'Join meeting'}
                                </button>
                            </>
                        )}
                    </div>

                    <p className="mt-6 text-center text-[11px] text-white/35">
                        Powered by Biiteek Global Management System · Secure WebRTC conferencing
                    </p>
                </div>
            </div>
        </div>
    );
}
