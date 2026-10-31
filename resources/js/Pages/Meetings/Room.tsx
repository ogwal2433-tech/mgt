import {
    ControlBar,
    GridLayout,
    LiveKitRoom,
    ParticipantTile,
    RoomAudioRenderer,
    useConnectionState,
    useParticipants,
    useRoomContext,
    useTracks,
} from '@livekit/components-react';
import { Track } from 'livekit-client';
import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import './meeting-theme.css';

/* ------------------------------------------------------------------ */
/* helpers                                                             */
/* ------------------------------------------------------------------ */

function useElapsed() {
    const [seconds, setSeconds] = useState(0);
    useEffect(() => {
        const t = setInterval(() => setSeconds((s) => s + 1), 1000);
        return () => clearInterval(t);
    }, []);
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function Avatar({ name, size = 'h-9 w-9', className = '' }: any) {
    return (
        <span className={`flex ${size} items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-emerald-500 text-xs font-bold text-white ${className}`}>
            {name.split(' ').map((p: string) => p[0]).slice(0, 2).join('')}
        </span>
    );
}

/* ------------------------------------------------------------------ */
/* chat + people panel                                                 */
/* ------------------------------------------------------------------ */

type ChatMessage = { id: string; name: string; body: string; at: number; self?: boolean };

function SidePanel({ room, meeting, identity, displayName, isHost, onClose }: any) {
    const [tab, setTab] = useState<'chat' | 'people'>('chat');
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [draft, setDraft] = useState('');
    const participants = useParticipants();
    const bottomRef = useRef<HTMLDivElement>(null);

    // Load persisted history once.
    useEffect(() => {
        const historyUrl = identity.startsWith('guest:')
            ? `/meet/${meeting.public_id}/chat`
            : `/meetings/${meeting.id}/chat`;
        fetch(historyUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : { messages: [] }))
            .then((data) =>
                setMessages(
                    (data.messages ?? []).map((m: any) => ({
                        id: String(m.id),
                        name: m.name,
                        body: m.body,
                        at: new Date(m.at ?? Date.now()).getTime(),
                    })),
                ),
            )
            .catch(() => undefined);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Realtime messages over the LiveKit data channel.
    useEffect(() => {
        const handler = (payload: Uint8Array, participant: any) => {
            try {
                const data = JSON.parse(new TextDecoder().decode(payload));
                if (data?.type !== 'chat') return;
                setMessages((prev) => [
                    ...prev,
                    {
                        id: data.id ?? `${Date.now()}-${Math.random()}`,
                        name: data.name ?? participant?.name ?? 'Guest',
                        body: String(data.body ?? ''),
                        at: data.at ?? Date.now(),
                    },
                ]);
            } catch {
                /* ignore malformed payloads */
            }
        };
        room.on('dataReceived', handler);
        return () => {
            room.off('dataReceived', handler);
        };
    }, [room]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length, tab]);

    const send = useCallback(async () => {
        const body = draft.trim();
        if (!body) return;
        setDraft('');

        const id = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        const at = Date.now();
        setMessages((prev) => [...prev, { id, name: displayName, body, at, self: true }]);

        try {
            await room.localParticipant.publishData(
                new TextEncoder().encode(JSON.stringify({ type: 'chat', id, name: displayName, body, at })),
                { topic: 'chat', reliable: true },
            );
        } catch {
            /* channel may be momentarily unavailable */
        }

        const chatUrl = identity.startsWith('guest:')
            ? `/meet/${meeting.public_id}/chat`
            : `/meetings/${meeting.id}/chat`;
        fetch(chatUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ body }),
        }).catch(() => undefined);
    }, [draft, room, displayName, meeting.id, meeting.public_id, identity]);

    const removeParticipant = (p: any) => {
        const name = p.name || p.identity;
        if (!confirm(`Remove ${name} from this meeting? They cannot rejoin.`)) return;
        router.delete(`/meetings/${meeting.id}/participants/${encodeURIComponent(p.identity)}`, {
            preserveScroll: true,
            onError: () => undefined,
        });
    };

    return (
        <aside className="flex h-full w-[340px] shrink-0 flex-col border-l border-white/10 bg-ink-950/95">
            <div className="flex items-center gap-1 border-b border-white/10 px-3 py-2.5">
                {(['chat', 'people'] as const).map((t) => (
                    <button
                        key={t}
                        type="button"
                        onClick={() => setTab(t)}
                        className={`rounded-lg px-3.5 py-1.5 text-xs font-bold transition ${
                            tab === t ? 'bg-white/10 text-white' : 'text-white/50 hover:text-white/80'
                        }`}
                    >
                        {t === 'chat' ? 'In-call messages' : `People (${participants.length})`}
                    </button>
                ))}
                <button type="button" onClick={onClose} className="ml-auto rounded-lg p-1.5 text-white/50 transition hover:bg-white/10 hover:text-white">
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {tab === 'chat' ? (
                <>
                    <div className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                        {messages.length === 0 && (
                            <p className="mt-8 text-center text-xs text-white/40">
                                No messages yet. Say hello — messages are saved with the meeting.
                            </p>
                        )}
                        {messages.map((m) => (
                            <div key={m.id} className={`flex gap-2.5 ${m.self ? 'flex-row-reverse' : ''}`}>
                                {!m.self && <Avatar name={m.name} size="h-7 w-7" className="shrink-0 text-[9px]" />}
                                <div className={`max-w-[75%] ${m.self ? 'text-right' : ''}`}>
                                    <p className="text-[10px] font-semibold text-white/50">
                                        {m.self ? 'You' : m.name} · {new Date(m.at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                    </p>
                                    <div
                                        className={`mt-0.5 inline-block break-words rounded-2xl px-3 py-2 text-left text-xs leading-relaxed ${
                                            m.self ? 'rounded-tr-sm bg-brand-600 text-white' : 'rounded-tl-sm bg-white/10 text-white/90'
                                        }`}
                                    >
                                        {m.body}
                                    </div>
                                </div>
                            </div>
                        ))}
                        <div ref={bottomRef} />
                    </div>

                    <div className="border-t border-white/10 p-3">
                        <div className="flex items-end gap-2">
                            <textarea
                                value={draft}
                                onChange={(e) => setDraft(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !e.shiftKey) {
                                        e.preventDefault();
                                        send();
                                    }
                                }}
                                rows={1}
                                placeholder="Message everyone…"
                                className="max-h-24 flex-1 resize-none rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs text-white placeholder-white/30 outline-none transition focus:border-brand-400/60"
                            />
                            <button
                                type="button"
                                onClick={send}
                                className="rounded-xl bg-brand-600 px-3.5 py-2.5 text-white transition hover:bg-brand-500"
                                aria-label="Send message"
                            >
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </>
            ) : (
                <div className="flex-1 space-y-1.5 overflow-y-auto px-3 py-3">
                    {participants.map((p: any) => {
                        const name = p.name || p.identity || 'Guest';
                        const muted = p.getTrackPublication?.(Track.Source.Microphone)?.isMuted ?? false;
                        return (
                            <div key={p.sid} className="flex items-center gap-2.5 rounded-xl px-2.5 py-2 transition hover:bg-white/5">
                                <Avatar name={name} size="h-8 w-8" className="text-[10px]" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-xs font-semibold text-white/90">
                                        {name}
                                        {p.identity === identity && <span className="ml-1 text-[10px] font-medium text-white/40">(you)</span>}
                                    </p>
                                    <p className="flex items-center gap-1 text-[10px] text-white/40">
                                        <svg className={`h-3 w-3 ${muted ? 'text-red-400' : 'text-emerald-400'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            {muted ? (
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                            ) : (
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                            )}
                                        </svg>
                                        {muted ? 'Muted' : 'Speaking'}
                                    </p>
                                </div>
                                {isHost && p.identity !== identity && (
                                    <button
                                        type="button"
                                        onClick={() => removeParticipant(p)}
                                        className="rounded-lg px-2 py-1 text-[10px] font-bold text-red-300 transition hover:bg-red-500/20"
                                    >
                                        Remove
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </aside>
    );
}

/* ------------------------------------------------------------------ */
/* meeting shell (inside LiveKitRoom)                                  */
/* ------------------------------------------------------------------ */

function MeetingShell({ meeting, identity, displayName, isHost }: any) {
    const tracks = useTracks([{ source: Track.Source.Camera, withPlaceholder: true }]);
    const connectionState = useConnectionState();
    const elapsed = useElapsed();
    const [panelOpen, setPanelOpen] = useState(false);
    const [copied, setCopied] = useState(false);
    const room = useRoomContext();

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(meeting.link);
        } catch {
            /* clipboard unavailable */
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 1800);
    };

    const endForAll = () => {
        if (!confirm('End this meeting for everyone?')) return;
        router.post(`/meetings/${meeting.id}/end`);
    };

    return (
        <div className="meeting-shell">
            {/* header */}
            <header className="meeting-header">
                <div className="flex min-w-0 items-center gap-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-emerald-500 text-sm font-extrabold text-white shadow-md">
                        B
                    </span>
                    <div className="min-w-0">
                        <p
                            className="truncate text-[17px] font-extrabold leading-snug tracking-[-0.01em] text-white"
                            style={{ textShadow: '0 1px 3px rgba(0,0,0,0.55)' }}
                        >
                            {meeting.title}
                        </p>
                        <p className="mt-0.5 flex items-center gap-2 text-[11px] font-medium text-white/65">
                            <span className={`h-1.5 w-1.5 rounded-full ${connectionState === 'connected' ? 'bg-emerald-400' : 'bg-amber-400'}`} />
                            {connectionState === 'connected' ? 'Connected' : 'Connecting…'}
                            <span className="text-white/35">·</span>
                            <span className="font-mono tabular-nums">{elapsed}</span>
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <button type="button" onClick={copyLink} className="meeting-chip-btn">
                        {copied ? 'Link copied!' : 'Copy link'}
                    </button>
                    {isHost && (
                        <button type="button" onClick={endForAll} className="meeting-chip-btn meeting-chip-btn--danger">
                            End for all
                        </button>
                    )}
                </div>
            </header>

            {/* video grid */}
            <div className="meeting-stage">
                <GridLayout tracks={tracks}>
                    <ParticipantTile />
                </GridLayout>
            </div>

            {/* controls */}
            <div className="meeting-controls">
                <ControlBar
                    variation="minimal"
                    controls={{ microphone: true, camera: true, screenShare: true, chat: false, settings: false, leave: true }}
                />
                <button type="button" onClick={() => setPanelOpen((v) => !v)} className="meeting-control-extra">
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    Chat & people
                </button>
            </div>

            <RoomAudioRenderer />

            {panelOpen && (
                <SidePanel
                    room={room}
                    meeting={meeting}
                    identity={identity}
                    displayName={displayName}
                    isHost={isHost}
                    onClose={() => setPanelOpen(false)}
                />
            )}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* page                                                                */
/* ------------------------------------------------------------------ */

export default function MeetingRoom({ serverUrl, token, identity, displayName, isHost, meeting }: any) {
    const [ended, setEnded] = useState<'leave' | 'removed' | 'ended' | null>(null);

    return (
        <div className="meeting-page">
            <Head title={`${meeting.title} · Meeting`} />

            {ended && (
                <div className="meeting-overlay">
                    <div className="meeting-overlay-card">
                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-white">
                            <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h2 className="mt-4 text-xl font-extrabold text-white">
                            {ended === 'removed' ? 'You were removed' : ended === 'ended' ? 'Meeting ended' : 'You left the meeting'}
                        </h2>
                        <p className="mt-2 text-sm text-white/60">
                            {ended === 'removed'
                                ? 'The host removed you from this meeting, so your access has been revoked.'
                                : ended === 'ended'
                                  ? 'The host ended this meeting for everyone.'
                                  : 'Thanks for joining — see you next time.'}
                        </p>
                        <button
                            type="button"
                            onClick={() => router.visit(identity.startsWith('user:') ? '/meetings' : '/')}
                            className="mt-6 rounded-xl bg-white px-6 py-3 text-sm font-bold text-ink-900 transition hover:bg-emerald-50"
                        >
                            {identity.startsWith('user:') ? 'Back to Meetings' : 'Back to home'}
                        </button>
                    </div>
                </div>
            )}

            <LiveKitRoom
                serverUrl={serverUrl}
                token={token}
                connect
                audio
                video
                onDisconnected={(reason: any) => {
                    const r = String(reason ?? '');
                    if (r.includes('REMOVED')) setEnded('removed');
                    else if (r.includes('ROOM_DELETED') || r.includes('DELETED')) setEnded('ended');
                    else setEnded('leave');
                }}
                onError={() => undefined}
                className="meeting-lk"
            >
                {!ended && (
                    <MeetingShell meeting={meeting} identity={identity} displayName={displayName} isHost={isHost} />
                )}
            </LiveKitRoom>
        </div>
    );
}
