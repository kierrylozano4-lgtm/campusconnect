@extends('layouts.app')
@section('content')
<section class="section-heading">
    <div>
        <p class="eyebrow">Environmental conditions</p>
        <h1>Live weather report</h1>
        <p class="muted">Check current conditions for the default city or search another location.</p>
    </div>
    <a class="button button-small" href="{{ route('admin.index') }}">Admin overview</a>
</section>

@include('admin._navigation', ['active' => 'weather'])

<section class="panel admin-section" aria-labelledby="weather-heading">
    <div class="admin-section-heading">
        <div>
            <p class="eyebrow">OpenWeather</p>
            <h2 id="weather-heading">Search current conditions</h2>
        </div>
    </div>
    <form id="weather-search-form" class="form-stack">
        <label for="weather-location">City or location
            <input id="weather-location" name="location" type="search" maxlength="100" placeholder="Leave blank for the default city">
        </label>
        <button class="button" type="submit">Get weather</button>
    </form>

    <p id="weather-status" class="alert hidden" role="status" aria-live="polite" hidden></p>

    <div id="weather-result" class="admin-list hidden" aria-live="polite" hidden>
        <div class="admin-row">
            <div class="service-meta">
                <strong id="weather-city"></strong>
                <span id="weather-condition"></span>
            </div>
            <div class="service-meta">
                <strong id="weather-temperature"></strong>
                <span>Temperature</span>
            </div>
            <div class="service-meta">
                <strong id="weather-humidity"></strong>
                <span>Humidity</span>
            </div>
            <div class="service-meta">
                <strong id="weather-wind"></strong>
                <span>Wind speed</span>
            </div>
            <div class="service-meta">
                <strong id="weather-updated"></strong>
                <span>Last updated</span>
            </div>
        </div>
    </div>
</section>

<script>
(() => {
    const form = document.getElementById('weather-search-form');
    const input = document.getElementById('weather-location');
    const status = document.getElementById('weather-status');
    const result = document.getElementById('weather-result');
    const endpoint = @json(route('admin.weather.data'));

    const loadWeather = async (location = '') => {
        status.textContent = 'Loading weather…';
        status.hidden = false;
        status.classList.remove('hidden');
        result.hidden = true;
        result.classList.add('hidden');

        try {
            const query = new URLSearchParams();
            if (location) {
                query.set('location', location);
            }

            const response = await fetch(`${endpoint}${query.size ? `?${query}` : ''}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Weather information is unavailable.');
            }

            document.getElementById('weather-city').textContent = `${data.city}${data.country ? `, ${data.country}` : ''}`;
            document.getElementById('weather-condition').textContent = data.condition;
            document.getElementById('weather-temperature').textContent = `${Number(data.temperature).toFixed(1)} °C`;
            document.getElementById('weather-humidity').textContent = `${data.humidity}%`;
            document.getElementById('weather-wind').textContent = `${Number(data.wind_speed).toFixed(1)} m/s`;
            document.getElementById('weather-updated').textContent = data.observed_at;
            status.hidden = true;
            status.classList.add('hidden');
            result.hidden = false;
            result.classList.remove('hidden');
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : 'Weather information is unavailable.';
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        loadWeather(input.value.trim());
    });

    loadWeather();
})();
</script>
@endsection
