<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Super admin panel: every account on the platform, read-only.
 * Protected by the EnsureSuperAdmin middleware (see routes/web.php).
 */
class SuperAdminController extends Controller
{
    public const SORTS = [
        'newest' => 'Newest accounts',
        'name' => 'Name (A–Z)',
        'billed' => 'Highest billed',
        'due' => 'Highest outstanding',
        'invoices' => 'Most invoices',
        'active' => 'Most recent invoice',
    ];

    /** All accounts with their totals, plus platform-wide figures. */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'newest';

        $accounts = User::query()
            ->withCount(['invoices', 'vendors'])
            ->withSum(['invoices as billed_bdt' => fn ($q) => $q->where('currency', 'BDT')], 'total')
            ->withSum(['invoices as due_bdt' => fn ($q) => $q->where('currency', 'BDT')->where('due', '>', 0)], 'due')
            ->withMax('invoices as last_invoice_at', 'invoice_date')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%");
                });
            });

        match ($sort) {
            'name' => $accounts->orderBy('name'),
            'billed' => $accounts->orderByDesc('billed_bdt'),
            'due' => $accounts->orderByDesc('due_bdt'),
            'invoices' => $accounts->orderByDesc('invoices_count'),
            'active' => $accounts->orderByDesc('last_invoice_at'),
            default => $accounts->orderByDesc('created_at')->orderByDesc('id'),
        };

        $bdt = Invoice::where('currency', 'BDT');
        $platform = [
            'accounts' => User::count(),
            'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'invoices' => Invoice::count(),
            'vendors' => Vendor::count(),
            'billed' => (float) (clone $bdt)->sum('total'),
            'outstanding' => (float) (clone $bdt)->where('due', '>', 0)->sum('due'),
        ];

        return view('admin.index', [
            'accounts' => $accounts->paginate(25)->withQueryString(),
            'platform' => $platform,
            'search' => $search,
            'sort' => $sort,
            'sorts' => self::SORTS,
        ]);
    }

    /** One account: business details, totals, invoices and vendors. */
    public function show(Request $request, User $account): View
    {
        $invoices = $account->invoices()
            ->with('vendor')
            ->orderByDesc('invoice_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'invoices_page')
            ->withQueryString();

        $vendors = $account->vendors()
            ->withCount('invoices')
            ->withSum(['invoices as due_bdt' => fn ($q) => $q->where('currency', 'BDT')->where('due', '>', 0)], 'due')
            ->orderBy('name')
            ->paginate(15, ['*'], 'vendors_page')
            ->withQueryString();

        $bdt = $account->invoices()->where('currency', 'BDT');
        $stats = [
            'invoices' => $account->invoices()->count(),
            'vendors' => $account->vendors()->count(),
            'billed' => (float) (clone $bdt)->sum('total'),
            'outstanding' => (float) (clone $bdt)->where('due', '>', 0)->sum('due'),
            'last_invoice' => $account->invoices()->max('invoice_date'),
        ];
        $stats['collected'] = $stats['billed'] - $stats['outstanding'];

        return view('admin.show', compact('account', 'invoices', 'vendors', 'stats'));
    }
}
