<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Note there is no WithoutModelEvents here: Game generates its public
     * slug from a `creating` hook, and suppressing model events would leave
     * every seeded match without a shareable link.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
