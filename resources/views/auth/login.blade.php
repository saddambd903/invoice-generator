@extends('layouts.app')
@section('title', 'Sign in')

@section('content')
<div class="auth stack">
    <h1>Sign in</h1>
    <p class="muted" style="margin:0">Your invoices and vendors are private to your account.</p>
    <form class="sheet" method="POST" action="{{ route('login') }}">
        @csrf
        <label class="f">Email
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        </label>
        <label class="f">Password
            <input id="password" type="password" name="password" required autocomplete="current-password">
        </label>
        <label class="row" style="gap:8px;font-weight:500">
            <input id="remember" type="checkbox" name="remember" style="width:auto"> Keep me signed in
        </label>
        <button class="btn primary" type="submit">Sign in</button>
        <p class="muted" style="margin:0">New here? <a href="{{ route('register') }}">Create an account</a></p>
    </form>
</div>
@endsection
