<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_fundamental', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->foreignId('fundamental_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['exercise_id', 'fundamental_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_fundamental');
    }
};
