<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'type' => 'onetime',
            'number' => '',
            'invoice_date' => '2026-11-08',
            'due_date' => '2026-11-15',
            'currency' => 'BDT',
            'currency_other' => '',
            'vendor' => ['name' => 'Rahman Traders', 'phone' => '01711000000', 'email' => 'rahman@example.com', 'address' => 'Motijheel, Dhaka'],
            'items' => [
                ['description' => 'Website design', 'quantity' => 1, 'amount' => 25000],
                ['description' => 'Hosting (1 year)', 'quantity' => 1, 'amount' => 5000],
                ['description' => '', 'quantity' => 1, 'amount' => ''], // blank row is ignored
            ],
            'before_pay' => 10000,
            'now_pay' => 5000,
            'comment' => 'Thanks for your business.',
        ], $overrides);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/invoices')->assertRedirect('/login');
    }

    public function test_user_can_register_and_reach_the_form(): void
    {
        $this->post('/register', [
            'name' => 'Mahadi', 'email' => 'm@example.com',
            'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('invoices.create'));

        $this->get(route('invoices.create'))->assertOk()->assertSee('New invoice');
    }

    public function test_one_time_invoice_totals_are_calculated_on_the_server(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('invoices.store'), $this->payload())->assertRedirect();

        $invoice = $user->invoices()->with('items')->first();
        $this->assertSame('30000.00', $invoice->total);
        $this->assertSame('15000.00', $invoice->due);
        $this->assertCount(2, $invoice->items);
        $this->assertSame(['1', '2'], $invoice->items->pluck('sl')->map(fn ($s) => (string) $s)->all());
        $this->assertSame('partial', $invoice->status());
        $this->assertStringStartsWith('INV-'.now()->format('Ym').'-', $invoice->number);
    }

    public function test_monthly_invoice_has_no_quantity_and_needs_a_month(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('invoices.store'), $this->payload([
            'type' => 'monthly', 'billing_month' => '',
        ]))->assertSessionHasErrors('billing_month');

        $data = $this->payload(['type' => 'monthly', 'billing_month' => '2026-11']);
        $data['items'] = [['description' => 'Social media posts', 'quantity' => 12, 'unit_price' => 750]];
        $this->actingAs($user)->post(route('invoices.store'), $data)->assertRedirect();

        $invoice = $user->invoices()->with('items')->first();
        $this->assertSame('monthly', $invoice->type);
        $this->assertSame('12.00', $invoice->items->first()->quantity);
        $this->assertSame('750.00', $invoice->items->first()->unit_price);
        $this->assertSame('9000.00', $invoice->total); // 12 × 750
    }

    public function test_line_total_is_quantity_times_unit_cost(): void
    {
        $user = User::factory()->create();
        $data = $this->payload(['before_pay' => 0, 'now_pay' => 0]);
        $data['items'] = [
            ['description' => 'Business cards (box)', 'quantity' => 5, 'unit_price' => 800],
            ['description' => 'Design hours', 'quantity' => 2.5, 'unit_price' => 1200],
            // a sneaky browser-sent "amount" is ignored; the server recalculates
            ['description' => 'Printing', 'quantity' => 3, 'unit_price' => 100, 'amount' => 99999],
        ];
        $this->actingAs($user)->post(route('invoices.store'), $data)->assertRedirect();

        $invoice = $user->invoices()->with('items')->first();
        $this->assertSame(['4000.00', '3000.00', '300.00'], $invoice->items->pluck('amount')->all());
        $this->assertSame('7300.00', $invoice->total);
    }

    public function test_other_currency_is_stored_as_its_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('invoices.store'), $this->payload([
            'currency' => 'OTHER', 'currency_other' => 'aed',
        ]))->assertRedirect();

        $this->assertSame('AED', $user->invoices()->first()->currency);
    }

    public function test_users_cannot_see_each_others_invoices(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->post(route('invoices.store'), $this->payload());
        $invoice = Invoice::first();

        $this->actingAs($other)->get(route('invoices.show', $invoice))->assertForbidden();
        $this->actingAs($other)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($other)->delete(route('invoices.destroy', $invoice))->assertForbidden();
        $this->actingAs($other)->get(route('invoices.index'))->assertDontSee('Rahman Traders');
        $this->actingAs($other)->getJson(route('vendors.lookup', ['name' => 'Rahman Traders']))
            ->assertJson(['found' => false]);
    }

    public function test_same_vendor_lookup_returns_details_and_last_items(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('invoices.store'), $this->payload());

        $this->actingAs($user)->getJson(route('vendors.lookup', ['name' => 'Rahman Traders']))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('vendor.phone', '01711000000')
            ->assertJsonPath('last.items.0.description', 'Website design')
            ->assertJsonPath('last.currency', 'BDT')
            ->assertJsonCount(2, 'last.items');

        // A second invoice for the same name reuses the vendor instead of duplicating it.
        $this->actingAs($user)->post(route('invoices.store'), $this->payload());
        $this->assertSame(1, $user->vendors()->count());
        $this->assertSame(2, $user->invoices()->count());
    }

    public function test_invoice_can_be_updated_and_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('invoices.store'), $this->payload());
        $invoice = $user->invoices()->first();

        $this->actingAs($user)->put(route('invoices.update', $invoice), $this->payload([
            'number' => $invoice->number, 'now_pay' => 20000,
        ]))->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame('paid', $invoice->fresh()->status());

        $this->actingAs($user)->get(route('invoices.show', $invoice))->assertOk()->assertSee('Rahman Traders');

        $this->actingAs($user)->delete(route('invoices.destroy', $invoice))->assertRedirect(route('invoices.index'));
        $this->assertSame(0, $user->invoices()->count());
    }
}
