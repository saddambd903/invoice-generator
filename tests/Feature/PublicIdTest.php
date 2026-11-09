<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicIdTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(User $user): Invoice
    {
        $this->actingAs($user)->post(route('invoices.store'), [
            'type' => 'onetime', 'number' => '', 'invoice_date' => '2026-11-08', 'currency' => 'BDT',
            'vendor' => ['name' => 'Rahman Traders'],
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 1000]],
            'before_pay' => 0, 'now_pay' => 0,
        ])->assertRedirect();

        return $user->invoices()->latest('id')->first();
    }

    public function test_urls_use_random_ids_not_numbers(): void
    {
        $user = User::factory()->create();
        $invoice = $this->makeInvoice($user);
        $vendor = $invoice->vendor;

        $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $invoice->uid);
        $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $vendor->uid);
        $this->assertStringEndsWith('/invoices/'.$invoice->uid, route('invoices.show', $invoice));
        $this->assertStringEndsWith('/vendors/'.$vendor->uid, route('vendors.show', $vendor));

        $this->actingAs($user)->get(route('invoices.show', $invoice))->assertOk();
        $this->actingAs($user)->get(route('vendors.show', $vendor))->assertOk();

        // Old-style counting numbers no longer work.
        $this->actingAs($user)->get('/invoices/'.$invoice->id)->assertNotFound();
        $this->actingAs($user)->get('/vendors/'.$vendor->id)->assertNotFound();
    }

    public function test_every_record_gets_a_different_id(): void
    {
        $user = User::factory()->create();
        $a = $this->makeInvoice($user);
        $b = $this->makeInvoice($user);

        $this->assertNotSame($a->uid, $b->uid);
    }

    public function test_random_id_of_someone_elses_invoice_is_still_forbidden(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner);
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('invoices.show', $invoice))->assertForbidden();
    }

    public function test_new_invoice_for_vendor_uses_the_vendor_random_id(): void
    {
        $user = User::factory()->create();
        $vendor = $this->makeInvoice($user)->vendor;

        $this->actingAs($user)->get(route('invoices.create', ['vendor' => $vendor->uid]))
            ->assertOk()
            ->assertSee('data-prefill-vendor="'.$vendor->uid.'"', false);

        $this->actingAs($user)->getJson(route('vendors.lookup', ['id' => $vendor->uid]))
            ->assertJsonPath('found', true)
            ->assertJsonPath('vendor.uid', $vendor->uid)
            ->assertJsonMissingPath('vendor.id');
    }
}
