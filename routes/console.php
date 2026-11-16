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

Artisan::command('gotry:seed-demo-activities {provider : Email of an existing active provider}', function (\App\Services\ActivityDraft $draft): int {
    $provider = User::where('email', mb_strtolower(trim($this->argument('provider'))))
        ->where('role', 'provider')->where('is_active', true)->first();

    if ($provider === null) {
        $this->error('Use the email of an existing active provider.');
        return Command::FAILURE;
    }

    $today = \Carbon\CarbonImmutable::today('Australia/Sydney');
    $firstDate = $today->next('Saturday')->addWeek();
    $deadline = $firstDate->subDays(2)->toDateString();
    $defaults = [
        'business_name' => 'GoTry Demo Activities', 'contact_name' => 'Demo Contact',
        'contact_email' => 'activities@example.test', 'phone' => '0200000000',
        'min_age' => 6, 'max_age' => 12, 'trial_duration' => 45, 'deadline' => $deadline,
        'trial_type' => 'group', 'trial_date' => $firstDate->toDateString(), 'trial_time' => '09:00',
        'trial_min' => 2, 'trial_max' => 6,
        'regular_format' => 'group', 'multiple_days' => '0', 'regular_day' => 'Saturday',
        'regular_time' => '09:00', 'regular_min' => 3, 'regular_standard' => 5, 'regular_max' => 8,
        'standard_price' => '24', 'small_group_price' => '30', 'lessons_per_term' => 8, 'discount_type' => 'none',
    ];
    $examples = [
        'soccer' => [
            'activity_name' => 'Demo: Junior Soccer Skills', 'category' => 'Sports & Fitness',
            'description' => 'Practise dribbling, passing and teamwork through small-sided games.',
            'venue' => 'North Sydney (demo venue)', 'bring' => 'Water bottle and sports shoes',
            'extra_trial_times' => $firstDate->addDay()->format('Y-m-d').' 10:00',
        ],
        'piano' => [
            'activity_name' => 'Demo: First Piano Lesson', 'category' => 'Music',
            'description' => 'Explore the keyboard, find a steady beat and play a short tune.',
            'venue' => 'Chatswood (demo venue)', 'trial_type' => 'individual',
            'trial_availability' => 'Ask the provider for an after-school time.',
            'regular_format' => 'individual', 'usual_availability' => 'Weekdays after school', 'individual_price' => '45',
        ],
        'art' => [
            'activity_name' => 'Demo: Watercolour Studio', 'category' => 'Arts & Crafts',
            'description' => 'Mix colours and paint a small landscape using wet-on-wet techniques.',
            'venue' => 'Parramatta (demo venue)', 'min_age' => 8, 'trial_time' => '11:00',
            'bring' => 'An apron or old shirt',
        ],
    ];

    $results = DB::transaction(function () use ($provider, $draft, $defaults, $examples): array {
        $results = [];
        foreach ($examples as $key => $example) {
            $input = array_replace($defaults, $example);
            $details = [];
            for ($step = 1; $step <= 4; $step++) {
                $details = array_replace($details, $draft->validate($step, $input));
            }
            $token = \Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_URL, 'gotry:demo:'.$provider->id.':'.$key)->toString();
            $activity = \App\Models\Activity::firstOrCreate(
                ['submission_token' => $token, 'provider_id' => $provider->id],
                ['title' => $input['activity_name'], 'category' => $input['category'], 'status' => 'pending_approval',
                    'details' => $details, 'submitted_at' => now()],
            );
            if ($activity->wasRecentlyCreated) {
                \App\Models\ActivityEvent::create([
                    'activity_id' => $activity->id, 'actor_id' => $provider->id, 'action' => 'demo_seeded',
                    'to_status' => 'pending_approval', 'reason' => 'Demo data created by the GoTry console for this provider.',
                ]);
            }
            $results[] = [$activity->id, $activity->title, $activity->statusLabel(), $activity->wasRecentlyCreated ? 'Created' : 'Already exists'];
        }

        return $results;
    }, 5);

    $this->table(['ID', 'Activity', 'Status', 'Result'], $results);
    return Command::SUCCESS;
})->purpose('Create three sample activities for an existing provider');

Artisan::command('gotry:seed-demo-payment {parent} {provider} {admin} {run=first}', function (
    \App\Services\ParentInterests $registrations,
    \App\Services\AdminActivityLifecycle $lifecycle,
): int {
    $accounts = [];
    foreach (['parent', 'provider', 'admin'] as $role) {
        $accounts[$role] = User::where('email', mb_strtolower(trim($this->argument($role))))
            ->where('role', $role)->where('is_active', true)->first();
        if ($accounts[$role] === null) {
            $this->error("Use the email of an existing active {$role}.");
            return Command::FAILURE;
        }
    }
    $run = $this->argument('run');
    if (! preg_match('/\A[a-z0-9-]{1,24}\z/', $run)) {
        $this->error('Use 1 to 24 lowercase letters, numbers or hyphens for the run name.');
        return Command::FAILURE;
    }

    $activity = DB::transaction(function () use ($accounts, $run, $registrations, $lifecycle) {
        ['parent' => $parent, 'provider' => $provider, 'admin' => $admin] = $accounts;
        $date = \Carbon\CarbonImmutable::today('Australia/Sydney')->addWeeks(2);
        $token = \Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_URL,
            "gotry:demo-payment:{$parent->id}:{$provider->id}:{$run}")->toString();
        $activity = \App\Models\Activity::firstOrCreate(['submission_token' => $token], [
            'provider_id' => $provider->id,
            'title' => "Demo: Soccer Sandbox Checkout ({$run})",
            'category' => 'Sports & Fitness',
            'status' => 'pending_registrations',
            'version' => 1,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'details' => [
                'business_name' => 'GoTry Demo Activities', 'description' => 'Sample trial for PayPal Sandbox checkout.',
                'venue' => 'North Sydney (demo venue)', 'min_age' => 6, 'max_age' => 12,
                'trial_type' => 'group', 'trial_date' => $date->toDateString(), 'trial_time' => '09:00',
                'trial_duration' => 45, 'deadline' => $date->subDays(2)->toDateString(), 'trial_min' => 1, 'trial_max' => 6,
                'regular_format' => 'individual', 'usual_availability' => 'Weekdays after school',
                'individual_price' => '45', 'discount_type' => 'none',
            ],
        ]);
        if (! $activity->wasRecentlyCreated) {
            return $activity;
        }

        $registrations->register($parent, $activity->id, [
            'child_name' => 'Demo Child', 'child_age' => 8, 'expected_activity_version' => $activity->version,
        ]);

        return $lifecycle->change($admin, $activity->id, [
            'action' => 'confirm_minimum', 'expected_version' => $activity->version,
            'reason' => 'PayPal Sandbox demo fixture.',
        ]);
    }, 5);

    $this->info($activity->title.' · '.$activity->statusLabel());
    $this->line(route('parent.dashboard', ['activity' => $activity->id]));
    return Command::SUCCESS;
})->purpose('Prepare a sample PayPal Sandbox registration for existing GoTry test accounts');
