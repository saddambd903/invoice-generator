@extends('layouts.app')
@section('title', 'Account')

@php
    $p = $errors->getBag('profile');
    $w = $errors->getBag('password');
    $eye = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
@endphp

@section('content')
<div class="settings">
    <div>
        <h1>Account</h1>
        <p class="muted" style="margin:2px 0 0">Manage the details you sign in with.</p>
    </div>

    {{-- Who you are --}}
    <section class="acct-card">
        <div class="acct-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
        <div class="acct-id">
            <div class="acct-name">
                {{ $user->name }}
                @if ($user->isSuperAdmin())<span class="chip admin">Super admin</span>@endif
            </div>
            <div class="muted">{{ $user->email }}</div>
            <div class="acct-meta">
                <span>Member since {{ $user->created_at?->format('d M Y') }}</span>
                @if ($user->business_name)<span>{{ $user->business_name }}</span>@endif
            </div>
        </div>
        <a class="btn" href="{{ route('profile.edit') }}">Business details →</a>
    </section>

    {{-- Profile --}}
    <form method="POST" action="{{ route('account.update') }}" class="set-section" id="profile-form" novalidate>
        @csrf @method('PUT')
        <div class="set-intro">
            <h2>Profile</h2>
            <p>Your name and the email address you use to sign in. Changing your email asks for your password first.</p>
        </div>
        <div class="set-card">
            <div class="set-fields">
                @if ($p->any())
                    <div class="errors" role="alert"><ul style="margin:0">@foreach ($p->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                <label class="f">Full name
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name">
                </label>
                <label class="f">Email address
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255"
                           autocomplete="email" data-original="{{ $user->email }}">
                </label>
                <div class="confirm-pass" id="email-password-wrap" @unless ($p->has('email_password')) hidden @endunless>
                    <label class="f">Current password
                        <span class="pw">
                            <input id="email_password" type="password" name="email_password" autocomplete="current-password">
                            <button type="button" class="pw-eye" data-toggle="email_password" aria-label="Show password">{!! $eye !!}</button>
                        </span>
                    </label>
                    <span class="hint">You're changing your sign-in email. Enter your password to confirm it's you.</span>
                </div>
            </div>
            <div class="set-foot">
                <span class="hint" id="profile-dirty" hidden>You have unsaved changes.</span>
                <span class="spacer"></span>
                <button class="btn primary" type="submit">Save profile</button>
            </div>
        </div>
    </form>

    {{-- Password --}}
    <form method="POST" action="{{ route('account.password') }}" class="set-section" id="password-form" novalidate>
        @csrf @method('PUT')
        <div class="set-intro">
            <h2>Password</h2>
            <p>Use at least 8 characters. A longer password with a mix of letters, numbers and symbols is harder to guess.</p>
            <p>After you change it, "Keep me signed in" stops working on your other devices.</p>
        </div>
        <div class="set-card">
            <div class="set-fields">
                @if ($w->any())
                    <div class="errors" role="alert"><ul style="margin:0">@foreach ($w->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                <label class="f">Current password
                    <span class="pw">
                        <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
                        <button type="button" class="pw-eye" data-toggle="current_password" aria-label="Show password">{!! $eye !!}</button>
                    </span>
                </label>

                <div class="set-split">
                    <label class="f">New password
                        <span class="pw">
                            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" aria-describedby="pw-strength-label">
                            <button type="button" class="pw-eye" data-toggle="password" aria-label="Show password">{!! $eye !!}</button>
                        </span>
                    </label>
                    <label class="f">Confirm new password
                        <span class="pw">
                            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" aria-describedby="pw-match">
                            <button type="button" class="pw-eye" data-toggle="password_confirmation" aria-label="Show password">{!! $eye !!}</button>
                        </span>
                    </label>
                </div>

                <div class="pw-meter" aria-live="polite">
                    <div class="bars" data-score="0"><i></i><i></i><i></i><i></i></div>
                    <div class="pw-meta">
                        <span id="pw-strength-label" class="hint">Enter a new password</span>
                        <span id="pw-match" class="hint"></span>
                    </div>
                    <ul class="pw-rules">
                        <li data-rule="len">At least 8 characters</li>
                        <li data-rule="case">Upper and lower case letters</li>
                        <li data-rule="num">A number</li>
                        <li data-rule="sym">A symbol (e.g. ! @ # $)</li>
                    </ul>
                </div>
            </div>
            <div class="set-foot">
                <span class="spacer"></span>
                <button class="btn primary" type="submit">Update password</button>
            </div>
        </div>
    </form>
</div>

<script>
    (function () {
        // ---- Profile: ask for the password only when the email changes; flag unsaved edits ----
        var email = document.getElementById('email');
        var name = document.getElementById('name');
        var wrap = document.getElementById('email-password-wrap');
        var dirty = document.getElementById('profile-dirty');
        var origName = name.value;
        function checkProfile() {
            var emailChanged = email.value.trim().toLowerCase() !== email.dataset.original.toLowerCase();
            if (emailChanged) wrap.hidden = false;
            else if (!document.getElementById('email_password').value) wrap.hidden = true;
            dirty.hidden = !(emailChanged || name.value !== origName);
        }
        email.addEventListener('input', checkProfile);
        name.addEventListener('input', checkProfile);
        checkProfile();

        // ---- Show / hide each password field ----
        document.querySelectorAll('.pw-eye').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.toggle);
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.classList.toggle('on', show);
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        });

        // ---- Strength meter and match check ----
        var pw = document.getElementById('password');
        var pw2 = document.getElementById('password_confirmation');
        var bars = document.querySelector('.pw-meter .bars');
        var label = document.getElementById('pw-strength-label');
        var match = document.getElementById('pw-match');
        var names = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];

        function update() {
            var v = pw.value;
            var rules = {
                len: v.length >= 8,
                case: /[a-z]/.test(v) && /[A-Z]/.test(v),
                num: /\d/.test(v),
                sym: /[^A-Za-z0-9]/.test(v)
            };
            Object.keys(rules).forEach(function (k) {
                document.querySelector('.pw-rules [data-rule="' + k + '"]').classList.toggle('ok', rules[k]);
            });
            var score = 0;
            if (rules.len) {
                score = 1 + (rules.case ? 1 : 0) + (rules.num ? 1 : 0) + (rules.sym ? 1 : 0);
                if (v.length >= 14 && score < 4) score++;
                score = Math.min(score, 4);
            }
            bars.dataset.score = v ? score : 0;
            label.textContent = v ? 'Strength: ' + names[score] : 'Enter a new password';

            if (!pw2.value) { match.textContent = ''; match.className = 'hint'; }
            else if (pw2.value === v) { match.textContent = '✓ Passwords match'; match.className = 'hint good'; }
            else { match.textContent = 'Passwords don’t match yet'; match.className = 'hint bad'; }
        }
        pw.addEventListener('input', update);
        pw2.addEventListener('input', update);
        update();
    })();
</script>
@endsection
