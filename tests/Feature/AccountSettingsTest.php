<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'name' => 'Old Name', 'email' => 'old@example.com', 'password' => 'old-password',
        ]);
    }

    public function test_page_opens_for_signed_in_users_only(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));
        $this->actingAs($this->user())->get(route('account.edit'))
            ->assertOk()->assertSee('old@example.com')->assertSee('Update password');
    }

    public function test_name_can_change_without_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('account.update'), ['name' => 'New Name', 'email' => 'old@example.com'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_email_change_needs_correct_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('account.update'), ['name' => 'Old Name', 'email' => 'new@example.com'])
            ->assertSessionHasErrorsIn('profile', 'email_password');

        $this->actingAs($user)->put(route('account.update'), ['name' => 'Old Name', 'email' => 'new@example.com', 'email_password' => 'wrong'])
            ->assertSessionHasErrorsIn('profile', 'email_password');
        $this->assertSame('old@example.com', $user->fresh()->email);

        $this->actingAs($user)->put(route('account.update'), ['name' => 'Old Name', 'email' => 'new@example.com', 'email_password' => 'old-password'])
            ->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_email_must_not_belong_to_another_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = $this->user();

        $this->actingAs($user)->put(route('account.update'), ['name' => 'Old Name', 'email' => 'taken@example.com', 'email_password' => 'old-password'])
            ->assertSessionHasErrorsIn('profile', 'email');
    }

    public function test_password_update_rules(): void
    {
        $user = $this->user();
        $put = fn (array $d) => $this->actingAs($user)->put(route('account.password'), $d);

        $put(['current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHasErrorsIn('password', 'current_password');
        $put(['current_password' => 'old-password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrorsIn('password', 'password');
        $put(['current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'different-pass'])
            ->assertSessionHasErrorsIn('password', 'password');
        $put(['current_password' => 'old-password', 'password' => 'old-password', 'password_confirmation' => 'old-password'])
            ->assertSessionHasErrorsIn('password', 'password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));

        $put(['current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_user_can_sign_in_with_new_details(): void
    {
        $user = $this->user();
        $this->actingAs($user)->put(route('account.update'), ['name' => 'X', 'email' => 'new@example.com', 'email_password' => 'old-password']);
        $this->actingAs($user)->put(route('account.password'), ['current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass']);
        $this->post(route('logout'));

        $this->post(route('login'), ['email' => 'old@example.com', 'password' => 'brand-new-pass'])->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => 'new@example.com', 'password' => 'brand-new-pass'])->assertRedirect(route('invoices.index'));
    }
}
