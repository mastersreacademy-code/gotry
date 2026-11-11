<?php
namespace Tests\Feature;
use App\Models\{Activity, ActivityEvent, AccountEvent, Interest, OutboxMessage, User};
use App\Services\{AdminAccounts, ParentInterests};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-10 12:00','Australia/Sydney')); config(['hashing.bcrypt.rounds'=>4]); }
    private function user(string $role='admin'): User { return User::create(['name'=>'Workspace '.$role,'email'=>Str::uuid().'@example.test','password'=>'workspace test password!','role'=>$role]); }
    private function activity(string $status='pending_registrations', array $details=[]): Activity
    {
        return Activity::create(['provider_id'=>$this->user('provider')->id,'submission_token'=>Str::uuid(),'title'=>'Reviewable Music','category'=>'Music','status'=>$status,'version'=>$status==='pending_approval'?1:2,'submitted_at'=>now(),
            'details'=>[...['trial_type'=>'group','trial_date'=>'2026-11-20','trial_time'=>'09:00','deadline'=>'2026-11-11','trial_min'=>2,'trial_max'=>3,'trial_duration'=>45,
                'description'=>'Music discovery','venue'=>'Sydney','min_age'=>6,'max_age'=>10,'regular_format'=>'individual','individual_price'=>60,'usual_availability'=>'After school','discount_type'=>'none'],...$details]]);
    }
    private function interest(User $parent,Activity $activity,string $name): Interest { return (new ParentInterests)->register($parent,$activity->id,['child_name'=>$name,'child_age'=>8,'expected_activity_version'=>2]); }
    public function test_stale_admin_forms_return_to_current_listing_without_changes(): void
    {
        $activity = $this->activity('pending_approval');
        $path = '/admin/activities/'.$activity->id;
        $this->actingAs($this->user())->post($path.'/decision', [
            'action' => 'approve', 'expected_version' => 1,
        ])->assertRedirect();
        $input = ['expected_version' => 1, 'return_to_listing' => 1, 'reason' => 'Stale decision'];
        $this->post($path.'/decision', [...$input, 'action' => 'reject'])
            ->assertRedirect($path)->assertSessionHasErrors('listing');
        $this->get($path)->assertOk()->assertSee('This activity has already changed')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post($path.'/lifecycle', [...$input, 'action' => 'cancel_admin'])
            ->assertRedirect($path)->assertSessionHasErrors('listing');
        $this->assertSame('pending_registrations', $activity->fresh()->status);
        $this->assertDatabaseCount('activity_events', 1);
        $this->assertDatabaseCount('notification_outbox', 1);
    }

    public function test_admin_pages_filters_and_audit_are_protected_and_usable(): void
    {
        $activity=$this->activity('pending_approval'); $this->actingAs($this->user());
        $this->get('/admin')->assertOk()->assertSee('Super admin workspace')->assertSee('Reviewable Music');
        $this->get('/admin/activities?status=pending_approval&q=Reviewable')->assertOk()->assertSee('Reviewable Music');
        $this->get('/admin/activities?status=rejected')->assertOk()->assertDontSee('Reviewable Music');
        $this->get('/admin/users?role=provider')->assertOk()->assertSee('Workspace provider');
        $this->get('/admin/guide')->assertOk()->assertSee('Payment collection');
        foreach(['activity','interest','account'] as $type) $this->get('/admin/audit?type='.$type)->assertOk()->assertHeader('X-Robots-Tag','noindex, nofollow');
        foreach(['parent','provider'] as $role) { $this->actingAs($this->user($role)); foreach(['/admin','/admin/users','/admin/audit','/admin/activities','/admin/guide'] as $path) $this->get($path)->assertForbidden(); }
    }
    public function test_suspension_is_audited_revokes_only_target_sessions_and_blocks_login_and_existing_access(): void
    {
        $admin=$this->user(); $parent=$this->user('parent');
        foreach([$admin,$parent] as $user) DB::table('sessions')->insert(['id'=>'fixture-'.$user->id,'user_id'=>$user->id,'payload'=>'fixture','last_activity'=>time()]);
        $this->actingAs($admin)->post('/admin/users/'.$parent->id.'/access',['action'=>'suspend','expected_version'=>1,'reason'=>'Account review'])->assertRedirect('/admin/users');
        $this->assertFalse($parent->fresh()->is_active); $this->assertSame(2,$parent->fresh()->access_version); $this->assertSame('parent',$parent->fresh()->role);
        $this->assertDatabaseMissing('sessions',['user_id'=>$parent->id]); $this->assertDatabaseHas('sessions',['user_id'=>$admin->id]);
        $this->assertDatabaseHas('account_events',['actor_id'=>$admin->id,'user_id'=>$parent->id,'version'=>2]);
        $this->get('/admin/audit?type=account')->assertOk()->assertSee('Account review');
        $this->actingAs($parent)->get('/parent')->assertRedirect('/account'); $this->assertGuest();
        $this->post('/login',['email'=>$parent->email,'password'=>'workspace test password!'])->assertSessionHasErrors('email'); $this->assertGuest();
        $this->actingAs($admin)->post('/admin/users/'.$parent->id.'/access',['action'=>'activate','expected_version'=>2,'reason'=>'Review complete'])->assertRedirect();
        $this->assertTrue($parent->fresh()->is_active);
    }
    public function test_self_suspension_stale_changes_and_parent_account_access_are_rejected(): void
    {
        $admin=$this->user(); $target=$this->user('provider'); $this->actingAs($admin);
        $this->post('/admin/users/'.$admin->id.'/access',['action'=>'suspend','expected_version'=>1,'reason'=>'test'])->assertStatus(422);
        $this->post('/admin/users/'.$target->id.'/access',['action'=>'suspend','expected_version'=>2,'reason'=>'test'])->assertStatus(409);
        $this->post('/admin/users/'.$target->id.'/access',['action'=>'suspend','expected_version'=>1])->assertSessionHasErrors('reason');
        $this->actingAs($this->user('parent'))->post('/admin/users/'.$target->id.'/access',['action'=>'suspend','expected_version'=>1,'reason'=>'test'])->assertForbidden();
        $this->assertDatabaseCount('account_events',0); $this->assertTrue($admin->fresh()->is_active);
    }
    public function test_account_audit_failure_rolls_back_access_version_and_session_deletion(): void
    {
        $admin=$this->user(); $target=$this->user('parent'); DB::table('sessions')->insert(['id'=>'protected-session','user_id'=>$target->id,'payload'=>'fixture','last_activity'=>time()]);
        DB::statement("CREATE TRIGGER fail_account_event BEFORE INSERT ON account_events BEGIN SELECT RAISE(ABORT,'audit unavailable'); END");
        $this->actingAs($admin)->post('/admin/users/'.$target->id.'/access',['action'=>'suspend','expected_version'=>1,'reason'=>'test'])->assertStatus(500);
        $this->assertTrue($target->fresh()->is_active); $this->assertSame(1,$target->fresh()->access_version); $this->assertDatabaseHas('sessions',['id'=>'protected-session']); $this->assertDatabaseCount('account_events',0);
    }
    public function test_approval_rejects_expired_trials_and_suspended_providers(): void
    {
        $admin=$this->user(); $expired=$this->activity('pending_approval',['deadline'=>'2026-11-09']); $suspended=$this->activity('pending_approval');
        User::findOrFail($suspended->provider_id)->forceFill(['is_active'=>false])->save(); $this->actingAs($admin);
        foreach([$expired,$suspended] as $activity) $this->post('/admin/activities/'.$activity->id.'/decision',['action'=>'approve','expected_version'=>1])->assertStatus(409);
        $this->assertDatabaseCount('activity_events',0); $this->assertDatabaseCount('notification_outbox',0);
    }
    public function test_proceeding_withdrawal_minimum_loss_and_reconfirmation_are_atomic_and_private(): void
    {
        $admin=$this->user(); $activity=$this->activity(); $a=$this->user('parent'); $b=$this->user('parent');
        $interest=$this->interest($a,$activity,'Private Child A'); $this->interest($b,$activity,'Private Child B');
        $url='/admin/activities/'.$activity->id.'/lifecycle';
        $this->actingAs($admin)->post($url,['action'=>'confirm_minimum','expected_version'=>2])->assertRedirect();
        $this->assertSame('proceeding_pending_payments',$activity->fresh()->status);
        $this->assertSame(2,OutboxMessage::where('kind','parent_trial_proceeding')->count());
        $this->post($url,['action'=>'confirm_minimum','expected_version'=>2])->assertStatus(409);
        $this->actingAs($a)->post('/parent/interests/'.$interest->id.'/withdraw',['expected_version'=>1,'withdraw_confirm'=>1])->assertRedirect();
        $this->assertSame('pending_registrations',$activity->fresh()->status); $this->assertSame(4,$activity->fresh()->version);
        $this->assertDatabaseHas('activity_events',['action'=>'minimum_lost','actor_id'=>$a->id]);
        $this->assertSame([$b->id],OutboxMessage::where('kind','parent_trial_review')->pluck('recipient_id')->all());
        $this->assertStringNotContainsString('Private Child',OutboxMessage::all()->toJson());
        $this->actingAs($admin)->post($url,['action'=>'confirm_minimum','expected_version'=>4])->assertStatus(409);
        $this->actingAs($a)->post('/parent/interests/'.$interest->id.'/restore',['expected_version'=>2,'expected_activity_version'=>4,'interest_terms'=>1])->assertRedirect();
        $this->actingAs($admin)->post($url,['action'=>'confirm_minimum','expected_version'=>4])->assertRedirect();
        $this->assertSame(5,$activity->fresh()->version);
        $this->get('/admin/audit?type=interest')->assertOk()->assertDontSee('Private Child A');
    }
    public function test_low_number_cancellation_waits_for_deadline_and_admin_cancellation_requires_reason(): void
    {
        $activity=$this->activity(); $this->actingAs($this->user()); $url='/admin/activities/'.$activity->id.'/lifecycle';
        $this->post($url,['action'=>'cancel_low_numbers','expected_version'=>2])->assertStatus(409);
        $this->post($url,['action'=>'cancel_admin','expected_version'=>2])->assertSessionHasErrors('reason');
        $this->travel(2)->days();
        $this->post($url,['action'=>'cancel_low_numbers','expected_version'=>2])->assertRedirect(); $this->assertSame('cancelled_low_numbers',$activity->fresh()->status);
        $other=$this->activity(); $this->post('/admin/activities/'.$other->id.'/lifecycle',['action'=>'cancel_admin','expected_version'=>2,'reason'=>'Private provider feedback'])->assertRedirect();
        $this->assertSame('cancelled_by_admin',$other->fresh()->status);
        $this->assertStringNotContainsString('Private provider feedback',OutboxMessage::all()->toJson());
    }
    public function test_lifecycle_outbox_failure_rolls_back_state_audit_and_partial_recipients(): void
    {
        $activity=$this->activity(); $this->interest($this->user('parent'),$activity,'Child one'); $this->interest($this->user('parent'),$activity,'Child two');
        DB::statement("CREATE TRIGGER fail_lifecycle_notice BEFORE INSERT ON notification_outbox WHEN NEW.kind='parent_trial_proceeding' BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END");
        $this->actingAs($this->user())->post('/admin/activities/'.$activity->id.'/lifecycle',['action'=>'confirm_minimum','expected_version'=>2])->assertStatus(500);
        $this->assertSame('pending_registrations',$activity->fresh()->status); $this->assertSame(2,$activity->fresh()->version);
        $this->assertDatabaseCount('activity_events',0); $this->assertDatabaseCount('notification_outbox',4);
    }
    public function test_suspended_provider_listings_stop_accepting_new_interest(): void
    {
        $activity=$this->activity(); User::findOrFail($activity->provider_id)->forceFill(['is_active'=>false])->save();
        $parent=$this->user('parent'); $this->actingAs($parent)->get('/activities')->assertOk()->assertDontSee('Reviewable Music');
        $this->get('/activities/'.$activity->id)->assertOk()->assertSee('provider is currently unavailable');
        $this->post('/activities/'.$activity->id.'/interest',['child_name'=>'Child','child_age'=>8,'expected_activity_version'=>2,'interest_terms'=>1])->assertStatus(409);
        $this->assertDatabaseCount('interests',0);
    }
    public function test_lifecycle_notices_deduplicate_parents_and_exclude_withdrawn_families(): void
    {
        $activity=$this->activity(); $parent=$this->user('parent'); $withdrawn=$this->user('parent');
        $this->interest($parent,$activity,'Child one'); $this->interest($parent,$activity,'Child two'); $old=$this->interest($withdrawn,$activity,'Withdrawn child');
        (new ParentInterests)->change($withdrawn,$old->id,'withdraw',['expected_version'=>1]);
        $this->actingAs($this->user())->post('/admin/activities/'.$activity->id.'/lifecycle',['action'=>'confirm_minimum','expected_version'=>2])->assertRedirect();
        $this->assertSame([$parent->id],OutboxMessage::where('kind','parent_trial_proceeding')->pluck('recipient_id')->all());
        $this->post('/admin/activities/'.$activity->id.'/lifecycle',['action'=>'cancel_admin','expected_version'=>3,'reason'=>'Venue unavailable'])->assertRedirect();
        $this->assertSame([$parent->id],OutboxMessage::where('kind','parent_trial_cancelled')->pluck('recipient_id')->all());
    }
    public function test_minimum_loss_notification_failure_rolls_back_parent_withdrawal_and_activity_state(): void
    {
        $activity=$this->activity(); $parent=$this->user('parent'); $interest=$this->interest($parent,$activity,'Child one'); $this->interest($this->user('parent'),$activity,'Child two');
        $this->actingAs($this->user())->post('/admin/activities/'.$activity->id.'/lifecycle',['action'=>'confirm_minimum','expected_version'=>2])->assertRedirect();
        DB::statement("CREATE TRIGGER fail_review_notice BEFORE INSERT ON notification_outbox WHEN NEW.kind='parent_trial_review' BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END");
        $this->actingAs($parent)->post('/parent/interests/'.$interest->id.'/withdraw',['expected_version'=>1,'withdraw_confirm'=>1])->assertStatus(500);
        $this->assertSame('interested',$interest->fresh()->status); $this->assertSame(1,$interest->fresh()->version);
        $this->assertSame('proceeding_pending_payments',$activity->fresh()->status); $this->assertSame(3,$activity->fresh()->version);
        $this->assertDatabaseCount('interest_events',2); $this->assertDatabaseCount('activity_events',1); $this->assertDatabaseCount('notification_outbox',7);
    }
}
