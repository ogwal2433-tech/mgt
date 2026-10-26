<?php

namespace App\Http\Controllers;

use App\Models\DocumentSubmission;
use App\Models\DocumentSubmissionFile;
use App\Support\AuditLogger;
use App\Support\DocumentService;
use App\Support\NotificationService;
use App\Support\Permissions;
use App\Support\Sequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Employee document submissions to HR.
 * Any employee can submit documents and follow their status
 * (accepted / rejected). HR reviews them.
 */
class DocumentSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canReview = $user->hasPermission(Permissions::DOCS_REVIEW);

        $query = DocumentSubmission::with(['employee:id,employee_code,first_name,last_name', 'user:id,username', 'reviewer:id,username', 'files.document']);

        if (! $canReview) {
            $query->where('user_id', $user->id);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $submissions = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Documents/Index', [
            'submissions' => $submissions,
            'filters' => $request->only(['search', 'status']),
            'canReview' => $canReview,
            'pendingCount' => $canReview
                ? DocumentSubmission::where('status', 'submitted')->count()
                : DocumentSubmission::where('user_id', $user->id)->where('status', 'submitted')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['general', 'certificate', 'identification', 'claim', 'contract', 'leave', 'other'])],
            'notes' => ['nullable', 'string', 'max:4000'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,txt,zip,ppt,pptx,odt,rtf'],
        ]);

        $submission = DB::transaction(function () use ($request, $data, $user) {
            $submission = DocumentSubmission::create([
                'reference_number' => Sequence::next('document_submission', 'DOC', 4),
                'user_id' => $user->id,
                'employee_id' => $user->employee_id,
                'title' => $data['title'],
                'category' => $data['category'],
                'notes' => $data['notes'] ?? null,
                'status' => 'submitted',
            ]);

            foreach ($request->file('files', []) as $file) {
                try {
                    $document = DocumentService::store($file, 'employee_submission', $submission);
                } catch (\RuntimeException $e) {
                    continue;
                }

                DocumentSubmissionFile::create([
                    'document_submission_id' => $submission->id,
                    'document_id' => $document->id,
                ]);
            }

            AuditLogger::log('document_submission.created', DocumentSubmission::class, $submission->id,
                "Submitted documents to HR: {$submission->title}");

            return $submission;
        });

        // Tell HR there is something to review.
        foreach ($this->reviewers() as $reviewer) {
            NotificationService::notify($reviewer, 'document_submission.submitted',
                'New document submission',
                "{$user->displayName()} submitted \"{$submission->title}\" ({$submission->reference_number}) for review.",
                '/documents-submissions', ['submission_id' => $submission->id]);
        }

        return back()->with('success', "Documents submitted to HR ({$submission->reference_number}). You can follow the status here.");
    }

    /**
     * Users allowed to review employee document submissions (HR).
     */
    private function reviewers()
    {
        return \App\Models\User::active()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'hr'))
            ->get();
    }

    public function show(DocumentSubmission $document_submission)
    {
        $user = request()->user();

        if (! $user->hasPermission(Permissions::DOCS_REVIEW) && $document_submission->user_id !== $user->id) {
            abort(403, 'You can only view your own submissions.');
        }

        $document_submission->load(['employee:id,employee_code,first_name,last_name', 'user:id,username', 'reviewer:id,username', 'files.document']);

        return Inertia::render('Documents/Show', [
            'submission' => $document_submission,
            'canReview' => $user->hasPermission(Permissions::DOCS_REVIEW),
        ]);
    }

    public function review(Request $request, DocumentSubmission $document_submission)
    {
        if (! $request->user()->hasPermission(Permissions::DOCS_REVIEW)) {
            abort(403);
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'review_comments' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ], [
            'review_comments.required_if' => 'A reason is mandatory when rejecting documents.',
        ]);

        $document_submission->update([
            'status' => $data['decision'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_comments' => $data['review_comments'] ?? null,
        ]);

        AuditLogger::log('document_submission.' . $data['decision'], DocumentSubmission::class, $document_submission->id,
            "Document submission {$data['decision']}", ['status' => 'submitted'], ['status' => $data['decision']]);

        $owner = $document_submission->user;
        if ($owner) {
            NotificationService::notify($owner, 'document_submission.' . $data['decision'],
                'Your documents were ' . $data['decision'],
                "Your submission {$document_submission->reference_number} ({$document_submission->title}) was {$data['decision']}."
                    . ($data['review_comments'] ? " Reviewer said: {$data['review_comments']}" : ''),
                '/documents-submissions', ['submission_id' => $document_submission->id]);
        }

        return back()->with('success', 'Documents ' . $data['decision'] . '. The employee has been notified.');
    }
}
