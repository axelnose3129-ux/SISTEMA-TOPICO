<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class NumericDemoAccessSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Las cuentas de demostracion solo se configuran en el entorno local.');
        }

        $accounts = DB::transaction(function (): array {
            $accounts = [];

            foreach (['coordinador', 'encargado', 'paciente', 'docente'] as $index => $role) {
                $email = $role.'.demo@topico.local';
                $dni = (string) (10000001 + $index);
                $password = '1234567890';
                $user = User::firstOrNew(['email' => $email]);

                if ($user->exists && $user->role !== $role) {
                    throw new RuntimeException('La cuenta '.$email.' tiene otro rol. No se modifico.');
                }

                if (User::where('dni', $dni)->where('email', '!=', $email)->exists()) {
                    throw new RuntimeException('El DNI de demostracion '.$dni.' ya pertenece a otra cuenta.');
                }

                if (! $user->exists) {
                    $user->name = ucfirst($role).' de demostracion';
                    $user->role = $role;
                    $user->can_prescribe = $role === 'encargado';
                }

                $user->dni = $dni;
                $user->password = Hash::make($password);
                $user->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();

                if ($role === 'paciente') {
                    $patient = DB::table('patients')->where('user_id', $user->id)->first();

                    if (! $patient) {
                        DB::table('patients')->insert([
                            'user_id' => $user->id,
                            'name' => 'Paciente de demostracion',
                            'document' => $dni,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } elseif (str_starts_with($patient->document, 'DEMO-PAC-')) {
                        DB::table('patients')->where('id', $patient->id)->update(['document' => $dni, 'updated_at' => now()]);
                    }
                }

                $accounts[] = [$role, $dni, $password];
            }

            return $accounts;
        });

        $this->command->table(['Rol', 'DNI de demostracion', 'Contrasena numerica'], $accounts);
        $this->command->info('Solo se actualizaron las cuatro cuentas demo. Tambien conservan su acceso por correo.');
    }
}
