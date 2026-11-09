<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(User $user, string $vendor, float $amount, float $now = 0): void
    {
        $this->actingAs($user)->post(route('invoices.store'), [
            'type' => 'onetime', 'number' => '', 'invoice_date' => '2026-11-08', 'currency' => 'BDT',
            'vendor' => ['name' => $vendor, 'phone' => '01711000000'],
            'items' => [['description' => 'Work', 'quantity' => 1, 'amount' => $amount]],
            'before_pay' => 0, 'now_pay' => $now,
        ])->assertRedirect();
    }

    public function test_profile_shows_details_totals_and_invoices(): void
    {
        $user = User::factory()->create();
        $this->makeInvoice($user, 'Rahman Traders', 10000, 4000);
        $this->makeInvoice($user, 'Rahman Traders', 5000);
        $this->makeInvoice($user, 'Someone Else', 999);
        $vendor = $user->vendors()->where('name', 'Rahman Traders')->first();

        $this->actingAs($user)->get(route('vendors.show', $vendor))
            ->assertOk()
            ->assertSee('Rahman Traders')
            ->assertSee('01711000000')
            ->assertSee('৳15,000.00')   // billed
            ->assertSee('৳4,000.00')    // collected
            ->assertSee('৳11,000.00')   // outstanding
            ->assertDontSee('999.00');  // other vendor's invoice
    }

    public function test_vendor_details_can_be_edited(): void
    {
        $user = User::factory()->create();
        $this->makeInvoice($user, 'Rahman Traders', 1000);
        $vendor = $user->vendors()->first();

        $this->actingAs($user)->put(route('vendors.update', $vendor), [
            'name' => 'Rahman Traders Ltd', 'phone' => '01800000000', 'email' => 'a@b.com', 'address' => 'Dhaka',
        ])->assertRedirect(route('vendors.show', $vendor));

        $this->assertSame('Rahman Traders Ltd', $vendor->fresh()->name);
        $this->assertSame('01800000000', $vendor->fresh()->phone);
    }

    public function test_vendor_name_must_stay_unique_per_user(): void
    {
        $user = User::factory()->create();
        $this->makeInvoice($user, 'A Traders', 1000);
        $this->makeInvoice($user, 'B Traders', 1000);
        $b = $user->vendors()->where('name', 'B Traders')->first();

        $this->actingAs($user)->put(route('vendors.update', $b), ['name' => 'A Traders'])
            ->assertSessionHasErrors('name');
    }

    public function test_users_cannot_see_or_edit_each_others_vendors(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->makeInvoice($owner, 'Rahman Traders', 1000);
        $vendor = Vendor::first();

        $this->actingAs($other)->get(route('vendors.show', $vendor))->assertForbidden();
        $this->actingAs($other)->put(route('vendors.update', $vendor), ['name' => 'Hacked'])->assertForbidden();
        $this->assertSame('Rahman Traders', $vendor->fresh()->name);
    }
}
