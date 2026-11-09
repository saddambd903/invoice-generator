<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Create a super admin, promote an existing account, or remove the role.
 *
 *   php artisan app:super-admin admin@example.com            (create or promote)
 *   php artisan app:super-admin admin@example.com --revoke   (remove the role)
 *   php artisan app:super-admin --list                       (show current super admins)
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'app:super-admin
        {email? : Email of the account to create or promote}
        {--name= : Name for a new account}
        {--revoke : Remove super admin from this account}
        {--list : List current super admins}';

    protected $description = 'Create or promote a super admin account (can view every account, read-only)';

    public function handle(): int
    {
        if ($this->option('list')) {
            $admins = User::where('is_super_admin', true)->orderBy('email')->get(['name', 'email', 'created_at']);
            if ($admins->isEmpty()) {
                $this->info('There are no super admins yet.');
            } else {
                $this->table(['Name', 'Email', 'Joined'], $admins->map(fn ($u) => [$u->name, $u->email, $u->created_at?->format('d M Y')]));
            }

            return self::SUCCESS;
        }

        $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Email'))));
        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Please give a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($this->option('revoke')) {
            if (! $user || ! $user->isSuperAdmin()) {
                $this->warn("{$email} is not a super admin.");

                return self::SUCCESS;
            }
            $user->forceFill(['is_super_admin' => false])->save();
            $this->info("Super admin removed from {$email}.");

            return self::SUCCESS;
        }

        if ($user) {
            if ($user->isSuperAdmin()) {
                $this->info("{$email} is already a super admin.");

                return self::SUCCESS;
            }
            $user->forceFill(['is_super_admin' => true])->save();
            $this->info("{$email} is now a super admin. They can sign in with their existing password.");

            return self::SUCCESS;
        }

        // New account: ask for a password (typed input is hidden).
        $name = $this->option('name') ?: $this->ask('Name', 'Super Admin');
        $password = $this->secret('Password (at least 8 characters)');
        $confirm = $this->secret('Confirm password');

        if ($password !== $confirm) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }
        $check = Validator::make(['password' => $password], ['password' => ['required', Password::min(8)]]);
        if ($check->fails()) {
            $this->error($check->errors()->first('password'));

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'invoice_prefix' => 'INV']);
        $user->forceFill(['is_super_admin' => true])->save();

        $this->info("Super admin account created for {$email}. Sign in at ".route('login'));

        return self::SUCCESS;
    }
}
