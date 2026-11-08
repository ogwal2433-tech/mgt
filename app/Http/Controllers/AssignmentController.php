<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentDeliverable;
use App\Models\AssignmentRecipient;
use App\Models\User;
use App\Notifications\AssignmentInviteMail;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $search = trim((string) $request->string('search'));

        $assignments = Assignment::query()
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('recipients', fn ($r) => $r->where('user_id', $user->id));
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('title', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('recipients', function ($r) use ($search) {
                            $r->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->with(['recipients'])
            ->withCount(['recipients as recipient_count', 'recipients as submitted_count' => fn ($q) => $q->whereNotNull('submitted_at')])
            ->orderByDesc('due_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Assignment $a) => $this->present($a));

        return Inertia::render('Assignments/Index', [
            'assignments' => $assignments,
            'staffOptions' => $this->staffOptions(),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission(Permissions::ASSIGNMENTS_CREATE), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'due_at' => ['required', 'date', 'after:now'],
            'require_deliverables' => ['nullable', 'boolean'],
            'recipient_emails' => ['required', 'array', 'min:1', 'max:100'],
            'recipient_emails.*' => ['email:rfc', 'max:190'],
            'cc_user_ids' => ['array', 'max:100'],
            'cc_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $dueAt = \Illuminate\Support\Carbon::parse($data['due_at']);
        $dueLabel = $dueAt->format('D d M Y, H:i');
        $nextId = (int) (Assignment::max('id') ?? 0) + 1;

        $assignment = Assignment::create([
            'reference_number' => 'ASN-' . now()->format('Y') . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT),
            'title' => $data['title'],
            'body' => $data['body'],
            'require_deliverables' => (bool) $request->input('require_deliverables', false),
            'created_by' => $user->id,
            'due_at' => $dueAt,
            'status' => Assignment::STATUS_SENT,
        ]);

        foreach (array_unique(array_filter($data['recipient_emails'])) as $email) {
            $recipient = AssignmentRecipient::create([
                'assignment_id' => $assignment->id,
                'kind' => AssignmentRecipient::KIND_TO,
                'email' => $email,
                'token' => Str::random(48),
                'sent_at' => now(),
            ]);

            NotificationService::sendEmailTo(
                $email,
                new AssignmentInviteMail(
                    $assignment->title,
                    $assignment->body,
                    url('/assignment/' . $recipient->token),
                    $dueLabel,
                    '',
                    false,
                    $assignment->reference_number,
                ),
                'Assignment: ' . $assignment->title,
                'assignment.sent',
                Assignment::class,
                $assignment->id,
            );
        }

        $ccUsers = User::query()
            ->whereIn('id', $data['cc_user_ids'] ?? [])
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->get();

        foreach ($ccUsers as $cc) {
            AssignmentRecipient::create([
                'assignment_id' => $assignment->id,
                'kind' => AssignmentRecipient::KIND_CC,
                'email' => $cc->email,
                'name' => $cc->displayName(),
                'user_id' => $cc->id,
                'token' => Str::random(48),
                'sent_at' => now(),
            ]);

            NotificationService::notify(
                $cc,
                'assignment.cc',
                'CC: ' . $assignment->title,
                $user->displayName() . ' sent an assignment and copied you. Due ' . $dueLabel . '.',
                '/assignments/' . $assignment->id,
                ['assignment_id' => $assignment->id],
                false,
            );

            NotificationService::sendMailable(
                $cc,
                new AssignmentInviteMail(
                    $assignment->title,
                    $assignment->body,
                    url('/assignments/' . $assignment->id),
                    $dueLabel,
                    $cc->displayName(),
                    true,
                    $assignment->reference_number,
                ),
                'CC: Assignment: ' . $assignment->title,
                'assignment.cc',
                Assignment::class,
                $assignment->id,
            );
        }

        AuditLogger::log('assignment.created', Assignment::class, $assignment->id, "Sent assignment \"{$assignment->title}\"");

        return redirect()->route('assignments.show', $assignment)
            ->with('success', 'Assignment sent to ' . count($data['recipient_emails']) . ' recipient(s)'
                . (count($data['cc_user_ids'] ?? []) ? ' and ' . count($data['cc_user_ids']) . ' copied.' : '.'));
    }

    public function show(Request $request, Assignment $assignment)
    {
        $this->authorizeView($request, $assignment);

        $assignment->load(['creator:id,name', 'recipients' => fn ($q) => $q->orderBy('kind')->orderBy('id'), 'recipients.deliverables']);

        return Inertia::render('Assignments/Show', [
            'assignment' => $this->present($assignment, true),
        ]);
    }

    public function close(Request $request, Assignment $assignment)
    {
        $user = $request->user();
        abort_unless($assignment->created_by === $user->id || $user->hasPermission(Permissions::ASSIGNMENTS_MANAGE), 403);

        $assignment->update(['status' => Assignment::STATUS_CLOSED, 'closed_at' => now()]);
        AuditLogger::log('assignment.closed', Assignment::class, $assignment->id, "Closed assignment \"{$assignment->title}\"");

        return back()->with('success', 'Assignment closed — external submission links are now disabled.');
    }

    public function download(Request $request, Assignment $assignment, AssignmentDeliverable $deliverable)
    {
        $this->authorizeView($request, $assignment);
        abort_unless($deliverable->recipient->assignment_id === $assignment->id, 404);

        return Storage::disk('local')->download($deliverable->path, $deliverable->original_filename, [
            'Content-Type' => $deliverable->mime_type ?: 'application/octet-stream',
        ]);
    }

    /** Stream the deliverable inline so reviewers can view it in the browser. */
    public function view(Request $request, Assignment $assignment, AssignmentDeliverable $deliverable)
    {
        $this->authorizeView($request, $assignment);
        abort_unless($deliverable->recipient->assignment_id === $assignment->id, 404);
        abort_unless(Storage::disk('local')->exists($deliverable->path), 404);

        return response()->file(Storage::disk('local')->path($deliverable->path), [
            'Content-Type' => $deliverable->mime_type ?: 'application/octet-stream',
        ]);
    }

    /** Stream a recipient's signature image inline. */
    public function signature(Request $request, Assignment $assignment, AssignmentRecipient $recipient)
    {
        $this->authorizeView($request, $assignment);
        abort_unless($recipient->assignment_id === $assignment->id, 404);
        abort_unless($recipient->signature_path && Storage::disk('local')->exists($recipient->signature_path), 404);

        return response()->file(Storage::disk('local')->path($recipient->signature_path), [
            'Content-Type' => 'image/png',
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function authorizeView(Request $request, Assignment $assignment): void
    {
        $user = $request->user();
        $allowed = $assignment->created_by === $user->id
            || $user->hasPermission(Permissions::ASSIGNMENTS_MANAGE)
            || $assignment->recipients()->where('user_id', $user->id)->exists();

        abort_unless($allowed, 403);
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

    private function present(Assignment $assignment, bool $detailed = false): array
    {
        $base = [
            'id' => $assignment->id,
            'reference_number' => $assignment->reference_number,
            'title' => $assignment->title,
            'require_deliverables' => (bool) $assignment->require_deliverables,
            'status' => $assignment->isPastDue() && $assignment->status === Assignment::STATUS_SENT ? 'overdue' : $assignment->status,
            'due_at' => $assignment->due_at?->toIso8601String(),
            'is_past_due' => $assignment->isPastDue(),
            'created_by' => $assignment->creator ? ['id' => $assignment->creator->id, 'name' => $assignment->creator->displayName()] : null,
            'recipient_count' => (int) ($assignment->recipient_count ?? $assignment->recipients->count()),
            'submitted_count' => (int) ($assignment->submitted_count ?? $assignment->recipients->whereNotNull('submitted_at')->count()),
        ];

        if (! $detailed) {
            return $base;
        }

        return array_merge($base, [
            'body' => $assignment->body,
            'recipients' => $assignment->recipients->map(fn (AssignmentRecipient $r) => [
                'id' => $r->id,
                'kind' => $r->kind,
                'email' => $r->email,
                'name' => $r->name,
                'user_id' => $r->user_id,
                'external_link' => $r->kind === AssignmentRecipient::KIND_TO ? url('/assignment/' . $r->token) : null,
                'viewed_at' => $r->viewed_at?->toIso8601String(),
                'submitted_at' => $r->submitted_at?->toIso8601String(),
                'signer_name' => $r->signer_name,
                'signed_at' => $r->signed_at?->toIso8601String(),
                'has_signature' => (bool) $r->signature_path,
                'deliverables' => $r->deliverables->map(fn (AssignmentDeliverable $d) => [
                    'id' => $d->id,
                    'filename' => $d->original_filename,
                    'size' => $d->size,
                    'note' => $d->note,
                    'uploaded_at' => $d->uploaded_at?->toIso8601String(),
                ])->values(),
            ])->values(),
        ]);
    }
}
