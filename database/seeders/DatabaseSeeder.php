<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Grados y Secciones
        $grados = [
            ['id' => Str::uuid(), 'nombre' => '1° Grado', 'nivel' => 1],
            ['id' => Str::uuid(), 'nombre' => '2° Grado', 'nivel' => 2],
            ['id' => Str::uuid(), 'nombre' => '3° Grado', 'nivel' => 3],
            ['id' => Str::uuid(), 'nombre' => '4° Grado', 'nivel' => 4],
            ['id' => Str::uuid(), 'nombre' => '5° Grado', 'nivel' => 5],
        ];

        foreach ($grados as $grado) {
            DB::table('grados')->insert(array_merge($grado, ['created_at' => now(), 'updated_at' => now()]));
            
            foreach (['A', 'B', 'C'] as $letra) {
                DB::table('secciones')->insert([
                    'id' => Str::uuid(),
                    'grado_id' => $grado['id'],
                    'nombre' => $letra,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Usuarios (Password: Prueba123!)
        $adminId = '11111111-1111-1111-1111-111111111111';
        DB::table('usuarios')->insert([
            'id' => $adminId,
            'nombre_completo' => 'Director General',
            'email' => 'admin@colegio.edu',
            'dni' => '00000001',
            'password' => Hash::make('Prueba123!'),
            'rol' => 'superusuario',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $auxId = Str::uuid();
            DB::table('usuarios')->insert([
                'id' => $auxId,
                'nombre_completo' => "Auxiliar $i",
                'email' => "aux$i@colegio.edu",
                'dni' => "1000000$i",
                'password' => Hash::make('Prueba123!'),
                'rol' => 'auxiliar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Asignar a la primera sección encontrada para pruebas
            $seccion = DB::table('secciones')->first();
            DB::table('auxiliar_secciones')->insert([
                'id' => Str::uuid(),
                'usuario_id' => $auxId,
                'seccion_id' => $seccion->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
