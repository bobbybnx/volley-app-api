<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique();
            $table->string('display_name')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_private')->default(false);
            $table->string('theme', 16)->default('light');
        });

        // Rellena el username de los usuarios ya existentes para que el
        // directorio social funcione desde el primer arranque.
        DB::table('users')->orderBy('id')->each(function ($user) {
            if (! empty($user->username)) {
                return;
            }

            $base = Str::slug(Str::before($user->email, '@'), '.') ?: 'entrenador';
            $base = substr($base, 0, 40);
            $candidate = $base;
            $suffix = 1;

            while (DB::table('users')->where('username', $candidate)->exists()) {
                $candidate = $base . '-' . $suffix;
                $suffix++;
            }

            DB::table('users')->where('id', $user->id)->update([
                'username' => $candidate,
                'display_name' => $user->display_name ?? $user->name,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'display_name', 'bio', 'is_private', 'theme']);
        });
    }
};
