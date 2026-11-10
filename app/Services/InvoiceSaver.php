<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves an invoice with its vendor and items in one transaction.
 * Totals are always recalculated here, never trusted from the browser.
 */
class InvoiceSaver
{
    public function save(User $user, array $data, ?Invoice $invoice = null): Invoice
    {
        return DB::transaction(function () use ($user, $data, $invoice) {
            $vendorData = $data['vendor'];

            // Same name = same vendor (per user). Contact details are refreshed from this invoice.
            $vendor = $user->vendors()->updateOrCreate(
                ['name' => trim($vendorData['name'])],
                [
                    'phone' => $vendorData['phone'] ?? null,
                    'email' => $vendorData['email'] ?? null,
                    'address' => $vendorData['address'] ?? null,
                ],
            );

            $monthly = $data['type'] === 'monthly';
            // Both types: Total = Quantity × Unit cost.
            $items = collect($data['items'])->values()->map(function ($row, $i) {
                $qty = round((float) ($row['quantity'] ?? 1), 2);
                $unit = round((float) $row['unit_price'], 2);

                return [
                    'sl' => $i + 1,
                    'description' => trim($row['description']),
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'amount' => round($qty * $unit, 2),
                ];
            });

            $total = round($items->sum('amount'), 2);
            $before = $monthly ? 0.0 : round((float) $data['before_pay'], 2); // monthly invoices have no "Before pay"
            $now = round((float) $data['now_pay'], 2);

            $attributes = [
                'vendor_id' => $vendor->id,
                // Snapshot: this invoice keeps these details even if the vendor is edited later.
                'vendor_name' => $vendor->name,
                'vendor_phone' => $vendor->phone,
                'vendor_email' => $vendor->email,
                'vendor_address' => $vendor->address,
                'number' => ($data['number'] ?? null) ?: ($invoice?->number ?? $user->nextInvoiceNumber()),
                'type' => $data['type'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'billing_month' => $data['billing_month'] ?? null, // required for monthly, optional for one time
                'currency' => $data['currency'] === 'OTHER' ? $data['currency_other'] : $data['currency'],
                'total' => $total,
                'before_pay' => $before,
                'now_pay' => $now,
                'due' => round($total - $before - $now, 2),
                'comment' => $data['comment'] ?? null,
            ];

            if ($invoice) {
                $invoice->update($attributes);
                $invoice->items()->delete();
            } else {
                $invoice = $user->invoices()->create($attributes);
            }

            $invoice->items()->createMany($items->all());

            return $invoice->load('items', 'vendor');
        });
    }
}
