@extends('layouts.app')

@section('content')
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">A better campus exchange</p>
        <h1>Trade skills, solve problems, find your people.</h1>
        <p class="hero-text">CampusConnect gives students one trusted place to offer help, request services, and build a reputation through real collaboration.</p>
        <div class="hero-actions"><a class="button" href="{{ route('register') }}">Create your account</a><a class="text-link" href="{{ route('login') }}">Already a member? Log in</a></div>
    </div>
    <div class="hero-panel"><span class="panel-label">Today on campus</span><strong>30</strong><span>student services ready to explore</span><div class="mini-list"><span>Academic help</span><span>Tech support</span><span>Personal services</span></div></div>
</section>
@endsection
