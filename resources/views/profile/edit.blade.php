@extends('layouts.app')
@section('title', 'My business')

@section('content')
<h1>My business</h1>
<p class="muted" style="margin:0">Printed at the top of every invoice.</p>

<form class="sheet" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')

    <div class="logo-field">
        <div class="logo-preview" id="logo-preview">
            @if ($user->logoUrl())
                <img src="{{ $user->logoUrl() }}" alt="Current logo">
            @else
                <span class="muted">No logo</span>
            @endif
        </div>
        <div class="stack" style="gap:8px;min-width:0">
            <label class="f">Logo
                <input id="business_logo" type="file" name="business_logo" accept="image/png,image/jpeg,image/webp">
            </label>
            <span class="hint">PNG, JPG or WebP, up to 2 MB. A wide logo on a transparent background prints best.</span>
            @if ($user->business_logo)
                <label class="row" style="gap:8px;font-weight:500">
                    <input id="remove_logo" type="checkbox" name="remove_logo" value="1" style="width:auto"> Remove current logo
                </label>
            @endif
        </div>
    </div>

    <div class="grid">
        <label class="f">Business name<input id="business_name" name="business_name" value="{{ old('business_name', $user->business_name) }}"></label>
        <label class="f">Phone<input id="business_phone" name="business_phone" value="{{ old('business_phone', $user->business_phone) }}"></label>
        <label class="f">Email<input id="business_email" type="email" name="business_email" value="{{ old('business_email', $user->business_email) }}"></label>
    </div>
    <label class="f">Address<input id="business_address" name="business_address" value="{{ old('business_address', $user->business_address) }}"></label>
    <label class="f">Invoice number prefix
        <input id="invoice_prefix" name="invoice_prefix" value="{{ old('invoice_prefix', $user->invoice_prefix) }}" style="max-width:200px" required>
        <span class="hint" style="text-transform:none;letter-spacing:0">Next number: {{ $user->nextInvoiceNumber() }}</span>
    </label>
    <div><button class="btn primary" type="submit">Save details</button></div>
</form>

<script>
    // Show the chosen logo before saving.
    document.getElementById('business_logo').addEventListener('change', function () {
        const file = this.files[0];
        const box = document.getElementById('logo-preview');
        if (!file) return;
        const img = document.createElement('img');
        img.alt = 'New logo preview';
        img.src = URL.createObjectURL(file);
        box.replaceChildren(img);
    });
</script>
@endsection
