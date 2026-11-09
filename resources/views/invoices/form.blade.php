@extends('layouts.app')
@php
    $editing = $invoice->exists;
    $known = array_key_exists($invoice->currency, \App\Models\Invoice::CURRENCIES);
    $currency = old('currency', $known ? $invoice->currency : 'OTHER');
    $currencyOther = old('currency_other', $known ? '' : $invoice->currency);
    $type = old('type', $invoice->type ?? 'onetime');
    $rows = old('items', $items);
    $vendor = $invoice->vendor;
@endphp
@section('title', $editing ? 'Edit '.$invoice->number : 'New invoice')

@section('content')
<form id="invoice-form" method="POST"
      action="{{ $editing ? route('invoices.update', $invoice) : route('invoices.store') }}"
      autocomplete="off" class="stack"
      data-lookup-url="{{ route('vendors.lookup') }}"
      data-editing="{{ $editing ? '1' : '0' }}"
      data-prefill-vendor="{{ $prefillVendor?->uid }}"
      data-symbols='@json(collect(\App\Models\Invoice::CURRENCIES)->map(fn ($c) => $c['symbol']))'>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row">
        <h1>{{ $editing ? 'Edit invoice '.$invoice->number : 'New invoice' }}</h1>
        <span class="spacer"></span>
        @if ($editing)
            <a class="btn" href="{{ route('invoices.show', $invoice) }}">Cancel</a>
        @endif
        <button class="btn primary" type="submit">Save invoice</button>
    </div>

    <div class="sheet">
        <div class="row">
            <div class="seg" role="radiogroup" aria-label="Invoice type">
                <label><input type="radio" name="type" id="type-onetime" value="onetime" @checked($type === 'onetime')><span>One time</span></label>
                <label><input type="radio" name="type" id="type-monthly" value="monthly" @checked($type === 'monthly')><span>Monthly</span></label>
            </div>
            <span class="spacer"></span>
            <span class="hint" id="type-hint"></span>
        </div>

        <div class="grid">
            <label class="f">Invoice no.
                <input id="number" name="number" value="{{ old('number', $invoice->number) }}" maxlength="40">
            </label>
            <label class="f">Invoice date
                <input id="invoice_date" type="date" name="invoice_date" value="{{ old('invoice_date', $invoice->invoice_date?->format('Y-m-d')) }}" required>
            </label>
            <label class="f" id="wrap-month">Billing month
                <input id="billing_month" type="month" name="billing_month" value="{{ old('billing_month', $invoice->billing_month ?? now()->format('Y-m')) }}">
            </label>
            <label class="f">Currency
                <select id="currency" name="currency">
                    @foreach (\App\Models\Invoice::CURRENCIES as $code => $c)
                        <option value="{{ $code }}" @selected($currency === $code)>{{ $code }} — {{ $c['name'] }} ({{ $c['symbol'] }})</option>
                    @endforeach
                    <option value="OTHER" @selected($currency === 'OTHER')>Other…</option>
                </select>
            </label>
            <label class="f" id="wrap-curother">Currency code
                <input id="currency_other" name="currency_other" value="{{ $currencyOther }}" maxlength="6" placeholder="e.g. AED">
            </label>
        </div>

        <div class="stack" style="gap:12px">
            <div class="sec-title"><h2>Bill to</h2><span class="muted">Pick a saved vendor and the rest fills in</span></div>
            <div id="autofill-note" class="autofill" hidden></div>
            <div class="grid">
                <label class="f">Vendor / client name
                    <input id="vendor_name" name="vendor[name]" list="vendor-list" value="{{ old('vendor.name', $vendor?->name) }}" required placeholder="Start typing a name">
                </label>
                <label class="f">Phone
                    <input id="vendor_phone" name="vendor[phone]" inputmode="tel" value="{{ old('vendor.phone', $vendor?->phone) }}">
                </label>
                <label class="f">Email
                    <input id="vendor_email" type="email" name="vendor[email]" value="{{ old('vendor.email', $vendor?->email) }}">
                </label>
            </div>
            <label class="f">Address
                <input id="vendor_address" name="vendor[address]" value="{{ old('vendor.address', $vendor?->address) }}">
            </label>
            <datalist id="vendor-list">
                @foreach ($vendors as $v)
                    <option value="{{ $v->name }}"></option>
                @endforeach
            </datalist>
        </div>

        <div class="stack" style="gap:10px">
            <div class="sec-title"><h2>Items</h2><span class="muted" id="items-sub"></span></div>
            <div class="tablebox">
                <table class="items">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Description</th>
                            <th class="num">Quantity</th>
                            <th class="num">Unit cost</th>
                            <th class="num">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        @foreach ($rows as $i => $row)
                            <tr>
                                <td class="sl">{{ $i + 1 }}</td>
                                <td><input data-field="description" name="items[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" aria-label="Description" placeholder="e.g. Website design"></td>
                                @php
                                    $q = $row['quantity'] ?? 1;
                                    $u = $row['unit_price'] ?? ($row['amount'] ?? '');
                                @endphp
                                <td class="qty"><input class="num" type="number" min="0" step="any" data-field="quantity" name="items[{{ $i }}][quantity]" value="{{ $q }}" aria-label="Quantity"></td>
                                <td class="amt"><input class="num" type="number" min="0" step="0.01" data-field="unit_price" name="items[{{ $i }}][unit_price]" value="{{ $u }}" aria-label="Unit cost" placeholder="0.00"></td>
                                <td class="line-total num" data-total>{{ number_format((float) $q * (float) $u, 2) }}</td>
                                <td class="x"><button type="button" class="iconbtn" data-remove aria-label="Remove row" title="Remove row">×</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div><button class="btn small" type="button" id="add-row">+ Add row</button></div>
        </div>

        <div class="foot">
            <label class="f">Comment
                <textarea id="comment" name="comment" placeholder="Payment terms, bank details, thank-you note…">{{ old('comment', $invoice->comment) }}</textarea>
            </label>
            <div class="ledger" aria-label="Totals">
                <div class="l total"><span>Total amount</span><span class="v" id="o-total">0.00</span></div>
                <div class="l" id="row-before"><label for="before_pay">Before pay</label><input id="before_pay" name="before_pay" type="number" step="0.01" min="0" value="{{ old('before_pay', (float) $invoice->before_pay) }}"></div>
                <div class="l"><label for="now_pay">Now pay</label><input id="now_pay" name="now_pay" type="number" step="0.01" min="0" value="{{ old('now_pay', (float) $invoice->now_pay) }}"></div>
                <div class="l due" id="due-row"><span>Due amount</span><span class="v" id="o-due">0.00</span></div>
            </div>
        </div>
    </div>

    <div class="row">
        <span class="spacer"></span>
        <button class="btn primary" type="submit">Save invoice</button>
    </div>
</form>

<template id="row-template">
    <tr>
        <td class="sl"></td>
        <td><input data-field="description" aria-label="Description" placeholder="e.g. Website design"></td>
        <td class="qty"><input data-field="quantity" class="num" type="number" min="0" step="any" value="1" aria-label="Quantity"></td>
        <td class="amt"><input data-field="unit_price" class="num" type="number" min="0" step="0.01" aria-label="Unit cost" placeholder="0.00"></td>
        <td class="line-total num" data-total>0.00</td>
        <td class="x"><button type="button" class="iconbtn" data-remove aria-label="Remove row" title="Remove row">×</button></td>
    </tr>
</template>
@endsection

@push('scripts')
    <script src="{{ asset('js/invoice-form.js') }}?v={{ filemtime(public_path('js/invoice-form.js')) }}" defer></script>
@endpush
