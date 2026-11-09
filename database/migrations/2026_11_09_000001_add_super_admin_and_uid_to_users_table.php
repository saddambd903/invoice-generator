<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * - is_super_admin: can open the Super admin panel and view every account (read-only).
 *   Only set with `php artisan app:super-admin`, never through registration.
 * - uid: random public ID so account pages are /super-admin/accounts/<random>, not /5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->char('uid', 26)->nullable()->after('id');
        });

        DB::table('users')->whereNull('uid')->orderBy('id')->each(function ($row) {
            DB::table('users')->where('id', $row->id)->update(['uid' => strtolower((string) Str::ulid())]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('uid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_uid_unique');
            $table->dropColumn(['is_super_admin', 'uid']);
        });
    }
};
