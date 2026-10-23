<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('is_private');
            $table->string('profile_image_path')->nullable()->after('theme');
            $table->string('banner_image_path')->nullable()->after('profile_image_path');
            $table->index('is_admin');
        });

        // El usuario bobson actúa como administrador de la plataforma desde el
        // primer arranque, sin depender de que se vuelva a ejecutar el seeder.
        DB::table('users')
            ->where('username', 'bobson')
            ->orWhere('email', 'j.a.vansleeuwen@gmail.com')
            ->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_admin']);
            $table->dropColumn(['is_admin', 'profile_image_path', 'banner_image_path']);
        });
    }
};
