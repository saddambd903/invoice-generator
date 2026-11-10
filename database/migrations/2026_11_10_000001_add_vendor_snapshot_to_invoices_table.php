<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each invoice keeps its own copy of the vendor's name and contact details as they were
 * when the invoice was saved. Editing a vendor later no longer changes past invoices.
 * Existing invoices are filled in from the vendor's current details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('vendor_name')->nullable()->after('vendor_id');
            $table->string('vendor_phone', 40)->nullable()->after('vendor_name');
            $table->string('vendor_email')->nullable()->after('vendor_phone');
            $table->string('vendor_address')->nullable()->after('vendor_email');
        });

        DB::table('invoices')->whereNull('vendor_name')->orderBy('id')->each(function ($invoice) {
            $vendor = DB::table('vendors')->where('id', $invoice->vendor_id)->first();
            if ($vendor) {
                DB::table('invoices')->where('id', $invoice->id)->update([
                    'vendor_name' => $vendor->name,
                    'vendor_phone' => $vendor->phone,
                    'vendor_email' => $vendor->email,
                    'vendor_address' => $vendor->address,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['vendor_name', 'vendor_phone', 'vendor_email', 'vendor_address']);
        });
    }
};
