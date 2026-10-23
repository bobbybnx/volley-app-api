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

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@volley.app'],
            [
                'name' => 'Entrenador Demo',
                'username' => 'entrenador-demo',
                'display_name' => 'Entrenador Demo',
                'bio' => 'Perfil de ejemplo con entrenamientos de voleibol de base.',
                'is_private' => false,
                'password' => Hash::make('password123'),
            ]
        );

        if ($user->exercises()->exists()) {
            return;
        }

        $calentamiento = Category::where('name', 'Calentamiento')->first();
        $fuerza = Category::where('name', 'Fuerza')->first();
        $resistencia = Category::where('name', 'Resistencia')->first();
        $analitico = Methodology::where('name', 'Analítico')->first();
        $sintetico = Methodology::where('name', 'Sintético')->first();
        $global = Methodology::where('name', 'Global')->first();

        $trenInferior = BodyArea::where('name', 'Tren inferior')->first();
        $core = BodyArea::where('name', 'Core')->first();
        $trenSuperior = BodyArea::where('name', 'Tren superior')->first();

        $recepcion = Fundamental::where('name', 'Recepción')->first();
        $remate = Fundamental::where('name', 'Remate')->first();
        $defensaAtaque = Fundamental::where('name', 'Defensa/Ataque')->first();

        // ---------------------------------------------------------------
        // Bloque 1 · Calentamiento
        // ---------------------------------------------------------------
        $movilidad = Exercise::create([
            'user_id' => $user->id,
            'title' => 'Movilidad articular y activación',
            'type' => 'warmup',
            'description' => "1. Círculos de tobillo, rodilla y cadera (30 s cada uno).\n2. Balanceos de brazos y apertura de hombro.\n3. Skipping suave 2 x 15 m.\n\nMaterial: ninguno.\nPunto clave: subir la temperatura corporal sin llegar a la fatiga.",
            'category_id' => $calentamiento->id,
            'measurement_mode' => 'time',
            'default_time_seconds' => 300,
            'default_series' => 1,
        ]);
        $movilidad->bodyAreas()->attach([$trenInferior->id, $trenSuperior->id, $core->id]);

        $hombros = Exercise::create([
            'user_id' => $user->id,
            'title' => 'Activación de hombro con banda',
            'type' => 'warmup',
            'description' => "1. Sujeta la banda con las dos manos a la altura del pecho.\n2. Abre los brazos manteniendo los codos ligeramente flexionados.\n3. Vuelve al centro controlando la resistencia.\n\nMaterial: banda elástica.\nPunto clave: movimiento lento, sin encoger los hombros.",
            'category_id' => $calentamiento->id,
            'measurement_mode' => 'repetitions',
            'default_reps' => 15,
            'default_series' => 2,
        ]);
        $hombros->bodyAreas()->attach([$trenSuperior->id]);

        // ---------------------------------------------------------------
        // Bloque 2 · Físico
        // ---------------------------------------------------------------
        $squatJump = Exercise::create([
            'user_id' => $user->id,
            'title' => 'Sentadilla con salto',
            'type' => 'physical',
            'description' => "1. Colócate con los pies a la anchura de los hombros.\n2. Baja en sentadilla llevando la cadera atrás.\n3. Explota hacia arriba buscando la máxima altura.\n4. Aterriza con las rodillas ligeramente flexionadas.\n\nMaterial: ninguno.\nPunto clave: aterrizaje silencioso y controlado.",
            'category_id' => $fuerza->id,
            'measurement_mode' => 'repetitions',
            'default_reps' => 10,
            'default_series' => 4,
        ]);
        $squatJump->bodyAreas()->attach([$trenInferior->id, $core->id]);

        $plancha = Exercise::create([
            'user_id' => $user->id,
            'title' => 'Plancha frontal isométrica',
            'type' => 'physical',
            'description' => "1. Apoya antebrazos y puntas de los pies.\n2. Mantén el cuerpo en línea recta desde la cabeza a los talones.\n3. Activa el core y evita hundir la cadera.\n\nSeries de 30-45 segundos con descanso de 30 segundos.",
            'category_id' => $resistencia->id,
            'measurement_mode' => 'time',
            'default_time_seconds' => 40,
            'default_series' => 3,
        ]);
        $plancha->bodyAreas()->attach([$core->id]);

        // ---------------------------------------------------------------
        // Bloque 3 · Entreno principal (técnico-táctico)
        // ---------------------------------------------------------------
        $recepcionGlobal = Exercise::create([
            'user_id' => $user->id,
            'title' => 'Recepción en W con desplazamiento',
            'type' => 'technical_tactical',
            'description' => "1. Coloca a dos receptores en zona 5 y 6.\n2. El entrenador envía el balón desde el otro campo.\n3. Los receptores se desplazan y reciben en W, dirigiendo el balón al colocador.\n\nObjetivo: lectura de trayectoria y control del primer toque.\nVariante: saque flotante y saque potente alternados.",
            'methodology_id' => $global->id,
        ]);
        $recepcionGlobal->fundamentals()->attach(array_filter([$recepcion?->id, $defensaAtaque?->id]));

        $remateAnalitico = Exercise::create([
            'user_id' => $user->id,
            'title' => 'Remate sin salto: golpeo y dirección',
            'type' => 'technical_tactical',
            'description' => "1. En parejas, uno lanza el balón desde la red.\n2. El rematador golpea sin salto buscando las dos esquinas.\n3. Repetir 8 golpeos por zona.\n\nPunto clave: brazo extendido, muñeca activa y contacto alto.",
            'methodology_id' => $analitico->id,
        ]);
        $remateAnalitico->fundamentals()->attach(array_filter([$remate?->id]));

        $complejo = Exercise::create([
            'user_id' => $user->id,
            'title' => 'Complejo 3 vs 3 con transición',
            'type' => 'technical_tactical',
            'description' => "1. Dos equipos de 3 jugadores en medio campo.\n2. El entrenador introduce el balón y se juega punto.\n3. Tras cada punto, transición inmediata a defensa.\n\nObjetivo: lectura de juego y toma de decisiones.\nMaterial: red, balones y petos.",
            'methodology_id' => $sintetico->id,
        ]);
        $complejo->fundamentals()->attach(array_filter([$recepcion?->id, $remate?->id, $defensaAtaque?->id]));

        // El catálogo de ejemplo nace ya validado y visible para todos.
        $user->exercises()->update(['is_validated' => true]);

        // ---------------------------------------------------------------
        // Entrenamiento de ejemplo con los 3 bloques
        // ---------------------------------------------------------------
        $workout = Workout::create([
            'user_id' => $user->id,
            'title' => 'Sesión completa: calentamiento, fuerza y recepción',
            'description' => 'Trabajo de potencia de piernas y primer toque bajo presión.',
            'date' => now()->addDay()->toDateString(),
            'status' => 'planned',
            'notes' => 'Llevar 6 balones, conos y bandas elásticas.',
            'rating' => 4,
            'feedback_notes' => 'Sesión de referencia: buen ritmo y buena actitud del grupo.',
            'is_public' => true,
        ]);

        $workout->exercises()->attach($movilidad->id, [
            'section' => 'warmup', 'order' => 0, 'series' => 1, 'time_seconds' => 300,
            'rating' => 4, 'feedback_notes' => 'Muy útil para arrancar; el grupo llegó con poca movilidad de tobillo.',
        ]);
        $workout->exercises()->attach($hombros->id, [
            'section' => 'warmup', 'order' => 1, 'series' => 2, 'reps' => 15, 'rest_seconds' => 30,
            'rating' => 5,
        ]);
        $workout->exercises()->attach($squatJump->id, [
            'section' => 'physical', 'order' => 0, 'series' => 4, 'reps' => 10, 'rest_seconds' => 90,
            'rating' => 4, 'feedback_notes' => 'Subir carga el próximo día; el aterrizaje mejoró mucho.',
        ]);
        $workout->exercises()->attach($plancha->id, [
            'section' => 'physical', 'order' => 1, 'series' => 3, 'time_seconds' => 40, 'rest_seconds' => 30,
            'rating' => 3,
        ]);
        $workout->exercises()->attach($recepcionGlobal->id, [
            'section' => 'main', 'order' => 0, 'series' => 4, 'time_seconds' => 120, 'rest_seconds' => 60,
            'rating' => 5, 'feedback_notes' => 'Excelente lectura de trayectoria en zona 5.',
        ]);
        $workout->exercises()->attach($complejo->id, [
            'section' => 'main', 'order' => 1, 'series' => 3, 'time_seconds' => 240, 'rest_seconds' => 90,
            'rating' => 4,
        ]);
    }
}
