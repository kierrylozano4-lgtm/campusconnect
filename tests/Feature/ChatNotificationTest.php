<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_message_notifies_the_other_service_request_participant(): void
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
            'status' => 'Accepted',
        ]);

        $this->actingAs($requester)
            ->post(route('chat.store', $serviceRequest), ['message' => 'When are you available?'])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'service_request_id' => $serviceRequest->id,
            'sender_id' => $requester->id,
            'receiver_id' => $provider->id,
            'message' => 'When are you available?',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $provider->id,
            'message' => 'New message from '.$requester->full_name.' about '.$service->title.'.',
            'link' => route('chat.show', $serviceRequest),
            'is_read' => false,
        ]);
    }
}
