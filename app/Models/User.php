<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicId, Notifiable;

    // "is_super_admin" is deliberately NOT fillable: it can't be set from any form,
    // only with `php artisan app:super-admin`.
    protected $fillable = [
        'name', 'email', 'password',
        'business_name', 'business_logo', 'business_phone', 'business_email', 'business_address', 'invoice_prefix',
    ];

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /** Public URL of the uploaded business logo, or null. */
    public function logoUrl(): ?string
    {
        return $this->business_logo
            ? asset('uploads/'.$this->business_logo) // file lives in public/uploads/
            : null;
    }

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Next invoice number for this user, e.g. INV-202611-004.
     * Counts up within the current month and restarts at 001 the next month.
     */
    public function nextInvoiceNumber(): string
    {
        $prefix = trim($this->invoice_prefix ?: 'INV');
        $stem = $prefix.'-'.now()->format('Ym').'-';

        $max = $this->invoices()
            ->where('number', 'like', $stem.'%')
            ->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($stem)))
            ->max() ?? 0;

        return $stem.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
