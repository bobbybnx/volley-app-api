<?php

namespace Database\Seeders;

use App\Models\BodyArea;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\Fundamental;
use App\Models\Methodology;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de ejemplo para el directorio social y el muro de entrenamientos.
 * Es idempotente: se puede ejecutar varias veces sin duplicar información.
 */
class SocialDemoSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = $this->catalog();

        $trainers = [
            [
                'email' => 'laura@volley.app',
                'name' => 'Laura Martín',
                'username' => 'laura-martin',
                'display_name' => 'Laura Martín',
                'bio' => 'Entrenadora de categorías formativas. Especialista en técnica de recepción y defensa.',
                'is_private' => false,
                'theme' => 'light',
            ],
            [
                'email' => 'carlos@volley.app',
                'name' => 'Carlos Pérez',
                'username' => 'carlos-perez',
                'display_name' => 'Carlos Pérez',
                'bio' => 'Preparador físico y entrenador de bloqueo. Comparto sesiones de fuerza aplicada al voleibol.',
                'is_private' => false,
                'theme' => 'dark',
            ],
            [
                'email' => 'marta@volley.app',
                'name' => 'Marta Privada',
                'username' => 'marta-privada',
                'display_name' => 'Marta Privada',
                'bio' => 'Perfil privado de ejemplo: sus entrenamientos no aparecen en el muro público.',
                'is_private' => true,
                'theme' => 'light',
            ],
        ];

        foreach ($trainers as $data) {
            $trainer = User::firstOrCreate(
                ['email' => $data['email']],
                [...$data, 'password' => Hash::make('password123')]
            );

            if ($trainer->workouts()->exists()) {
                continue;
            }

            $warmup = Exercise::create([
                'user_id' => $trainer->id,
                'title' => "Activación específica · {$trainer->display_name}",
                'type' => 'warmup',
                'description' => "1. Movilidad de tobillo y cadera durante 3 minutos.\n2. Desplazamientos laterales suaves.\n3. Activación de hombro con banda.\n\nPunto clave: empezar progresivamente y sin balón.",
                'category_id' => $catalog['warmup']->id,
                'measurement_mode' => 'time',
                'default_time_seconds' => 240,
                'default_series' => 1,
                'is_validated' => true,
            ]);

            $physical = Exercise::create([
                'user_id' => $trainer->id,
                'title' => "Fuerza de piernas · {$trainer->display_name}",
                'type' => 'physical',
                'description' => "1. Sentadilla con salto controlada.\n2. Aterrizaje con rodillas alineadas con los pies.\n3. Descanso completo entre series.\n\nMaterial: ninguno.",
                'category_id' => $catalog['strength']->id,
                'measurement_mode' => 'repetitions',
                'default_reps' => 8,
                'default_series' => 4,
                'is_validated' => true,
            ]);
            $physical->bodyAreas()->attach(array_filter([
                $catalog['lower']?->id,
                $catalog['core']?->id,
            ]));

            $technical = Exercise::create([
                'user_id' => $trainer->id,
                'title' => "Saque y recepción · {$trainer->display_name}",
                'type' => 'technical_tactical',
                'description' => "1. Saque flotante desde zona 1.\n2. Recepción en W hacia el colocador.\n3. Punto jugado hasta finalizar la acción.\n\nObjetivo: precisión del saque y control del primer toque.",
                'methodology_id' => $catalog['global']->id,
                'is_validated' => true,
            ]);
            $technical->fundamentals()->attach(array_filter([
                $catalog['serve']?->id,
                $catalog['reception']?->id,
            ]));

            $workout = Workout::create([
                'user_id' => $trainer->id,
                'title' => "Sesión de {$trainer->display_name}: activación, fuerza y saque",
                'description' => 'Sesión compartida en la comunidad de VolleyApp.',
                'date' => now()->subDays(rand(1, 10))->toDateString(),
                'status' => 'completed',
                'rating' => rand(3, 5),
                'feedback_notes' => 'Buena sesión: el grupo mantuvo la intensidad en los tres bloques.',
                'is_public' => true,
            ]);

            $workout->exercises()->attach($warmup->id, [
                'section' => 'warmup', 'order' => 0, 'series' => 1, 'time_seconds' => 240,
                'rating' => 4,
            ]);
            $workout->exercises()->attach($physical->id, [
                'section' => 'physical', 'order' => 0, 'series' => 4, 'reps' => 8, 'rest_seconds' => 90,
                'rating' => 4, 'feedback_notes' => 'Ajustar la altura del salto en la última serie.',
            ]);
            $workout->exercises()->attach($technical->id, [
                'section' => 'main', 'order' => 0, 'series' => 3, 'time_seconds' => 300, 'rest_seconds' => 60,
                'rating' => 5,
            ]);
        }

        $this->command?->info('Datos sociales de ejemplo listos (2 perfiles públicos y 1 privado).');
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        return [
            'warmup' => Category::where('name', 'Calentamiento')->firstOrFail(),
            'strength' => Category::where('name', 'Fuerza')->firstOrFail(),
            'global' => Methodology::where('name', 'Global')->firstOrFail(),
            'lower' => BodyArea::where('name', 'Tren inferior')->first(),
            'core' => BodyArea::where('name', 'Core')->first(),
            'serve' => Fundamental::where('name', 'Saque')->first(),
            'reception' => Fundamental::where('name', 'Recepción')->first(),
        ];
    }
}
