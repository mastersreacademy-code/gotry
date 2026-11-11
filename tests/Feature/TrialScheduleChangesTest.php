<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Models\Interest;
use App\Models\InterestEvent;
use App\Models\OutboxMessage;
use App\Models\User;
use App\Services\ParentInterests;
use App\Services\TrialSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrialScheduleChangesTest extends TestCase
{
    use RefreshDatabase;

    private User $provider;
    private Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00', 'Australia/Sydney'));
        config(['hashing.bcrypt.rounds' => 4]);
        $this->provider = $this->user('provider');
        $this->activity = Activity::create([
            'provider_id' => $this->provider->id, 'submission_token' => Str::uuid(), 'title' => 'Junior Music',
            'category' => 'Music', 'status' => 'pending_registrations', 'version' => 2, 'submitted_at' => now(),
            'details' => ['min_age' => 6, 'max_age' => 10, 'trial_type' => 'group', 'trial_min' => 1, 'trial_max' => 1,
                'trial_date' => '2026-12-10', 'trial_time' => '09:00', 'deadline' => '2026-12-09',
                'extra_trial_times' => "2026-12-11 10:00\n2026-12-12 11:00", 'trial_duration' => 45,
                'regular_format' => 'individual', 'usual_availability' => 'Weekdays', 'individual_price' => '60',
                'discount_type' => 'none', 'description' => 'Discover music', 'venue' => 'Sydney'],
        ]);
    }

    private function user(string $role = 'parent'): User
    {
        return User::create(['name' => $role, 'email' => Str::uuid().'@example.test', 'password' => 'local test password!', 'role' => $role]);
    }

    private function register(User $parent, int $session, string $name = 'Private child'): Interest
    {
        return app(ParentInterests::class)->register($parent, $this->activity->id, [
            'child_name' => $name, 'child_age' => 8, 'trial_session' => $session,
            'expected_activity_version' => $this->activity->fresh()->version,
        ]);
    }

    public function test_transfer_checks_ownership_capacity_and_versions_and_keeps_a_single_interest(): void
    {
        $parent = $this->user();
        $original = $this->register($parent, 1);
        $other = $this->user();
        $this->register($other, 2, 'Other child');
        $url = '/parent/interests/'.$original->id.'/transfer';
        $request = ['expected_version' => 1, 'expected_activity_version' => 2, 'trial_session' => 3, 'transfer_confirm' => 1];
        $this->actingAs($other)->post($url)->assertNotFound();
        $this->actingAs($this->provider)->post($url, $request)->assertForbidden();
        $this->actingAs($parent)->get('/parent')->assertOk()->assertSee('Move interest')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post($url, [...$request, 'trial_session' => 2])->assertStatus(409);
        $this->post($url, [...$request, 'trial_session' => 1])->assertStatus(422);
        $this->post($url, [...$request, 'expected_activity_version' => 1])->assertStatus(409);
        $this->assertSame(1, $original->fresh()->trial_session);
        $this->post($url, $request)->assertRedirect('/parent');
        $this->post($url, $request)->assertStatus(409);
        $this->post('/parent/interests/'.$original->id.'/restore', [
            'expected_version' => 1, 'expected_activity_version' => 2, 'interest_terms' => 1,
        ])->assertStatus(409);
        $this->assertSame(3, $original->fresh()->trial_session);
        $this->assertSame(2, $original->fresh()->version);
        $this->assertDatabaseCount('interests', 2);
        $event = InterestEvent::where('action', 'transferred')->sole();
        $this->assertSame(['from_session' => 1, 'to_session' => 3, 'from_start' => '2026-12-10 09:00', 'to_start' => '2026-12-12 11:00'], $event->schedule_change);
        $this->assertEqualsCanonicalizing([$parent->id, $this->provider->id], OutboxMessage::where('interest_event_id', $event->id)->pluck('recipient_id')->all());
        $this->assertStringNotContainsString('Private child', OutboxMessage::where('kind', 'provider_transferred')->sole()->message);
        $this->register($other, 1, 'A different child');
        $this->actingAs($this->user('admin'))->get('/admin/audit?type=interest')->assertOk()
            ->assertSee('2026-12-10 09:00')->assertSee('2026-12-12 11:00')->assertDontSee('Private child');
    }

    public function test_transfer_reopens_confirmed_source_and_rolls_back_if_notice_fails(): void
    {
        $parent = $this->user();
        $interest = $this->register($parent, 1);
        $this->actingAs($this->user('admin'))->post('/admin/activities/'.$this->activity->id.'/lifecycle', [
            'action' => 'confirm_minimum', 'trial_session' => 1, 'expected_version' => 2,
        ])->assertRedirect();
        $url = '/parent/interests/'.$interest->id.'/transfer';
        $request = ['expected_version' => 1, 'expected_activity_version' => 3, 'trial_session' => 2, 'transfer_confirm' => 1];
        DB::statement("CREATE TRIGGER block_transfer_notice BEFORE INSERT ON notification_outbox WHEN NEW.kind='trial_review' BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END");
        $this->actingAs($parent)->post($url, $request)->assertStatus(500);
        $this->assertSame(1, $interest->fresh()->trial_session);
        $this->assertDatabaseCount('interest_events', 1);
        $this->assertDatabaseCount('activity_events', 1);
        $this->assertDatabaseCount('notification_outbox', 4);
        DB::statement('DROP TRIGGER block_transfer_notice');
        $this->post($url, $request)->assertRedirect();
        $this->assertSame('pending_registrations', TrialSchedule::status($this->activity->fresh(), 1));
        $this->assertDatabaseHas('activity_events', ['action' => 'minimum_lost', 'trial_session' => 1]);
        $this->assertDatabaseMissing('notification_outbox', ['kind' => 'parent_trial_review', 'recipient_id' => $parent->id]);
    }

    public function test_provider_reschedule_is_versioned_and_notifies_only_active_families_in_the_session(): void
    {
        $parent = $this->user();
        $original = $this->register($parent, 1);
        $this->register($this->user(), 2);
        $withdrawn = $this->user();
        $this->actingAs($withdrawn)->post('/parent/interests/'.$this->register($withdrawn, 3)->id.'/withdraw', [
            'expected_version' => 1, 'withdraw_confirm' => 1,
        ])->assertRedirect();
        $url = '/provider/activities/'.$this->activity->id.'/reschedule';
        $request = ['expected_activity_version' => 2, 'trial_session' => 1, 'trial_date' => '2026-12-13',
            'trial_time' => '15:30', 'reschedule_confirm' => 1];
        $this->actingAs($this->user('provider'))->post($url)->assertNotFound();
        $this->actingAs($parent)->post($url, $request)->assertForbidden();
        $this->actingAs($this->provider)->get('/provider/activities/'.$this->activity->id)->assertOk()->assertSee('Reschedule a trial session');
        $this->post($url, [...$request, 'trial_date' => '2027-04-04', 'trial_time' => '02:30'])->assertSessionHasErrors('trial_time');
        $this->post($url, [...$request, 'trial_date' => '2026-12-11', 'trial_time' => '10:00'])->assertSessionHasErrors('trial_time');
        $this->post($url, [...$request, 'trial_date' => '2026-12-08'])->assertSessionHasErrors('trial_time');
        $this->post($url, $request)->assertRedirect('/provider/activities/'.$this->activity->id);
        $this->post($url, $request)->assertStatus(409);
        $this->assertSame(3, $this->activity->fresh()->version);
        $this->assertSame([1 => '2026-12-13 15:30', 2 => '2026-12-11 10:00', 3 => '2026-12-12 11:00'], TrialSchedule::sessions($this->activity->fresh()));
        $this->assertSame(1, $original->fresh()->trial_session);
        $event = ActivityEvent::where('action', 'rescheduled')->sole();
        $this->assertEqualsCanonicalizing([$parent->id, $this->provider->id], OutboxMessage::where('activity_event_id', $event->id)->pluck('recipient_id')->all());
        $notice = OutboxMessage::where('kind', 'parent_trial_rescheduled')->sole();
        $this->assertStringContainsString('2026-12-10 09:00', $notice->message);
        $this->assertStringContainsString('2026-12-13 15:30', $notice->message);
        $this->actingAs($parent)->get('/parent')->assertOk()->assertSee('2026-12-13 15:30');
        $this->post('/parent/interests/'.$original->id.'/transfer', ['expected_version' => 1, 'expected_activity_version' => 2,
            'trial_session' => 3, 'transfer_confirm' => 1])->assertStatus(409);
        $this->actingAs($this->user('admin'))->get('/admin/audit')->assertOk()->assertSee('2026-12-10 09:00')->assertSee('2026-12-13 15:30');
    }

    public function test_reschedule_rolls_back_time_version_and_audit_if_notice_fails(): void
    {
        $this->register($this->user(), 2);
        DB::statement("CREATE TRIGGER block_reschedule_notice BEFORE INSERT ON notification_outbox WHEN NEW.kind='parent_trial_rescheduled' BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END");
        $this->actingAs($this->provider)->post('/provider/activities/'.$this->activity->id.'/reschedule', [
            'expected_activity_version' => 2, 'trial_session' => 2, 'trial_date' => '2026-12-13',
            'trial_time' => '15:30', 'reschedule_confirm' => 1,
        ])->assertStatus(500);
        $this->assertSame(2, $this->activity->fresh()->version);
        $this->assertSame('2026-12-11 10:00', TrialSchedule::sessions($this->activity->fresh())[2]);
        $this->assertDatabaseCount('activity_events', 0);
        $this->assertDatabaseCount('notification_outbox', 2);
    }
}
