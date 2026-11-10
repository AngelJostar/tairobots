<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdministrator extends Command
{
    protected $signature = 'tairobots:admin {identifier} {--username=} {--email=} {--name=Guillermo}';

    protected $description = 'Crear o actualizar el administrador de Tairobots';

    public function handle(): int
    {
        $identifier = strtolower($this->argument('identifier'));
        $email = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : strtolower((string) ($this->option('email') ?: $identifier.'@local.tairobots'));
        $username = strtolower((string) ($this->option('username') ?: (filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strstr($identifier, '@', true) : $identifier)));
        $password = $this->secret('Contraseña del administrador (mínimo 12 caracteres)');
        $confirmation = $this->secret('Repite la contraseña');
        $validator = Validator::make(['email' => $email, 'username' => $username, 'password' => $password, 'password_confirmation' => $confirmation], ['email' => 'required|email|max:254', 'username' => 'required|alpha_dash|min:3|max:50', 'password' => 'required|string|min:12|confirmed']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = User::query()->where('username', $username)->orWhere('email', $email)->first() ?? new User;
        $user->name = $this->option('name');
        $user->email = $email;
        $user->username = $username;
        $user->password = $password;
        $user->is_admin = true;
        $user->save();
        $this->info('Administrador listo. Ingresa con tu usuario o correo y contraseña en /ingresar.');

        return self::SUCCESS;
    }
}
