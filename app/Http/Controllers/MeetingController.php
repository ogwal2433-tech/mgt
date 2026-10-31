<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingChatMessage;
use App\Models\MeetingInvitation;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Notifications\MeetingInviteMail;
use App\Services\LiveKitService;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $meetings = Meeting::query()
            ->where(function ($q) use ($user) {
                $q->where('host_user_id', $user->id)
                    ->orWhereHas('invitations', fn ($i) => $i->where('user_id', $user->id));
            })
            ->with(['host:id,name,username', 'invitations.user:id,name'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Meeting $m) => $this->present($m));

        return Inertia::render('Meetings/Index', [
            'meetings' => $meetings,
            'staffOptions' => $this->staffOptions(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission(Permissions::MEETINGS_CREATE), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'access_mode' => ['required', 'in:' . Meeting::MODE_GUEST . ',' . Meeting::MODE_STAFF],
            'scheduled_at' => ['nullable', 'date'],
            'expires_in' => ['required', 'integer', 'in:60,240,1440,10080,43200'],
            'invitee_ids' => ['array', 'max:100'],
            'invitee_ids.*' => ['integer', 'exists:users,id'],
            'guest_emails' => ['array', 'max:100'],
            'guest_emails.*' => ['email:rfc', 'max:190'],
        ]);

        $meeting = Meeting::create([
            'title' => $data['title'],
            'host_user_id' => $request->user()->id,
            'access_mode' => $data['access_mode'],
            'scheduled_at' => $data['scheduled_at'] ?? now(),
            'link_expires_at' => now()->addMinutes((int) $data['expires_in']),
            'status' => Meeting::STATUS_SCHEDULED,
        ]);

        $link = $meeting->publicLink();
        $hostName = $request->user()->displayName();
        $expiresLabel = $meeting->link_expires_at->format('D d M Y, H:i');
        $scheduledLabel = $meeting->scheduled_at?->format('D d M Y, H:i');
        $accessLabel = $meeting->access_mode === Meeting::MODE_STAFF
            ? 'Biiteek Global staff only (sign-in required)'
            : 'Anyone with the link can join (display name only)';

        $staff = User::query()
            ->whereIn('id', $request->input('invitee_ids', []))
            ->where('is_active', true)
            ->get();

        foreach ($staff as $invitee) {
            MeetingInvitation::updateOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $invitee->id],
                ['email' => $invitee->email, 'notified_at' => now()],
            );

            NotificationService::notify(
                $invitee,
                'meeting.invited',
                'Meeting invitation: ' . $meeting->title,
                $hostName . ' invited you to a meeting. It starts ' . ($scheduledLabel ?? 'now') . ' and the link is valid until ' . $expiresLabel . '.',
                '/meetings/' . $meeting->id,
                ['meeting_id' => $meeting->id, 'link' => $link],
                false,
            );

            NotificationService::sendMailable(
                $invitee,
                new MeetingInviteMail($meeting->title, $hostName, $link, $expiresLabel, $scheduledLabel, $accessLabel),
                'Invitation: ' . $meeting->title,
                'meeting.invited',
                Meeting::class,
                $meeting->id,
            );
        }

        foreach (array_unique(array_filter($request->input('guest_emails', []))) as $email) {
            MeetingInvitation::updateOrCreate(
                ['meeting_id' => $meeting->id, 'email' => $email],
                ['notified_at' => now()],
            );

            NotificationService::sendEmailTo(
                $email,
                new MeetingInviteMail($meeting->title, $hostName, $link, $expiresLabel, $scheduledLabel, $accessLabel),
                'Invitation: ' . $meeting->title,
                'meeting.invited',
                Meeting::class,
                $meeting->id,
            );
        }

        AuditLogger::log('meeting.created', Meeting::class, $meeting->id, "Created meeting \"{$meeting->title}\" ({$meeting->access_mode})");

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Meeting created. The invitation link is ready to share.');
    }

    public function show(Request $request, Meeting $meeting)
    {
        $this->authorizeView($request, $meeting);

        $meeting->load(['host:id,name,username', 'invitations.user:id,name,email', 'participants' => fn ($q) => $q->orderBy('id')]);

        return Inertia::render('Meetings/Show', [
            'meeting' => $this->present($meeting, true),
            'staffOptions' => $this->staffOptions(),
        ]);
    }

    /** Mint a token and render the conference room for an authenticated staff member. */
    public function room(Request $request, Meeting $meeting)
    {
        $this->authorizeView($request, $meeting);

        $state = $meeting->effectiveStatus();
        if (in_array($state, [Meeting::STATUS_ENDED, Meeting::STATUS_EXPIRED], true)) {
            return redirect()->route('meetings.show', $meeting)
                ->with('error', $state === Meeting::STATUS_ENDED ? 'This meeting has ended.' : 'This meeting link has expired.');
        }

        $user = $request->user();
        $identity = 'user:' . $user->id;

        if ($this->isRemoved($meeting, $identity)) {
            return redirect()->route('meetings.show', $meeting)
                ->with('error', 'Your access to this meeting was revoked by the host.');
        }

        return $this->renderRoom($meeting, $identity, $user->displayName(), $meeting->isHost($user) || $user->hasPermission(Permissions::MEETINGS_MANAGE), $user->id);
    }

    /** Invite more people after creation (staff in-app + email, guests by email). */
    public function invite(Request $request, Meeting $meeting)
    {
        $this->authorizeView($request, $meeting);

        $data = $request->validate([
            'invitee_ids' => ['array', 'max:100'],
            'invitee_ids.*' => ['integer', 'exists:users,id'],
            'guest_emails' => ['array', 'max:100'],
            'guest_emails.*' => ['email:rfc', 'max:190'],
        ]);

        $hostName = $request->user()->displayName();
        $expiresLabel = $meeting->link_expires_at->format('D d M Y, H:i');
        $scheduledLabel = $meeting->scheduled_at?->format('D d M Y, H:i');
        $accessLabel = $meeting->access_mode === Meeting::MODE_STAFF
            ? 'Biiteek Global staff only (sign-in required)'
            : 'Anyone with the link can join (display name only)';
        $link = $meeting->publicLink();

        $added = 0;

        $staff = User::query()
            ->whereIn('id', $data['invitee_ids'] ?? [])
            ->where('is_active', true)
            ->whereNotIn('id', $meeting->invitations()->whereNotNull('user_id')->pluck('user_id'))
            ->get();

        foreach ($staff as $invitee) {
            MeetingInvitation::updateOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $invitee->id],
                ['email' => $invitee->email, 'notified_at' => now()],
            );

            NotificationService::notify(
                $invitee,
                'meeting.invited',
                'Meeting invitation: ' . $meeting->title,
                $hostName . ' invited you to a meeting. The link is valid until ' . $expiresLabel . '.',
                '/meetings/' . $meeting->id,
                ['meeting_id' => $meeting->id, 'link' => $link],
                false,
            );

            NotificationService::sendMailable(
                $invitee,
                new MeetingInviteMail($meeting->title, $hostName, $link, $expiresLabel, $scheduledLabel, $accessLabel),
                'Invitation: ' . $meeting->title,
                'meeting.invited',
                Meeting::class,
                $meeting->id,
            );
            $added++;
        }

        foreach (array_unique(array_filter($data['guest_emails'] ?? [])) as $email) {
            $exists = $meeting->invitations()->where('email', $email)->exists();
            MeetingInvitation::updateOrCreate(
                ['meeting_id' => $meeting->id, 'email' => $email],
                ['notified_at' => now()],
            );

            if (! $exists) {
                NotificationService::sendEmailTo(
                    $email,
                    new MeetingInviteMail($meeting->title, $hostName, $link, $expiresLabel, $scheduledLabel, $accessLabel),
                    'Invitation: ' . $meeting->title,
                    'meeting.invited',
                    Meeting::class,
                    $meeting->id,
                );
                $added++;
            }
        }

        return back()->with('success', $added > 0
            ? "Invitation sent to {$added} recipient(s) with the join link."
            : 'Those recipients were already invited.');
    }

    public function end(Request $request, Meeting $meeting)
    {
        $user = $request->user();
        abort_unless($meeting->isHost($user) || $user->hasPermission(Permissions::MEETINGS_MANAGE), 403);

        if (! $meeting->isEnded()) {
            try {
                LiveKitService::deleteRoom($meeting->roomName());
            } catch (\Throwable $e) {
                report($e);
            }

            $meeting->update(['status' => Meeting::STATUS_ENDED, 'ended_at' => now()]);
            AuditLogger::log('meeting.ended', Meeting::class, $meeting->id, "Ended meeting \"{$meeting->title}\" for everyone");
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Meeting ended for everyone.');
    }

    public function removeParticipant(Request $request, Meeting $meeting, string $identity)
    {
        $user = $request->user();
        abort_unless($meeting->isHost($user) || $user->hasPermission(Permissions::MEETINGS_MANAGE), 403);

        $participant = $meeting->participants()->where('identity', $identity)->firstOrFail();

        if (! $participant->isRemoved()) {
            try {
                LiveKitService::removeParticipant($meeting->roomName(), $participant->identity);
            } catch (\Throwable $e) {
                report($e);
            }

            $participant->update(['removed_at' => now(), 'removed_by' => $user->id, 'left_at' => now()]);
            AuditLogger::log('meeting.participant_removed', MeetingParticipant::class, $participant->id, "Removed {$participant->display_name} from \"{$meeting->title}\"");
        }

        return back()->with('success', $participant->display_name . ' was removed and cannot rejoin.');
    }

    public function chatIndex(Meeting $meeting)
    {
        return response()->json([
            'messages' => $meeting->chatMessages()
                ->orderBy('sent_at')
                ->orderBy('id')
                ->limit(200)
                ->get()
                ->map(fn (MeetingChatMessage $m) => [
                    'id' => $m->id,
                    'name' => $m->display_name,
                    'body' => $m->body,
                    'at' => $m->sent_at?->toIso8601String(),
                ]),
        ]);
    }

    public function chatStore(Request $request, Meeting $meeting)
    {
        $this->authorizeView($request, $meeting);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = $meeting->chatMessages()->create([
            'user_id' => $request->user()?->id,
            'display_name' => $request->user()?->displayName() ?? (string) $request->input('display_name', 'Guest'),
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

    /* ------------------------------------------------------------------
     * Internals
     * ------------------------------------------------------------------ */

    private function authorizeView(Request $request, Meeting $meeting): void
    {
        $user = $request->user();
        $allowed = $meeting->isHost($user)
            || $user->hasPermission(Permissions::MEETINGS_MANAGE)
            || $meeting->access_mode === Meeting::MODE_GUEST
            || $meeting->invitations()->where('user_id', $user->id)->exists();

        abort_unless($allowed, 403);
    }

    private function isRemoved(Meeting $meeting, string $identity): bool
    {
        return $meeting->participants()
            ->where('identity', $identity)
            ->whereNotNull('removed_at')
            ->exists();
    }

    private function renderRoom(Meeting $meeting, string $identity, string $displayName, bool $isHost, ?int $userId = null)
    {
        $meeting->participants()->updateOrCreate(
            ['meeting_id' => $meeting->id, 'identity' => $identity],
            [
                'user_id' => $userId,
                'display_name' => $displayName,
                'role' => $isHost ? MeetingParticipant::ROLE_HOST : MeetingParticipant::ROLE_PARTICIPANT,
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
            'token' => LiveKitService::joinToken($meeting->roomName(), $identity, $displayName, $isHost, $ttl),
            'identity' => $identity,
            'displayName' => $displayName,
            'isHost' => $isHost,
            'meeting' => $this->present($meeting, true),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function staffOptions(): array
    {
        return User::query()
            ->where('is_active', true)
            ->with('roles:id,name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'employee_id'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->displayName(),
                'email' => $u->email,
                'roles' => $u->roles->pluck('name'),
            ])
            ->values()
            ->all();
    }

    private function present(Meeting $meeting, bool $detailed = false): array
    {
        $base = [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'public_id' => $meeting->public_id,
            'access_mode' => $meeting->access_mode,
            'status' => $meeting->effectiveStatus(),
            'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
            'link_expires_at' => $meeting->link_expires_at?->toIso8601String(),
            'link' => $meeting->publicLink(),
            'is_host' => $meeting->host_user_id === request()->user()?->id,
            'host' => $meeting->host ? ['id' => $meeting->host->id, 'name' => $meeting->host->displayName()] : null,
        ];

        if (! $detailed) {
            return $base;
        }

        return array_merge($base, [
            'invitations' => $meeting->invitations->map(fn (MeetingInvitation $i) => [
                'id' => $i->id,
                'name' => $i->user?->displayName() ?? $i->email,
                'email' => $i->user?->email ?? $i->email,
                'is_guest' => $i->user_id === null,
            ])->values(),
            'participants' => $meeting->participants->map(fn (MeetingParticipant $p) => [
                'id' => $p->id,
                'identity' => $p->identity,
                'display_name' => $p->display_name,
                'role' => $p->role,
                'removed_at' => $p->removed_at?->toIso8601String(),
                'joined_at' => $p->joined_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
