<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return $this->profileView($request, $request->user());
    }

    public function show(Request $request, User $user)
    {
        return $this->profileView($request, $user);
    }

    public function setup(Request $request)
    {
        return view('account.setup', ['profile' => $request->user()->profile]);
    }

    public function saveSetup(Request $request)
    {
        $data = $request->validate([
            'campus_address' => ['required', 'string', 'max:255'],
            'grade_level' => ['required', 'string', 'max:50'],
            'course_major' => ['required', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:100'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'tiktok' => ['nullable', 'string', 'max:100'],
        ]);

        $request->user()->profile()->updateOrCreate(['user_id' => $request->user()->id], $data);

        return redirect()->route('dashboard')->with('status', 'Profile setup saved successfully.');
    }

    public function storeReview(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($serviceRequest->status === 'Completed', 422, 'Only completed requests can be reviewed.');
        abort_unless(in_array($request->user()->id, [$serviceRequest->requester_id, $serviceRequest->provider_id], true), 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $reviewedUserId = $request->user()->id === $serviceRequest->requester_id
            ? $serviceRequest->provider_id
            : $serviceRequest->requester_id;

        Review::create(array_merge($data, [
            'service_request_id' => $serviceRequest->id,
            'reviewer_id' => $request->user()->id,
            'reviewed_user_id' => $reviewedUserId,
        ]));

        return back()->with('status', 'Review submitted successfully.');
    }

    public function markNotificationAsRead(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['is_read' => true]);

        return redirect()->route('profile.edit', ['tab' => 'notifications'])->with('status', 'Notification marked as read.');
    }

    private function profileView(Request $request, User $profileUser)
    {
        $profileUser->load(['profile', 'services', 'reviewsReceived.reviewer', 'notifications']);
        $tab = in_array($request->query('tab'), ['profile', 'services', 'reviews', 'notifications'], true)
            ? $request->query('tab')
            : 'profile';
        $isOwnProfile = $request->user()->is($profileUser);

        return view('profile.show', compact('profileUser', 'tab', 'isOwnProfile'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'campus_address' => ['required', 'string', 'max:255'],
            'grade_level' => ['required', 'string', 'max:50'],
            'course_major' => ['required', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:100'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'tiktok' => ['nullable', 'string', 'max:100'],
            'github' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);

        $request->user()->profile()->updateOrCreate(['user_id' => $request->user()->id], $data);

        return back()->with('status', 'Profile updated successfully.');
    }
}
