<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceSaver;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceSaver $saver) {}

    public const PER_PAGE = [10, 20, 50, 100];

    public const PERIODS = [
        'all' => 'All time',
        'month' => 'Month',
        'year' => 'Year',
        'range' => 'Date to date',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), ['paid', 'partial', 'unpaid'], true) ? $request->query('status') : 'all';
        $perPage = in_array($request->integer('per_page'), self::PER_PAGE, true) ? $request->integer('per_page') : 20;

        // ---- period: all time / one month / one year / from–to (by invoice date) ----
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'all';
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $year = preg_match('/^\d{4}$/', (string) $request->query('year')) ? (int) $request->query('year') : now()->year;
        $from = $this->dateOrNull($request->query('from')) ?? now()->startOfMonth();
        $to = $this->dateOrNull($request->query('to')) ?? now();
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        [$start, $end, $periodLabel] = match ($period) {
            'month' => [
                Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth(),
                Carbon::createFromFormat('Y-m-d', $month.'-01')->endOfMonth(),
                Carbon::createFromFormat('Y-m-d', $month.'-01')->format('F Y'),
            ],
            'year' => [Carbon::create($year)->startOfYear(), Carbon::create($year)->endOfYear(), (string) $year],
            'range' => [$from->copy()->startOfDay(), $to->copy()->endOfDay(), $from->format('d M Y').' – '.$to->format('d M Y')],
            default => [null, null, 'All time'],
        };

        // One filtered query drives both the table and the summary, so the totals always match what's listed.
        $filtered = $user->invoices()
            ->when($start, fn ($q) => $q->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()]))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'paid', fn ($q) => $q->where('due', '<=', 0))
            ->when($status === 'partial', fn ($q) => $q->where('due', '>', 0)->whereRaw('(before_pay + now_pay) > 0'))
            ->when($status === 'unpaid', fn ($q) => $q->where('due', '>', 0)->whereRaw('(before_pay + now_pay) = 0'));

        $invoices = (clone $filtered)
            ->with('vendor')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        // Summary figures are BDT only: adding taka to dollars would be meaningless.
        $bdt = (clone $filtered)->where('currency', 'BDT');
        $stats = [
            'count' => (clone $filtered)->count(),
            'billed' => (float) (clone $bdt)->sum('total'),
            'outstanding' => (float) (clone $bdt)->where('due', '>', 0)->sum('due'),
            'other_currency' => (clone $filtered)->where('currency', '!=', 'BDT')->count(),
        ];
        $stats['collected'] = $stats['billed'] - $stats['outstanding'];
        $stats['has_any'] = $user->invoices()->exists();

        // Years that have invoices, for the year picker (always includes this year).
        $years = $user->invoices()->pluck('invoice_date')
            ->map(fn ($d) => $d->year)->push(now()->year)->unique()->sortDesc()->values();

        return view('invoices.index', [
            'invoices' => $invoices,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE,
            'period' => $period,
            'periods' => self::PERIODS,
            'periodLabel' => $periodLabel,
            'month' => $month,
            'year' => $year,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'years' => $years,
        ]);
    }

    /** Saved vendors for the vendor picker on the invoice form (no database ids). */
    private function vendorOptions($user)
    {
        return $user->vendors()->orderBy('name')->get(['uid', 'name', 'phone', 'email', 'address'])
            ->map(fn ($v) => $v->only(['uid', 'name', 'phone', 'email', 'address']))
            ->values();
    }

    private function dateOrNull(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        $invoice = new Invoice([
            'type' => 'onetime',
            'currency' => 'BDT',
            'number' => $user->nextInvoiceNumber(),
            'invoice_date' => now(),
            'before_pay' => 0,
            'now_pay' => 0,
        ]);

        // "New invoice" from the vendor list: preselect that vendor so the form auto-fills.
        $prefillVendor = $request->filled('vendor')
            ? $user->vendors()->where('uid', (string) $request->query('vendor'))->first()
            : null;

        return view('invoices.form', [
            'invoice' => $invoice,
            'items' => [['description' => '', 'quantity' => 1, 'unit_price' => ''], ['description' => '', 'quantity' => 1, 'unit_price' => '']],
            'vendors' => $this->vendorOptions($user),
            'prefillVendor' => $prefillVendor,
        ]);
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->saver->save($request->user(), $request->validated());

        return redirect()->route('invoices.show', $invoice)->with('status', "Invoice {$invoice->number} saved.");
    }

    public function show(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);
        $invoice->load('items', 'vendor', 'user');

        return view('invoices.show', compact('invoice'));
    }

    public function edit(Request $request, Invoice $invoice): View|RedirectResponse
    {
        if ($blocked = $this->blockedChange('update', $invoice)) {
            return $blocked;
        }
        $invoice->load('items', 'vendor');

        return view('invoices.form', [
            'invoice' => $invoice,
            'items' => $invoice->items->map(fn ($i) => [
                'description' => $i->description,
                'quantity' => $i->quantity !== null ? (float) $i->quantity : 1,
                'unit_price' => (float) $i->unit_price,
            ])->all(),
            'vendors' => $this->vendorOptions($request->user()),
            'prefillVendor' => null,
        ]);
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        if ($blocked = $this->blockedChange('update', $invoice)) {
            return $blocked;
        }
        $invoice = $this->saver->save($request->user(), $request->validated(), $invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', "Invoice {$invoice->number} updated.");
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        if ($blocked = $this->blockedChange('delete', $invoice)) {
            return $blocked;
        }
        $number = $invoice->number;
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', "Invoice {$number} deleted.");
    }

    /**
     * Someone else's invoice: 403. The user's own invoice past its 5-day window:
     * back to the invoice with the reason. Otherwise null (go ahead).
     */
    private function blockedChange(string $ability, Invoice $invoice): ?RedirectResponse
    {
        $response = Gate::inspect($ability, $invoice);
        if ($response->allowed()) {
            return null;
        }

        abort_if($invoice->user_id !== auth()->id(), 403);

        return redirect()->route('invoices.show', $invoice)->withErrors(['invoice' => $response->message()]);
    }
}
