@extends('layouts.app')
@section('content')
<section class="login-console auth-console" aria-label="Log in to CampusConnect">
    <div class="login-brand-panel auth-showcase">
        <a class="auth-emblem" href="{{ route('home') }}" aria-label="CampusConnect home">CC</a>
        <p class="eyebrow">CampusConnect · student community</p>
        <h1>Your next great collaboration starts here.</h1>
        <p class="auth-showcase-copy">Share what you know, find the help you need, and make more of campus life.</p>
        <div class="auth-showcase-note">
            <span class="auth-showcase-signal" aria-hidden="true"></span>
            <span><strong>Made for campus</strong><small>Skills, services, and people in one place.</small></span>
        </div>
    </div>

    <div class="login-form-panel auth-form-panel">
        <div class="auth-heading">
            <p class="eyebrow">Welcome back</p>
            <h2>Log in</h2>
            <p>Pick up where your campus exchange left off.</p>
        </div>

        @if(session('error'))
            <p class="alert error" role="alert">{{ session('error') }}</p>
        @endif

        <a class="button auth-google-button" href="{{ route('google.redirect') }}">
            <span class="google-mark" aria-hidden="true">G</span>
            Continue with Google
        </a>

        <div class="auth-divider"><span>or continue with email</span></div>

        <form method="POST" action="{{ route('login') }}" class="form-stack auth-form">
            @csrf
            <label>Email address
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@campus.edu">
            </label>
            <label>Password
                <input type="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
            </label>
            <label class="check auth-remember"><input type="checkbox" name="remember" value="1"> Remember me</label>
            <button class="button auth-submit" type="submit">Log in to CampusConnect <span aria-hidden="true">↗</span></button>
        </form>

        <p class="form-foot auth-switch">New to CampusConnect? <a class="text-link" href="{{ route('register') }}">Create an account</a></p>
    </div>
</section>
@endsection