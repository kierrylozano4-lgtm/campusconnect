<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'users' => User::with('profile')->latest()->get(),
            'services' => Service::with('user')->latest()->get(),
            'stats' => [
                'users' => User::count(),
                'services' => Service::count(),
                'requests' => DB::table('service_requests')->count(),
                'completed' => DB::table('service_requests')->where('status', 'Completed')->count(),
            ],
        ]);
    }

    public function toggleRole(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'You cannot change your own admin role.');
        $user->update(['is_admin' => ! $user->is_admin]);

        return back()->with('status', 'User role updated.');
    }

    public function destroyUser(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'You cannot delete your own account here.');
        $user->delete();

        return back()->with('status', 'User deleted.');
    }

    public function toggleService(Request $request, Service $service)
    {
        $data = $request->validate([
            'status' => ['required', 'in:Active,Inactive'],
            'inactive_reason' => ['nullable', 'string', 'max:1000'],
            'hide_from_marketplace' => ['sometimes', 'boolean'],
        ]);

        $hideFromMarketplace = $request->boolean('hide_from_marketplace');

        $service->update([
            'status' => $data['status'],
            'inactive_reason' => $data['status'] === 'Inactive' ? ($data['inactive_reason'] ?? 'Moderated by administrator') : null,
            'visible_in_dashboard' => ! $hideFromMarketplace,
        ]);

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

        foreach ($services as $service) {
            $service->update([
                'status' => $data['status'],
                'inactive_reason' => $data['status'] === 'Inactive' ? ($data['inactive_reason'] ?? 'Moderated by administrator') : null,
                'visible_in_dashboard' => ! $hideFromMarketplace,
            ]);
        }

        return back()->with('status', count($services).' service(s) updated.');
    }

    public function destroyService(Service $service)
    {
        $service->delete();

        return back()->with('status', 'Service deleted.');
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
}
