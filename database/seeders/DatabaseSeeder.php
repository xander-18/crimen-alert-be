<?php

namespace Database\Seeders;
use Illuminate\Support\Facades\Hash;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     */
    public function run(): void {
        User::factory()->create([
            'name' => 'RenzoRd_Redigb',
            'email' => 'redrojo@ejemplo.com',
            'telefono' => '+20 1234567890', 
            'password' => Hash::make('contraseña123'), 
        ]);

        User::factory()->create([
            'name' => 'Xander-Codex',
            'email' => 'usuario@ejemplo.com',
            'telefono' => '+20 4234567890', 
            'password' => Hash::make('contraseña123'), 
        ]);
    }
}
 