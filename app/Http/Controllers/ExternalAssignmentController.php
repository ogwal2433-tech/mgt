<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentDeliverable;
use App\Models\AssignmentRecipient;
use App\Support\NotificationService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Public, tokenized assignment flow for external recipients.
 * No account required — access is granted only through the recipient token.
 */
class ExternalAssignmentController extends Controller
{
    public function show(Request $request, string $token)
    {
        $recipient = $this->findRecipient($token);
        $assignment = $recipient->assignment;

        if (! $recipient->viewed_at) {
            $recipient->update(['viewed_at' => now()]);
        }

        return Inertia::render('Assignments/External', [
            'assignment' => $this->payload($assignment, $recipient),
            'company' => Settings::company(),
            'token' => $token,
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $recipient = $this->findRecipient($token);
        $assignment = $recipient->assignment;

        if ($assignment->isClosed()) {
            return back()->withErrors(['files' => 'This assignment has been closed by the sender.']);
        }

        if ($assignment->isPastDue()) {
            return back()->withErrors(['files' => 'The deadline for this assignment has passed — submissions are no longer accepted.']);
        }

        $rules = [
            'signer_name' => ['required', 'string', 'max:150'],
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,'],
            'files' => $assignment->require_deliverables
                ? ['required', 'array', 'min:1', 'max:10']
                : ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,png,jpg,jpeg,txt,md,csv'],
            'part_ids' => ['nullable', 'array', 'max:10'],
            'part_ids.*' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];

        $data = $request->validate($rules, [
            'signature.required' => 'Please sign the form before submitting.',
            'signature.starts_with' => 'The signature must be drawn in the signature box.',
            'files.required' => 'This assignment requires deliverable files — please attach at least one document.',
            'files.min' => 'This assignment requires deliverable files — please attach at least one document.',
        ]);

        // Store the signature image.
        if (! preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $data['signature'], $m)) {
            return back()->withErrors(['signature' => 'The signature image is not valid. Please sign again.']);
        }
        $binary = base64_decode($m[1], true);
        if ($binary === false || strlen($binary) < 100) {
            return back()->withErrors(['signature' => 'The signature image could not be read. Please sign again.']);
        }
        if (strlen($binary) > 400000) {
            return back()->withErrors(['signature' => 'The signature image is too large. Please sign again.']);
        }
        $signaturePath = 'assignments/' . $recipient->id . '/signature-' . \Illuminate\Support\Str::random(16) . '.png';
        Storage::disk('local')->put($signaturePath, $binary);

        $files = $request->file('files', []);
        $partIds = $request->input('part_ids', []);
        if ($assignment->require_deliverables && count($files) === 0) {
            return back()->withErrors(['files' => 'This assignment requires deliverable files — please attach at least one document.']);
        }

        $validPartIds = $assignment->parts->pluck('id')->all();

        foreach (array_values($files) as $i => $file) {
            $partId = isset($partIds[$i]) && in_array((int) $partIds[$i], $validPartIds, true) ? (int) $partIds[$i] : null;

            $path = $file->storeAs(
                'assignments/' . $recipient->id,
                Str::random(24) . '.' . strtolower($file->getClientOriginalExtension() ?: 'bin'),
                'local',
            );

            AssignmentDeliverable::create([
                'assignment_recipient_id' => $recipient->id,
                'assignment_part_id' => $partId,
                'original_filename' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
                'note' => $data['note'] ?? null,
                'uploaded_at' => now(),
            ]);
        }

        $recipient->update([
            'submitted_at' => now(),
            'signer_name' => trim($data['signer_name']),
            'signature_path' => $signaturePath,
            'signed_at' => now(),
        ]);

        // Notify the sender and everyone copied: in-app notification + email.
        $who = trim($recipient->name ?: $recipient->email);
        $fileCount = count($files);
        $notifyMessage = $who . ' submitted ' . ($fileCount > 0
            ? $fileCount . ' deliverable file(s)'
            : 'a signed response')
            . ' for "' . $assignment->title . '"'
            . ($data['note'] ?? '' ? '. Note: ' . \Illuminate\Support\Str::limit((string) $data['note'], 120) : '.');

        $targets = collect([$assignment->creator])
            ->merge($assignment->recipients()->where('kind', AssignmentRecipient::KIND_CC)->with('user')->get()->pluck('user'))
            ->filter()
            ->unique('id');

        foreach ($targets as $target) {
            NotificationService::notify(
                $target,
                'assignment.submitted',
                'Deliverables received: ' . $assignment->title,
                $notifyMessage,
                '/assignments/' . $assignment->id,
                ['assignment_id' => $assignment->id, 'recipient_id' => $recipient->id],
                true,
            );
        }

        return back()->with('success', 'Your deliverables have been submitted. Thank you.');
    }

    /** Download the assignment brief as a clean PDF (public, tokenized). */
    public function pdf(string $token)
    {
        $recipient = $this->findRecipient($token);
        $assignment = $recipient->assignment;
        $assignment->load('creator:id,name', 'parts');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.assignment-brief', [
            'company' => Settings::company(),
            'assignment' => $assignment,
        ]);

        return $pdf->download(($assignment->reference_number ?: 'assignment') . '.pdf');
    }

    /* ------------------------------------------------------------------ */

    private function findRecipient(string $token): AssignmentRecipient
    {
        return AssignmentRecipient::query()
            ->where('token', $token)
            ->where('kind', AssignmentRecipient::KIND_TO)
            ->with('assignment.creator')
            ->firstOrFail();
    }

    private function payload(Assignment $assignment, AssignmentRecipient $recipient): array
    {
        $deliverables = $recipient->deliverables()->with('part')->orderBy('uploaded_at')->get();

        return [
            'title' => $assignment->title,
            'reference_number' => $assignment->reference_number,
            'body' => $assignment->body,
            'due_at' => $assignment->due_at?->toIso8601String(),
            'due_label' => $assignment->due_at?->format('D d M Y, H:i'),
            'is_past_due' => $assignment->isPastDue(),
            'is_closed' => $assignment->isClosed(),
            'require_deliverables' => (bool) $assignment->require_deliverables,
            'can_submit' => ! $assignment->isClosed() && ! $assignment->isPastDue(),
            'submitted_at' => $recipient->submitted_at?->toIso8601String(),
            'signer_name' => $recipient->signer_name,
            'signed_at' => $recipient->signed_at?->toIso8601String(),
            'has_signature' => (bool) $recipient->signature_path,
            'sender_name' => $assignment->creator?->displayName(),
            'parts' => $assignment->parts->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'files' => $deliverables->where('assignment_part_id', $p->id)->values()->map(fn ($d) => [
                    'filename' => $d->original_filename,
                    'size' => $d->size,
                    'note' => $d->note,
                    'uploaded_at' => $d->uploaded_at?->toIso8601String(),
                ]),
            ])->values(),
            'deliverables' => $deliverables->map(fn ($d) => [
                'filename' => $d->original_filename,
                'size' => $d->size,
                'note' => $d->note,
                'part_id' => $d->assignment_part_id,
                'part_title' => $d->part?->title,
                'uploaded_at' => $d->uploaded_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
