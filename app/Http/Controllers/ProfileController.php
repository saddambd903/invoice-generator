<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:40'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'invoice_prefix' => ['required', 'alpha_dash', 'max:12'],
            // SVG is left out on purpose: it can carry scripts.
            'business_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'business_logo.image' => 'The logo must be an image (PNG, JPG or WebP).',
            'business_logo.mimes' => 'The logo must be a PNG, JPG or WebP file.',
            'business_logo.max' => 'The logo must be 2 MB or smaller.',
        ]);

        unset($data['business_logo'], $data['remove_logo']);

        if ($request->hasFile('business_logo')) {
            $this->deleteLogo($user->business_logo);
            // Saved to public/uploads/logos/<random>.<ext>
            $data['business_logo'] = $request->file('business_logo')->store('logos', 'uploads');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($user->business_logo);
            $data['business_logo'] = null;
        }

        $user->update($data);

        return back()->with('status', 'Business details saved.');
    }

    private function deleteLogo(?string $path): void
    {
        if ($path) {
            Storage::disk('uploads')->delete($path);
        }
    }
}
