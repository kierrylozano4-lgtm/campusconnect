<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()
            ->where('email', strtolower($validated['email']))
            ->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        $token = $user->createToken('CampusConnect API', ['services:read', 'services:write']);
        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 10080));
        $token->accessToken->forceFill(['expires_at' => $expiresAt])->save();

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken) {
            return response()->json(['message' => 'A personal access token is required.'], 401);
        }

        $accessToken->delete();

        return response()->json([], 204);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
            ],
        ]);
    }
}
