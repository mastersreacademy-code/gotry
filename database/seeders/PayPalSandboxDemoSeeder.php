<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PayPalSandboxDemoSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = DB::transaction(function (): array {
            $accounts = [];
            foreach (['parent', 'provider', 'admin'] as $role) {
                $email = "gotry-paypal-{$role}@example.test";
                $password = Str::random(24);
                $user = User::firstOrCreate(['email' => $email], [
                    'name' => 'GoTry PayPal Test '.ucfirst($role),
                    'role' => $role,
                    'password' => $password,
                ]);

                if ($user->role !== $role || ! $user->is_active) {
                    throw new RuntimeException("Account role or status mismatch: {$email}");
                }

                $accounts[$role] = [
                    'email' => $email,
                    'password' => $user->wasRecentlyCreated ? $password : 'Existing password retained',
                ];
            }

            return $accounts;
        });

        $this->command->table(['Role', 'Email', 'Password'], array_map(
            fn ($role, $account) => [$role, $account['email'], $account['password']],
            array_keys($accounts),
            array_values($accounts),
        ));

        $status = $this->command->call('gotry:seed-demo-payment', [
            'parent' => $accounts['parent']['email'],
            'provider' => $accounts['provider']['email'],
            'admin' => $accounts['admin']['email'],
        ]);

        if ($status !== 0) {
            throw new RuntimeException('Could not seed the PayPal Sandbox trial.');
        }
    }
}
