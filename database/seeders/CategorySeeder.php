<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Calentamiento es una categoría propia (no una subcategoría física).
        Category::firstOrCreate(
            ['name' => 'Calentamiento'],
            ['type' => 'warmup']
        );

        $physical = ['Fuerza', 'Velocidad', 'Resistencia', 'Flexibilidad'];

        foreach ($physical as $category) {
            Category::firstOrCreate(
                ['name' => $category],
                ['type' => 'physical']
            );
        }
    }
}
