<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['code' => 'ES', 'name' => 'Spain'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'PT', 'name' => 'Portugal'],
            ['code' => 'IT', 'name' => 'Italy'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'US', 'name' => 'United States'],
        ];

        foreach ($countries as $c) {
            Country::updateOrCreate(['code' => $c['code']], $c);
        }
    }
}
