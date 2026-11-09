<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Random public IDs for URLs (e.g. /invoices/01jck8z5t9w3q6m2r7n4b1xhvd) instead of
 * counting numbers, so addresses reveal nothing and can't be guessed.
 * Existing invoices and vendors get one here.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'vendors'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->char('uid', 26)->nullable()->after('id');
            });

            DB::table($table)->whereNull('uid')->orderBy('id')->each(function ($row) use ($table) {
                DB::table($table)->where('id', $row->id)->update(['uid' => strtolower((string) Str::ulid())]);
            });

            Schema::table($table, function (Blueprint $t) {
                $t->unique('uid');
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'vendors'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table.'_uid_unique');
                $t->dropColumn('uid');
            });
        }
    }
};
