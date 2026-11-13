<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
