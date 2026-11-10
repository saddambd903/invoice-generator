<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasPublicId;

    public const TYPES = ['onetime' => 'One time', 'monthly' => 'Monthly'];

    /** An invoice can be edited or deleted for this many days after it was created. */
    public const LOCK_AFTER_DAYS = 5;

    /** When editing and deleting stop being allowed. */
    public function locksAt(): \Illuminate\Support\Carbon
    {
        return ($this->created_at ?? now())->copy()->addDays(self::LOCK_AFTER_DAYS);
    }

    public function isLocked(): bool
    {
        return now()->greaterThanOrEqualTo($this->locksAt());
    }

    public const CURRENCIES = [
        'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳'],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
    ];

    protected $fillable = [
        'vendor_id', 'vendor_name', 'vendor_phone', 'vendor_email', 'vendor_address', 'number', 'type', 'invoice_date', 'due_date', 'billing_month',
        'currency', 'total', 'before_pay', 'now_pay', 'due', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'total' => 'decimal:2',
            'before_pay' => 'decimal:2',
            'now_pay' => 'decimal:2',
            'due' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sl');
    }

    /**
     * Who the invoice was billed to, exactly as it was when the invoice was saved.
     * Falls back to the vendor record for any invoice saved before this was recorded.
     *
     * @return array{name: string, phone: ?string, email: ?string, address: ?string}
     */
    public function billTo(): array
    {
        $v = $this->vendor;

        return [
            'name' => $this->vendor_name ?? $v?->name ?? '',
            'phone' => $this->vendor_name !== null ? $this->vendor_phone : $v?->phone,
            'email' => $this->vendor_name !== null ? $this->vendor_email : $v?->email,
            'address' => $this->vendor_name !== null ? $this->vendor_address : $v?->address,
        ];
    }

    public function billToName(): string
    {
        return $this->billTo()['name'];
    }

    public function isMonthly(): bool
    {
        return $this->type === 'monthly';
    }

    public function symbol(): string
    {
        return self::CURRENCIES[$this->currency]['symbol'] ?? $this->currency.' ';
    }

    public function money(float|string|null $value): string
    {
        return $this->symbol().number_format((float) $value, 2);
    }

    /** paid | partial | unpaid */
    public function status(): string
    {
        if ((float) $this->due <= 0.004) {
            return 'paid';
        }

        return ((float) $this->before_pay + (float) $this->now_pay) > 0 ? 'partial' : 'unpaid';
    }

    public function statusLabel(): string
    {
        return ['paid' => 'Paid', 'partial' => 'Partly paid', 'unpaid' => 'Unpaid'][$this->status()];
    }

    public function billingMonthLabel(): ?string
    {
        return $this->billing_month
            ? \Carbon\Carbon::createFromFormat('Y-m-d', $this->billing_month.'-01')->format('F Y')
            : null;
    }
}
