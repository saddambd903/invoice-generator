@extends('layouts.app')
@section('title', $vendor->name)

@section('content')
{{-- isOwner is false when a super admin views someone else's vendor (read-only) --}}
@php $isOwner = $vendor->user_id === auth()->id(); @endphp
<div class="row">
    @if ($isOwner)
        <a href="{{ route('vendors.index') }}">← All vendors</a>
    @else
        <a href="{{ route('admin.show', $vendor->user) }}">← {{ $vendor->user->name }}'s account</a>
    @endif
</div>

@unless ($isOwner)
    <div class="readonly-banner" role="note">
        Super admin view of a vendor belonging to <strong>{{ $vendor->user->name }}</strong> ({{ $vendor->user->email }}). Read-only.
    </div>
@endunless

<div class="sheet vendor-head">
    <div class="row" style="align-items:flex-start">
        <div class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($vendor->name, 0, 1)) }}</div>
        <div class="stack" style="gap:4px;min-width:0;flex:1">
            <h1>{{ $vendor->name }}</h1>
            <div class="contact">
                @if ($vendor->phone)<span>{{ $vendor->phone }}</span>@endif
                @if ($vendor->email)<span>{{ $vendor->email }}</span>@endif
                @if ($vendor->address)<span>{{ $vendor->address }}</span>@endif
                @unless ($vendor->phone || $vendor->email || $vendor->address)
                    <span class="muted">No contact details saved</span>
                @endunless
            </div>
            @if ($firstDate)
                <div class="hint">
                    {{ $invoices->count() }} {{ Str::plural('invoice', $invoices->count()) }}
                    · first {{ $firstDate->format('d M Y') }} · latest {{ $lastDate->format('d M Y') }}
                </div>
            @endif
        </div>
        @if ($isOwner)
            <a class="btn primary" href="{{ route('invoices.create', ['vendor' => $vendor->uid]) }}">New invoice</a>
        @endif
    </div>

    @if ($isOwner)
    <details class="edit-vendor" @if ($errors->hasAny(['name', 'phone', 'email', 'address'])) open @endif>
        <summary class="btn small">Edit details</summary>
        <form method="POST" action="{{ route('vendors.update', $vendor) }}" class="stack" style="margin-top:14px">
            @csrf @method('PUT')
            <div class="grid">
                <label class="f">Name<input id="name" name="name" value="{{ old('name', $vendor->name) }}" required></label>
                <label class="f">Phone<input id="phone" name="phone" inputmode="tel" value="{{ old('phone', $vendor->phone) }}"></label>
                <label class="f">Email<input id="email" type="email" name="email" value="{{ old('email', $vendor->email) }}"></label>
            </div>
            <label class="f">Address<input id="address" name="address" value="{{ old('address', $vendor->address) }}"></label>
            <div class="hint">Past invoices keep the details they were issued with. New invoices use these details.</div>
            <div><button class="btn primary" type="submit">Save details</button></div>
        </form>
    </details>

    @if ($invoices->isEmpty())
        <details class="confirm">
            <summary class="btn danger small">Delete vendor</summary>
            <div class="confirm-box">
                <span>Delete {{ $vendor->name }} permanently?</span>
                <form method="POST" action="{{ route('vendors.destroy', $vendor) }}">
                    @csrf @method('DELETE')
                    <button class="btn small danger" type="submit">Delete</button>
                </form>
            </div>
        </details>
    @else
        <div class="hint">This vendor has invoices, so they can't be deleted.</div>
    @endif
    @endif {{-- owner-only actions --}}
</div>

@forelse ($totals as $code => $t)
    <div class="stack" style="gap:8px">
        @if ($totals->count() > 1)<h2>{{ $code }}</h2>@endif
        <div class="stats">
            <div class="stat"><div class="k">Billed ({{ $code }})</div><div class="n">{{ $t['symbol'] }}{{ number_format($t['billed'], 2) }}</div></div>
            <div class="stat"><div class="k">Collected ({{ $code }})</div><div class="n">{{ $t['symbol'] }}{{ number_format($t['collected'], 2) }}</div></div>
            <div class="stat"><div class="k">Outstanding ({{ $code }})</div><div class="n" @if ($t['outstanding'] > 0) style="color:var(--warn)" @endif>{{ $t['symbol'] }}{{ number_format($t['outstanding'], 2) }}</div></div>
        </div>
    </div>
@empty
@endforelse

<div class="stack" style="gap:10px">
    <h2>Invoices</h2>
    <div class="list">
        @forelse ($invoices as $invoice)
            <a class="li" href="{{ route('invoices.show', $invoice) }}">
                <div style="min-width:0">
                    <div class="t">{{ $invoice->number }}</div>
                    <div class="s">
                        {{ $invoice->invoice_date->format('d M Y') }}
                        @if ($invoice->billing_month) · for {{ $invoice->billingMonthLabel() }} @endif
                    </div>
                </div>
                <div class="hide-sm"><span class="chip type">{{ \App\Models\Invoice::TYPES[$invoice->type] }}</span></div>
                <div class="num">
                    <div style="font-weight:500">{{ $invoice->money($invoice->total) }}</div>
                    <div class="s">{{ (float) $invoice->due > 0.004 ? 'Due '.$invoice->money($invoice->due) : 'Nothing due' }}</div>
                </div>
                <div><span class="chip {{ $invoice->status() }}">{{ $invoice->statusLabel() }}</span></div>
            </a>
        @empty
            <div class="empty">
                <strong>No invoices for this vendor yet</strong>
                <div style="margin-top:12px"><a class="btn primary" href="{{ route('invoices.create', ['vendor' => $vendor->uid]) }}">Create one</a></div>
            </div>
        @endforelse
    </div>
</div>
@endsection
