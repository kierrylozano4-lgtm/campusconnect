<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    /**
     * @return array{
     *     city: string,
     *     country: string,
     *     temperature: float,
     *     condition: string,
     *     humidity: int,
     *     wind_speed: float,
     *     observed_at: string
     * }|null
     */
    public function current(?string $location = null): ?array
    {
        $configuration = config('services.weather');

        if (! is_string($configuration['api_key'] ?? null) || $configuration['api_key'] === '') {
            return null;
        }

        $coordinates = $this->coordinatesFor(trim($location ?: $configuration['city']), $configuration);

        if ($coordinates === null) {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withOptions(['verify' => $configuration['verify_ssl']])
                ->connectTimeout($configuration['connect_timeout'])
                ->timeout($configuration['timeout'])
                ->retry(2, 250, fn (\Throwable $exception): bool => $exception instanceof ConnectionException)
                ->get($configuration['current_endpoint'], [
                    'lat' => $coordinates['latitude'],
                    'lon' => $coordinates['longitude'],
                    'appid' => $configuration['api_key'],
                    'units' => 'metric',
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('OpenWeather current weather request failed.', [
                'location' => $location,
                'exception' => $exception::class,
                'status' => $exception instanceof RequestException ? $exception->response->status() : null,
            ]);

            return null;
        }

        $current = $response->json();
        $condition = data_get($current, 'weather.0.description');

        if (
            ! is_array($current)
            || ! is_numeric(data_get($current, 'main.temp'))
            || ! is_numeric(data_get($current, 'main.humidity'))
            || ! is_numeric(data_get($current, 'wind.speed'))
            || blank($current['dt'] ?? null)
            || blank($condition)
        ) {
            Log::warning('OpenWeather returned an invalid weather payload.', ['location' => $location]);

            return null;
        }

        return [
            'city' => $coordinates['city'],
            'country' => $coordinates['country'],
            'temperature' => (float) $current['main']['temp'],
            'condition' => ucfirst((string) $condition),
            'humidity' => (int) $current['main']['humidity'],
            'wind_speed' => (float) $current['wind']['speed'],
            'observed_at' => date('M d, Y H:i', (int) $current['dt']),
        ];
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array{city: string, country: string, latitude: float, longitude: float}|null
     */
    private function coordinatesFor(string $location, array $configuration): ?array
    {
        try {
            $response = Http::acceptJson()
                ->withOptions(['verify' => $configuration['verify_ssl']])
                ->connectTimeout($configuration['connect_timeout'])
                ->timeout($configuration['timeout'])
                ->get($configuration['geocoding_endpoint'], [
                    'q' => $location,
                    'limit' => 1,
                    'appid' => $configuration['api_key'],
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('OpenWeather geocoding request failed.', [
                'location' => $location,
                'exception' => $exception::class,
                'status' => $exception instanceof RequestException ? $exception->response->status() : null,
            ]);

            return null;
        }

        $place = $response->json('0');

        if (
            ! is_array($place)
            || blank($place['name'] ?? null)
            || ! is_numeric($place['lat'] ?? null)
            || ! is_numeric($place['lon'] ?? null)
        ) {
            return null;
        }

        return [
            'city' => (string) $place['name'],
            'country' => (string) ($place['country'] ?? ''),
            'latitude' => (float) $place['lat'],
            'longitude' => (float) $place['lon'],
        ];
    }
}
