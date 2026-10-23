<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BobsonUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'j.a.vansleeuwen@gmail.com';
        $password = env('BOBSON_PASSWORD');

        if (! $password) {
            if (! app()->environment('local')) {
                $this->command?->warn('Define BOBSON_PASSWORD en .env para crear el usuario bobson en este entorno.');
                return;
            }

            $password = 'Vq7!mN2xL9pR4tK8';
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'bobson',
                'username' => 'bobson',
                'display_name' => 'Bobson',
                'bio' => 'Entrenador de voleibol. Comparto sesiones y ejercicios con la comunidad.',
                'is_private' => false,
                'theme' => 'light',
                'password' => Hash::make($password),
            ]
        );

        // Completa los campos sociales si el usuario ya existía de antes.
        $username = $user->username;

        if (! $username || $username === 'j.a.vansleeuwen') {
            $username = User::where('username', 'bobson')
                ->where('id', '!=', $user->id)
                ->exists()
                    ? User::generateUsername($email, 'bobson')
                    : 'bobson';
        }

        $displayName = $user->display_name;

        if (! $displayName || $displayName === 'bobson') {
            $displayName = 'Bobson';
        }

        $user->fill([
            'username' => $username,
            'display_name' => $displayName,
        ])->save();

        // bobson es el administrador de la plataforma.
        $user->forceFill(['is_admin' => true])->save();

        if (! $user->wasRecentlyCreated) {
            $this->command?->info('El usuario bobson ya existe; se han completado sus datos sociales y permisos de administrador.');
            return;
        }

        $this->command?->info("Usuario creado: bobson <{$email}>");
        if (app()->environment('local')) {
            $this->command?->warn("Contraseña temporal: {$password}");
            $this->command?->warn('Cámbiala después del primer inicio de sesión.');
        }
    }
}
