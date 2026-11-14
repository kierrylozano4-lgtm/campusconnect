<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_a_sanctum_token_and_manage_their_services(): void
    {
        $user = User::factory()->create([
            'email' => 'api.user@example.test',
            'password' => Hash::make('correct-password'),
        ]);
        $anotherUser = User::factory()->create();
        $anotherUser->services()->create([
            'title' => 'Private service',
            'category' => 'Tech',
            'description' => 'This listing belongs to another account.',
        ]);

        $tokenResponse = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])
            ->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id);

        $this->assertNotNull($tokenResponse->json('expires_at'));
        $token = $tokenResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.google_id');

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/services', [
                'title' => 'API tutoring',
                'category' => 'Academic',
                'description' => 'Tutoring created through the internal API.',
                'price' => 225,
                'price_negotiable' => false,
                'price_note' => 'Per hour',
                'user_id' => $anotherUser->id,
                'status' => 'Inactive',
                'visible_in_dashboard' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'API tutoring')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonPath('data.visible_in_dashboard', true);

        $serviceId = $createResponse->json('data.id');
        $createResponse->assertHeader('Location', route('api.v1.services.show', $serviceId));

        $this->withToken($token)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $serviceId);

        $this->withToken($token)
            ->patchJson('/api/v1/services/'.$serviceId, [
                'title' => 'Updated API tutoring',
                'price' => 250,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated API tutoring')
            ->assertJsonPath('data.price', '250.00');

        $this->assertDatabaseHas('services', [
            'id' => $serviceId,
            'title' => 'Updated API tutoring',
            'price' => '250.00',
        ]);

        $this->withToken($token)
            ->deleteJson('/api/v1/services/'.$serviceId)
            ->assertOk()
            ->assertJsonPath('message', 'Service deleted successfully.');

        $this->assertDatabaseMissing('services', ['id' => $serviceId]);
    }

    public function test_api_rejects_missing_or_invalid_bearer_tokens(): void
    {
        $this->getJson('/api/v1/services')->assertUnauthorized();

        $this->withToken('invalid-token')
            ->getJson('/api/v1/services')
            ->assertUnauthorized();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'api.user@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'api.user@example.test',
            'password' => 'incorrect-password',
        ])->assertUnauthorized();
    }

    public function test_token_cannot_read_or_change_another_users_service(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $service = $anotherUser->services()->create([
            'title' => 'Private service',
            'category' => 'Tech',
            'description' => 'This listing belongs to another account.',
        ]);
        $token = $user->createToken('test-token', ['services:read', 'services:write'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/services/'.$service->id)
            ->assertNotFound();

        $this->withToken($token)
            ->patchJson('/api/v1/services/'.$service->id, ['title' => 'Attempted change'])
            ->assertNotFound();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'title' => 'Private service',
        ]);
    }

    public function test_service_api_enforces_token_abilities(): void
    {
        $user = User::factory()->create();
        $readOnlyToken = $user->createToken('read-only', ['services:read'])->plainTextToken;

        $this->withToken($readOnlyToken)
            ->postJson('/api/v1/services', [
                'title' => 'API tutoring',
                'category' => 'Academic',
                'description' => 'Tutoring created through the internal API.',
                'price' => 225,
            ])
            ->assertForbidden();
    }

    public function test_logout_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
