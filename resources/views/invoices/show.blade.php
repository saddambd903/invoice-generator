@extends('layouts.app')
@section('title', $invoice->number)

@section('content')
@php
    $biz = $invoice->user;
    $isOwner = $invoice->user_id === auth()->id(); // false when a super admin views someone else's invoice
@endphp

<div class="row no-print">
    @if ($isOwner)
        <a href="{{ route('invoices.index') }}">← All invoices</a>
    @else
        <a href="{{ route('admin.show', $biz) }}">← {{ $biz->name }}'s account</a>
    @endif
    <span class="spacer"></span>
    <span class="chip {{ $invoice->status() }}">{{ $invoice->statusLabel() }}</span>
    <a class="btn" href="{{ route('vendors.show', $invoice->vendor) }}">Vendor profile</a>
    @if ($isOwner)
        <a class="btn" href="{{ route('invoices.create', ['vendor' => $invoice->vendor->uid]) }}">New for this vendor</a>
        @unless ($invoice->isLocked())
            <a class="btn" href="{{ route('invoices.edit', $invoice) }}">Edit</a>
        @endunless
    @endif
    <button class="btn primary" type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

@unless ($isOwner)
    <div class="readonly-banner no-print" role="note">
        Super admin view of an invoice belonging to <strong>{{ $biz->name }}</strong> ({{ $biz->email }}). Read-only.
    </div>
@endunless

@if ($isOwner)
<div class="lock-note no-print {{ $invoice->isLocked() ? 'locked' : '' }}">
    @if ($invoice->isLocked())
        Locked. This invoice can no longer be edited or deleted (the {{ \App\Models\Invoice::LOCK_AFTER_DAYS }}-day window ended {{ $invoice->locksAt()->format('d M Y, g:i A') }}).
    @else
        You can edit or delete this invoice until {{ $invoice->locksAt()->format('d M Y, g:i A') }}. After that it locks.
    @endif
</div>
@endif

<article class="paper">
    <div class="head">
        <div class="biz">
            @if ($biz->logoUrl())
                <img class="logo" src="{{ $biz->logoUrl() }}" alt="{{ $biz->business_name ?: $biz->name }} logo">
            @endif
            <h2>{{ $biz->business_name ?: $biz->name }}</h2>
            @if ($biz->business_address)<div>{{ $biz->business_address }}</div>@endif
            <div>{{ collect([$biz->business_phone, $biz->business_email])->filter()->join('  ·  ') }}</div>
        </div>
        <div>
            <div class="word">INVOICE</div>
            <dl class="meta">
                <dt>Invoice no.</dt><dd>{{ $invoice->number }}</dd>
                <dt>Date</dt><dd>{{ $invoice->invoice_date->format('d M Y') }}</dd>
                @if ($invoice->billing_month)<dt>Billing month</dt><dd>{{ $invoice->billingMonthLabel() }}</dd>@endif
                <dt>Currency</dt><dd>{{ $invoice->currency }}</dd>
            </dl>
        </div>
    </div>

    <div class="billto">
        <div class="lbl">Bill to</div>
        <div style="font-weight:600;font-size:16px">{{ $invoice->vendor->name }}</div>
        @if ($invoice->vendor->address)<div>{{ $invoice->vendor->address }}</div>@endif
        <div>{{ collect([$invoice->vendor->phone, $invoice->vendor->email])->filter()->join('  ·  ') }}</div>
    </div>

    <div class="tablebox">
        <table>
            <thead>
                <tr>
                    <th style="width:50px">SL</th>
                    <th>Description</th>
                    <th class="num" style="width:90px">Quantity</th>
                    <th class="num" style="width:130px">Unit cost</th>
                    <th class="num" style="width:140px">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->sl }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) ($item->quantity ?? 1), 2), '0'), '.') }}</td>
                        <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="num">{{ number_format((float) $item->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="tot">
        <div><strong>Total amount</strong><span class="num">{{ $invoice->money($invoice->total) }}</span></div>
        @if ((float) $invoice->before_pay > 0)
            <div><span>Before pay</span><span class="num">{{ $invoice->money($invoice->before_pay) }}</span></div>
        @endif
        <div><span>Now pay</span><span class="num">{{ $invoice->money($invoice->now_pay) }}</span></div>
        <div class="grand"><span>Due amount</span><span class="num">{{ $invoice->money($invoice->due) }}</span></div>
    </div>

    @if ($invoice->comment)
        <div class="note">
            <div class="lbl">Comment</div>
            {{ $invoice->comment }}
        </div>
    @endif
</article>

@if ($isOwner && ! $invoice->isLocked())
<details class="confirm no-print">
    <summary class="btn danger small">Delete this invoice</summary>
    <div class="confirm-box">
        <span>Delete {{ $invoice->number }} permanently?</span>
        <form method="POST" action="{{ route('invoices.destroy', $invoice) }}">
            @csrf @method('DELETE')
            <button class="btn small danger" type="submit">Delete</button>
        </form>
    </div>
</details>
@endif
@endsection
