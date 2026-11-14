<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const USER_INFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->googleCredentialsAreConfigured()) {
            return $this->redirectToLoginWithError('Google sign-in is not configured yet.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away(self::AUTHORIZATION_ENDPOINT.'?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->get('google_oauth_state');
        $receivedState = $request->query('state');

        if (! is_string($expectedState) || ! is_string($receivedState) || ! hash_equals($expectedState, $receivedState)) {
            return $this->redirectToLoginWithError('Google sign-in could not be verified. Please try again.');
        }

        $request->session()->forget('google_oauth_state');

        if ($request->query('error') !== null) {
            return $this->redirectToLoginWithError('Google sign-in was cancelled or not completed.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->redirectToLoginWithError('Google did not return an authorization code.');
        }

        if (! $this->googleCredentialsAreConfigured()) {
            return $this->redirectToLoginWithError('Google sign-in is not configured yet.');
        }

        try {
            $tokenResponse = Http::asForm()
                ->acceptJson()
                ->timeout(10)
                ->post(self::TOKEN_ENDPOINT, [
                    'code' => $code,
                    'client_id' => config('services.google.client_id'),
                    'client_secret' => config('services.google.client_secret'),
                    'redirect_uri' => config('services.google.redirect'),
                    'grant_type' => 'authorization_code',
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Google OAuth token exchange failed.', ['exception' => $exception::class]);

            return $this->redirectToLoginWithError('Google sign-in could not be completed. Please try again.');
        }

        $accessToken = $tokenResponse->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            Log::warning('Google OAuth token response did not include an access token.');

            return $this->redirectToLoginWithError('Google sign-in could not be completed. Please try again.');
        }

        try {
            $profileResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get(self::USER_INFO_ENDPOINT)
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Google OAuth profile request failed.', ['exception' => $exception::class]);

            return $this->redirectToLoginWithError('Google sign-in could not be completed. Please try again.');
        }

        $googleProfile = $profileResponse->json();
        $googleId = is_array($googleProfile) ? ($googleProfile['sub'] ?? null) : null;
        $name = is_array($googleProfile) ? ($googleProfile['name'] ?? null) : null;
        $email = is_array($googleProfile) ? ($googleProfile['email'] ?? null) : null;

        if (
            ! is_string($googleId) || $googleId === '' || strlen($googleId) > 255
            || ! is_string($name) || trim($name) === '' || strlen(trim($name)) > 100
            || ! is_string($email) || strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ($googleProfile['email_verified'] ?? false) !== true
        ) {
            Log::warning('Google OAuth profile did not contain a valid verified email.');

            return $this->redirectToLoginWithError('Google did not return a valid verified account email.');
        }

        $email = strtolower($email);
        $name = trim($name);

        $user = DB::transaction(function () use ($googleId, $name, $email): ?User {
            $user = User::query()
                ->where('google_id', $googleId)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                $user = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->lockForUpdate()
                    ->first();
            }

            if ($user !== null) {
                if ($user->google_id !== null && $user->google_id !== $googleId) {
                    return null;
                }

                if ($user->google_id === null) {
                    $user->google_id = $googleId;
                    $user->save();
                }

                return $user;
            }

            do {
                $studentId = now()->format('Y').'-'.random_int(100000, 999999);
            } while (User::query()->where('student_id', $studentId)->exists());

            $user = User::query()->create([
                'full_name' => $name,
                'student_id' => $studentId,
                'email' => $email,
                'password' => Str::random(64),
            ]);
            $user->google_id = $googleId;
            $user->save();
            $user->profile()->create(['verification_status' => 'Unverified']);

            return $user;
        });

        if ($user === null) {
            return $this->redirectToLoginWithError('This email is already connected to a different Google account.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function googleCredentialsAreConfigured(): bool
    {
        return is_string(config('services.google.client_id'))
            && config('services.google.client_id') !== ''
            && is_string(config('services.google.client_secret'))
            && config('services.google.client_secret') !== ''
            && is_string(config('services.google.redirect'))
            && config('services.google.redirect') !== '';
    }

    private function redirectToLoginWithError(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}
