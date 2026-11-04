<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PresentationAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Las cuentas de presentacion solo pueden crearse en el entorno local.');
        }

        $accounts = DB::transaction(function (): array {
            $accounts = [];

            foreach (['coordinador', 'encargado', 'paciente', 'docente'] as $role) {
                $email = $role.'.demo@topico.local';

                if (User::where('email', $email)->exists()) {
                    $this->command->warn('La cuenta '.$email.' ya existe. Se conserva sin cambiar su contrasena.');

                    continue;
                }

                $password = ucfirst($role).'-'.bin2hex(random_bytes(4)).'!';
                $user = new User;
                $user->name = ucfirst($role).' de demostracion';
                $user->email = $email;
                $user->password = Hash::make($password);
                $user->role = $role;
                $user->can_prescribe = $role === 'encargado';
                $user->save();

                if ($role === 'paciente') {
                    DB::table('patients')->insert([
                        'user_id' => $user->id,
                        'name' => 'Paciente de demostracion',
                        'document' => 'DEMO-PAC-'.$user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $accounts[] = [$role, $email, $password];
            }

            return $accounts;
        });

        $this->command->table(['Rol', 'Correo', 'Contrasena'], $accounts);
        $this->command->info('Cuentas locales de presentacion creadas. Las cuentas anteriores se conservan.');
    }
}
