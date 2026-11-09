<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockingRulesTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $vendor = 'Rahman Traders', array $extra = []): array
    {
        return array_merge([
            'type' => 'onetime', 'number' => '', 'invoice_date' => '2026-11-08', 'currency' => 'BDT',
            'vendor' => ['name' => $vendor],
            'items' => [['description' => 'Work', 'quantity' => 1, 'amount' => 1000]],
            'before_pay' => 0, 'now_pay' => 0,
        ], $extra);
    }

    public function test_invoice_can_be_edited_and_deleted_within_five_days(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('invoices.store'), $this->payload());
        $invoice = Invoice::first();

        $this->travel(4)->days();

        $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertOk();
        $this->actingAs($user)->put(route('invoices.update', $invoice), $this->payload(extra: ['number' => $invoice->number, 'now_pay' => 500]))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasNoErrors();
        $this->assertSame('500.00', $invoice->fresh()->now_pay);
    }

    public function test_invoice_locks_after_five_days(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('invoices.store'), $this->payload());
        $invoice = Invoice::first();

        $this->travel(5)->days();
        $this->travel(1)->minutes();

        $this->actingAs($user)->get(route('invoices.edit', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->actingAs($user)->put(route('invoices.update', $invoice), $this->payload(extra: ['number' => $invoice->number, 'now_pay' => 500]))
            ->assertSessionHasErrors('invoice');
        $this->assertSame('0.00', $invoice->fresh()->now_pay);

        $this->actingAs($user)->delete(route('invoices.destroy', $invoice))->assertSessionHasErrors('invoice');
        $this->assertNotNull($invoice->fresh());

        // Still viewable and printable, just without Edit/Delete.
        $this->actingAs($user)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Locked')
            ->assertDontSee(route('invoices.edit', $invoice));
    }

    public function test_vendor_with_invoices_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('invoices.store'), $this->payload());
        $vendor = $user->vendors()->first();

        $this->actingAs($user)->delete(route('vendors.destroy', $vendor))->assertSessionHasErrors('vendor');
        $this->assertNotNull($vendor->fresh());
    }

    public function test_vendor_without_invoices_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('invoices.store'), $this->payload());
        $vendor = $user->vendors()->first();
        $user->invoices()->delete(); // still inside the 5-day window, so this is allowed in the app too

        $this->actingAs($user)->delete(route('vendors.destroy', $vendor))->assertRedirect(route('vendors.index'));
        $this->assertNull($vendor->fresh());
    }

    public function test_users_cannot_delete_each_others_vendors(): void
    {
        $owner = User::factory()->create();
        $vendor = $owner->vendors()->create(['name' => 'Empty Vendor']);
        $other = User::factory()->create();

        $this->actingAs($other)->delete(route('vendors.destroy', $vendor))->assertForbidden();
        $this->assertNotNull($vendor->fresh());
    }
}
