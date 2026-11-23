<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ConversationMessenger;
use App\Services\TransactionLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(
        private ConversationMessenger $messenger,
        private TransactionLifecycleService $transactions,
    ) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $category = $request->query('category');
        $user = $request->user();

        $services = Service::with('user')
            ->where('status', 'Active')
            ->where('visible_in_dashboard', true)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->latest()
            ->get();

        $reviewsByUser = fn ($query) => $query->where('reviewer_id', $user->id);
        $incoming = ServiceRequest::with(['service', 'requester', 'reviews' => $reviewsByUser])
            ->where('provider_id', $user->id)
            ->latest()
            ->get();
        $outgoing = ServiceRequest::with(['service', 'provider', 'reviews' => $reviewsByUser])
            ->where('requester_id', $user->id)
            ->latest()
            ->get();

        return view('dashboard.marketplace', [
            'services' => $services,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'categories' => ['Academic', 'Personal', 'Tech'],
            'search' => $search,
            'category' => $category,
            'stats' => [
                'users' => User::count(),
                'services' => Service::where('status', 'Active')->count(),
                'requests' => $incoming->where('status', 'Pending')->count(),
            ],
        ]);
    }

    public function showService(Service $service)
    {
        abort_unless($service->status === 'Active' && $service->visible_in_dashboard, 404);

        $service->load('user');

        return view('services.show', compact('service'));
    }

    public function storeService(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:Academic,Personal,Tech'],
            'description' => ['required', 'string', 'max:5000'],
            'price' => [Rule::requiredIf(fn (): bool => ! $request->boolean('price_negotiable')), 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'price_negotiable' => ['sometimes', 'boolean'],
            'price_note' => ['nullable', 'string', 'max:500'],
        ]);

        $data['price_negotiable'] = $request->boolean('price_negotiable');
        $request->user()->services()->create($data);

        return back()->with('status', 'Campus service posted successfully.');
    }

    public function requestService(Request $request, Service $service)
    {
        abort_if($service->user_id === $request->user()->id, 403);
        abort_if($service->status !== 'Active', 422, 'This service is inactive.');
        abort_if(! $service->visible_in_dashboard, 404);

        $existingRequest = ServiceRequest::where('service_id', $service->id)
            ->where('requester_id', $request->user()->id)
            ->whereIn('status', ['Pending', 'Accepted', 'Ongoing', 'Completed'])
            ->latest()
            ->first();

        if ($existingRequest) {
            if ($request->boolean('start_chat')) {
                return redirect()->route('conversations.show', $existingRequest->ensureConversation());
            }

            return back()->withErrors(['service' => 'You already requested this service.']);
        }

        $serviceRequest = DB::transaction(function () use ($request, $service): ServiceRequest {
            $serviceRequest = ServiceRequest::create([
                'service_id' => $service->id,
                'requester_id' => $request->user()->id,
                'provider_id' => $service->user_id,
            ]);

            DB::table('service_applications')->updateOrInsert(
                ['service_id' => $service->id, 'user_id' => $request->user()->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $this->messenger->recordEvent(
                $serviceRequest,
                $request->user(),
                'Service request sent for '.$service->title.'.',
                'service_request',
                'You have a new service request for '.$service->title.'.'
            );

            return $serviceRequest;
        });

        if ($request->boolean('start_chat')) {
            return redirect()->route('conversations.show', $serviceRequest->ensureConversation())->with('status', 'Your chat is ready. A service request was sent to the provider.');
        }

        return back()->with('status', 'Your service request was sent.');
    }

    public function updateRequest(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $request->validate(['status' => ['required', 'in:Accepted,Ongoing,Declined,Completed,Cancelled']]);

        $updated = DB::transaction(function () use ($request, $serviceRequest, $data): bool {
            $lockedRequest = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);
            $userId = $request->user()->id;

            abort_unless(in_array($userId, [$lockedRequest->requester_id, $lockedRequest->provider_id], true), 403);

            $isProvider = $userId === $lockedRequest->provider_id;
            $allowedStatuses = match ($lockedRequest->status) {
                'Pending' => $isProvider ? ['Accepted', 'Ongoing', 'Declined'] : ['Cancelled'],
                'Accepted' => ['Completed', ...($isProvider ? ['Ongoing', 'Declined'] : [])],
                'Ongoing' => ['Completed'],
                default => [],
            };

            if (! in_array($data['status'], $allowedStatuses, true)) {
                return false;
            }

            $lockedRequest->update(['status' => $data['status']]);

            if ($data['status'] === 'Accepted') {
                $this->transactions->createForServiceRequest($lockedRequest);
            }

            $this->messenger->recordEvent(
                $lockedRequest,
                $request->user(),
                'Request for '.$lockedRequest->service->title.' is now '.$data['status'].'.',
                'request_status',
                'The request for '.$lockedRequest->service->title.' is now '.$data['status'].'.'
            );

            return true;
        });

        if (! $updated) {
            return back()->withErrors(['status' => 'That request status change is not allowed.']);
        }

        return back()->with('status', 'Request status updated.');
    }
}
