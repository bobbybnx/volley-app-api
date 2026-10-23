<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_area_exercise', function (Blueprint $table) {
            $table->id();
            $table->foreignId('body_area_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['body_area_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_area_exercise');
    }
};
