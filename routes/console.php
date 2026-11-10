<?php

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('gotry:create-admin', function (): int {
    $name = $this->ask('Admin full name');
    $email = mb_strtolower(trim((string) $this->ask('Admin email')));
    $password = $this->secret('Password (12-128 characters)');
    $confirmation = $this->secret('Confirm password');
    $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], [
        'name' => ['required','string','max:100'], 'email' => ['required','email','max:254','unique:users,email'],
        'password' => ['required','string','min:12','max:128','confirmed'],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $message) $this->error($message);
        return Command::FAILURE;
    }
    User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'admin']);
    $this->info('Admin account created. Sign in at /account.');
    return Command::SUCCESS;
})->purpose('Create a new admin interactively, without a default password or changing existing accounts');
