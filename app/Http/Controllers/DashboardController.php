<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $category = $request->query('category');
        $user = $request->user();

        $services = Service::with('user')
            ->where('status', 'Active')
            ->where('visible_in_dashboard', true)
            ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")->orWhereHas('user', fn ($userQuery) => $userQuery->where('full_name', 'like', "%{$search}%"))))
            ->when($category, fn ($query) => $query->where('category', $category))
            ->latest()
            ->get();

        $incoming = ServiceRequest::with(['service', 'requester'])->where('provider_id', $user->id)->latest()->get();
        $outgoing = ServiceRequest::with(['service', 'provider'])->where('requester_id', $user->id)->latest()->get();

        return view('dashboard.index', [
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

    public function storeService(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:Academic,Personal,Tech'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $request->user()->services()->create($data);

        return back()->with('status', 'Campus service posted successfully.');
    }

    public function requestService(Request $request, Service $service)
    {
        abort_if($service->user_id === $request->user()->id, 403);
        abort_if($service->status !== 'Active', 422, 'This service is inactive.');
        abort_if(! $service->visible_in_dashboard, 404);

        $exists = ServiceRequest::where('service_id', $service->id)
            ->where('requester_id', $request->user()->id)
            ->whereIn('status', ['Pending', 'Accepted', 'Ongoing', 'Completed'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['service' => 'You already requested this service.']);
        }

        $serviceRequest = ServiceRequest::create([
            'service_id' => $service->id,
            'requester_id' => $request->user()->id,
            'provider_id' => $service->user_id,
        ]);

        DB::table('service_applications')->updateOrInsert(
            ['service_id' => $service->id, 'user_id' => $request->user()->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
        DB::table('notifications')->insert([
            'user_id' => $service->user_id,
            'message' => 'You have a new service request for '.$service->title.'.',
            'link' => route('chat.show', $serviceRequest),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Your service request was sent.');
    }

    public function updateRequest(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless(in_array($request->user()->id, [$serviceRequest->requester_id, $serviceRequest->provider_id], true), 403);

        $data = $request->validate(['status' => ['required', 'in:Accepted,Ongoing,Declined,Completed']]);
        $serviceRequest->update(['status' => $data['status']]);

        return back()->with('status', 'Request status updated.');
    }
}
