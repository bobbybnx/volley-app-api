<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los ejercicios pasan a ser globales y se añade el tipo "warmup"
     * (calentamiento). Además desaparece la valoración por ejercicio:
     * el feedback vive ahora en el entrenamiento y en cada ejercicio
     * dentro de un entrenamiento.
     */
    public function up(): void
    {
        // enum -> string para no depender de ALTER ENUM al añadir tipos.
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('type', 32)->default('physical')->change();
        });

        Schema::dropIfExists('ratings');

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'rating_count']);
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->decimal('rating_avg', 2, 1)->default(0.00);
            $table->unsignedInteger('rating_count')->default(0);
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('rating');
            $table->timestamps();

            $table->unique(['user_id', 'exercise_id']);
        });
    }
};
