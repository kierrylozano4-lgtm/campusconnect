<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeatherController extends Controller
{
    public function index(): View
    {
        return view('admin.weather');
    }

    public function show(Request $request, WeatherService $weatherService): JsonResponse
    {
        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:100'],
        ]);

        $weather = $weatherService->current(
            isset($validated['location']) ? trim($validated['location']) : null
        );

        if ($weather === null) {
            return response()->json([
                'message' => 'Weather information is unavailable for that location right now.',
            ], 422);
        }

        return response()->json($weather);
    }
}
