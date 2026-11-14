<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_uses_the_workspace_sidebar_and_campus_services_feed(): void
    {
        $provider = User::factory()->create();
        Service::create([
            'user_id' => $provider->id,
            'title' => 'Calculus tutoring',
            'category' => 'Academic',
            'description' => 'Help with calculus.',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);

        $this->actingAs($provider)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="workspace-sidebar"', false)
            ->assertSee('Campus broadcasts')
            ->assertSee('Campus services feed')
            ->assertSee('Calculus tutoring')
            ->assertSee('id="service-post"', false)
            ->assertSee(route('conversations.index'), false);
    }

    public function test_workspace_sidebar_displays_recent_campus_broadcasts(): void
    {
        $user = User::factory()->create();
        DB::table('announcements')->insert([
            'title' => 'Campus maintenance',
            'message' => 'The study hall will close early this Friday.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Campus maintenance')
            ->assertSee('The study hall will close early this Friday.');
    }

    public function test_provider_can_accept_a_pending_request_and_notifies_the_requester(): void
    {
        $requester = User::factory()->create();
        $provider = User::factory()->create();
        $service = Service::create([
            'user_id' => $provider->id,
            'title' => 'Calculus tutoring',
            'category' => 'Academic',
            'description' => 'Help with calculus.',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);
        $serviceRequest = ServiceRequest::create([
            'service_id' => $service->id,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
            'status' => 'Pending',
        ]);

        $this->actingAs($provider)
            ->patch(route('requests.update', $serviceRequest), ['status' => 'Accepted'])
            ->assertRedirect();

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => 'Accepted',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $requester->id,
            'message' => 'The request for '.$service->title.' is now Accepted.',
            'link' => route('conversations.show', $serviceRequest->ensureConversation()),
            'is_read' => false,
        ]);
    }

    public function test_requester_cannot_complete_a_pending_request(): void
    {
        $requester = User::factory()->create();
        $provider = User::factory()->create();
        $service = Service::create([
            'user_id' => $provider->id,
            'title' => 'Calculus tutoring',
            'category' => 'Academic',
            'description' => 'Help with calculus.',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);
        $serviceRequest = ServiceRequest::create([
            'service_id' => $service->id,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
            'status' => 'Pending',
        ]);

        $this->actingAs($requester)
            ->patch(route('requests.update', $serviceRequest), ['status' => 'Completed'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => 'Pending',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $provider->id,
            'message' => 'The request for '.$service->title.' is now Completed.',
        ]);
    }

    public function test_requester_can_cancel_a_pending_request_and_notifies_the_provider(): void
    {
        $requester = User::factory()->create();
        $provider = User::factory()->create();
        $service = Service::create([
            'user_id' => $provider->id,
            'title' => 'Calculus tutoring',
            'category' => 'Academic',
            'description' => 'Help with calculus.',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);
        $serviceRequest = ServiceRequest::create([
            'service_id' => $service->id,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
            'status' => 'Pending',
        ]);

        $this->actingAs($requester)
            ->patch(route('requests.update', $serviceRequest), ['status' => 'Cancelled'])
            ->assertRedirect();

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => 'Cancelled',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $provider->id,
            'message' => 'The request for '.$service->title.' is now Cancelled.',
            'link' => route('conversations.show', $serviceRequest->ensureConversation()),
            'is_read' => false,
        ]);
    }

    public function test_both_participants_can_review_a_completed_request_once(): void
    {
        $requester = User::factory()->create();
        $provider = User::factory()->create();
        $service = Service::create([
            'user_id' => $provider->id,
            'title' => 'Calculus tutoring',
            'category' => 'Academic',
            'description' => 'Help with calculus.',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);
        $serviceRequest = ServiceRequest::create([
            'service_id' => $service->id,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
            'status' => 'Completed',
        ]);

        $this->actingAs($requester)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Write a review')
            ->assertSee(route('reviews.store', $serviceRequest), false);

        $this->post(route('reviews.store', $serviceRequest), [
            'rating' => 5,
            'comment' => 'Excellent collaboration.',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Review submitted successfully.');

        $this->assertDatabaseHas('reviews', [
            'service_request_id' => $serviceRequest->id,
            'reviewer_id' => $requester->id,
            'reviewed_user_id' => $provider->id,
            'rating' => 5,
            'comment' => 'Excellent collaboration.',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Write a review')
            ->assertSee('You reviewed this completed request.');

        $this->from(route('dashboard'))
            ->post(route('reviews.store', $serviceRequest), [
                'rating' => 4,
                'comment' => 'Duplicate review.',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('review');

        $this->assertDatabaseCount('reviews', 1);

        $this->actingAs($provider)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Write a review')
            ->assertSee(route('reviews.store', $serviceRequest), false);

        $this->post(route('reviews.store', $serviceRequest), [
            'rating' => 4,
            'comment' => 'Clear communication.',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Review submitted successfully.');

        $this->assertDatabaseHas('reviews', [
            'service_request_id' => $serviceRequest->id,
            'reviewer_id' => $provider->id,
            'reviewed_user_id' => $requester->id,
            'rating' => 4,
            'comment' => 'Clear communication.',
        ]);

        $this->assertDatabaseCount('reviews', 2);
    }

    public function test_only_completed_requests_can_be_reviewed(): void
    {
        $requester = User::factory()->create();
        $provider = User::factory()->create();
        $service = Service::create([
            'user_id' => $provider->id,
            'title' => 'Calculus tutoring',
            'category' => 'Academic',
            'description' => 'Help with calculus.',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);
        $serviceRequest = ServiceRequest::create([
            'service_id' => $service->id,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
            'status' => 'Pending',
        ]);

        $this->actingAs($requester)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Write a review');

        $this->post(route('reviews.store', $serviceRequest), [
            'rating' => 5,
            'comment' => 'Not completed.',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_provider_can_post_a_priced_negotiable_service(): void
    {
        $provider = User::factory()->create();

        $this->actingAs($provider)
            ->from(route('dashboard'))
            ->post(route('services.store'), [
                'title' => 'Design tutoring',
                'category' => 'Academic',
                'description' => 'One-on-one design help.',
                'price' => '350.00',
                'price_negotiable' => '1',
                'price_note' => 'Materials included.',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('services', [
            'user_id' => $provider->id,
            'title' => 'Design tutoring',
            'price' => '350.00',
            'price_negotiable' => true,
            'price_note' => 'Materials included.',
        ]);
    }

    public function test_service_requires_a_price_or_a_negotiable_selection(): void
    {
        $provider = User::factory()->create();
        $serviceData = [
            'title' => 'Study help',
            'category' => 'Academic',
            'description' => 'Study session.',
        ];

        $this->actingAs($provider)
            ->from(route('dashboard'))
            ->post(route('services.store'), $serviceData)
            ->assertSessionHasErrors('price');

        $this->from(route('dashboard'))
            ->post(route('services.store'), [...$serviceData, 'price_negotiable' => '1'])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('services', [
            'user_id' => $provider->id,
            'title' => 'Study help',
            'price' => null,
            'price_negotiable' => true,
        ]);
    }

    public function test_marketplace_service_details_show_pricing_and_provider_profile_link(): void
    {
        $viewer = User::factory()->create();
        $provider = User::factory()->create();
        $service = Service::create([
            'user_id' => $provider->id,
            'title' => 'Portfolio review',
            'category' => 'Tech',
            'description' => 'A detailed portfolio review.',
            'price' => '450.00',
            'price_negotiable' => true,
            'price_note' => 'Final price depends on the project.',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('services.show', $service), false)
            ->assertSee(route('profile.show', $provider), false)
            ->assertSee('₱450.00');

        $this->get(route('services.show', $service))
            ->assertOk()
            ->assertSee('Final price depends on the project.')
            ->assertSee(route('profile.show', $provider), false)
            ->assertSee('₱450.00');
    }

    public function test_chat_button_creates_a_service_request_and_opens_its_conversation(): void
    {
        $requester = User::factory()->create();
        $provider = User::factory()->create();
        $service = Service::create([
            'user_id' => $provider->id,
            'title' => 'Portfolio review',
            'category' => 'Tech',
            'description' => 'A detailed portfolio review.',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);

        $response = $this->actingAs($requester)
            ->post(route('services.request', $service), ['start_chat' => '1']);

        $serviceRequest = ServiceRequest::where('service_id', $service->id)
            ->where('requester_id', $requester->id)
            ->firstOrFail();

        $conversation = $serviceRequest->fresh()->conversation;
        $response->assertRedirect(route('conversations.show', $conversation));
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'service_request_id' => $serviceRequest->id,
            'type' => 'service_request',
            'message' => 'Service request sent for '.$service->title.'.',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $provider->id,
            'message' => 'You have a new service request for '.$service->title.'.',
            'link' => route('conversations.show', $conversation),
        ]);
    }

    public function test_chat_button_reopens_an_existing_conversation_without_creating_another_request(): void
    {
        $requester = User::factory()->create();
        $provider = User::factory()->create();
        $service = Service::create([
            'user_id' => $provider->id,
            'title' => 'Portfolio review',
            'category' => 'Tech',
            'description' => 'A detailed portfolio review.',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);
        $serviceRequest = ServiceRequest::create([
            'service_id' => $service->id,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
            'status' => 'Pending',
        ]);

        $this->actingAs($requester)
            ->post(route('services.request', $service), ['start_chat' => '1'])
            ->assertRedirect(route('conversations.show', $serviceRequest->ensureConversation()));

        $this->assertSame(1, ServiceRequest::where('service_id', $service->id)->count());
    }
}
