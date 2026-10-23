<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tren superior, Tren inferior, Core, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_areas');
    }
};
