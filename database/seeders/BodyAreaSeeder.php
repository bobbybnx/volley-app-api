<?php

namespace Database\Seeders;

use App\Models\BodyArea;
use Illuminate\Database\Seeder;

class BodyAreaSeeder extends Seeder
{
    public function run(): void
    {
        $bodyAreas = ['Tren superior', 'Tren inferior', 'Core', 'Brazos', 'Piernas', 'Hombros', 'Espalda'];
        
        foreach ($bodyAreas as $bodyArea) {
            BodyArea::firstOrCreate(['name' => $bodyArea]);
        }
    }
}
