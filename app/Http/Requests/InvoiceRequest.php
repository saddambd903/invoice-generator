<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Drop rows the user left completely empty, and renumber what's left.
     * Quantity defaults to 1. An older-style row that only sends "amount" is read as 1 × amount.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($row) => is_array($row))
            ->map(function ($row) {
                if (($row['unit_price'] ?? '') === '' && ($row['amount'] ?? '') !== '') {
                    $row['unit_price'] = $row['amount'];
                }
                if (($row['quantity'] ?? '') === '' || $row['quantity'] === null) {
                    $row['quantity'] = 1;
                }
                unset($row['amount']); // the line total is always calculated: quantity × unit cost

                return $row;
            })
            ->filter(fn ($row) => trim((string) ($row['description'] ?? '')) !== '' || ($row['unit_price'] ?? '') !== '')
            ->values()
            ->all();

        $this->merge([
            'items' => $items,
            'before_pay' => $this->input('before_pay') ?: 0,
            'now_pay' => $this->input('now_pay') ?: 0,
            'currency_other' => strtoupper(trim((string) $this->input('currency_other'))),
        ]);
    }

    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $currencies = array_merge(array_keys(Invoice::CURRENCIES), ['OTHER']);

        return [
            'type' => ['required', Rule::in(array_keys(Invoice::TYPES))],
            'number' => [
                'nullable', 'string', 'max:40',
                Rule::unique('invoices', 'number')
                    ->where('user_id', $this->user()->id)
                    ->ignore($invoice?->id),
            ],
            'invoice_date' => ['required', 'date'],
            'billing_month' => ['nullable', 'required_if:type,monthly', 'date_format:Y-m'],
            'currency' => ['required', Rule::in($currencies)],
            'currency_other' => ['nullable', 'required_if:currency,OTHER', 'alpha', 'min:2', 'max:6'],

            'vendor.name' => ['required', 'string', 'max:255'],
            'vendor.phone' => ['nullable', 'string', 'max:40'],
            'vendor.email' => ['nullable', 'email', 'max:255'],
            'vendor.address' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],

            'before_pay' => ['numeric', 'min:0'],
            'now_pay' => ['numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'vendor.name' => 'vendor name',
            'vendor.email' => 'vendor email',
            'items' => 'items',
            'items.*.description' => 'description',
            'items.*.quantity' => 'quantity',
            'items.*.unit_price' => 'unit cost',
            'currency_other' => 'currency code',
            'before_pay' => 'before pay',
            'now_pay' => 'now pay',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item.',
            'billing_month.required_if' => 'Pick the billing month for a monthly invoice.',
            'currency_other.required_if' => 'Enter the currency code, for example AED.',
        ];
    }
}
