<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_workout', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('series')->nullable();
            $table->unsignedInteger('reps')->nullable();
            $table->unsignedInteger('time_seconds')->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_workout');
    }
};
