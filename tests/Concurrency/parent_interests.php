<?php

// Real InnoDB concurrency checks. Use ONLY an isolated localhost *_test database.
// APP_ENV=testing DB_CONNECTION=mysql ... php tests/Concurrency/parent_interests.php
use App\Models\Activity;
use App\Models\Interest;
use App\Models\InterestEvent;
use App\Models\User;
use App\Services\ParentInterests;
use App\Services\NotificationOutbox;
use App\Services\AdminAccounts;
use App\Services\AdminActivityLifecycle;
use App\Models\OutboxMessage;
use App\Models\InboxNotification;
use App\Models\AccountEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || ! in_array(config('database.connections.mysql.host'), ['127.0.0.1','localhost'], true)
    || ! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Requires APP_ENV=testing, mysql driver and an isolated localhost *_test database.');
}
DB::statement('SET SESSION innodb_lock_wait_timeout=5');
$requiredTables = ['activities','interests','interest_events','notification_outbox','inbox_notifications','account_events'];
$engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::getDatabaseName())
    ->whereIn('TABLE_NAME', $requiredTables)->pluck('ENGINE', 'TABLE_NAME');
foreach ($requiredTables as $table) {
    if (strtoupper((string) $engines->get($table)) !== 'INNODB') throw new RuntimeException('Requires InnoDB table: '.$table);
}

function check(bool $condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}

function executeJob(array $job): array
{
    $parent = User::findOrFail($job['parent_id']);
    if (isset($job['barrier'])) {
        touch($job['barrier'].'.ready-'.$job['worker']);
        $deadline = microtime(true) + 15;
        while (! file_exists($job['barrier'].'.go')) {
            if (microtime(true) > $deadline) throw new RuntimeException('Concurrency barrier timed out.');
            usleep(1000); clearstatcache();
        }
    }
    try {
        if ($job['action']==='deliver') return ['status'=>200,'delivered'=>(new NotificationOutbox)->deliver($parent)];
        if ($job['action']==='access') {
            $user=(new AdminAccounts)->access($parent,$job['target_id'],['action'=>'suspend','expected_version'=>1,'reason'=>'Concurrency access test']);
            return ['status'=>200,'id'=>$user->id];
        }
        if ($job['action']==='lifecycle') {
            $activity=(new AdminActivityLifecycle)->change($parent,$job['activity_id'],['action'=>'confirm_minimum','expected_version'=>2]);
            return ['status'=>200,'id'=>$activity->id];
        }
        $service = new ParentInterests;
        $interest = $job['action'] === 'register'
            ? $service->register($parent, $job['activity_id'], ['child_name' => $job['name'], 'child_age' => 8, 'expected_activity_version' => 2])
            : $service->change($parent, $job['interest_id'], $job['action'], ['expected_version' => $job['version'], 'expected_activity_version' => 2]);
        return ['status' => 200, 'id' => $interest->id, 'version' => $interest->version];
    } catch (HttpExceptionInterface $exception) {
        return ['status' => $exception->getStatusCode()];
    }
}

if (($argv[1] ?? '') === 'worker') {
    echo json_encode(executeJob(json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR);
    exit;
}

function parallel(array $jobs): array
{
    $directory = sys_get_temp_dir().'/gotry-interest-'.bin2hex(random_bytes(8));
    mkdir($directory); $barrier = $directory.'/barrier'; $workers = [];
    try {
        foreach ($jobs as $index => $job) {
            $job = [...$job, 'barrier' => $barrier, 'worker' => $index];
            $process = proc_open([PHP_BINARY, __FILE__, 'worker', json_encode($job)], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
            check(is_resource($process), 'Cannot launch a worker.');
            fclose($pipes[0]); $workers[] = [$process, $pipes];
        }
        $deadline = microtime(true) + 15;
        while (count(glob($barrier.'.ready-*')) !== count($jobs)) {
            check(microtime(true) < $deadline, 'Workers did not reach the barrier.');
            usleep(1000); clearstatcache();
        }
        touch($barrier.'.go'); $results = [];
        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
            check($exit === 0, 'Worker failed: '.$error.' '.$output);
            $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        }
        $workers = [];
        return $results;
    } finally {
        foreach ($workers as [$process, $pipes]) {
            if (is_resource($process)) proc_terminate($process);
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            if (is_resource($process)) proc_close($process);
        }
        foreach (glob($directory.'/*') as $file) unlink($file);
        rmdir($directory);
    }
}

config(['hashing.bcrypt.rounds' => 4]);
$provider = User::create(['name' => 'Concurrency Provider', 'email' => Str::uuid().'@example.test', 'password' => 'isolated test password!', 'role' => 'provider']);
$parents = [];
for ($index = 0; $index < 8; $index++) $parents[] = User::create(['name' => 'Concurrency Parent', 'email' => Str::uuid().'@example.test', 'password' => 'isolated test password!', 'role' => 'parent']);
$fixture = function (int $capacity) use ($provider): Activity {
    return Activity::create(['provider_id' => $provider->id, 'submission_token' => Str::uuid(), 'title' => 'Concurrency Trial', 'category' => 'Music',
        'status' => 'pending_registrations', 'version' => 2, 'submitted_at' => now(),
        'details' => ['min_age' => 6, 'max_age' => 10, 'trial_type' => 'group', 'trial_min' => 1, 'trial_max' => $capacity,
            'trial_date' => now('Australia/Sydney')->addDays(3)->format('Y-m-d'), 'trial_time' => '09:00', 'deadline' => now('Australia/Sydney')->addDays(2)->format('Y-m-d')]]);
};
$job = fn (User $parent, Activity $activity, string $name) => ['action' => 'register', 'parent_id' => $parent->id, 'activity_id' => $activity->id, 'name' => $name];
$active = fn (Activity $activity) => Interest::where('activity_id', $activity->id)->where('status', 'interested')->count();
$events = fn (Activity $activity) => InterestEvent::whereIn('interest_id', Interest::where('activity_id', $activity->id)->select('id'))->count();

$activity = $fixture(2);
$results = parallel(array_map(fn ($parent) => $job($parent, $activity, 'Child '.$parent->id), $parents));
check(count(array_filter($results, fn ($r) => $r['status'] === 200)) === 2, 'Capacity race did not accept exactly two.');
check(count(array_filter($results, fn ($r) => $r['status'] === 409)) === 6, 'Capacity race did not reject six.');
check($active($activity) === 2 && $events($activity) === 2, 'Capacity or audit drift.');
echo "PASS: eight simultaneous parents, exactly two accepted into two seats; two audit events\n";

$activity = $fixture(1);
$results = parallel(array_fill(0, 8, $job($parents[0], $activity, 'Same Child')));
check(array_unique(array_column($results, 'status')) === [200], 'Duplicate retries failed.');
check(count(array_unique(array_column($results, 'id'))) === 1 && $active($activity) === 1 && $events($activity) === 1, 'Duplicate record/audit created.');
echo "PASS: eight simultaneous duplicate submissions, one interest and one audit event\n";

$service = new ParentInterests;
$activity = $fixture(1);
$interest = $service->register($parents[0], $activity->id, ['child_name' => 'Original Child', 'child_age' => 8, 'expected_activity_version' => 2]);
$results = parallel([
    ['action' => 'withdraw', 'parent_id' => $parents[0]->id, 'interest_id' => $interest->id, 'version' => 1],
    $job($parents[1], $activity, 'New Child'),
]);
check($results[0]['status'] === 200 && in_array($results[1]['status'], [200,409], true), 'Withdrawal race failed.');
check($active($activity) <= 1 && $events($activity) === 2 + ($results[1]['status'] === 200 ? 1 : 0), 'Withdrawal race caused drift.');
echo "PASS: withdrawal versus new registration, no overbooking or audit drift\n";

$activity = $fixture(1);
$interest = $service->register($parents[0], $activity->id, ['child_name' => 'Returning Child', 'child_age' => 8, 'expected_activity_version' => 2]);
$service->change($parents[0], $interest->id, 'withdraw', ['expected_version' => 1]);
$results = parallel([
    ['action' => 'restore', 'parent_id' => $parents[0]->id, 'interest_id' => $interest->id, 'version' => 2],
    $job($parents[1], $activity, 'Competing Child'),
]);
$statuses = array_column($results, 'status'); sort($statuses);
check($statuses === [200,409] && $active($activity) === 1 && $events($activity) === 3, 'Restore race overbooked or lost audit.');
echo "PASS: restoration versus new registration, exactly one gets the released seat\n";

// Prove rollback on the actual MySQL-compatible engine, not just SQLite.
$activity = $fixture(1);
DB::unprepared('DROP TRIGGER IF EXISTS gotry_test_interest_audit_failure');
DB::unprepared("CREATE TRIGGER gotry_test_interest_audit_failure BEFORE INSERT ON interest_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit unavailable'");
try {
    $failed = false;
    try { $service->register($parents[0], $activity->id, ['child_name' => 'Rollback Child', 'child_age' => 8, 'expected_activity_version' => 2]); }
    catch (\Illuminate\Database\QueryException $exception) { $failed = true; }
    check($failed && $active($activity) === 0 && $events($activity) === 0, 'Audit failure did not roll back on InnoDB.');
} finally { DB::unprepared('DROP TRIGGER gotry_test_interest_audit_failure'); }
echo "PASS: InnoDB audit insert failure rolls back registration and capacity usage\n";

$interest = $service->register($parents[0], $activity->id, ['child_name' => 'Rollback Child', 'child_age' => 8, 'expected_activity_version' => 2]);
DB::unprepared("CREATE TRIGGER gotry_test_interest_audit_failure BEFORE INSERT ON interest_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit unavailable'");
try {
    $failed = false;
    try { $service->change($parents[0], $interest->id, 'withdraw', ['expected_version' => 1]); }
    catch (\Illuminate\Database\QueryException $exception) { $failed = true; }
    check($failed && $interest->fresh()->status === 'interested' && $interest->fresh()->version === 1 && $events($activity) === 1, 'Withdrawal did not roll back on InnoDB.');
} finally { DB::unprepared('DROP TRIGGER gotry_test_interest_audit_failure'); }
$service->change($parents[0], $interest->id, 'withdraw', ['expected_version' => 1]);
DB::unprepared("CREATE TRIGGER gotry_test_interest_audit_failure BEFORE INSERT ON interest_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit unavailable'");
try {
    $failed = false;
    try { $service->change($parents[0], $interest->id, 'restore', ['expected_version' => 2, 'expected_activity_version' => 2]); }
    catch (\Illuminate\Database\QueryException $exception) { $failed = true; }
    check($failed && $interest->fresh()->status === 'withdrawn' && $interest->fresh()->version === 2 && $events($activity) === 2, 'Restore did not roll back on InnoDB.');
} finally { DB::unprepared('DROP TRIGGER gotry_test_interest_audit_failure'); }
echo "PASS: InnoDB audit failure rolls back withdrawal and restoration, including versions\n";

$makeUser=fn($role)=>User::create(['name'=>'Isolated '.$role,'email'=>Str::uuid().'@example.test','password'=>'isolated test password!','role'=>$role]);
$deliveryProvider=$makeUser('provider'); $deliveryParent=$makeUser('parent'); $deliveryOther=$makeUser('parent');
$activity=$fixture(2); $activity->update(['provider_id'=>$deliveryProvider->id]);
$service->register($deliveryParent,$activity->id,['child_name'=>'Private A','child_age'=>8,'expected_activity_version'=>2]);
$service->register($deliveryOther,$activity->id,['child_name'=>'Private B','child_age'=>8,'expected_activity_version'=>2]);
$results=parallel(array_fill(0,8,['action'=>'deliver','parent_id'=>$deliveryProvider->id]));
check(array_sum(array_column($results,'delivered'))===2,'Concurrent delivery duplicated acknowledgements.');
check(InboxNotification::where('recipient_id',$deliveryProvider->id)->count()===2,'Concurrent delivery duplicated notices.');
check(OutboxMessage::where('recipient_id',$deliveryParent->id)->whereNull('delivered_at')->count()===1,'Another recipient was acknowledged.');
echo "PASS: eight simultaneous inbox deliveries, exactly two notices and no cross-recipient acknowledgement\n";
DB::unprepared("CREATE TRIGGER gotry_test_ack_failure BEFORE UPDATE ON notification_outbox FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ack unavailable'");
try {
    $failed=false;
    try {(new NotificationOutbox)->deliver($deliveryParent);} catch (\Illuminate\Database\QueryException) {$failed=true;}
    check($failed && InboxNotification::where('recipient_id',$deliveryParent->id)->count()===0,'Failed acknowledgement did not roll back notice insertion.');
    check(OutboxMessage::where('recipient_id',$deliveryParent->id)->whereNull('delivered_at')->count()===1,'Failed acknowledgement lost pending message.');
} finally {DB::unprepared('DROP TRIGGER gotry_test_ack_failure');}
check((new NotificationOutbox)->deliver($deliveryParent)===1 && (new NotificationOutbox)->deliver($deliveryParent)===0,'Pending delivery did not recover.');
echo "PASS: InnoDB acknowledgement failure rolls back delivery; retry recovers once\n";
$source=OutboxMessage::where('recipient_id',$deliveryOther->id)->firstOrFail(); $blocked=false;
try {InboxNotification::create(['id'=>Str::uuid(),'outbox_id'=>$source->id,'recipient_id'=>$deliveryProvider->id,'activity_id'=>$activity->id,'kind'=>$source->kind,'title'=>$source->title,'message'=>$source->message,'created_at'=>$source->getRawOriginal('created_at')]);}
catch (\Illuminate\Database\QueryException) {$blocked=true;}
check($blocked,'Composite recipient FK accepted cross-recipient delivery.');
echo "PASS: InnoDB composite foreign key blocks delivery to the wrong recipient\n";

$admin=$makeUser('admin'); $activity=$fixture(2); $activity->update(['details'=>[...$activity->details,'trial_min'=>2]]);
$one=$service->register($parents[0],$activity->id,['child_name'=>'Race A','child_age'=>8,'expected_activity_version'=>2]);
$service->register($parents[1],$activity->id,['child_name'=>'Race B','child_age'=>8,'expected_activity_version'=>2]);
$results=parallel([['action'=>'lifecycle','parent_id'=>$admin->id,'activity_id'=>$activity->id],['action'=>'withdraw','parent_id'=>$parents[0]->id,'interest_id'=>$one->id,'version'=>1]]);
check(in_array($results[0]['status'],[200,409],true) && $results[1]['status']===200,'Confirmation/withdrawal race failed.');
check($active($activity)===1 && $activity->fresh()->status==='pending_registrations','Trial remained proceeding below minimum.');
check(OutboxMessage::where('activity_id',$activity->id)->count()===6+($results[0]['status']===200?5:0),'Lifecycle race lost or duplicated notifications.');
echo "PASS: simultaneous minimum confirmation/withdrawal never leaves a below-minimum trial proceeding\n";

$adminA=$makeUser('admin'); $adminB=$makeUser('admin');
$results=parallel([['action'=>'access','parent_id'=>$adminA->id,'target_id'=>$adminB->id],['action'=>'access','parent_id'=>$adminB->id,'target_id'=>$adminA->id]]);
$statuses=array_column($results,'status'); sort($statuses);
check($statuses===[200,403],'Concurrent admin suspension did not recheck the actor.');
check(User::whereIn('id',[$adminA->id,$adminB->id])->where('is_active',true)->count()===1,'Both concurrent admins were suspended.');
check(AccountEvent::whereIn('user_id',[$adminA->id,$adminB->id])->count()===1,'Account access audit drift.');
echo "PASS: simultaneous mutual admin suspensions retain one active admin and one audit event\n";
