<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\TransactionDispute;
use App\Models\User;
use App\Models\UserFeedback;
use App\Services\DisputeService;
use App\Services\PayoutService;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'stats' => [
                'users' => User::count(),
                'services' => Service::where('status', 'Active')->count(),
                'requests' => DB::table('service_requests')->count(),
                'completed' => DB::table('service_requests')->where('status', 'Completed')->count(),
            ],
        ]);
    }

    public function users()
    {
        return view('admin.users', [
            'users' => User::with('profile')->latest()->get(),
        ]);
    }

    public function services()
    {
        return view('admin.services', [
            'services' => Service::with('user')->latest()->get(),
        ]);
    }

    public function feedback(): View
    {
        return view('admin.feedback', [
            'feedbackMessages' => UserFeedback::with('user')->latest()->paginate(25),
            'unreadFeedbackCount' => UserFeedback::whereNull('read_at')->count(),
        ]);
    }

    public function updateFeedbackStatus(Request $request, UserFeedback $userFeedback): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:In review,Resolved'],
        ]);

        $userFeedback->update([
            'status' => $data['status'],
            'read_at' => $userFeedback->read_at ?? now(),
        ]);

        return back()->with('status', 'Feedback marked as '.$data['status'].'.');
    }

    public function toggleRole(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'You cannot change your own admin role.');
        $user->is_admin = ! $user->is_admin;
        $user->save();

        return back()->with('status', 'User role updated.');
    }

    public function destroyUser(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'You cannot delete your own account here.');
        $user->delete();

        return back()->with('status', 'User deleted.');
    }

    public function bulkUpdateUsers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'action' => ['required', 'in:promote,demote,delete'],
        ]);

        abort_if(
            in_array($request->user()->id, $data['user_ids']),
            422,
            'You cannot change or delete your own account through bulk actions.'
        );

        $count = DB::transaction(function () use ($data): int {
            $users = User::whereIn('id', $data['user_ids']);

            if ($data['action'] === 'delete') {
                return $users->delete();
            }

            return $users->update(['is_admin' => $data['action'] === 'promote']);
        });

        $message = match ($data['action']) {
            'promote' => $count.' user(s) granted administrator access.',
            'demote' => $count.' user(s) changed to member access.',
            'delete' => $count.' user(s) deleted.',
        };

        return back()->with('status', $message);
    }

    public function toggleService(Request $request, Service $service)
    {
        $data = $request->validate([
            'status' => ['required', 'in:Active,Inactive'],
            'inactive_reason' => ['nullable', 'string', 'max:1000'],
            'hide_from_marketplace' => ['sometimes', 'boolean'],
        ]);

        $hideFromMarketplace = $request->boolean('hide_from_marketplace');

        DB::transaction(function () use ($service, $data, $hideFromMarketplace): void {
            $wasActive = $service->status === 'Active';

            $service->update([
                'status' => $data['status'],
                'inactive_reason' => $data['status'] === 'Inactive' ? ($data['inactive_reason'] ?? 'Moderated by administrator') : null,
                'visible_in_dashboard' => ! $hideFromMarketplace,
            ]);

            if ($wasActive && $data['status'] === 'Inactive') {
                $this->notifyServiceOwner($service, $data['inactive_reason'] ?? null);
            }
        });

        return back()->with('status', 'Service moderation updated.');
    }

    public function bulkToggleServices(Request $request)
    {
        $data = $request->validate([
            'service_ids' => ['required', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'status' => ['required', 'in:Active,Inactive'],
            'inactive_reason' => ['nullable', 'string', 'max:1000'],
            'hide_from_marketplace' => ['sometimes', 'boolean'],
        ]);

        $services = Service::whereIn('id', $data['service_ids'])->get();
        $hideFromMarketplace = $request->boolean('hide_from_marketplace');

        DB::transaction(function () use ($services, $data, $hideFromMarketplace): void {
            foreach ($services as $service) {
                $wasActive = $service->status === 'Active';

                $service->update([
                    'status' => $data['status'],
                    'inactive_reason' => $data['status'] === 'Inactive' ? ($data['inactive_reason'] ?? 'Moderated by administrator') : null,
                    'visible_in_dashboard' => ! $hideFromMarketplace,
                ]);

                if ($wasActive && $data['status'] === 'Inactive') {
                    $this->notifyServiceOwner($service, $data['inactive_reason'] ?? null);
                }
            }
        });

        return back()->with('status', count($services).' service(s) updated.');
    }

    public function destroyService(Service $service)
    {
        $service->delete();

        return back()->with('status', 'Service deleted.');
    }

    public function transactions(Request $request): View
    {
        $filter = $request->query('status');
        $allowedFilters = [
            'pending_payment', 'verified_payment', 'active_project',
            'submitted_work', 'pending_review', 'revision', 'open_dispute',
            'pending_refund', 'pending_payout', 'completed_payout',
        ];

        $query = Transaction::query()
            ->with(['client', 'freelancer', 'serviceRequest.service', 'agreement', 'payments', 'payout', 'refunds', 'dispute']);

        if (in_array($filter, $allowedFilters, true)) {
            $query->when($filter === 'pending_payment', fn ($builder) => $builder->whereIn('payment_status', ['PAYMENT_REQUIRED', 'PROCESSING']))
                ->when($filter === 'verified_payment', fn ($builder) => $builder->where('payment_status', 'VERIFIED'))
                ->when($filter === 'active_project', fn ($builder) => $builder->where('work_status', 'IN_PROGRESS'))
                ->when($filter === 'submitted_work', fn ($builder) => $builder->where('work_status', 'SUBMITTED'))
                ->when($filter === 'pending_review', fn ($builder) => $builder->where('work_status', 'SUBMITTED')->where('confirmation_status', 'PENDING'))
                ->when($filter === 'revision', fn ($builder) => $builder->where('work_status', 'REVISION_REQUIRED'))
                ->when($filter === 'open_dispute', fn ($builder) => $builder->whereIn('dispute_status', ['OPEN', 'EVIDENCE_REQUESTED']))
                ->when($filter === 'pending_refund', fn ($builder) => $builder->whereHas('refunds', fn ($refund) => $refund->whereIn('status', ['REQUESTED', 'PROCESSING'])))
                ->when($filter === 'pending_payout', fn ($builder) => $builder->whereIn('payout_status', ['ELIGIBLE', 'AUTHORIZED']))
                ->when($filter === 'completed_payout', fn ($builder) => $builder->where('payout_status', 'PAID'));
        }

        $transactions = $query->latest()->paginate(25)->withQueryString();

        return view('admin.transactions', [
            'transactions' => $transactions,
            'filter' => in_array($filter, $allowedFilters, true) ? $filter : null,
            'summary' => [
                'total' => Transaction::count(),
                'pending_payment' => Transaction::whereIn('payment_status', ['PAYMENT_REQUIRED', 'PROCESSING'])->count(),
                'verified_payment' => Transaction::where('payment_status', 'VERIFIED')->count(),
                'active_project' => Transaction::where('work_status', 'IN_PROGRESS')->count(),
                'submitted_work' => Transaction::where('work_status', 'SUBMITTED')->count(),
                'pending_review' => Transaction::where('work_status', 'SUBMITTED')->where('confirmation_status', 'PENDING')->count(),
                'revision' => Transaction::where('work_status', 'REVISION_REQUIRED')->count(),
                'open_dispute' => Transaction::whereIn('dispute_status', ['OPEN', 'EVIDENCE_REQUESTED'])->count(),
                'pending_refund' => Transaction::whereHas('refunds', fn ($refund) => $refund->whereIn('status', ['REQUESTED', 'PROCESSING']))->count(),
                'pending_payout' => Transaction::whereIn('payout_status', ['ELIGIBLE', 'AUTHORIZED'])->count(),
                'completed_payout' => Transaction::where('payout_status', 'PAID')->count(),
                'revenue' => Transaction::where('payment_status', 'VERIFIED')->sum('agreed_amount'),
            ],
        ]);
    }

    public function transactionShow(Transaction $transaction): View
    {
        $transaction->load([
            'client', 'freelancer', 'serviceRequest.service', 'agreement', 'payments',
            'submissions.files', 'confirmations', 'revisions', 'dispute.evidence',
            'statusLogs.actor', 'payout', 'refunds', 'ledgerEntries',
        ]);

        return view('admin.transaction-show', compact('transaction'));
    }

    public function disputes()
    {
        return view('admin.disputes', [
            'disputes' => TransactionDispute::query()
                ->with(['transaction.client', 'transaction.freelancer', 'evidence'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function financials()
    {
        return view('admin.financials', [
            'transactions' => Transaction::query()
                ->with(['client', 'freelancer', 'payout', 'refunds', 'ledgerEntries'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function authorizePayout(Request $request, Transaction $transaction, PayoutService $payouts): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);
        $payouts->authorize($transaction, $request->user(), $data['note']);

        return back()->with('status', 'Payout authorized.');
    }

    public function completePayout(Request $request, Transaction $transaction, PayoutService $payouts): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);
        $payouts->complete($transaction, $request->user(), $data['note']);

        return back()->with('status', 'Payout completion recorded.');
    }

    public function requestRefund(Request $request, Transaction $transaction, RefundService $refunds): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        $refunds->request($transaction, $request->user(), (float) $data['amount'], $data['reason']);

        return back()->with('status', 'Refund request recorded.');
    }

    public function completeRefund(Request $request, Transaction $transaction, RefundService $refunds): RedirectResponse
    {
        $data = $request->validate([
            'provider_reference' => ['required', 'string', 'max:150'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        $refunds->complete($transaction, $request->user(), $data['provider_reference'], $data['reason']);

        return back()->with('status', 'Refund completion recorded.');
    }

    public function resolveDispute(Request $request, Transaction $transaction, DisputeService $disputes): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:full_refund,freelancer_payout,partial_refund_payout,request_evidence,other'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        $disputes->resolve($transaction, $request->user(), $data['action'], $data['reason']);

        return back()->with('status', 'Dispute resolution recorded.');
    }

    public function storeAnnouncement(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);
        DB::table('announcements')->insert(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return back()->with('status', 'Announcement published.');
    }

    private function notifyServiceOwner(Service $service, ?string $reason): void
    {
        $message = 'Your service "'.$service->title.'" was deactivated by an administrator.';
        $reason = trim((string) $reason);

        if ($reason !== '') {
            $message .= ' Reason: '.$reason;
        }

        Notification::create([
            'user_id' => $service->user_id,
            'message' => $message,
            'link' => route('profile.edit', ['tab' => 'notifications']),
            'is_read' => false,
        ]);
    }
}
