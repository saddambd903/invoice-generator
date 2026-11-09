@extends('layouts.app')
@section('title', 'Invoices')

@section('content')
<div class="page-head">
    <div>
        <h1>Invoices</h1>
        <p class="muted" style="margin:2px 0 0">Invoices you have issued, newest first. Pick a month, year or date range to see its totals.</p>
    </div>
    <a class="btn primary" href="{{ route('invoices.create') }}">+ New invoice</a>
</div>

<form id="filters" class="periodbar" method="GET" action="{{ route('invoices.index') }}">
    <input type="hidden" name="per_page" value="{{ $perPage }}">
    <div class="seg" role="radiogroup" aria-label="Period">
        @foreach ($periods as $key => $label)
            <label><input type="radio" name="period" value="{{ $key }}" @checked($period === $key)><span>{{ $label }}</span></label>
        @endforeach
    </div>
    <div class="period-inputs">
        <label class="pin" data-for="month" @unless ($period === 'month') hidden @endunless>
            <span class="muted">Month</span>
            <input type="month" id="month" name="month" value="{{ $month }}" onchange="this.form.submit()">
        </label>
        <label class="pin" data-for="year" @unless ($period === 'year') hidden @endunless>
            <span class="muted">Year</span>
            <select id="year" name="year" onchange="this.form.submit()">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </label>
        <span class="pin" data-for="range" @unless ($period === 'range') hidden @endunless>
            <label><span class="muted">From</span> <input type="date" id="from" name="from" value="{{ $from }}"></label>
            <label><span class="muted">To</span> <input type="date" id="to" name="to" value="{{ $to }}"></label>
            <button class="btn small primary" type="submit">Apply</button>
        </span>
    </div>
    <div class="period-label">Showing: <strong>{{ $periodLabel }}</strong></div>
</form>

<section class="summary" aria-label="Summary for {{ $periodLabel }}">
    <div class="sum">
        <div class="k">Invoices</div>
        <div class="n">{{ number_format($stats['count']) }}</div>
        <div class="sub">{{ $periodLabel }}</div>
    </div>
    <div class="sum">
        <div class="k">Billed</div>
        <div class="n">৳{{ number_format($stats['billed'], 2) }}</div>
        <div class="sub">BDT invoices{{ $stats['other_currency'] ? ' · '.$stats['other_currency'].' in other currencies not included' : '' }}</div>
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

<section class="panel">
    <div class="toolbar" role="search">
        <input id="q" name="q" form="filters" value="{{ $search }}" placeholder="Search by vendor or invoice no." aria-label="Search invoices">
        <select id="status" name="status" form="filters" onchange="this.form.submit()" aria-label="Filter by status">
            <option value="all" @selected($status === 'all')>All statuses</option>
            <option value="unpaid" @selected($status === 'unpaid')>Unpaid</option>
            <option value="partial" @selected($status === 'partial')>Partly paid</option>
            <option value="paid" @selected($status === 'paid')>Paid</option>
        </select>
        <button class="btn" type="submit" form="filters">Search</button>
        @if ($search !== '' || $status !== 'all' || $period !== 'all')
            <a class="btn ghost" href="{{ route('invoices.index', ['per_page' => $perPage]) }}">Clear</a>
        @endif
    </div>

    @if ($invoices->count())
        <div class="tablebox">
            <table class="data">
                <thead>
                    <tr>
                        <th>Invoice no.</th>
                        <th>Vendor</th>
                        <th>Date</th>
                        <th>Billing month</th>
                        <th>Type</th>
                        <th class="num">Amount</th>
                        <th class="num">Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr onclick="window.location='{{ route('invoices.show', $invoice) }}'">
                            <td class="mono">
                                <a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a>
                                @if ($invoice->isLocked())<span class="lock" title="Locked: can no longer be edited or deleted" aria-label="Locked"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span>@endif
                            </td>
                            <td class="strong">{{ $invoice->vendor->name }}</td>
                            <td class="nowrap">{{ $invoice->invoice_date->format('d M Y') }}</td>
                            <td class="nowrap muted">{{ $invoice->billingMonthLabel() ?? '—' }}</td>
                            <td><span class="chip type">{{ \App\Models\Invoice::TYPES[$invoice->type] }}</span></td>
                            <td class="num strong">{{ $invoice->money($invoice->total) }}</td>
                            <td class="num {{ (float) $invoice->due > 0.004 ? 'warn' : 'muted' }}">
                                {{ (float) $invoice->due > 0.004 ? $invoice->money($invoice->due) : '—' }}
                            </td>
                            <td><span class="chip {{ $invoice->status() }}">{{ $invoice->statusLabel() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="panel-foot">
            @include('partials.pagination', ['paginator' => $invoices, 'perPage' => $perPage, 'perPageOptions' => $perPageOptions])
        </div>
    @else
        <div class="empty">
            @if (! $stats['has_any'])
                <strong>No invoices yet</strong>
                Your saved invoices appear here. Only you can see them.
                <div style="margin-top:12px"><a class="btn primary" href="{{ route('invoices.create') }}">Create your first invoice</a></div>
            @else
                <strong>No matching invoices</strong>
                Nothing matches this period, search or status. Try a wider period or clear the filters.
            @endif
        </div>
    @endif
</section>
<script>
    // Show the inputs for the chosen period; "All time" applies straight away.
    (function () {
        var form = document.getElementById('filters');
        form.querySelectorAll('input[name="period"]').forEach(function (r) {
            r.addEventListener('change', function () {
                form.querySelectorAll('.pin').forEach(function (el) { el.hidden = el.dataset.for !== r.value; });
                if (r.value === 'all' || r.value === 'month' || r.value === 'year') form.submit();
            });
        });
    })();
</script>
@endsection
