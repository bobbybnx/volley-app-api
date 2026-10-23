<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El catálogo de categorías necesita admitir "warmup" (Calentamiento)
     * además de las categorías físicas.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('type', 32)->default('physical')->change();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('type', ['physical'])->default('physical')->change();
        });
    }
};
