<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed AI Hub data
        $this->call(AiHubSeeder::class);

        // Seed legacy data (optional)
        // $account = Account::create(['name' => 'Acme Corporation']);
        // ...
    }
}
