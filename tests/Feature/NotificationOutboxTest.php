<?php
namespace Tests\Feature;

use App\Models\{Activity, ActivityEvent, InboxNotification, Interest, InterestEvent, OutboxMessage, User};
use App\Services\{NotificationOutbox, ParentInterests};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationOutboxTest extends TestCase
{
    use RefreshDatabase;
    private function user(string $role = 'parent'): User
    {
        return User::create(['name' => 'Private Account Name', 'email' => Str::uuid().'@example.test', 'password' => 'test password long!', 'role' => $role]);
    }
    private function fixture(): array
    {
        config(['hashing.bcrypt.rounds' => 4]);
        $parent = $this->user(); $provider = $this->user('provider');
        $activity = Activity::create(['provider_id' => $provider->id, 'submission_token' => Str::uuid(), 'title' => 'Music Discovery', 'category' => 'Music',
            'status' => 'pending_registrations', 'version' => 2, 'submitted_at' => now(), 'details' => ['trial_type' => 'individual', 'trial_availability' => 'After school', 'trial_duration' => 45,
                'min_age' => 6, 'max_age' => 10, 'deadline' => now('Australia/Sydney')->addDays(5)->toDateString()]]);
        return [$parent, $provider, $activity];
    }
    private function register(User $parent, Activity $activity): Interest
    {
        return (new ParentInterests)->register($parent, $activity->id, ['child_name' => 'Secret Child Nickname', 'child_age' => 8, 'expected_activity_version' => 2]);
    }
    public function test_interest_receipts_are_atomic_private_and_retries_do_not_enqueue_again(): void
    {
        [$parent,$provider,$activity] = $this->fixture(); $service = new ParentInterests;
        $interest = $this->register($parent,$activity); $this->register($parent,$activity);
        $service->change($parent,$interest->id,'withdraw',['expected_version'=>1]);
        $service->change($parent,$interest->id,'withdraw',['expected_version'=>1]);
        $service->change($parent,$interest->id,'restore',['expected_version'=>2,'expected_activity_version'=>2]);
        $service->change($parent,$interest->id,'restore',['expected_version'=>2,'expected_activity_version'=>2]);
        $this->assertDatabaseCount('interest_events',3); $this->assertDatabaseCount('notification_outbox',6);
        $this->assertSame(3,OutboxMessage::where('recipient_id',$parent->id)->count());
        $this->assertSame(3,OutboxMessage::where('recipient_id',$provider->id)->count());
        $copy = OutboxMessage::all()->toJson();
        foreach (['Secret Child Nickname','Private Account Name',$parent->email,$provider->email] as $private) $this->assertStringNotContainsString($private,$copy);
        DB::transaction(fn () => app(NotificationOutbox::class)->interest(InterestEvent::latest('id')->firstOrFail(),$interest->fresh(),$activity));
        $this->assertDatabaseCount('notification_outbox',6);
    }
    public function test_second_recipient_enqueue_failure_rolls_back_business_audit_and_first_recipient(): void
    {
        [$parent,$provider,$activity] = $this->fixture();
        DB::statement("CREATE TRIGGER fail_provider_notice BEFORE INSERT ON notification_outbox WHEN NEW.recipient_id={$provider->id} BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END");
        try {$this->register($parent,$activity); $this->fail('Expected failure');} catch (\Illuminate\Database\QueryException) {}
        $this->assertDatabaseCount('interests',0); $this->assertDatabaseCount('interest_events',0); $this->assertDatabaseCount('notification_outbox',0);
        DB::statement('DROP TRIGGER fail_provider_notice'); $this->register($parent,$activity);
        $this->assertDatabaseCount('notification_outbox',2);
    }
    public function test_withdraw_restore_enqueue_failures_roll_back_versions_and_audits(): void
    {
        [$parent,$provider,$activity] = $this->fixture(); $interest = $this->register($parent,$activity); $service = new ParentInterests;
        foreach ([['withdraw',1,'interested',2],['restore',2,'withdrawn',4]] as [$action,$version,$status,$count]) {
            DB::statement("CREATE TRIGGER fail_notice BEFORE INSERT ON notification_outbox BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END");
            try {$service->change($parent,$interest->id,$action,['expected_version'=>$version,'expected_activity_version'=>2]); $this->fail('Expected failure');} catch (\Illuminate\Database\QueryException) {}
            $this->assertSame($status,$interest->fresh()->status); $this->assertSame($version,$interest->fresh()->version);
            $this->assertDatabaseCount('notification_outbox',$count); $this->assertDatabaseCount('interest_events',$version);
            DB::statement('DROP TRIGGER fail_notice'); $service->change($parent,$interest->id,$action,['expected_version'=>$version,'expected_activity_version'=>2]);
        }
    }
    public function test_delivery_read_and_inbox_are_scoped_to_recipient(): void
    {
        [$parent,$provider,$activity] = $this->fixture(); $this->register($parent,$activity);
        $this->actingAs($parent)->get('/notifications?recipient_id='.$provider->id)->assertOk()->assertSee('Your interest was registered')->assertDontSee('A new interest was registered')->assertHeader('X-Robots-Tag','noindex, nofollow');
        $notice = InboxNotification::firstOrFail();
        $this->assertSame($parent->id,$notice->recipient_id);
        $this->assertSame(1,OutboxMessage::where('recipient_id',$provider->id)->whereNull('delivered_at')->count());
        $this->post('/notifications/'.$notice->id.'/read')->assertRedirect('/notifications'); $read = $notice->fresh()->read_at;
        $this->travel(1)->minutes(); $this->post('/notifications/'.$notice->id.'/read')->assertRedirect();
        $this->assertTrue($read->equalTo($notice->fresh()->read_at));
        $this->actingAs($provider)->post('/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->get('/notifications')->assertOk()->assertDontSee($notice->id)->assertSee('A new interest was registered');
        $this->assertDatabaseCount('inbox_notifications',2);
        $this->get('/notifications')->assertOk(); $this->assertDatabaseCount('inbox_notifications',2);
        auth()->logout(); $this->get('/notifications')->assertRedirect('/account');
    }
    public function test_materialization_and_acknowledgement_failures_leave_messages_pending(): void
    {
        [$parent,,$activity] = $this->fixture(); $this->register($parent,$activity);
        foreach (["CREATE TRIGGER fail_delivery BEFORE INSERT ON inbox_notifications BEGIN SELECT RAISE(ABORT,'inbox unavailable'); END", "CREATE TRIGGER fail_delivery BEFORE UPDATE ON notification_outbox WHEN NEW.delivered_at IS NOT NULL BEGIN SELECT RAISE(ABORT,'ack unavailable'); END"] as $sql) {
            DB::statement($sql);
            try {app(NotificationOutbox::class)->deliver($parent); $this->fail('Expected failure');} catch (\Illuminate\Database\QueryException) {}
            $this->assertDatabaseCount('inbox_notifications',0);
            $this->assertSame(2,OutboxMessage::whereNull('delivered_at')->count());
            DB::statement('DROP TRIGGER fail_delivery');
        }
        $this->assertSame(1,app(NotificationOutbox::class)->deliver($parent));
        $this->assertSame(0,app(NotificationOutbox::class)->deliver($parent));
    }
    public function test_notice_id_collision_stays_pending_and_next_retry_recovers(): void
    {
        [$parent,,$activity] = $this->fixture(); $this->register($parent,$activity);
        $other = $this->user(); $this->register($other,$activity);
        app(NotificationOutbox::class)->deliver($other); $otherNotice = InboxNotification::firstOrFail();
        Str::createUuidsUsing(fn () => \Ramsey\Uuid\Uuid::fromString($otherNotice->id));
        try {app(NotificationOutbox::class)->deliver($parent); $this->fail('Expected collision');} catch (\Illuminate\Database\QueryException) {} finally {Str::createUuidsNormally();}
        $this->assertSame(1,OutboxMessage::where('recipient_id',$parent->id)->whereNull('delivered_at')->count());
        app(NotificationOutbox::class)->deliver($parent);
        $this->assertDatabaseCount('inbox_notifications',2);
        $this->assertSame($other->id,$otherNotice->fresh()->recipient_id);
    }
    public function test_wrong_recipient_is_blocked_by_database_and_wrong_payload_is_not_acknowledged(): void
    {
        [$parent,$provider,$activity] = $this->fixture(); $this->register($parent,$activity);
        $source = OutboxMessage::where('recipient_id',$parent->id)->firstOrFail();
        $payload = ['id'=>Str::uuid(),'outbox_id'=>$source->id,'recipient_id'=>$provider->id,'activity_id'=>$activity->id,'kind'=>$source->kind,'title'=>$source->title,'message'=>'Wrong copy','created_at'=>$source->getRawOriginal('created_at')];
        try {InboxNotification::create($payload); $this->fail('Wrong recipient accepted');} catch (\Illuminate\Database\QueryException) {}
        $notice = InboxNotification::create([...$payload,'recipient_id'=>$parent->id]);
        try {app(NotificationOutbox::class)->deliver($parent); $this->fail('Wrong copy acknowledged');} catch (\LogicException) {}
        $this->assertNull($source->fresh()->delivered_at);
        $notice->update(['message'=>$source->message,'read_at'=>now()]);
        app(NotificationOutbox::class)->deliver($parent);
        $this->assertNotNull($source->fresh()->delivered_at); $this->assertNotNull($notice->fresh()->read_at);
        $this->assertDatabaseCount('inbox_notifications',1);
    }
    public function test_approval_and_rejection_notify_only_provider_without_feedback_copies(): void
    {
        [$parent,$provider,$activity] = $this->fixture(); $admin = $this->user('admin');
        $activity->update(['status'=>'pending_approval','version'=>1]);
        $this->actingAs($admin)->post('/admin/activities/'.$activity->id.'/decision',['action'=>'reject','expected_version'=>1,'reason'=>'Private review feedback'])->assertRedirect();
        $this->assertDatabaseCount('notification_outbox',1);
        $message = OutboxMessage::firstOrFail(); $this->assertSame($provider->id,$message->recipient_id);
        $this->assertStringNotContainsString('Private review feedback',$message->message);
        $this->actingAs($parent)->get('/notifications')->assertOk()->assertDontSee('Activity rejected');
        $activity->refresh()->update(['status'=>'pending_approval','version'=>3]);
        DB::statement("CREATE TRIGGER fail_approval_notice BEFORE INSERT ON notification_outbox BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END");
        $this->actingAs($admin)->post('/admin/activities/'.$activity->id.'/decision',['action'=>'approve','expected_version'=>3])->assertStatus(500);
        $this->assertSame('pending_approval',$activity->fresh()->status); $this->assertSame(3,$activity->fresh()->version);
        $this->assertDatabaseCount('activity_events',1); $this->assertDatabaseCount('notification_outbox',1);
        DB::statement('DROP TRIGGER fail_approval_notice');
        $this->post('/admin/activities/'.$activity->id.'/decision',['action'=>'approve','expected_version'=>3])->assertRedirect();
        $this->assertDatabaseCount('notification_outbox',2);
    }
    public function test_delivery_is_bounded_and_read_requires_csrf(): void
    {
        [$parent,,$activity] = $this->fixture();
        for ($i=0;$i<5;$i++) (new ParentInterests)->register($parent,$activity->id,['child_name'=>'Child '.$i,'child_age'=>8,'expected_activity_version'=>2]);
        $outbox = app(NotificationOutbox::class);
        $this->assertSame(2,$outbox->deliver($parent,2)); $this->assertSame(3,$outbox->deliver($parent,50));
        $notice = InboxNotification::firstOrFail();
        $this->actingAs($parent); $this->app->instance('env','local');
        $this->post('/notifications/'.$notice->id.'/read')->assertStatus(419); $this->assertNull($notice->fresh()->read_at);
    }
}
