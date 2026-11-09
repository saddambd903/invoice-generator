<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorListTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(User $user, string $vendor, float $amount, float $now = 0): void
    {
        $this->actingAs($user)->post(route('invoices.store'), [
            'type' => 'onetime', 'number' => '', 'invoice_date' => '2026-11-08', 'currency' => 'BDT',
            'vendor' => ['name' => $vendor, 'phone' => '01711000000'],
            'items' => [['description' => 'Work', 'quantity' => 1, 'amount' => $amount]],
            'before_pay' => 0, 'now_pay' => $now,
        ])->assertRedirect();
    }

    public function test_list_shows_totals_search_filter_and_sort(): void
    {
        $user = User::factory()->create();
        $this->invoice($user, 'Alpha Traders', 10000, 10000); // paid up
        $this->invoice($user, 'Beta Clinic', 8000, 2000);     // owes 6000
        $user->vendors()->create(['name' => 'Gamma Shop']);   // no invoices

        $this->actingAs($user)->get(route('vendors.index'))
            ->assertOk()
            ->assertSee('Alpha Traders')->assertSee('Beta Clinic')->assertSee('Gamma Shop')
            ->assertSee('৳18,000.00')  // billed
            ->assertSee('৳6,000.00');  // outstanding

        $this->actingAs($user)->get(route('vendors.index', ['q' => 'beta']))
            ->assertSee('Beta Clinic')->assertDontSee('Alpha Traders');

        $this->actingAs($user)->get(route('vendors.index', ['filter' => 'due']))
            ->assertSee('Beta Clinic')->assertDontSee('Alpha Traders')->assertDontSee('Gamma Shop');

        $this->actingAs($user)->get(route('vendors.index', ['filter' => 'none']))
            ->assertSee('Gamma Shop')->assertDontSee('Beta Clinic');

        $this->actingAs($user)->get(route('vendors.index', ['sort' => 'due']))
            ->assertSeeInOrder(['Beta Clinic', 'Alpha Traders']);
    }

    public function test_vendor_can_be_added_by_hand(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('vendors.store'), [
            'name' => 'New Client', 'phone' => '01900000000', 'email' => 'x@example.com',
        ])->assertRedirect();
        $this->assertSame(1, $user->vendors()->where('name', 'New Client')->count());

        $this->actingAs($user)->post(route('vendors.store'), ['name' => 'New Client'])
            ->assertSessionHasErrorsIn('newVendor', 'name');
    }

    public function test_export_downloads_only_own_vendors_as_csv(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->invoice($user, 'Mine Ltd', 5000, 1000);
        $this->invoice($other, 'Not Mine', 100);

        $response = $this->actingAs($user)->get(route('vendors.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Mine Ltd', $csv);
        $this->assertStringContainsString('4000.00', $csv); // outstanding
        $this->assertStringNotContainsString('Not Mine', $csv);
    }
}
