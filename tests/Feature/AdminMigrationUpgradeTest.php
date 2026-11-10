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
        $this->artisan('migrate:rollback',['--step'=>1,'--force'=>true])->assertExitCode(0);
        foreach(['admin','provider','parent'] as $role) DB::table('users')->insert(['name'=>'Existing '.$role,'email'=>$role.'@example.test','role'=>$role,'password'=>'existing-password-hash-'.$role,'created_at'=>now(),'updated_at'=>now()]);
        $this->artisan('migrate',['--force'=>true])->assertExitCode(0);
        foreach(DB::table('users')->get() as $user) {
            $this->assertSame('Existing '.$user->role,$user->name); $this->assertSame($user->role.'@example.test',$user->email);
            $this->assertSame('existing-password-hash-'.$user->role,$user->password); $this->assertEquals(1,$user->is_active); $this->assertEquals(1,$user->access_version);
        }
        $this->assertDatabaseCount('account_events',0);
    }
}
