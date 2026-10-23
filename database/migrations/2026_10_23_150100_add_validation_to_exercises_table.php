<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->boolean('is_validated')->default(false)->after('type');
            $table->index('is_validated');
        });

        // El catálogo existente (seeders y datos previos) ya era visible para
        // todos, así que se marca como validado para no romper la app.
        DB::table('exercises')->update(['is_validated' => true]);
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex(['is_validated']);
            $table->dropColumn('is_validated');
        });
    }
};
