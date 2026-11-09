@extends('layouts.app')
@section('title', $account->name.' · Super admin')

@section('content')
<div class="row">
    <a href="{{ route('admin.index') }}">← All accounts</a>
</div>

<div class="readonly-banner" role="note">
    Super admin view of <strong>{{ $account->name }}</strong>'s account. Read-only: nothing here can be changed from this view.
</div>

<div class="sheet vendor-head">
    <div class="row" style="align-items:flex-start;gap:16px">
        @if ($account->logoUrl())
            <img class="acct-logo" src="{{ $account->logoUrl() }}" alt="{{ $account->business_name ?: $account->name }} logo">
        @else
            <div class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($account->name, 0, 1)) }}</div>
        @endif
        <div class="stack" style="gap:4px;min-width:0;flex:1">
            <h1>{{ $account->business_name ?: $account->name }}</h1>
            <div class="contact">
                <span>Owner: {{ $account->name }}</span>
                <span>Sign-in email: {{ $account->email }}</span>
                @if ($account->isSuperAdmin())<span class="chip admin">Super admin</span>@endif
            </div>
            <div class="hint">
                Joined {{ $account->created_at?->format('d M Y') }}
                @if ($stats['last_invoice']) · last invoice {{ \Carbon\Carbon::parse($stats['last_invoice'])->format('d M Y') }} @endif
            </div>
        </div>
    </div>

    <dl class="kv">
        <div><dt>Business phone</dt><dd>{{ $account->business_phone ?: '—' }}</dd></div>
        <div><dt>Business email</dt><dd>{{ $account->business_email ?: '—' }}</dd></div>
        <div><dt>Address</dt><dd>{{ $account->business_address ?: '—' }}</dd></div>
        <div><dt>Invoice prefix</dt><dd class="mono">{{ $account->invoice_prefix ?: 'INV' }}</dd></div>
    </dl>
</div>

<section class="summary" aria-label="Account totals">
    <div class="sum">
        <div class="k">Invoices</div>
        <div class="n">{{ number_format($stats['invoices']) }}</div>
        <div class="sub">{{ number_format($stats['vendors']) }} vendors</div>
    </div>
    <div class="sum">
        <div class="k">Billed</div>
        <div class="n">৳{{ number_format($stats['billed'], 2) }}</div>
        <div class="sub">BDT invoices</div>
    </div>
    <div class="sum">
        <div class="k">Collected</div>
        <div class="n good">৳{{ number_format($stats['collected'], 2) }}</div>
        <div class="sub">{{ $stats['billed'] > 0 ? round($stats['collected'] / $stats['billed'] * 100) : 0 }}% of billed</div>
    </div>
    <div class="sum">
        <div class="k">Outstanding</div>
        <div class="n {{ $stats['outstanding'] > 0 ? 'warn' : '' }}">৳{{ number_format($stats['outstanding'], 2) }}</div>
        <div class="sub">Still to be paid</div>
    </div>
</section>

<section class="panel" id="invoices">
    <div class="panel-head"><h2>Invoices</h2></div>
    @if ($invoices->count())
        <div class="tablebox">
            <table class="data">
                <thead>
                    <tr>
                        <th>Invoice no.</th>
                        <th>Vendor</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="num">Amount</th>
                        <th class="num">Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr onclick="window.location='{{ route('invoices.show', $invoice) }}'">
                            <td class="mono"><a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                            <td class="strong">{{ $invoice->vendor->name }}</td>
                            <td class="nowrap">{{ $invoice->invoice_date->format('d M Y') }}</td>
                            <td><span class="chip type">{{ \App\Models\Invoice::TYPES[$invoice->type] }}</span></td>
                            <td class="num strong">{{ $invoice->money($invoice->total) }}</td>
                            <td class="num {{ (float) $invoice->due > 0.004 ? 'warn' : 'muted' }}">{{ (float) $invoice->due > 0.004 ? $invoice->money($invoice->due) : '—' }}</td>
                            <td><span class="chip {{ $invoice->status() }}">{{ $invoice->statusLabel() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-foot">@include('partials.pagination', ['paginator' => $invoices->fragment('invoices')])</div>
    @else
        <div class="empty"><strong>No invoices</strong>This account hasn't created any invoices yet.</div>
    @endif
</section>

<section class="panel" id="vendors">
    <div class="panel-head"><h2>Vendors</h2></div>
    @if ($vendors->count())
        <div class="tablebox">
            <table class="data">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Contact</th>
                        <th class="num">Invoices</th>
                        <th class="num">Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendors as $vendor)
                        <tr onclick="window.location='{{ route('vendors.show', $vendor) }}'">
                            <td>
                                <div class="who">
                                    <span class="avatar sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($vendor->name, 0, 1)) }}</span>
                                    <a class="name" href="{{ route('vendors.show', $vendor) }}">{{ $vendor->name }}</a>
                                </div>
                            </td>
                            <td class="contact-cell">
                                @if ($vendor->phone)<div>{{ $vendor->phone }}</div>@endif
                                @if ($vendor->email)<div class="muted">{{ $vendor->email }}</div>@endif
                                @unless ($vendor->phone || $vendor->email)<span class="muted">—</span>@endunless
                            </td>
                            <td class="num">{{ $vendor->invoices_count }}</td>
                            <td class="num {{ (float) $vendor->due_bdt > 0 ? 'warn' : 'muted' }}">{{ (float) $vendor->due_bdt > 0 ? '৳'.number_format((float) $vendor->due_bdt, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-foot">@include('partials.pagination', ['paginator' => $vendors->fragment('vendors')])</div>
    @else
        <div class="empty"><strong>No vendors</strong>This account hasn't added any vendors yet.</div>
    @endif
</section>
@endsection
