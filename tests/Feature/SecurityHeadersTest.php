<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_pages_are_never_cached(): void
    {
        $user = User::factory()->create();

        foreach (['invoices.index', 'invoices.create', 'vendors.index', 'profile.edit'] as $route) {
            $response = $this->actingAs($user)->get(route($route));
            $response->assertOk();
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'), $route);
            $response->assertHeader('Pragma', 'no-cache');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_logout_clears_browser_cache_and_blocks_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('invoices.index'))->assertOk();

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertHeader('Clear-Site-Data', '"cache"');

        $this->assertGuest();
        $this->get(route('invoices.index'))->assertRedirect(route('login'));
        $this->get(route('vendors.index'))->assertRedirect(route('login'));
    }
}
