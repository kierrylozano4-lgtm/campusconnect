<?php

namespace App\Providers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        view()->composer('layouts.app', function (View $view): void {
            if (! Auth::check()) {
                return;
            }

            $view->with('sidebarAnnouncements', DB::table('announcements')->latest()->limit(3)->get())
                ->with('sidebarStats', [
                    'users' => User::count(),
                    'active_services' => Service::where('status', 'Active')->count(),
                    'inactive_services' => Service::where('status', 'Inactive')->count(),
                    'requests' => DB::table('service_requests')
                        ->where('provider_id', Auth::id())
                        ->where('status', 'Pending')
                        ->count(),
                ])
                ->with('unreadNotificationCount', Auth::user()->notifications()->where('is_read', false)->count());
        });
    }
}
