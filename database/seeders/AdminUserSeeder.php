<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear o actualizar usuario admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@andycorporation.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tel' => '04141234567',
                'cedula_type' => 'v',
                'cedula_ID' => '12345678',
            ]
        );

        // Asignar rol de admin
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $this->command->info('Usuario admin creado/actualizado:');
        $this->command->info('Email: admin@andycorporation.com');
        $this->command->info('Password: password');
    }
}
