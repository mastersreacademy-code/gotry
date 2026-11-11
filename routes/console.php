<?php

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

Artisan::command('gotry:check-database', function (): int {
    if (DB::getDriverName() !== 'mysql') {
        $this->error('Deployment requires MySQL/MariaDB with InnoDB. SQLite is for local tests.');
        return Command::FAILURE;
    }
    $tables = ['users','sessions','cache','cache_locks','activities','activity_events','interests','interest_events','notification_outbox','inbox_notifications','account_events'];
    $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::getDatabaseName())
        ->whereIn('TABLE_NAME', $tables)->pluck('ENGINE', 'TABLE_NAME');
    $valid = true;
    foreach ($tables as $table) {
        if (strtoupper((string) $engines->get($table)) !== 'INNODB') {
            $this->error($table.': missing or not InnoDB. Review migrations/storage engines before enabling registrations.');
            $valid = false;
        }
    }
    if ($valid) $this->info('GoTry database tables use InnoDB; transactional capacity locking is available.');
    return $valid ? Command::SUCCESS : Command::FAILURE;
})->purpose('Check required deployment tables and their transactional storage engine without modifying data');

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

Artisan::command('gotry:process-waitlists', function (\App\Services\ParentInterests $service): int {
    $candidates = \App\Models\Interest::query()->whereNotNull('trial_session')->where(function ($query): void {
        $query->where(fn ($offers) => $offers->where('status', 'offered')->where('offer_expires_at', '<=', now()))
            ->orWhere(fn ($queue) => $queue->where('status', 'waitlisted')->whereIn('activity_id',
                \App\Models\Activity::where('status', 'pending_registrations')->select('id')));
    })->select('activity_id');
    $failed = false;
    \App\Models\Activity::whereIn('id', $candidates)->select('id')->chunkById(100, function ($activities) use ($service, &$failed): void {
        foreach ($activities as $activity) {
            try {
                $service->processWaitlist($activity->id);
            } catch (\Exception $error) {
                report($error);
                $this->error('Waitlist processing failed for activity '.$activity->id.'.');
                $failed = true;
            }
        }
    });

    return $failed ? Command::FAILURE : Command::SUCCESS;
})->purpose('Expire trial seat offers and fill unreserved seats from eligible waitlists');

\Illuminate\Support\Facades\Schedule::command('gotry:process-waitlists')->everyMinute();
