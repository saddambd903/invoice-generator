<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Items now have Quantity × Unit cost = Total (amount) on both one-time and monthly invoices.
 * Existing rows are converted so their totals do not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_price', 14, 2)->default(0)->after('quantity');
        });

        // Old monthly rows had no quantity: treat them as 1 × amount.
        DB::table('invoice_items')->whereNull('quantity')->orWhere('quantity', '<=', 0)->update(['quantity' => 1]);

        // Unit cost = old line amount ÷ quantity.
        DB::table('invoice_items')->orderBy('id')->each(function ($row) {
            $qty = (float) $row->quantity ?: 1;
            DB::table('invoice_items')->where('id', $row->id)->update([
                'unit_price' => round((float) $row->amount / $qty, 2),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};
