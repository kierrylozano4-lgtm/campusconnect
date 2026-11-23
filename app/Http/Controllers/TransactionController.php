<?php

namespace App\Http\Controllers;

use App\Models\SubmissionFile;
use App\Models\Transaction;
use App\Models\TransactionEvidence;
use App\Services\DisputeService;
use App\Services\TransactionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionLifecycleService $transactions,
        private DisputeService $disputes,
    ) {}

    public function start(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('start', $transaction);
        $this->transactions->startWork($transaction, $request->user());

        return back()->with('status', 'Work started successfully.');
    }

    public function submit(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('submit', $transaction);
        $data = $request->validate([
            'description' => ['required', 'string', 'max:5000'],
            'external_links' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,pdf,doc,docx,txt,zip', 'max:20480'],
        ]);

        $submission = $this->transactions->submitWork($transaction, $request->user(), $data['description'], $data['external_links'] ?? null);
        $storedPaths = [];

        try {
            foreach ($request->file('files', []) as $file) {
                $path = $file->store('transaction-submissions/'.$transaction->id.'/'.$submission->id, 'local');

                if (! is_string($path)) {
                    throw new \RuntimeException('A submission file could not be stored.');
                }

                $storedPaths[] = $path;
                $sha256 = hash_file('sha256', (string) $file->getRealPath());

                $submission->files()->create([
                    'uploaded_by' => $request->user()->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize() ?: 0,
                    'sha256' => is_string($sha256) ? $sha256 : hash('sha256', ''),
                ]);
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            $submission->delete();
            throw $exception;
        }

        return back()->with('status', 'Work submitted successfully.');
    }

    public function accept(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('accept', $transaction);
        $request->validate(['confirmed' => ['accepted']]);
        $this->transactions->acceptWork($transaction, $request->user());

        return back()->with('status', 'Work accepted successfully.');
    }

    public function revise(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('revise', $transaction);
        $data = $request->validate(['instructions' => ['required', 'string', 'max:5000']]);
        $this->transactions->requestRevision($transaction, $request->user(), $data['instructions']);

        return back()->with('status', 'Revision requested successfully.');
    }

    public function downloadSubmissionFile(Request $request, SubmissionFile $submissionFile)
    {
        $submission = $submissionFile->submission()->with('transaction')->firstOrFail();
        $transaction = $submission->transaction;

        abort_unless(
            $request->user()->is_admin
            || in_array($request->user()->id, [$transaction->client_id, $transaction->freelancer_id], true),
            403,
        );
        abort_unless(Storage::disk($submissionFile->disk)->exists($submissionFile->path), 404);

        return Storage::disk($submissionFile->disk)->download($submissionFile->path, $submissionFile->original_name);
    }

    public function openDispute(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:Payment problem,Incomplete work,Incorrect deliverables,Freelancer unresponsive,Client unresponsive,Unauthorized cancellation,Agreement violation,Suspected fraud'],
            'explanation' => ['required', 'string', 'max:5000'],
        ]);
        $this->disputes->open($transaction, $request->user(), $data['category'], $data['explanation']);

        return back()->with('status', 'Dispute opened. Payout authorization is suspended.');
    }

    public function addDisputeEvidence(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,txt,zip', 'max:20480'],
        ]);
        $this->disputes->addEvidence($transaction, $request->user(), $data['description'] ?? '', $data['evidence']);

        return back()->with('status', 'Dispute evidence submitted.');
    }

    public function downloadEvidence(Request $request, TransactionEvidence $evidence)
    {
        $dispute = $evidence->dispute()->with('transaction')->firstOrFail();
        $transaction = $dispute->transaction;
        abort_unless(
            $request->user()->is_admin
            || in_array($request->user()->id, [$transaction->client_id, $transaction->freelancer_id], true),
            403,
        );
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name);
    }

    public function acknowledge(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('acknowledge', $transaction);
        $this->transactions->acknowledgeCompletion($transaction, $request->user());

        return back()->with('status', 'Completion acknowledged successfully.');
    }
}
