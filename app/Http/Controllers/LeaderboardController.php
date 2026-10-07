<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function index()
    {
        $topRated = Review::query()
            ->select('reviewed_user_id', DB::raw('AVG(rating) as average_rating'), DB::raw('COUNT(*) as review_count'))
            ->with('reviewedUser.profile')
            ->groupBy('reviewed_user_id')
            ->orderByDesc('average_rating')
            ->orderByDesc('review_count')
            ->limit(10)
            ->get();

        $topCompleted = ServiceRequest::query()
            ->select('provider_id', DB::raw('COUNT(*) as completed_count'))
            ->where('status', 'Completed')
            ->with('provider.profile')
            ->groupBy('provider_id')
            ->orderByDesc('completed_count')
            ->limit(10)
            ->get();

        return view('leaderboard.index', compact('topRated', 'topCompleted'));
    }
}
