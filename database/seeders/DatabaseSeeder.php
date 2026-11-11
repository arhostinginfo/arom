<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\Unit;
use App\Models\User;
use App\Support\Access;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Units, currencies, the current financial year, every page permission, the four roles from decision D2 and the first Admin.
     * Safe to run again: it only adds what is missing.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (config('nursery.units') as $u) {
            Unit::firstOrCreate(['code' => $u['code']], ['name' => $u['name']]);
        }

        // Currencies for quotations (default INR) until the Currency/Country master is built.
        foreach ([['India', 'INR', '₹'], ['United States', 'USD', '$'], ['Euro area', 'EUR', '€']] as [$country, $code, $symbol]) {
            Currency::firstOrCreate(['code' => $code], ['country' => $country, 'symbol' => $symbol]);
        }
        // This year and next, so 1 April never has to create a row while people are working (A6).
        FinancialYear::forDate(now());
        FinancialYear::forDate(now()->addYear());

        foreach (Access::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (config('nursery.roles') as $name => $perms) {
            $role = Role::findOrCreate($name, 'web');
            if ($role->wasRecentlyCreated || $name === 'Admin') {
                $role->syncPermissions(Access::expand($perms));
            }
        }

        $email = env('ADMIN_EMAIL', 'admin@nursery.local');
        if (! User::where('email', $email)->exists()) {
            $admin = User::create([
                'name' => env('ADMIN_NAME', 'Administrator'),
                'email' => $email,
                'mobile' => env('ADMIN_MOBILE', '9800000000'),
                'password' => env('ADMIN_PASSWORD', 'Admin@12345'),
                'status' => 'Active',
            ]);
            $admin->assignRole('Admin');
            $admin->units()->sync(Unit::pluck('id'));
        }
    }
}
