<?php

namespace Database\Seeders;

use App\Models\Methodology;
use Illuminate\Database\Seeder;

class MethodologySeeder extends Seeder
{
    public function run(): void
    {
        $methodologies = ['Analítico', 'Sintético', 'Global'];
        
        foreach ($methodologies as $methodology) {
            Methodology::firstOrCreate(['name' => $methodology]);
        }
    }
}
