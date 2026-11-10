<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdministrator extends Command
{
    protected $signature = 'tairobots:admin {email} {--name=Guillermo}';

    protected $description = 'Crear o actualizar el administrador de Tairobots';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = $this->secret('Contraseña del administrador (mínimo 12 caracteres)');
        $confirmation = $this->secret('Repite la contraseña');
        $validator = Validator::make(['email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], ['email' => 'required|email|max:254', 'password' => 'required|string|min:12|confirmed']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $this->option('name');
        $user->password = $password;
        $user->is_admin = true;
        $user->save();
        $this->info('Administrador listo. Ingresa con tu correo y contraseña en /ingresar.');

        return self::SUCCESS;
    }
}
