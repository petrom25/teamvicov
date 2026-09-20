<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdministrator extends Command
{
    protected $signature = 'teamvicov:admin';

    protected $description = 'Creează administratorul privat, fără parolă implicită.';

    public function handle(): int
    {
        $name = $this->ask('Nume');
        $email = $this->ask('Email');
        $password = $this->secret('Parolă (minimum 16 caractere)');
        $v = Validator::make(compact('name', 'email', 'password'), ['name' => 'required|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|min:16|max:255']);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }
        if ($password !== $this->secret('Repetă parola')) {
            $this->error('Parolele diferă.');

            return self::FAILURE;
        }
        $user = new User(compact('name', 'email', 'password'));
        $user->is_admin = true;
        $user->save();
        $this->info('Administrator creat. Intră la /admin.');

        return self::SUCCESS;
    }
}
