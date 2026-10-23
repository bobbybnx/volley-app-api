<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            MethodologySeeder::class,
            FundamentalSeeder::class,
            BodyAreaSeeder::class,
            DemoSeeder::class,
            SocialDemoSeeder::class,
            BobsonUserSeeder::class,
        ]);
    }
}
