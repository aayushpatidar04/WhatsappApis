<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\CreditPackage;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super Admin
        $superAdmin = User::create([
            'client_id'      => null,
            'name'           => 'Super Admin',
            'email'          => 'superadmin@waplatform.com',
            'password'       => Hash::make('password'),
            'role'           => 'super_admin',
            'credit_balance' => 999,
            'is_active'      => true,
        ]);

        // 2. Demo Client Tenant
        $client = Client::create([
            'name'                   => 'Demo Agency',
            'super_admin_id'         => $superAdmin->id,
            'max_rate_per_minute'    => 20,
            'max_instances_per_user' => 5,
            'credit_balance'         => 50,
            'is_active'              => true,
        ]);

        // 3. Client Master Admin
        User::create([
            'client_id'      => $client->id,
            'name'           => 'Master Admin',
            'email'          => 'admin@demoagency.com',
            'password'       => Hash::make('password'),
            'role'           => 'client_admin',
            'credit_balance' => 0,
            'is_active'      => true,
        ]);

        // 4. Demo End User
        $user = User::create([
            'client_id'      => $client->id,
            'name'           => 'Demo User',
            'email'          => 'user@demoagency.com',
            'password'       => Hash::make('password'),
            'role'           => 'user',
            'credit_balance' => 5,
            'is_active'      => true,
        ]);

        // 5. Demo Instances
        // Master admin's own instance (owner_type = 'client')
        WhatsappInstance::create([
            'owner_id'        => $client->id,
            'owner_type'      => 'client',
            'client_id'       => $client->id,
            'name'            => 'Agency Main Number',
            'status'          => 'pending',
            'credits_assigned'=> 3,
        ]);

        // End user instance (owner_type = 'user')
        WhatsappInstance::create([
            'owner_id'        => $user->id,
            'owner_type'      => 'user',
            'client_id'       => $client->id,
            'name'            => 'Sales WhatsApp',
            'status'          => 'pending',
            'credits_assigned'=> 2,
        ]);

        // 6. Credit Packages
        foreach ([
            ['Starter',    1,  199.00,  '1 instance for 1 month.'],
            ['Growth',     5,  899.00,  '5 instance-months. Flexible usage.'],
            ['Business',   15, 2399.00, '15 instance-months. Best for agencies.'],
            ['Enterprise', 50, 6999.00, '50 instance-months. Bulk pricing.'],
        ] as [$name, $credits, $price, $desc]) {
            CreditPackage::create([
                'name'        => $name,
                'credits'     => $credits,
                'price'       => $price,
                'currency'    => 'INR',
                'description' => $desc,
                'is_active'   => true,
            ]);
        }

        $this->command->info('superadmin@waplatform.com / password');
        $this->command->info('admin@demoagency.com / password');
        $this->command->info('user@demoagency.com / password');
    }
}