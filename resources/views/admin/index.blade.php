@extends('layouts.app')
@section('title', 'Super admin')

@section('content')
<div class="page-head">
    <div>
        <div class="admin-eyebrow">Super admin</div>
        <h1>All accounts</h1>
        <p class="muted" style="margin:2px 0 0">Every account on the platform. You can open any account and view its invoices and vendors. Viewing is read-only.</p>
    </div>
</div>

<section class="summary" aria-label="Platform totals">
    <div class="sum">
        <div class="k">Accounts</div>
        <div class="n">{{ number_format($platform['accounts']) }}</div>
        <div class="sub">{{ $platform['new_this_month'] }} new this month</div>
    </div>
    <div class="sum">
        <div class="k">Invoices</div>
        <div class="n">{{ number_format($platform['invoices']) }}</div>
        <div class="sub">{{ number_format($platform['vendors']) }} vendors</div>
    </div>
    <div class="sum">
        <div class="k">Billed</div>
        <div class="n">৳{{ number_format($platform['billed'], 2) }}</div>
        <div class="sub">BDT, all accounts</div>
    </div>
    <div class="sum">
        <div class="k">Outstanding</div>
        <div class="n {{ $platform['outstanding'] > 0 ? 'warn' : '' }}">৳{{ number_format($platform['outstanding'], 2) }}</div>
        <div class="sub">BDT, all accounts</div>
    </div>
</section>

<section class="panel">
    <form class="toolbar" method="GET" action="{{ route('admin.index') }}" role="search">
        <input id="q" name="q" value="{{ $search }}" placeholder="Search by name, email or business" aria-label="Search accounts">
        <select id="sort" name="sort" onchange="this.form.submit()" aria-label="Sort accounts">
            @foreach ($sorts as $key => $label)
                <option value="{{ $key }}" @selected($sort === $key)>Sort: {{ $label }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit">Search</button>
        @if ($search !== '' || $sort !== 'newest')
            <a class="btn ghost" href="{{ route('admin.index') }}">Clear</a>
        @endif
    </form>

    @if ($accounts->count())
        <div class="tablebox">
            <table class="data">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Business</th>
                        <th>Joined</th>
                        <th class="num">Invoices</th>
                        <th class="num">Vendors</th>
                        <th class="num">Billed</th>
                        <th class="num">Outstanding</th>
                        <th>Last invoice</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $a)
                        <tr onclick="if (!event.target.closest('a')) window.location='{{ route('admin.show', $a) }}'">
                            <td>
                                <div class="who">
                                    <span class="avatar sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($a->name, 0, 1)) }}</span>
                                    <div style="min-width:0">
                                        <a class="name" href="{{ route('admin.show', $a) }}">{{ $a->name }}</a>
                                        @if ($a->isSuperAdmin())<span class="chip admin">Super admin</span>@endif
                                        @if ($a->id === auth()->id())<span class="chip type">You</span>@endif
                                        <div class="muted small">{{ $a->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $a->business_name ?: '—' }}</td>
                            <td class="nowrap">{{ $a->created_at?->format('d M Y') }}</td>
                            <td class="num">{{ $a->invoices_count }}</td>
                            <td class="num">{{ $a->vendors_count }}</td>
                            <td class="num">{{ $a->billed_bdt ? '৳'.number_format((float) $a->billed_bdt, 2) : '—' }}</td>
                            <td class="num {{ (float) $a->due_bdt > 0 ? 'warn' : 'muted' }}">{{ (float) $a->due_bdt > 0 ? '৳'.number_format((float) $a->due_bdt, 2) : '—' }}</td>
                            <td class="nowrap">
                                @if ($a->last_invoice_at)
                                    {{ \Carbon\Carbon::parse($a->last_invoice_at)->format('d M Y') }}
                                @else
                                    <span class="muted">None yet</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-foot">
            @include('partials.pagination', ['paginator' => $accounts])
        </div>
    @else
        <div class="empty"><strong>No matching accounts</strong>Try a different name, email or business.</div>
    @endif
</section>
@endsection
