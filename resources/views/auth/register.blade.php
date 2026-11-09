@extends('layouts.app')
@section('title', 'Create account')

@section('content')
<div class="auth stack">
    <h1>Create your account</h1>
    <p class="muted" style="margin:0">Start sending invoices in taka in a couple of minutes.</p>
    <form class="sheet" method="POST" action="{{ route('register') }}">
        @csrf
        <label class="f">Your name
            <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        </label>
        <label class="f">Business name <span class="hint" style="text-transform:none;letter-spacing:0">(shown on invoices, optional)</span>
            <input id="business_name" name="business_name" value="{{ old('business_name') }}">
        </label>
        <label class="f">Email
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        </label>
        <label class="f">Password
            <input id="password" type="password" name="password" required autocomplete="new-password" minlength="8">
        </label>
        <label class="f">Confirm password
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </label>
        <button class="btn primary" type="submit">Create account</button>
        <p class="muted" style="margin:0">Already have one? <a href="{{ route('login') }}">Sign in</a></p>
    </form>
</div>
@endsection
