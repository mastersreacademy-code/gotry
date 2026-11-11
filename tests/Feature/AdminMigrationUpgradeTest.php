<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMigrationUpgradeTest extends TestCase
{
    use DatabaseMigrations;
    public function test_access_migration_preserves_existing_admin_provider_and_parent_accounts(): void
    {
        $this->artisan('migrate:rollback',['--step'=>3,'--force'=>true])->assertExitCode(0);
        foreach(['admin','provider','parent'] as $role) DB::table('users')->insert(['name'=>'Existing '.$role,'email'=>$role.'@example.test','role'=>$role,'password'=>'existing-password-hash-'.$role,'created_at'=>now(),'updated_at'=>now()]);
        $this->artisan('migrate',['--force'=>true])->assertExitCode(0);
        foreach(DB::table('users')->get() as $user) {
            $this->assertSame('Existing '.$user->role,$user->name); $this->assertSame($user->role.'@example.test',$user->email);
            $this->assertSame('existing-password-hash-'.$user->role,$user->password); $this->assertEquals(1,$user->is_active); $this->assertEquals(1,$user->access_version);
        }
        $this->assertDatabaseCount('account_events',0);
    }

    public function test_trial_session_migration_preserves_existing_accounts_and_single_trial_history(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertExitCode(0);
        $user = fn ($role) => DB::table('users')->insertGetId([
            'name' => $role, 'email' => $role.'@example.test', 'role' => $role,
            'password' => 'existing-hash-'.$role, 'is_active' => $role !== 'admin', 'access_version' => 2,
        ]);
        $admin = $user('admin');
        $provider = $user('provider');
        $parent = $user('parent');
        $activity = DB::table('activities')->insertGetId([
            'provider_id' => $provider, 'submission_token' => \Illuminate\Support\Str::uuid(), 'title' => 'Existing trial',
            'category' => 'Music', 'status' => 'pending_registrations', 'details' => '{}', 'version' => 2, 'submitted_at' => now(),
        ]);
        $interest = DB::table('interests')->insertGetId([
            'activity_id' => $activity, 'parent_id' => $parent, 'child_name' => 'Example child',
            'child_key' => hash('sha256', 'example child'), 'child_age' => 8, 'status' => 'interested',
            'version' => 3, 'activity_version' => 2,
        ]);
        $event = DB::table('activity_events')->insertGetId([
            'activity_id' => $activity, 'actor_id' => $admin, 'action' => 'approve',
            'from_status' => 'pending_approval', 'to_status' => 'pending_registrations',
        ]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertDatabaseHas('users', ['id' => $admin, 'password' => 'existing-hash-admin', 'is_active' => false, 'access_version' => 2]);
        $this->assertDatabaseHas('interests', ['id' => $interest, 'parent_id' => $parent, 'version' => 3, 'trial_session' => null]);
        $this->assertDatabaseHas('activity_events', ['id' => $event, 'actor_id' => $admin, 'trial_session' => null]);
        $this->assertDatabaseCount('users', 3);
    }
}
