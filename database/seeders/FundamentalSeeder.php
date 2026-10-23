<?php

namespace Database\Seeders;

use App\Models\Fundamental;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FundamentalSeeder extends Seeder
{
    public function run(): void
    {
        // "Defensa" y "Ataque" se unifican en un único fundamento transversal.
        $defensa = Fundamental::where('name', 'Defensa')->first();
        $ataque = Fundamental::where('name', 'Ataque')->first();
        $combined = Fundamental::where('name', 'Defensa/Ataque')->first();

        if (! $combined && $defensa) {
            $defensa->update(['name' => 'Defensa/Ataque']);
            $combined = $defensa;
        }

        if ($ataque && $combined) {
            foreach (DB::table('exercise_fundamental')->where('fundamental_id', $ataque->id)->get() as $row) {
                $exists = DB::table('exercise_fundamental')
                    ->where('exercise_id', $row->exercise_id)
                    ->where('fundamental_id', $combined->id)
                    ->exists();

                if ($exists) {
                    DB::table('exercise_fundamental')->where('id', $row->id)->delete();
                } else {
                    DB::table('exercise_fundamental')->where('id', $row->id)->update([
                        'fundamental_id' => $combined->id,
                    ]);
                }
            }

            $ataque->delete();
        }

        $fundamentals = ['Remate', 'Bloqueo', 'Recepción', 'Saque', 'Defensa/Ataque'];

        foreach ($fundamentals as $fundamental) {
            Fundamental::firstOrCreate(['name' => $fundamental]);
        }
    }
}
