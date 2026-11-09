{{--
    Numbered pagination bar.
    Usage: @include('partials.pagination', ['paginator' => $items, 'perPage' => 20, 'perPageOptions' => [10, 20, 50, 100]])
    perPage / perPageOptions are optional; when given, a "Rows per page" selector is shown.
--}}
@php
    $current = $paginator->currentPage();
    $last = max(1, $paginator->lastPage());
    // Window of page numbers around the current page, plus first and last.
    $pages = collect([1, $last])
        ->merge(range(max(1, $current - 2), min($last, $current + 2)))
        ->unique()->sort()->values();
@endphp

<div class="pager">
    <div class="pager-info muted">
        @if ($paginator->total())
            Showing <strong>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong> of <strong>{{ $paginator->total() }}</strong>
        @else
            No results
        @endif
    </div>

    @isset($perPageOptions)
        <form class="pager-size" method="GET">
            @foreach (request()->except(['per_page', 'page']) as $key => $value)
                @if (is_string($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
            @endforeach
            <label for="per_page" class="muted">Rows per page</label>
            <select id="per_page" name="per_page" onchange="this.form.submit()">
                @foreach ($perPageOptions as $n)
                    <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </form>
    @endisset

    <nav class="pager-links" aria-label="Pages">
        @if ($paginator->onFirstPage())
            <span class="pg disabled" aria-disabled="true">‹ Prev</span>
        @else
            <a class="pg" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Prev</a>
        @endif

        @foreach ($pages as $i => $page)
            @if ($i > 0 && $page - $pages[$i - 1] > 1)
                <span class="pg gap" aria-hidden="true">…</span>
            @endif
            @if ($page === $current)
                <span class="pg cur" aria-current="page">{{ $page }}</span>
            @else
                <a class="pg" href="{{ $paginator->url($page) }}">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="pg" href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>
        @else
            <span class="pg disabled" aria-disabled="true">Next ›</span>
        @endif
    </nav>
</div>
