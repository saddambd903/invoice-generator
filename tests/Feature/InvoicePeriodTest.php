<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePeriodTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(User $user, string $date, float $amount, float $now = 0, string $vendor = 'Rahman Traders'): void
    {
        $this->actingAs($user)->post(route('invoices.store'), [
            'type' => 'onetime', 'number' => '', 'invoice_date' => $date, 'currency' => 'BDT',
            'vendor' => ['name' => $vendor],
            'items' => [['description' => 'Work', 'quantity' => 1, 'amount' => $amount]],
            'before_pay' => 0, 'now_pay' => $now,
        ])->assertRedirect();
    }

    private function seedInvoices(User $user): void
    {
        $this->invoice($user, '2025-12-20', 1000, 0, 'December Co');   // last year
        $this->invoice($user, '2026-10-05', 2000, 2000, 'October Co'); // paid
        $this->invoice($user, '2026-11-03', 3000, 1000, 'November Co');
        $this->invoice($user, '2026-11-25', 4000, 0, 'Late Nov Co');
    }

    public function test_month_period_filters_list_and_totals(): void
    {
        $user = User::factory()->create();
        $this->seedInvoices($user);

        $this->actingAs($user)->get(route('invoices.index', ['period' => 'month', 'month' => '2026-11']))
            ->assertOk()
            ->assertSee('November 2026')
            ->assertSee('November Co')->assertSee('Late Nov Co')
            ->assertDontSee('October Co')->assertDontSee('December Co')
            ->assertSee('৳7,000.00')   // billed in November
            ->assertSee('৳6,000.00');  // outstanding in November
    }

    public function test_year_period(): void
    {
        $user = User::factory()->create();
        $this->seedInvoices($user);

        $this->actingAs($user)->get(route('invoices.index', ['period' => 'year', 'year' => 2026]))
            ->assertSee('October Co')->assertSee('November Co')
            ->assertDontSee('December Co')
            ->assertSee('৳9,000.00'); // billed in 2026
    }

    public function test_date_to_date_period(): void
    {
        $user = User::factory()->create();
        $this->seedInvoices($user);

        $this->actingAs($user)->get(route('invoices.index', ['period' => 'range', 'from' => '2025-12-01', 'to' => '2026-10-31']))
            ->assertSee('December Co')->assertSee('October Co')
            ->assertDontSee('November Co')
            ->assertSee('৳3,000.00'); // billed in range

        // Reversed dates are swapped rather than returning nothing.
        $this->actingAs($user)->get(route('invoices.index', ['period' => 'range', 'from' => '2026-10-31', 'to' => '2025-12-01']))
            ->assertSee('December Co')->assertSee('October Co');
    }

    public function test_pagination_and_rows_per_page(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 12; $i++) {
            $this->invoice($user, '2026-11-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 100, 0, "Vendor {$i}");
        }

        $this->actingAs($user)->get(route('invoices.index', ['per_page' => 10]))
            ->assertSee('Showing')
            ->assertSee('1–10')
            ->assertSee('of')
            ->assertSee('Next ›');

        $this->actingAs($user)->get(route('invoices.index', ['per_page' => 10, 'page' => 2]))
            ->assertSee('11–12');

        // Unsupported page sizes fall back to 20.
        $this->actingAs($user)->get(route('invoices.index', ['per_page' => 7]))
            ->assertSee('1–12');
    }
}
