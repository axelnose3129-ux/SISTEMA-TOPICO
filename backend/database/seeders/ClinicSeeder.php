<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('email', 'coordinador@topico.local')->exists()) {
            $password = bin2hex(random_bytes(8));
            $user = new User;
            $user->name = 'Coordinación de Tópico';
            $user->email = 'coordinador@topico.local';
            $user->password = Hash::make($password);
            $user->role = 'coordinador';
            $user->can_prescribe = false;
            $user->save();
            $this->command->info('Cuenta inicial: coordinador@topico.local');
            $this->command->info('Contraseña inicial: '.$password);
        }
    }
}
