@extends('layouts.app')
@section('title', 'Vendors')

@section('content')
@php $newErrors = $errors->getBag('newVendor'); @endphp

<div class="page-head">
    <div>
        <h1>Vendors</h1>
        <p class="muted" style="margin:2px 0 0">Everyone you bill. Vendors are added automatically when you save an invoice, or you can add one here.</p>
    </div>
    <div class="row" style="gap:8px">
        <a class="btn" href="{{ route('vendors.export') }}">Export CSV</a>
        <button class="btn primary" type="button" id="add-vendor-toggle" aria-expanded="{{ $newErrors->any() ? 'true' : 'false' }}" aria-controls="add-vendor">+ Add vendor</button>
    </div>
</div>

<section class="panel add-vendor" id="add-vendor" @unless ($newErrors->any()) hidden @endunless>
    <form method="POST" action="{{ route('vendors.store') }}" class="stack" style="padding:18px 20px">
        @csrf
        <div class="row"><h2>New vendor</h2><span class="spacer"></span>
            <button class="btn ghost small" type="button" id="add-vendor-cancel">Cancel</button></div>
        @if ($newErrors->any())
            <div class="errors" role="alert">
                <ul style="margin:0">@foreach ($newErrors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="grid">
            <label class="f">Name<input id="nv-name" name="name" value="{{ old('name') }}" required></label>
            <label class="f">Phone<input id="nv-phone" name="phone" inputmode="tel" value="{{ old('phone') }}"></label>
            <label class="f">Email<input id="nv-email" type="email" name="email" value="{{ old('email') }}"></label>
        </div>
        <label class="f">Address<input id="nv-address" name="address" value="{{ old('address') }}"></label>
        <div><button class="btn primary" type="submit">Save vendor</button></div>
    </form>
</section>

<section class="summary" aria-label="Summary">
    <div class="sum">
        <div class="k">Total vendors</div>
        <div class="n">{{ number_format($summary['count']) }}</div>
    </div>
    <div class="sum">
        <div class="k">With outstanding</div>
        <div class="n {{ $summary['with_due'] ? 'warn' : '' }}">{{ number_format($summary['with_due']) }}</div>
        <div class="sub">vendors owe you money</div>
    </div>
    <div class="sum">
        <div class="k">Billed</div>
        <div class="n">৳{{ number_format($summary['billed'], 2) }}</div>
        <div class="sub">BDT, all vendors</div>
    </div>
    <div class="sum">
        <div class="k">Outstanding</div>
        <div class="n {{ $summary['outstanding'] > 0 ? 'warn' : '' }}">৳{{ number_format($summary['outstanding'], 2) }}</div>
        <div class="sub">Still to be paid</div>
    </div>
</section>

<section class="panel">
    <form class="toolbar" method="GET" action="{{ route('vendors.index') }}" role="search">
        <input id="q" name="q" value="{{ $search }}" placeholder="Search by name, phone or email" aria-label="Search vendors">
        <select id="filter" name="filter" onchange="this.form.submit()" aria-label="Filter vendors">
            @foreach ($filters as $key => $label)
                <option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select id="sort" name="sort" onchange="this.form.submit()" aria-label="Sort vendors">
            @foreach ($sorts as $key => $label)
                <option value="{{ $key }}" @selected($sort === $key)>Sort: {{ $label }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit">Search</button>
        @if ($search !== '' || $filter !== 'all' || $sort !== 'name')
            <a class="btn ghost" href="{{ route('vendors.index') }}">Clear</a>
        @endif
    </form>

    @if ($vendors->count())
        <div class="tablebox">
            <table class="data">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Contact</th>
                        <th class="num">Invoices</th>
                        <th class="num">Billed</th>
                        <th class="num">Outstanding</th>
                        <th>Last invoice</th>
                        <th><span class="sr">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendors as $vendor)
                        @php $due = (float) $vendor->due_bdt; @endphp
                        <tr onclick="if (!event.target.closest('a,button')) window.location='{{ route('vendors.show', $vendor) }}'">
                            <td>
                                <div class="who">
                                    <span class="avatar sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($vendor->name, 0, 1)) }}</span>
                                    <a class="name" href="{{ route('vendors.show', $vendor) }}">{{ $vendor->name }}</a>
                                </div>
                            </td>
                            <td class="contact-cell">
                                @if ($vendor->phone || $vendor->email)
                                    @if ($vendor->phone)<div>{{ $vendor->phone }}</div>@endif
                                    @if ($vendor->email)<div class="muted">{{ $vendor->email }}</div>@endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td class="num">{{ $vendor->invoices_count }}</td>
                            <td class="num">{{ $vendor->billed_bdt ? '৳'.number_format((float) $vendor->billed_bdt, 2) : '—' }}</td>
                            <td class="num">
                                @if ($due > 0)
                                    <span class="warn">৳{{ number_format($due, 2) }}</span>
                                @elseif ($vendor->invoices_count)
                                    <span class="chip paid">Paid up</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                @if ($vendor->last_invoice_at)
                                    {{ \Carbon\Carbon::parse($vendor->last_invoice_at)->format('d M Y') }}
                                    <div class="muted small">{{ \Carbon\Carbon::parse($vendor->last_invoice_at)->diffForHumans() }}</div>
                                @else
                                    <span class="muted">No invoices yet</span>
                                @endif
                            </td>
                            <td class="actions">
                                <a class="btn small" href="{{ route('invoices.create', ['vendor' => $vendor->uid]) }}">New invoice</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="panel-foot">
            @include('partials.pagination', ['paginator' => $vendors])
        </div>
    @else
        <div class="empty">
            @if ($summary['count'] === 0)
                <strong>No vendors yet</strong>
                Save an invoice or add a vendor and they will appear here.
            @else
                <strong>No matching vendors</strong>
                Try a different name, phone, email or filter.
            @endif
        </div>
    @endif
</section>

<script>
    (function () {
        var panel = document.getElementById('add-vendor');
        var toggle = document.getElementById('add-vendor-toggle');
        function set(open) {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) document.getElementById('nv-name').focus();
        }
        toggle.addEventListener('click', function () { set(panel.hidden); });
        document.getElementById('add-vendor-cancel').addEventListener('click', function () { set(false); });
    })();
</script>
@endsection
