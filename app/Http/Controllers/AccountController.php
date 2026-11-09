<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** The signed-in user's own sign-in details: name, email and password. */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    /** Name and email. Changing the email needs the current password. */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => trim((string) $request->input('email'))]);
        $emailChanging = strcasecmp($request->input('email'), $user->email) !== 0;

        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'email_password' => ['nullable', Rule::requiredIf($emailChanging), 'current_password'],
        ], [
            'email.unique' => 'Another account already uses this email.',
            'email_password.required' => 'Enter your current password to change your email.',
            'email_password.current_password' => 'That password is not correct.',
        ]);

        $user->forceFill(['name' => $data['name'], 'email' => $data['email']]);
        if ($emailChanging) {
            $user->email_verified_at = null; // a new address hasn't been confirmed
        }
        $user->save();

        return redirect()->route('account.edit')->with('status', $emailChanging
            ? 'Name and email saved. Use your new email next time you sign in.'
            : 'Name saved.');
    }

    /** Password: needs the current one, and the new one twice. */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], [
            'current_password.current_password' => 'Your current password is not correct.',
            'password.different' => 'Choose a password different from your current one.',
            'password.confirmed' => 'The new passwords do not match.',
        ]);

        $user = $request->user();
        $user->password = $request->input('password'); // hashed by the model's "hashed" cast
        $user->setRememberToken(Str::random(60));     // "Keep me signed in" on other devices stops working
        $user->save();

        $request->session()->regenerate();

        return redirect()->route('account.edit')->with('status', 'Password updated.');
    }
}
