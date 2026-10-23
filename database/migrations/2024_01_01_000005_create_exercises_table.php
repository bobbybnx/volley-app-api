<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('title');
            $table->enum('type', ['physical', 'technical_tactical']);
            $table->longText('description');
            $table->string('image_path')->nullable();
            
            // For physical exercises
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('measurement_mode', ['time', 'repetitions'])->nullable();
            $table->integer('default_reps')->nullable();
            $table->integer('default_time_seconds')->nullable();
            $table->integer('default_series')->nullable();
            
            // For technical-tactical exercises
            $table->foreignId('methodology_id')->nullable()->constrained()->onDelete('set null');
            
            // Ratings
            $table->decimal('rating_avg', 2, 1)->default(0.00);
            $table->unsignedInteger('rating_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
