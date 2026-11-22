<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

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

        return redirect()->route('profile.edit', ['tab' => 'settings'])->with('status', 'Campus profile details saved successfully.');
    }

    public function savePreferences(Request $request)
    {
        $data = $request->validate([
            'design_theme' => ['required', Rule::in(['modern', 'glass', 'skeuomorphic', 'brutalist', 'editorial'])],
            'design_palette' => ['required', Rule::in(['campus', 'ocean', 'botanical', 'sunset', 'monochrome'])],
        ]);

        $request->user()->update($data);

        return back()->with('status', 'Your design preferences have been saved.');
    }

    public function storeFeedback(Request $request)
    {
        $data = $request->validate([
            'recipient' => ['required', Rule::in(['admin', 'developer'])],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $request->user()->feedbackMessages()->create([
            ...$data,
            'sender_name' => $request->user()->full_name,
            'sender_email' => $request->user()->email,
        ]);

        return redirect()->route('profile.edit', ['tab' => 'settings'])->with('status', 'Your feedback was sent to the '.($data['recipient'] === 'admin' ? 'admin team' : 'development team').'.');
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

        $review = Review::query()->firstOrCreate([
            'service_request_id' => $serviceRequest->id,
            'reviewer_id' => $request->user()->id,
        ], array_merge($data, [
            'reviewed_user_id' => $reviewedUserId,
        ]));

        if (! $review->wasRecentlyCreated) {
            return back()->withErrors(['review' => 'You have already reviewed this completed request.']);
        }

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
        $profileUser->load(['profile', 'services', 'reviewsReceived.reviewer']);
        $isOwnProfile = $request->user()->is($profileUser);
        $requestedTab = $request->query('tab');
        $allowedTabs = ['profile', 'services', 'reviews'];

        if ($isOwnProfile) {
            $profileUser->load(['notifications', 'feedbackMessages' => fn ($query) => $query->latest()]);
            $allowedTabs = [...$allowedTabs, 'settings', 'edit', 'notifications'];
        }

        $tab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'profile';
        $profile = $profileUser->profile;
        $completionFields = [
            filled($profile?->campus_address),
            filled($profile?->grade_level),
            filled($profile?->course_major),
            filled($profile?->bio),
            filled($profile?->facebook) || filled($profile?->instagram) || filled($profile?->tiktok) || filled($profile?->github),
        ];
        $profileCompletion = (int) round(collect($completionFields)->filter()->count() / count($completionFields) * 100);
        $averageRating = $profileUser->reviewsReceived->avg('rating');

        return view('profile.show', compact('profileUser', 'tab', 'isOwnProfile', 'profileCompletion', 'averageRating'));
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
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'cover_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_photo' => ['sometimes', 'boolean'],
            'remove_cover_photo' => ['sometimes', 'boolean'],
        ]);

        $profile = $request->user()->profile()->firstOrNew();
        $previousPaths = [];
        $storedPaths = [];

        foreach (['profile_photo' => 'profile_photo_path', 'cover_photo' => 'cover_photo_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $path = $request->file($input)->storePublicly('profiles/'.$request->user()->id, 'public');

                if (! is_string($path)) {
                    throw new RuntimeException('The uploaded image could not be stored.');
                }

                $storedPaths[] = $path;
                $data[$column] = $path;
                if ($profile->{$column}) {
                    $previousPaths[] = $profile->{$column};
                }
            } elseif ($request->boolean('remove_'.$input)) {
                if ($profile->{$column}) {
                    $previousPaths[] = $profile->{$column};
                }

                $data[$column] = null;
            }
        }

        unset($data['profile_photo'], $data['cover_photo'], $data['remove_profile_photo'], $data['remove_cover_photo']);

        try {
            $profile->fill($data);
            $profile->user_id = $request->user()->id;
            $profile->save();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        Storage::disk('public')->delete($previousPaths);

        return back()->with('status', 'Profile updated successfully.');
    }
}
