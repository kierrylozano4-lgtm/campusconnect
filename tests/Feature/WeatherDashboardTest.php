<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_weather_service_maps_openweather_data_for_a_searched_location(): void
    {
        $this->configureWeather();
        $this->fakeWeather();

        $weather = app(WeatherService::class)->current('Tokyo');

        $this->assertSame('Tokyo', $weather['city']);
        $this->assertSame('JP', $weather['country']);
        $this->assertSame(26.5, $weather['temperature']);
        $this->assertSame('Light rain', $weather['condition']);
        $this->assertSame(70, $weather['humidity']);
        $this->assertSame(3.2, $weather['wind_speed']);
    }

    public function test_weather_service_returns_null_when_openweather_fails(): void
    {
        $this->configureWeather();
        Http::preventStrayRequests();
        Http::fake([
            'api.openweathermap.org/geo/1.0/direct*' => Http::response([], 404),
        ]);

        $this->assertNull(app(WeatherService::class)->current('Unknown Place'));
    }

    public function test_weather_service_does_not_make_requests_without_an_api_key(): void
    {
        config(['services.weather.api_key' => null]);
        Http::preventStrayRequests();

        $this->assertNull(app(WeatherService::class)->current());
    }

    public function test_admin_weather_page_renders_the_weather_search(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.weather.index'))
            ->assertOk()
            ->assertSee('id="weather-search-form"', false)
            ->assertSee('Live weather report');
    }

    public function test_admin_weather_endpoint_returns_the_requested_location(): void
    {
        $this->configureWeather();
        $this->fakeWeather();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->getJson(route('admin.weather.data', ['location' => 'Tokyo']))
            ->assertOk()
            ->assertJsonPath('city', 'Tokyo')
            ->assertJsonPath('country', 'JP');
    }

    public function test_non_admin_cannot_access_weather_page_or_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.weather.index'))
            ->assertForbidden();

        $this->get(route('admin.weather.data'))
            ->assertForbidden();
    }

    private function configureWeather(): void
    {
        config([
            'services.weather.api_key' => 'test-openweather-key',
            'services.weather.city' => 'Manila',
        ]);
    }

    private function fakeWeather(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.openweathermap.org/geo/1.0/direct*' => Http::response([
                [
                    'name' => 'Tokyo',
                    'country' => 'JP',
                    'lat' => 35.6762,
                    'lon' => 139.6503,
                ],
            ]),
            'api.openweathermap.org/data/2.5/weather*' => Http::response([
                'dt' => 1789981200,
                'main' => [
                    'temp' => 26.5,
                    'humidity' => 70,
                ],
                'wind' => [
                    'speed' => 3.2,
                ],
                'weather' => [
                    ['description' => 'light rain'],
                ],
            ]),
        ]);
    }
}
