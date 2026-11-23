<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_shows_feedback_and_settings_are_private_to_the_owner(): void
    {
        $provider = User::factory()->create();
        $reviewer = User::factory()->create();
        $service = Service::create([
            'user_id' => $provider->id,
            'title' => 'Study coaching',
            'category' => 'Academic',
            'description' => 'One-on-one study support.',
        ]);
        $serviceRequest = ServiceRequest::create([
            'service_id' => $service->id,
            'requester_id' => $reviewer->id,
            'provider_id' => $provider->id,
            'status' => 'Completed',
        ]);
        Review::create([
            'service_request_id' => $serviceRequest->id,
            'reviewer_id' => $reviewer->id,
            'reviewed_user_id' => $provider->id,
            'rating' => 5,
            'comment' => 'Clear, reliable help before my exam.',
        ]);

        $this->actingAs($reviewer)
            ->get(route('profile.show', $provider))
            ->assertOk()
            ->assertSee('Recent feedback')
            ->assertSee('Clear, reliable help before my exam.')
            ->assertSee('Feedback (1)');

        $this->actingAs($provider)
            ->get(route('profile.edit', ['tab' => 'settings']))
            ->assertOk()
            ->assertSee('Profile settings')
            ->assertSee(route('account.setup'), false)
            ->assertSee(route('profile.edit', ['tab' => 'edit']), false);

        $this->actingAs($reviewer)
            ->get(route('profile.show', ['user' => $provider, 'tab' => 'settings']))
            ->assertOk()
            ->assertDontSee('Profile settings')
            ->assertSee('About');
    }
}
