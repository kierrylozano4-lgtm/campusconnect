<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_registration_pages_offer_google_sign_in(): void
    {
        $this->withoutVite();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee(route('google.redirect'));

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Register with Google')
            ->assertSee(route('google.redirect'));
    }

    public function test_google_redirect_stores_state_and_requests_account_consent(): void
    {
        $this->configureGoogleAuth();

        $response = $this->get(route('google.redirect'));
        $response->assertRedirect();

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('test-google-client-id', $query['client_id']);
        $this->assertSame('http://127.0.0.1:8000/auth/google/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertSame('select_account', $query['prompt']);
        $response->assertSessionHas('google_oauth_state', $query['state']);
    }

    public function test_google_redirect_reports_missing_credentials(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get(route('google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google sign-in is not configured yet.');
    }

    public function test_verified_google_profile_creates_user_and_authenticates_them(): void
    {
        $this->configureGoogleAuth();
        $this->fakeGoogleProfile();

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('google.callback', ['state' => 'valid-state', 'code' => 'authorization-code']))
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('google_id', 'google-user-123')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('Campus User', $user->full_name);
        $this->assertSame('campus.user@example.test', $user->email);
        $this->assertNotSame('', $user->student_id);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'verification_status' => 'Unverified',
        ]);
    }

    public function test_verified_google_email_links_an_existing_local_account(): void
    {
        $user = User::factory()->create([
            'email' => 'campus.user@example.test',
            'is_admin' => true,
        ]);
        $this->configureGoogleAuth();
        $this->fakeGoogleProfile();

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('google.callback', ['state' => 'valid-state', 'code' => 'authorization-code']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-user-123',
            'is_admin' => true,
        ]);
    }

    public function test_google_email_already_linked_to_another_google_account_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'campus.user@example.test']);
        $user->google_id = 'different-google-user';
        $user->save();
        $this->configureGoogleAuth();
        $this->fakeGoogleProfile();

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('google.callback', ['state' => 'valid-state', 'code' => 'authorization-code']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'This email is already connected to a different Google account.');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_callback_rejects_invalid_state_without_contacting_google(): void
    {
        Http::preventStrayRequests();

        $this->withSession(['google_oauth_state' => 'expected-state'])
            ->get(route('google.callback', ['state' => 'attacker-state', 'code' => 'authorization-code']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google sign-in could not be verified. Please try again.');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_callback_reports_cancelled_google_sign_in(): void
    {
        Http::preventStrayRequests();

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('google.callback', ['state' => 'valid-state', 'error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google sign-in was cancelled or not completed.');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_callback_rejects_an_unverified_google_email(): void
    {
        $this->configureGoogleAuth();
        $this->fakeGoogleProfile(['email_verified' => false]);

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('google.callback', ['state' => 'valid-state', 'code' => 'authorization-code']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google did not return a valid verified account email.');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    private function configureGoogleAuth(): void
    {
        config([
            'services.google.client_id' => 'test-google-client-id',
            'services.google.client_secret' => 'test-google-client-secret',
            'services.google.redirect' => 'http://127.0.0.1:8000/auth/google/callback',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeGoogleProfile(array $overrides = []): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'test-access-token',
                'token_type' => 'Bearer',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(array_merge([
                'sub' => 'google-user-123',
                'name' => 'Campus User',
                'email' => 'campus.user@example.test',
                'email_verified' => true,
            ], $overrides)),
        ]);
    }
}
