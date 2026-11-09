<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessProfileTest extends TestCase
{
    use RefreshDatabase;

    private array $fields = [
        'business_name' => 'Example Studio',
        'invoice_prefix' => 'INV',
    ];

    public function test_logo_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('uploads');
        $user = User::factory()->create();

        // Upload
        $this->actingAs($user)->put(route('profile.update'), $this->fields + [
            'business_logo' => UploadedFile::fake()->image('logo.png', 400, 120),
        ])->assertSessionHasNoErrors();

        $first = $user->fresh()->business_logo;
        $this->assertNotNull($first);
        Storage::disk('uploads')->assertExists($first);

        // Replace: the old file is deleted
        $this->actingAs($user)->put(route('profile.update'), $this->fields + [
            'business_logo' => UploadedFile::fake()->image('new.jpg', 300, 100),
        ]);
        $second = $user->fresh()->business_logo;
        $this->assertNotSame($first, $second);
        Storage::disk('uploads')->assertMissing($first);

        // Logo shows on the invoice page source data
        $this->assertStringContainsString('uploads/'.$second, $user->fresh()->logoUrl());

        // Saving other fields keeps the logo
        $this->actingAs($user)->put(route('profile.update'), $this->fields);
        $this->assertSame($second, $user->fresh()->business_logo);

        // Remove
        $this->actingAs($user)->put(route('profile.update'), $this->fields + ['remove_logo' => '1']);
        $this->assertNull($user->fresh()->business_logo);
        Storage::disk('uploads')->assertMissing($second);
    }

    public function test_non_image_and_svg_logos_are_rejected(): void
    {
        Storage::fake('uploads');
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), $this->fields + [
            'business_logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('business_logo');

        $this->actingAs($user)->put(route('profile.update'), $this->fields + [
            'business_logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('business_logo');

        $this->assertNull($user->fresh()->business_logo);
    }
}
