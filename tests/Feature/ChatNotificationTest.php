<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ten_message_exchange_preserves_order_and_unread_read_states(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $conversation = Conversation::between($firstUser, $secondUser);
        $expectedMessages = [];

        foreach (range(1, 5) as $messageNumber) {
            $message = 'First user message '.$messageNumber;
            $expectedMessages[] = $message;

            $this->actingAs($firstUser)
                ->post(route('conversations.messages.store', $conversation), ['message' => $message])
                ->assertRedirect(route('conversations.show', $conversation));
        }

        $this->actingAs($secondUser)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee($firstUser->full_name)
            ->assertSee('aria-label="5 unread messages"', false);

        $this->actingAs($secondUser)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSeeInOrder(array_slice($expectedMessages, 0, 5));

        foreach (range(1, 5) as $messageNumber) {
            $message = 'Second user message '.$messageNumber;
            $expectedMessages[] = $message;

            $this->actingAs($secondUser)
                ->post(route('conversations.messages.store', $conversation), ['message' => $message])
                ->assertRedirect(route('conversations.show', $conversation));
        }

        $this->actingAs($firstUser)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee($secondUser->full_name)
            ->assertSee('aria-label="5 unread messages"', false)
            ->assertSee('Second user message 5');

        $this->actingAs($firstUser)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSeeInOrder($expectedMessages);

        $storedMessages = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->pluck('message')
            ->all();

        $this->assertSame($expectedMessages, $storedMessages);
        $this->assertSame(0, ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('receiver_id', $firstUser->id)
            ->whereNull('read_at')
            ->count());
        $this->assertSame(0, ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('receiver_id', $secondUser->id)
            ->whereNull('read_at')
            ->count());
    }

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

        $conversation = $serviceRequest->fresh()->conversation;

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'service_request_id' => $serviceRequest->id,
            'sender_id' => $requester->id,
            'receiver_id' => $provider->id,
            'message' => 'When are you available?',
            'type' => 'text',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $provider->id,
            'message' => 'New message from '.$requester->full_name.' about '.$service->title.'.',
            'link' => route('conversations.show', $conversation),
            'is_read' => false,
        ]);

        $this->actingAs($provider)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('When are you available?');

        $this->assertDatabaseMissing('conversation_messages', [
            'conversation_id' => $conversation->id,
            'message' => 'When are you available?',
            'read_at' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $provider->id,
            'link' => route('conversations.show', $conversation),
            'is_read' => true,
        ]);
    }

    public function test_profile_chat_button_starts_a_direct_conversation_without_creating_a_service_request(): void
    {
        $visitor = User::factory()->create();
        $profileUser = User::factory()->create();

        $this->actingAs($visitor)
            ->get(route('profile.show', $profileUser))
            ->assertOk()
            ->assertSee('Chat with '.$profileUser->full_name)
            ->assertSee(route('conversations.start', $profileUser), false);

        $this->post(route('conversations.start', $profileUser))
            ->assertRedirect();

        $this->assertDatabaseHas('conversations', [
            'user_one_id' => min($visitor->id, $profileUser->id),
            'user_two_id' => max($visitor->id, $profileUser->id),
        ]);
        $this->assertDatabaseCount('service_requests', 0);

        $conversation = Conversation::firstOrFail();

        $this->actingAs($visitor)
            ->post(route('conversations.messages.store', $conversation), ['message' => 'Hi there!'])
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $visitor->id,
            'receiver_id' => $profileUser->id,
            'message' => 'Hi there!',
        ]);

        $this->actingAs($profileUser)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee($visitor->full_name)
            ->assertSee('Hi there!');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $profileUser->id,
            'message' => 'New message from '.$visitor->full_name.'.',
            'link' => route('conversations.show', $conversation),
            'is_read' => false,
        ]);
    }

    public function test_only_conversation_participants_can_view_or_send_messages(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($firstUser)
            ->post(route('conversations.start', $secondUser))
            ->assertRedirect();

        $conversation = Conversation::firstOrFail();

        $this->actingAs($outsider)
            ->get(route('conversations.show', $conversation))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('conversations.messages.store', $conversation), ['message' => 'Not allowed'])
            ->assertForbidden();
    }
}
