<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\InvoiceSaver;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo account: demo@example.com / password
     * Two example vendors and three example invoices, so the app opens with something to look at.
     */
    public function run(InvoiceSaver $saver): void
    {
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'business_name' => 'Example Studio',
            'business_phone' => '+880 1700-000000',
            'business_address' => 'House 1, Road 1, Dhanmondi, Dhaka',
            'invoice_prefix' => 'INV',
        ]);

        $saver->save($user, [
            'type' => 'onetime', 'number' => '', 'invoice_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(13)->toDateString(), 'currency' => 'BDT', 'currency_other' => '',
            'vendor' => ['name' => 'Example Traders', 'phone' => '01711-000000', 'email' => 'accounts@example.com', 'address' => 'Motijheel, Dhaka'],
            'items' => [
                ['description' => 'Logo design', 'quantity' => 1, 'unit_price' => 15000],
                ['description' => 'Business cards (box)', 'quantity' => 5, 'unit_price' => 800],
            ],
            'before_pay' => 10000, 'now_pay' => 0, 'comment' => 'Example invoice.',
        ]);

        foreach ([2, 1] as $monthsAgo) {
            $saver->save($user, [
                'type' => 'monthly', 'number' => '', 'invoice_date' => now()->subMonths($monthsAgo)->startOfMonth()->toDateString(),
                'due_date' => now()->subMonths($monthsAgo)->startOfMonth()->addDays(10)->toDateString(),
                'billing_month' => now()->subMonths($monthsAgo)->format('Y-m'), 'currency' => 'BDT', 'currency_other' => '',
                'vendor' => ['name' => 'Example Clinic', 'phone' => '01811-000000', 'email' => null, 'address' => 'Uttara, Dhaka'],
                'items' => [
                    ['description' => 'Website maintenance', 'quantity' => 1, 'unit_price' => 6000],
                    ['description' => 'Social media posts', 'quantity' => 12, 'unit_price' => 750],
                ],
                'before_pay' => 0, 'now_pay' => $monthsAgo === 2 ? 15000 : 0, 'comment' => 'Example monthly invoice.',
            ]);
        }
    }
}
