<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorController extends Controller
{
    public const SORTS = [
        'name' => 'Name (A–Z)',
        'due' => 'Highest outstanding',
        'billed' => 'Highest billed',
        'recent' => 'Most recent invoice',
        'invoices' => 'Most invoices',
    ];

    public const FILTERS = [
        'all' => 'All vendors',
        'due' => 'With outstanding',
        'clear' => 'Fully paid',
        'none' => 'No invoices yet',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : 'all';
        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'name';

        $vendors = $this->vendorQuery($request, $search, $filter, $sort)->paginate(25)->withQueryString();

        // Summary across all of this user's vendors (not just the current page or filter). BDT only.
        $all = $this->vendorQuery($request, '', 'all', 'name')->get();
        $summary = [
            'count' => $all->count(),
            'with_due' => $all->where('due_bdt', '>', 0)->count(),
            'billed' => (float) $all->sum('billed_bdt'),
            'outstanding' => (float) $all->sum('due_bdt'),
        ];

        return view('vendors.index', [
            'vendors' => $vendors,
            'summary' => $summary,
            'search' => $search,
            'filter' => $filter,
            'sort' => $sort,
            'sorts' => self::SORTS,
            'filters' => self::FILTERS,
        ]);
    }

    /** Add a vendor by hand, before any invoice exists for them. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('newVendor', [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('vendors', 'name')->where('user_id', $request->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'You already have a vendor with this name.',
        ]);

        $vendor = $request->user()->vendors()->create($data);

        return redirect()->route('vendors.show', $vendor)->with('status', "{$vendor->name} added.");
    }

    /** Download the vendor list (with totals) as a CSV file that opens in Excel. */
    public function export(Request $request): StreamedResponse
    {
        $vendors = $this->vendorQuery($request, '', 'all', 'name')->get();
        $filename = 'vendors-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($vendors) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Bangla and ৳ correctly
            fputcsv($out, ['Name', 'Phone', 'Email', 'Address', 'Invoices', 'Billed (BDT)', 'Collected (BDT)', 'Outstanding (BDT)', 'Last invoice']);
            foreach ($vendors as $v) {
                $billed = (float) $v->billed_bdt;
                $due = (float) $v->due_bdt;
                fputcsv($out, [
                    $v->name, $v->phone, $v->email, $v->address, $v->invoices_count,
                    number_format($billed, 2, '.', ''), number_format($billed - $due, 2, '.', ''), number_format($due, 2, '.', ''),
                    $v->last_invoice_at ? Carbon::parse($v->last_invoice_at)->format('Y-m-d') : '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** This user's vendors with invoice count, BDT billed / outstanding and last invoice date. */
    private function vendorQuery(Request $request, string $search, string $filter, string $sort)
    {
        $query = $request->user()->vendors()
            ->withCount('invoices')
            ->withSum(['invoices as billed_bdt' => fn ($q) => $q->where('currency', 'BDT')], 'total')
            ->withSum(['invoices as due_bdt' => fn ($q) => $q->where('currency', 'BDT')->where('due', '>', 0)], 'due')
            ->withMax('invoices as last_invoice_at', 'invoice_date')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        match ($filter) {
            'due' => $query->whereHas('invoices', fn ($q) => $q->where('due', '>', 0)),
            'clear' => $query->has('invoices')->whereDoesntHave('invoices', fn ($q) => $q->where('due', '>', 0)),
            'none' => $query->doesntHave('invoices'),
            default => null,
        };

        match ($sort) {
            'due' => $query->orderByDesc('due_bdt')->orderBy('name'),
            'billed' => $query->orderByDesc('billed_bdt')->orderBy('name'),
            'recent' => $query->orderByDesc('last_invoice_at')->orderBy('name'),
            'invoices' => $query->orderByDesc('invoices_count')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        return $query;
    }

    /** One vendor's profile: contact details, totals per currency and every invoice. */
    public function show(Vendor $vendor): View
    {
        Gate::authorize('view', $vendor);

        $invoices = $vendor->invoices()
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        // Totals are kept per currency: taka and dollars are never added together.
        $totals = $invoices->groupBy('currency')->map(function ($group) {
            $billed = (float) $group->sum('total');
            $outstanding = (float) $group->sum(fn ($i) => max(0, (float) $i->due));

            return [
                'symbol' => $group->first()->symbol(),
                'billed' => $billed,
                'collected' => $billed - $outstanding,
                'outstanding' => $outstanding,
            ];
        })->sortKeysUsing(fn ($a, $b) => $a === 'BDT' ? -1 : ($b === 'BDT' ? 1 : strcmp($a, $b)));

        return view('vendors.show', [
            'vendor' => $vendor,
            'invoices' => $invoices,
            'totals' => $totals,
            'firstDate' => $invoices->min('invoice_date'),
            'lastDate' => $invoices->max('invoice_date'),
        ]);
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        Gate::authorize('update', $vendor);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('vendors', 'name')->where('user_id', $request->user()->id)->ignore($vendor->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'You already have another vendor with this name.',
        ]);

        $vendor->update($data);

        return redirect()->route('vendors.show', $vendor)->with('status', 'Vendor details saved.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $response = Gate::inspect('delete', $vendor); // refused while the vendor has any invoices
        if ($response->denied()) {
            abort_if($vendor->user_id !== auth()->id(), 403);

            return redirect()->route('vendors.show', $vendor)->withErrors(['vendor' => $response->message()]);
        }
        $name = $vendor->name;
        $vendor->delete();

        return redirect()->route('vendors.index')->with('status', "{$name} deleted.");
    }

    /**
     * Auto-fill data for the invoice form: the vendor's saved contact details plus
     * the type, currency, items and comment of their most recent invoice.
     * Looks up only the signed-in user's vendors.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['name' => ['nullable', 'string', 'max:255'], 'id' => ['nullable', 'string', 'size:26']]);

        $query = $request->user()->vendors();
        $vendor = $request->filled('id')
            ? $query->where('uid', $request->query('id'))->first()
            : $query->where('name', trim((string) $request->query('name')))->first();

        if (! $vendor) {
            return response()->json(['found' => false]);
        }

        $last = $vendor->invoices()->with('items')->latest('invoice_date')->latest('id')->first();

        $payload = [
            'found' => true,
            'vendor' => $vendor->only(['uid', 'name', 'phone', 'email', 'address']),
            'last' => null,
        ];

        if ($last) {
            $nextMonth = $last->billing_month
                ? Carbon::createFromFormat('Y-m-d', $last->billing_month.'-01')->addMonth()->format('Y-m')
                : now()->format('Y-m');

            $payload['last'] = [
                'number' => $last->number,
                'type' => $last->type,
                'currency' => $last->currency,
                'next_billing_month' => $nextMonth,
                'comment' => $last->comment,
                'due' => (float) $last->due,
                'due_label' => $last->money($last->due),
                'items' => $last->items->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity' => $i->quantity !== null ? (float) $i->quantity : 1,
                    'unit_price' => (float) $i->unit_price,
                ])->values(),
            ];
        }

        return response()->json($payload);
    }
}
