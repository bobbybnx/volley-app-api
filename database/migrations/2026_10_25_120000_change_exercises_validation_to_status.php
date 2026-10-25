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
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->after('type')
                ->index();
        });

        // Migrar datos existentes
        DB::table('exercises')->update([
            'status' => DB::raw("CASE WHEN is_validated = 1 THEN 'approved' ELSE 'pending' END"),
        ]);

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex(['is_validated']);
            $table->dropColumn('is_validated');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->boolean('is_validated')->default(false)->after('type');
            $table->index('is_validated');
        });

        DB::table('exercises')->update([
            'is_validated' => DB::raw("CASE WHEN status = 'approved' THEN 1 ELSE 0 END"),
        ]);

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
