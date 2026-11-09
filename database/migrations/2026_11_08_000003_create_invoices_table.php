<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('number', 40);
            $table->enum('type', ['onetime', 'monthly'])->default('onetime');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('billing_month', 7)->nullable(); // YYYY-MM, monthly invoices only
            $table->string('currency', 10)->default('BDT'); // BDT, USD, EUR, GBP, INR or a custom code
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('before_pay', 14, 2)->default(0);
            $table->decimal('now_pay', 14, 2)->default(0);
            $table->decimal('due', 14, 2)->default(0);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'number']);
            $table->index(['user_id', 'invoice_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sl');
            $table->string('description');
            $table->decimal('quantity', 12, 2)->nullable(); // null on monthly invoices
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
