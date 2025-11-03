<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear o actualizar cliente de prueba
        $client = User::firstOrCreate(
            ['email' => 'cliente@prueba.com'],
            [
                'name' => 'Cliente de Prueba',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tel' => '04121234567',
                'cedula_type' => 'v',
                'cedula_ID' => '23456789',
            ]
        );

        // Asignar rol de cliente
        if (!$client->hasRole('client')) {
            $client->assignRole('client');
        }

        $this->command->info('Cliente de prueba creado/actualizado:');
        $this->command->info('Email: cliente@prueba.com');
        $this->command->info('Password: password');
        $this->command->info('Teléfono: 04121234567');
        $this->command->info('Cédula: V-23456789');
    }
}

