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
}
