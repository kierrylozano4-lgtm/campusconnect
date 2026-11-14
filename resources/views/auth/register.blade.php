@extends('layouts.app')
@section('content')
<section class="login-console auth-console register-console" aria-label="Create a CampusConnect account">
    <div class="login-brand-panel auth-showcase">
        <a class="auth-emblem" href="{{ route('home') }}" aria-label="CampusConnect home">CC</a>
        <p class="eyebrow">Join your campus community</p>
        <h1>Good things happen when students help each other.</h1>
        <p class="auth-showcase-copy">Create your account to discover services, share your skills, and build your campus reputation.</p>
        <ul class="auth-benefits">
            <li><span aria-hidden="true">01</span> Find help from fellow students</li>
            <li><span aria-hidden="true">02</span> Offer skills you’re proud of</li>
            <li><span aria-hidden="true">03</span> Build trust through real reviews</li>
        </ul>
    </div>

    <div class="login-form-panel auth-form-panel">
        <div class="auth-heading">
            <p class="eyebrow">Your campus starts here</p>
            <h2>Create your account</h2>
            <p>Your student ID is generated automatically. Complete your profile after signing up.</p>
        </div>

        <a class="button auth-google-button" href="{{ route('google.redirect') }}">
            <span class="google-mark" aria-hidden="true">G</span>
            Register with Google
        </a>

        <div class="auth-divider"><span>or register with email</span></div>

        <form method="POST" action="{{ route('register') }}" class="form-stack auth-form">
            @csrf
            <label>Full name
                <input type="text" name="full_name" value="{{ old('full_name') }}" required autofocus autocomplete="name" maxlength="100" placeholder="Your full name">
            </label>
            <label>Email address
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" maxlength="100" placeholder="you@campus.edu">
            </label>
            <label>Password
                <input type="password" name="password" required autocomplete="new-password" placeholder="Create a password">
            </label>
            <label>Confirm password
                <input type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Enter your password again">
            </label>
            <button class="button auth-submit" type="submit">Create account <span aria-hidden="true">↗</span></button>
        </form>

        <p class="form-foot auth-switch">Already a member? <a class="text-link" href="{{ route('login') }}">Log in</a></p>
    </div>
</section>
@endsection