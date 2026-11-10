<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(User $user, float $now = 0): void
    {
        $this->actingAs($user)->post(route('invoices.store'), [
            'type' => 'onetime', 'number' => '', 'invoice_date' => '2026-11-10', 'currency' => 'BDT',
            'vendor' => ['name' => 'Rahman Traders', 'phone' => '01711000000', 'address' => 'Motijheel, Dhaka'],
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 5000]],
            'before_pay' => 0, 'now_pay' => $now,
        ])->assertRedirect();
    }

    public function test_old_invoice_keeps_vendor_details_after_vendor_is_edited(): void
    {
        $user = User::factory()->create();
        $this->invoice($user);
        $invoice = $user->invoices()->first();
        $vendor = $invoice->vendor;

        $this->actingAs($user)->put(route('vendors.update', $vendor), [
            'name' => 'Rahman Traders Ltd', 'phone' => '01999999999', 'address' => 'Gulshan, Dhaka',
        ])->assertRedirect();

        $this->actingAs($user)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Rahman Traders')
            ->assertSee('01711000000')->assertSee('Motijheel, Dhaka')
            ->assertDontSee('Rahman Traders Ltd')->assertDontSee('01999999999');

        // The vendor profile shows the new details.
        $this->actingAs($user)->get(route('vendors.show', $vendor))->assertSee('Rahman Traders Ltd');
    }

    public function test_now_pay_row_is_hidden_when_zero(): void
    {
        $user = User::factory()->create();
        $this->invoice($user, 0);
        $this->actingAs($user)->get(route('invoices.show', $user->invoices()->first()))->assertDontSee('Now pay');

        $this->invoice($user, 1000);
        $this->actingAs($user)->get(route('invoices.show', $user->invoices()->latest('id')->first()))->assertSee('Now pay');
    }
}
