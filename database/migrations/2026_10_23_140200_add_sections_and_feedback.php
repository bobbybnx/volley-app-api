<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('feedback_notes')->nullable();
            $table->boolean('is_public')->default(true);
            $table->index(['user_id', 'date']);
        });

        Schema::table('exercise_workout', function (Blueprint $table) {
            // warmup | physical | main
            $table->string('section', 16)->default('main');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('feedback_notes')->nullable();
            $table->index(['workout_id', 'section', 'order']);
        });

        // Clasifica los ejercicios ya existentes en su bloque.
        DB::table('exercise_workout')
            ->join('exercises', 'exercises.id', '=', 'exercise_workout.exercise_id')
            ->select('exercise_workout.id', 'exercises.type')
            ->orderBy('exercise_workout.id')
            ->get()
            ->each(function ($row) {
                $section = match ($row->type) {
                    'warmup' => 'warmup',
                    'physical' => 'physical',
                    default => 'main',
                };

                DB::table('exercise_workout')
                    ->where('id', $row->id)
                    ->update(['section' => $section]);
            });
    }

    public function down(): void
    {
        Schema::table('exercise_workout', function (Blueprint $table) {
            $table->dropIndex(['workout_id', 'section', 'order']);
            $table->dropColumn(['section', 'rating', 'feedback_notes']);
        });

        Schema::table('workouts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date']);
            $table->dropColumn(['rating', 'feedback_notes', 'is_public']);
        });
    }
};
