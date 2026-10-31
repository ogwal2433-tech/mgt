<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Services\LiveKitService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GuestMeetingController extends Controller
{
    /** Public join page: display name only — no account needed. */
    public function show(Request $request, string $publicId)
    {
        $meeting = Meeting::where('public_id', $publicId)->first();
        $state = 'ok';

        if (! $meeting) {
            $state = 'invalid';
        } elseif ($meeting->isEnded()) {
            $state = 'ended';
        } elseif ($meeting->isExpired()) {
            $state = 'expired';
        } elseif ($meeting->access_mode === Meeting::MODE_STAFF) {
            $state = 'staff_only';
        }

        return Inertia::render('Meet/Join', [
            'state' => $state,
            'publicId' => $publicId,
            'meeting' => $meeting ? [
                'title' => $meeting->title,
                'host' => $meeting->host?->displayName(),
                'host_role' => $meeting->show_host_role ? 'Staff at Biiteek' : null,
                'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
                'link_expires_at' => $meeting->link_expires_at?->toIso8601String(),
            ] : null,
            'guestName' => (string) $request->session()->get($this->nameKey($publicId), ''),
        ]);
    }

    public function join(Request $request, string $publicId)
    {
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        abort_if($meeting->isEnded() || $meeting->isExpired() || $meeting->access_mode === Meeting::MODE_STAFF, 403);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:80'],
        ]);

        if (! $request->session()->has($this->idKey($publicId))) {
            $request->session()->put($this->idKey($publicId), \Illuminate\Support\Str::random(16));
        }
        $request->session()->put($this->nameKey($publicId), trim($data['display_name']));

        return redirect()->route('meetings.guest.room', ['publicId' => $publicId]);
    }

    public function room(Request $request, string $publicId)
    {
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        if ($meeting->isEnded() || $meeting->isExpired() || $meeting->access_mode === Meeting::MODE_STAFF) {
            return redirect()->route('meetings.guest', ['publicId' => $publicId]);
        }

        $displayName = (string) $request->session()->get($this->nameKey($publicId), '');
        if ($displayName === '') {
            return redirect()->route('meetings.guest', ['publicId' => $publicId]);
        }

        // Stable identity per browser session so host removals stick.
        $identity = 'guest:' . $request->session()->get($this->idKey($publicId));

        if ($meeting->participants()->where('identity', $identity)->whereNotNull('removed_at')->exists()) {
            return redirect()->route('meetings.guest', ['publicId' => $publicId])
                ->with('error', 'Your access to this meeting was revoked by the host.');
        }

        $meeting->participants()->updateOrCreate(
            ['meeting_id' => $meeting->id, 'identity' => $identity],
            [
                'user_id' => null,
                'display_name' => $displayName,
                'role' => MeetingParticipant::ROLE_PARTICIPANT,
                'joined_at' => now(),
                'left_at' => null,
            ],
        );

        if ($meeting->status === Meeting::STATUS_SCHEDULED) {
            $meeting->update(['status' => Meeting::STATUS_LIVE]);
        }

        $ttl = max(60, (int) now()->diffInSeconds($meeting->link_expires_at, false));

        return Inertia::render('Meetings/Room', [
            'serverUrl' => config('services.livekit.url'),
            'token' => LiveKitService::joinToken($meeting->roomName(), $identity, $displayName, false, $ttl),
            'identity' => $identity,
            'displayName' => $displayName,
            'isHost' => false,
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'public_id' => $meeting->public_id,
                'access_mode' => $meeting->access_mode,
                'status' => $meeting->effectiveStatus(),
                'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
                'link_expires_at' => $meeting->link_expires_at?->toIso8601String(),
                'link' => $meeting->publicLink(),
                'is_host' => false,
                'show_host_role' => (bool) $meeting->show_host_role,
                'host_role' => $meeting->show_host_role ? 'Staff at Biiteek' : null,
                'host' => $meeting->host ? ['id' => $meeting->host->id, 'name' => $meeting->host->displayName()] : null,
            ],
        ]);
    }

    /** Chat history for guests (same shape as the staff endpoint). */
    public function chatHistory(Request $request, string $publicId)
    {
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        abort_if($meeting->isEnded(), 403);

        return response()->json([
            'messages' => $meeting->chatMessages()
                ->orderBy('sent_at')
                ->orderBy('id')
                ->limit(200)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->display_name,
                    'body' => $m->body,
                    'at' => $m->sent_at?->toIso8601String(),
                ]),
        ]);
    }

    /** Persist a guest chat message (realtime delivery travels over the LiveKit data channel). */
    public function chat(Request $request, string $publicId)
    {
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        abort_if($meeting->isEnded() || $meeting->isExpired(), 403);

        $displayName = (string) $request->session()->get($this->nameKey($publicId), '');
        $identity = 'guest:' . (string) $request->session()->get($this->idKey($publicId), '');
        abort_if($displayName === '' || $identity === 'guest:' || $meeting->participants()->where('identity', $identity)->whereNotNull('removed_at')->exists(), 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = $meeting->chatMessages()->create([
            'user_id' => null,
            'display_name' => $displayName,
            'body' => $data['body'],
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'name' => $message->display_name,
                'body' => $message->body,
                'at' => $message->sent_at?->toIso8601String(),
            ],
        ]);
    }

    private function nameKey(string $publicId): string
    {
        return 'meet_guest_name_' . $publicId;
    }

    private function idKey(string $publicId): string
    {
        return 'meet_guest_id_' . $publicId;
    }
}
