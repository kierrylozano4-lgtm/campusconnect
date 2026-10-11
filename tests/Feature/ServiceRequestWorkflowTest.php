<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

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
            'link' => route('chat.show', $serviceRequest),
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
            'link' => route('chat.show', $serviceRequest),
            'is_read' => false,
        ]);
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

        $response->assertRedirect(route('chat.show', $serviceRequest));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $provider->id,
            'message' => 'You have a new service request for '.$service->title.'.',
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
            ->assertRedirect(route('chat.show', $serviceRequest));

        $this->assertSame(1, ServiceRequest::where('service_id', $service->id)->count());
    }
}
