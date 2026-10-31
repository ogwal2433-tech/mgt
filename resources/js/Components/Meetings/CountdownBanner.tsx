import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

type UpcomingMeeting = {
    id: number;
    title: string;
    scheduled_at: string | null;
    starts_in_seconds: number;
    is_host: boolean;
    join_url: string;
    detail_url: string;
};

function useCountdown(targetIso: string | null, initialSeconds: number) {
    const target = useMemo(
        () => (targetIso ? new Date(targetIso).getTime() : Date.now() + initialSeconds * 1000),
        [targetIso, initialSeconds],
    );
    const [remaining, setRemaining] = useState(() => Math.max(0, Math.floor((target - Date.now()) / 1000)));

    useEffect(() => {
        const t = setInterval(() => setRemaining(Math.max(0, Math.floor((target - Date.now()) / 1000))), 1000);
        return () => clearInterval(t);
    }, [target]);

    const d = Math.floor(remaining / 86400);
    const h = Math.floor((remaining % 86400) / 3600);
    const m = Math.floor((remaining % 3600) / 60);
    const s = remaining % 60;
    const clock = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    const label = d > 0 ? `${d}d ${clock}` : clock;

    return { remaining, label, started: remaining <= 0 };
}

function CountdownItem({ meeting }: { meeting: UpcomingMeeting }) {
    const { label, started, remaining } = useCountdown(meeting.scheduled_at, meeting.starts_in_seconds);

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
            <span className={`h-2 w-2 shrink-0 rounded-full ${started ? 'animate-pulse bg-emerald-400' : 'bg-amber-400'}`} />
            <div className="min-w-0">
                <p className="truncate text-sm font-bold text-white">{meeting.title}</p>
                <p className="text-[11px] font-medium text-white/60">
                    {meeting.is_host ? 'You are hosting' : 'You are invited'}
                    {' · '}
                    {started ? 'happening now' : `starts in ${label}`}
                </p>
            </div>
            <div className="ml-auto flex items-center gap-2">
                {!started && remaining <= 3600 && (
                    <span className="hidden rounded-md bg-white/10 px-2 py-1 font-mono text-xs font-semibold tabular-nums text-white/90 sm:inline">
                        {label}
                    </span>
                )}
                <button
                    type="button"
                    onClick={() => router.visit(meeting.join_url)}
                    className="rounded-lg bg-white px-3.5 py-1.5 text-xs font-bold text-ink-900 shadow-sm transition hover:bg-emerald-50"
                >
                    Join now
                </button>
                <button
                    type="button"
                    onClick={() => router.visit(meeting.detail_url)}
                    className="rounded-lg border border-white/20 px-3 py-1.5 text-xs font-semibold text-white/80 transition hover:bg-white/10"
                >
                    Details
                </button>
            </div>
        </div>
    );
}

export default function CountdownBanner() {
    const { meetings } = usePage().props as any;
    const upcoming: UpcomingMeeting[] = meetings?.upcoming ?? [];
    const [dismissed, setDismissed] = useState(
        () => typeof window !== 'undefined' && sessionStorage.getItem('meeting-banner-dismissed') === '1',
    );

    if (dismissed || upcoming.length === 0) return null;

    return (
        <div className="relative border-b border-ink-900/40 bg-gradient-to-r from-ink-900 via-ink-800 to-brand-700 px-4 py-2.5 sm:px-6 lg:px-8">
            <div className="flex flex-col gap-2">
                {upcoming.map((meeting) => (
                    <CountdownItem key={meeting.id} meeting={meeting} />
                ))}
            </div>
            <button
                type="button"
                onClick={() => {
                    sessionStorage.setItem('meeting-banner-dismissed', '1');
                    setDismissed(true);
                }}
                className="absolute right-2 top-2 hidden rounded p-1 text-white/50 transition hover:text-white lg:block"
                aria-label="Dismiss meeting reminder"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    );
}
