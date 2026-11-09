<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create(['name' => 'Platform Owner']);
        $u->forceFill(['is_super_admin' => true])->save();

        return $u;
    }

    private function ownerWithInvoice(): array
    {
        $owner = User::factory()->create(['name' => 'Shop Owner', 'business_name' => 'Rahman Shop']);
        $this->actingAs($owner)->post(route('invoices.store'), [
            'type' => 'onetime', 'number' => '', 'invoice_date' => '2026-11-08', 'currency' => 'BDT',
            'vendor' => ['name' => 'Client Ltd', 'phone' => '01711000000'],
            'items' => [['description' => 'Work', 'quantity' => 2, 'unit_price' => 1500]],
            'before_pay' => 0, 'now_pay' => 1000,
        ])->assertRedirect();
        auth()->logout();

        return [$owner, Invoice::first()];
    }

    public function test_panel_is_hidden_from_normal_users_and_guests(): void
    {
        $user = User::factory()->create();

        $this->get(route('admin.index'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('admin.index'))->assertNotFound();
        $this->actingAs($user)->get(route('invoices.index'))->assertDontSee('Super admin');
    }

    public function test_super_admin_sees_every_account_with_totals(): void
    {
        [$owner] = $this->ownerWithInvoice();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Shop Owner')->assertSee('Rahman Shop')->assertSee('Platform Owner')
            ->assertSee('৳3,000.00')   // billed
            ->assertSee('৳2,000.00');  // outstanding

        $this->actingAs($admin)->get(route('admin.show', $owner))
            ->assertOk()
            ->assertSee('Rahman Shop')
            ->assertSee($owner->email)
            ->assertSee('Client Ltd')
            ->assertSee('Read-only');
    }

    public function test_super_admin_can_view_but_not_change_another_accounts_data(): void
    {
        [$owner, $invoice] = $this->ownerWithInvoice();
        $vendor = $invoice->vendor;
        $admin = $this->superAdmin();

        // View: allowed, shown read-only without edit/delete controls.
        $this->actingAs($admin)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Read-only')
            ->assertDontSee(route('invoices.edit', $invoice))
            ->assertDontSee('Delete this invoice');

        $this->actingAs($admin)->get(route('vendors.show', $vendor))
            ->assertOk()
            ->assertSee('Read-only')
            ->assertDontSee('Edit details');

        // Change: refused.
        $this->actingAs($admin)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($admin)->delete(route('invoices.destroy', $invoice))->assertForbidden();
        $this->actingAs($admin)->put(route('vendors.update', $vendor), ['name' => 'Changed'])->assertForbidden();
        $this->actingAs($admin)->delete(route('vendors.destroy', $vendor))->assertForbidden();

        $this->assertNotNull($invoice->fresh());
        $this->assertSame('Client Ltd', $vendor->fresh()->name);

        // Super admin's own invoice list still only shows their own invoices.
        $this->actingAs($admin)->get(route('invoices.index'))->assertDontSee($invoice->number);
    }

    public function test_registration_cannot_make_a_super_admin(): void
    {
        $this->post(route('register'), [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com',
            'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
            'is_super_admin' => 1,
        ]);

        $this->assertFalse(User::where('email', 'sneaky@example.com')->first()->isSuperAdmin());
    }

    public function test_command_creates_promotes_and_revokes(): void
    {
        $this->artisan('app:super-admin', ['email' => 'boss@example.com', '--name' => 'Boss'])
            ->expectsQuestion('Password (at least 8 characters)', 'very-secret')
            ->expectsQuestion('Confirm password', 'very-secret')
            ->assertSuccessful();

        $boss = User::where('email', 'boss@example.com')->first();
        $this->assertTrue($boss->isSuperAdmin());
        $this->assertTrue(Hash::check('very-secret', $boss->password));

        $existing = User::factory()->create(['email' => 'staff@example.com']);
        $this->artisan('app:super-admin', ['email' => 'staff@example.com'])->assertSuccessful();
        $this->assertTrue($existing->fresh()->isSuperAdmin());

        $this->artisan('app:super-admin', ['email' => 'staff@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($existing->fresh()->isSuperAdmin());
    }

    public function test_command_rejects_short_or_mismatched_passwords(): void
    {
        $this->artisan('app:super-admin', ['email' => 'x@example.com', '--name' => 'X'])
            ->expectsQuestion('Password (at least 8 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->artisan('app:super-admin', ['email' => 'x@example.com', '--name' => 'X'])
            ->expectsQuestion('Password (at least 8 characters)', 'long-enough-1')
            ->expectsQuestion('Confirm password', 'long-enough-2')
            ->assertFailed();

        $this->assertNull(User::where('email', 'x@example.com')->first());
    }
}
