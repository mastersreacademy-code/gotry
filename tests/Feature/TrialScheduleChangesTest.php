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

    public function test_activity_detail_matches_trial_format_and_availability(): void
    {
        $path = '/activities/'.$this->activity->id;
        $details = $this->activity->details;
        $this->actingAs($this->user())->get($path)->assertOk()->assertSee('Choose a session')
            ->assertSee('data-intent="register"', false)->assertDontSee('data-intent="waitlist"', false);

        $holder = $this->user();
        foreach ([1, 2, 3] as $number) $this->register($holder, $number, 'Child '.$number);
        $this->get($path)->assertOk()->assertSee('Join the trial waitlist')->assertSee('Join the waitlist for free.')
            ->assertSee('data-intent="waitlist"', false)->assertDontSee('data-intent="register"', false);

        $this->activity->update(['details' => [...$details, 'deadline' => '2026-11-10']]);
        $this->get($path)->assertOk()->assertSee('No trial sessions are accepting interest.')
            ->assertDontSee('data-trial-form', false)->assertDontSee('How it works');

        $this->activity->update(['details' => [...$details, 'extra_trial_times' => null, 'trial_max' => 4]]);
        $this->get($path)->assertOk()->assertSee('Trial date')->assertSee('1–4 children')
            ->assertSee('data-intent="register"', false)->assertDontSee('Choose a session');

        $this->activity->update(['details' => [...$details, 'trial_type' => 'individual', 'trial_availability' => 'After school']]);
        $this->get($path)->assertOk()->assertSee('After school')->assertSee('Arrange a trial time with the provider.')
            ->assertDontSee('Attend the trial once the session is confirmed.')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_stale_trial_form_returns_parent_to_current_options_without_changing_intent(): void
    {
        $holder = $this->user();
        $seat = $this->register($holder, 1);
        $path = '/activities/'.$this->activity->id;
        $input = ['child_name' => 'Waiting child', 'child_age' => 8, 'trial_session' => 1,
            'expected_activity_version' => 2, 'interest_terms' => 1, 'return_to_trial' => 1];

        $this->actingAs($this->user())->post($path.'/interest', $input)
            ->assertRedirect($path.'#trial')->assertSessionHasErrors('trial')->assertSessionHasInput('child_name', 'Waiting child');
        $this->get($path)->assertOk()->assertSee('This trial is full')->assertSee('data-intent="waitlist"', false);
        $this->assertDatabaseCount('interests', 1);

        app(ParentInterests::class)->change($holder, $seat->id, 'withdraw', ['expected_version' => 1]);
        $this->post($path.'/interest', [...$input, 'waitlist' => 1])
            ->assertRedirect($path.'#trial')->assertSessionHasErrors('trial')->assertSessionHasInput('trial_session', 1);
        $this->get($path)->assertOk()->assertSee('no longer taking waitlist entries')
            ->assertSee('data-intent="register"', false)->assertDontSee('data-intent="waitlist"', false);
        $this->assertDatabaseCount('interests', 1);
    }

    public function test_stale_dashboard_forms_return_to_the_activity_without_mutation(): void
    {
        $parent = $this->user();
        $entry = $this->register($parent, 1);
        $path = '/parent/interests/'.$entry->id;
        $dashboard = '/parent?activity='.$this->activity->id;
        $input = ['expected_version' => 1, 'expected_activity_version' => 1, 'return_to_dashboard' => 1];

        $this->actingAs($parent)->post($path.'/transfer', [...$input, 'trial_session' => 2, 'transfer_confirm' => 1])
            ->assertRedirect($dashboard)->assertSessionHasErrors('registration');
        $this->get($dashboard)->assertOk()->assertSee('Activity details changed');
        $this->assertSame(1, $entry->fresh()->trial_session);
        app(ParentInterests::class)->change($parent, $entry->id, 'withdraw', ['expected_version' => 1]);
        $this->post($path.'/restore', [...$input, 'expected_version' => 2, 'interest_terms' => 1])
            ->assertRedirect($dashboard)->assertSessionHasErrors('registration');
        $this->assertSame('withdrawn', $entry->fresh()->status);

        $holder = $this->user();
        $seat = $this->register($holder, 2);
        $offer = app(ParentInterests::class)->register($parent, $this->activity->id, [
            'child_name' => 'Waitlisted child', 'child_age' => 8, 'trial_session' => 2, 'expected_activity_version' => 2,
        ], true);
        app(ParentInterests::class)->change($holder, $seat->id, 'withdraw', ['expected_version' => 1]);
        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->travel(24)->hours();
        $this->post('/parent/interests/'.$offer->id.'/waitlist', [
            ...$input, 'decision' => 'accept', 'expected_version' => 2, 'expected_activity_version' => 3,
        ])->assertRedirect($dashboard)->assertSessionHasErrors('registration');
        $this->get($dashboard)->assertOk()->assertSee('This seat offer has expired or is unavailable.')
            ->assertDontSee('Accept seat offer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame('offered', $offer->fresh()->status);
        $this->assertDatabaseMissing('interest_events', ['interest_id' => $offer->id, 'action' => 'offer_accepted']);
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

    public function test_stale_provider_forms_return_to_trial_management_without_changes(): void
    {
        $path = '/provider/activities/'.$this->activity->id;
        $input = ['trial_session' => 1, 'expected_activity_version' => 1, 'return_to_listing' => 1];
        $this->actingAs($this->provider)->post($path.'/reschedule', [
            ...$input, 'trial_date' => '2026-12-13', 'trial_time' => '15:30', 'reschedule_confirm' => 1,
        ])->assertRedirect($path.'#trial-management')->assertSessionHasErrors('listing');
        $this->get($path)->assertOk()->assertSee('Activity changed or is closed')->assertSee('2026-12-10 09:00');
        $this->post($path.'/offer', $input)->assertRedirect($path.'#trial-management')->assertSessionHasErrors('listing');
        $this->postJson($path.'/offer', $input)->assertStatus(409);
        $this->assertSame(2, $this->activity->fresh()->version);
        $this->assertDatabaseCount('activity_events', 0);
        $this->assertDatabaseCount('notification_outbox', 0);
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

    public function test_reschedule_then_cancel_updates_dashboards_and_scopes_notices(): void
    {
        $affected = $this->user();
        $unaffected = $this->user();
        $this->register($affected, 1);
        $this->register($unaffected, 2);
        $providerPath = '/provider/activities/'.$this->activity->id;
        $adminPath = '/admin/activities/'.$this->activity->id;
        $this->actingAs($this->provider)->post($providerPath.'/reschedule', [
            'trial_session' => 1, 'trial_date' => '2026-12-13', 'trial_time' => '15:30',
            'expected_activity_version' => 2, 'reschedule_confirm' => 1,
        ])->assertRedirect();
        $this->actingAs($affected)->get('/parent')->assertOk()->assertSee('2026-12-13 15:30');
        $this->get('/notifications')->assertOk()->assertSee('Trial rescheduled');
        $this->actingAs($this->user('admin'))->post($adminPath.'/lifecycle', [
            'action' => 'cancel_admin', 'reason' => 'Private cancellation reason',
            'expected_version' => 3, 'trial_session' => 1,
        ])->assertRedirect();
        $this->get($adminPath)->assertOk()->assertSee('Cancelled by Admin');
        $this->get('/admin/audit')->assertOk()->assertSee('Private cancellation reason');
        $this->actingAs($this->provider)->get($providerPath)->assertOk()->assertSee('Cancelled by Admin');
        $this->get('/notifications')->assertOk()->assertSee('Trial cancelled')->assertSee('Trial rescheduled')
            ->assertSee($providerPath.'#trial-management');
        $this->actingAs($affected)->get('/parent')->assertOk()->assertSee('Trial cancelled')
            ->assertDontSee('Interest registered')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/notifications')->assertOk()->assertSee('Trial cancelled')->assertDontSee('Private cancellation reason');
        $this->actingAs($unaffected)->get('/parent')->assertOk()->assertSee('Interest registered')->assertDontSee('Trial cancelled');
        $this->get('/notifications')->assertOk()->assertDontSee('Trial cancelled')->assertDontSee('Trial rescheduled');
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

    public function test_guest_can_sign_in_and_return_to_register_or_join_waitlist(): void
    {
        $this->register($this->user(), 1);
        $parent = $this->user();
        $path = '/activities/'.$this->activity->id;
        $this->get('/activities?q=Junior')->assertOk()->assertSee($path)->assertSee('Waitlist also open');
        $this->get($path)->assertOk()->assertSee('/account?activity='.$this->activity->id);
        $this->get('/account?activity='.$this->activity->id)->assertOk()
            ->assertSee('name="activity" value="'.$this->activity->id.'"', false)
            ->assertSee('/register?activity='.$this->activity->id);
        $this->from('/account?activity='.$this->activity->id)->post('/login', [
            'email' => $parent->email, 'password' => 'incorrect', 'activity' => $this->activity->id,
        ])->assertSessionHasErrors('email');
        $this->post('/login', [
            'email' => $parent->email, 'password' => 'local test password!', 'activity' => $this->activity->id,
        ])->assertRedirect($path.'#trial');
        $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $input = ['child_age' => 8, 'expected_activity_version' => 2, 'interest_terms' => 1];
        $this->post($path.'/interest', [...$input, 'child_name' => 'Open session child', 'trial_session' => 2])->assertRedirect('/parent');
        $this->post($path.'/interest', [...$input, 'child_name' => 'Waitlist child', 'trial_session' => 1, 'waitlist' => 1])->assertRedirect('/parent');
        $this->get('/parent')->assertOk()->assertSee('Open session child')->assertSee('Interest registered')
            ->assertSee('Waitlist child')->assertSee('On waitlist');
    }

    public function test_signup_can_return_to_activity_but_invalid_context_uses_dashboard(): void
    {
        $path = '/activities/'.$this->activity->id;
        $signup = ['name' => 'New parent', 'email' => 'new-parent@example.test', 'role' => 'parent',
            'password' => 'local test password!', 'password_confirmation' => 'local test password!'];
        $this->post('/register', [...$signup, 'activity' => $this->activity->id])->assertRedirect($path.'#trial');
        auth()->logout();
        $this->post('/login', [
            'email' => $signup['email'], 'password' => $signup['password'], 'activity' => 'https://example.test',
        ])->assertRedirect('/dashboard');
        auth()->logout();
        $this->activity->update(['status' => 'pending_approval']);
        $this->post('/login', [
            'email' => $signup['email'], 'password' => $signup['password'], 'activity' => $this->activity->id,
        ])->assertRedirect('/dashboard');
    }

    public function test_fifo_offer_reserves_capacity_and_only_owner_can_accept(): void
    {
        $holder = $this->user();
        $seat = $this->register($holder, 1);
        $first = $this->user();
        $second = $this->user();
        $url = '/activities/'.$this->activity->id.'/interest';
        $data = ['child_name' => 'Private waiting child', 'child_age' => 8, 'trial_session' => 1,
            'expected_activity_version' => 2, 'interest_terms' => 1, 'waitlist' => 1];
        $this->actingAs($first)->get('/activities')->assertOk()->assertSee('2 sessions open')->assertSee('Waitlist also open')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/activities/'.$this->activity->id)->assertOk()->assertSee('Join waitlist')
            ->assertSee('data-intent="waitlist"', false)->assertSee('data-intent="register"', false);
        $this->post($url, $data)->assertRedirect('/parent');
        $this->post($url, $data)->assertRedirect('/parent');
        $this->actingAs($second)->post($url, $data)->assertRedirect('/parent');
        $entry = Interest::where('parent_id', $first->id)->sole();
        $this->actingAs($holder)->post('/parent/interests/'.$seat->id.'/withdraw', [
            'expected_version' => 1, 'withdraw_confirm' => 1,
        ])->assertRedirect();
        $this->actingAs($this->user())->post($url, [...$data, 'waitlist' => null])->assertSessionHasErrors('waitlist');
        $direct = $data;
        unset($direct['waitlist']);
        $this->post($url, $direct)->assertStatus(409);
        $offer = '/provider/activities/'.$this->activity->id.'/offer';
        $this->actingAs($this->user('provider'))->post($offer)->assertNotFound();
        $this->actingAs($this->provider)->get('/provider/activities/'.$this->activity->id)->assertOk()->assertSee('Offer next seat');
        $this->post($offer, $data)->assertRedirect();
        $this->post($offer, $data)->assertStatus(409);
        $this->assertSame('offered', $entry->fresh()->status);
        $this->assertDatabaseHas('interests', ['parent_id' => $second->id, 'status' => 'waitlisted']);
        $event = InterestEvent::where('action', 'offered')->sole();
        $this->assertEqualsCanonicalizing([$first->id, $this->provider->id], OutboxMessage::where('interest_event_id', $event->id)->pluck('recipient_id')->all());
        $this->assertStringNotContainsString('Private waiting child', OutboxMessage::where('kind', 'provider_offered')->sole()->message);
        $response = '/parent/interests/'.$entry->id.'/waitlist';
        $accept = ['decision' => 'accept', 'expected_version' => 2, 'expected_activity_version' => 3];
        $this->actingAs($second)->post($response, $accept)->assertNotFound();
        $this->actingAs($first)->get('/parent')->assertOk()->assertSee('Accept seat offer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post($response, $accept)->assertRedirect('/parent');
        $this->post($response, $accept)->assertStatus(409);
        $this->assertSame('interested', $entry->fresh()->status);
        $this->assertSame(1, app(ParentInterests::class)->activeInterests($this->activity, 1)->count());
    }

    public function test_expiry_releases_capacity_and_next_offer_audits_expiry_atomically(): void
    {
        $service = app(ParentInterests::class);
        $holder = $this->user();
        $seat = $this->register($holder, 1);
        $first = $this->user();
        $second = $this->user();
        $input = ['child_name' => 'Waiting child', 'child_age' => 8, 'trial_session' => 1, 'expected_activity_version' => 2];
        $entry = $service->register($first, $this->activity->id, $input, true);
        $next = $service->register($second, $this->activity->id, $input, true);
        $service->change($holder, $seat->id, 'withdraw', ['expected_version' => 1]);
        $service->offerNext($this->provider, $this->activity->id, 1, 2);
        $this->assertEquals(now()->addHours(24)->timestamp, $entry->fresh()->offer_expires_at->timestamp);
        $this->travel(24)->hours();
        $this->actingAs($first)->post('/parent/interests/'.$entry->id.'/waitlist', [
            'decision' => 'accept', 'expected_version' => 2, 'expected_activity_version' => 3,
        ])->assertStatus(409);
        $this->assertSame(0, $service->availability($this->activity->fresh(), session: 1)['held']);
        $url = '/provider/activities/'.$this->activity->id.'/offer';
        $request = ['trial_session' => 1, 'expected_activity_version' => 3];
        DB::statement("CREATE TRIGGER block_offer_notice BEFORE INSERT ON notification_outbox WHEN NEW.kind='parent_offered' BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END");
        $this->actingAs($this->provider)->post($url, $request)->assertStatus(500);
        $this->assertSame(3, $this->activity->fresh()->version);
        $this->assertSame('offered', $entry->fresh()->status);
        $this->assertSame('waitlisted', $next->fresh()->status);
        $this->assertDatabaseMissing('interest_events', ['action' => 'offer_expired']);
        DB::statement('DROP TRIGGER block_offer_notice');
        $this->post($url, $request)->assertRedirect();
        $this->assertSame('withdrawn', $entry->fresh()->status);
        $this->assertSame('offered', $next->fresh()->status);
        $this->assertDatabaseHas('interest_events', ['action' => 'offer_expired', 'interest_id' => $entry->id]);
        $this->assertSame([$first->id], OutboxMessage::where('kind', 'parent_offer_expired')->pluck('recipient_id')->all());
    }

    public function test_scheduled_processing_expires_and_offers_once_with_scoped_notices(): void
    {
        $service = app(ParentInterests::class);
        $holder = $this->user();
        $seat = $this->register($holder, 1);
        $first = $this->user();
        $second = $this->user();
        $input = ['child_name' => 'Private waiting child', 'child_age' => 8, 'trial_session' => 1, 'expected_activity_version' => 2];
        $entry = $service->register($first, $this->activity->id, $input, true);
        $next = $service->register($second, $this->activity->id, $input, true);
        $service->change($holder, $seat->id, 'withdraw', ['expected_version' => 1]);

        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->assertSame('offered', $entry->fresh()->status);
        $this->assertSame('waitlisted', $next->fresh()->status);
        $this->assertSame(3, $this->activity->fresh()->version);

        $this->travel(24)->hours();
        DB::statement("CREATE TRIGGER block_automatic_offer BEFORE INSERT ON notification_outbox WHEN NEW.kind='parent_offered' BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END");
        $this->artisan('gotry:process-waitlists')->assertFailed();
        $this->assertSame('offered', $entry->fresh()->status);
        $this->assertSame('waitlisted', $next->fresh()->status);
        $this->assertSame(3, $this->activity->fresh()->version);
        $this->assertDatabaseMissing('interest_events', ['action' => 'offer_expired']);
        DB::statement('DROP TRIGGER block_automatic_offer');

        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->assertSame('withdrawn', $entry->fresh()->status);
        $this->assertSame('offered', $next->fresh()->status);
        $this->assertSame(4, $this->activity->fresh()->version);
        $this->assertSame(1, $service->seatHolds($this->activity, 1)->count());
        $this->assertSame(3, InterestEvent::whereNull('actor_id')->count());
        $this->assertSame([$first->id], OutboxMessage::where('kind', 'parent_offer_expired')->pluck('recipient_id')->all());
        $this->assertEqualsCanonicalizing([$first->id, $second->id], OutboxMessage::where('kind', 'parent_offered')->pluck('recipient_id')->all());
        $this->assertDatabaseMissing('notification_outbox', ['kind' => 'parent_offered', 'recipient_id' => $holder->id]);
        $this->assertStringNotContainsString('Private waiting child', OutboxMessage::where('kind', 'provider_offer_expired')->sole()->message);
        $this->actingAs($this->user('admin'))->get('/admin/audit?type=interest')->assertOk()->assertSee('GoTry automation');
    }

    public function test_parent_sees_scoped_offer_notices_and_decline_and_expiry_outcomes(): void
    {
        $service = app(ParentInterests::class);
        $holder = $this->user();
        $seat = $this->register($holder, 1);
        $first = $this->user();
        $second = $this->user();
        $input = ['child_name' => 'Waiting child', 'child_age' => 8, 'trial_session' => 1, 'expected_activity_version' => 2];
        $entry = $service->register($first, $this->activity->id, $input, true);
        $service->register($second, $this->activity->id, $input, true);
        $service->change($holder, $seat->id, 'withdraw', ['expected_version' => 1]);
        $this->artisan('gotry:process-waitlists')->assertSuccessful();

        $this->actingAs($second)->get('/notifications')->assertOk()->assertDontSee('Review seat offer');
        $this->actingAs($first)->get('/notifications')->assertOk()->assertSee('Review seat offer')
            ->assertSee('href="'.route('parent.dashboard', ['activity' => $this->activity->id]).'"', false);
        $this->get('/parent?activity='.$this->activity->id)->assertOk()->assertSee('Accept seat offer')->assertSee('All registrations');
        $this->get('/parent?activity=999999')->assertOk()->assertSee('No registrations for this activity')
            ->assertDontSee('Accept seat offer');
        $this->get('/parent')->assertOk()->assertSee('Accept by')->assertSee('Decline offer');
        $this->post('/parent/interests/'.$entry->id.'/waitlist', [
            'decision' => 'leave', 'expected_version' => 2, 'expected_activity_version' => 3,
        ])->assertRedirect('/parent');
        $this->get('/parent')->assertOk()->assertSee('Offer declined')->assertDontSee('Accept seat offer');
        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->actingAs($second)->get('/notifications')->assertOk()->assertSee('Review seat offer');
        $this->travel(24)->hours();
        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->get('/notifications')->assertOk()->assertSee('Seat offer expired');
        $this->get('/parent')->assertOk()->assertSee('Offer expired')->assertDontSee('Accept seat offer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_provider_suspension_pauses_registration_and_waitlist_promotion_until_reactivated(): void
    {
        $service = app(ParentInterests::class);
        $holder = $this->user();
        $first = $this->user();
        $second = $this->user();
        $admin = $this->user('admin');
        $seat = $this->register($holder, 1);
        $input = ['child_name' => 'Waiting child', 'child_age' => 8, 'trial_session' => 1, 'expected_activity_version' => 2];
        $offer = $service->register($first, $this->activity->id, $input, true);
        $next = $service->register($second, $this->activity->id, $input, true);
        $service->change($holder, $seat->id, 'withdraw', ['expected_version' => 1]);
        $this->artisan('gotry:process-waitlists')->assertSuccessful();

        $access = '/admin/users/'.$this->provider->id.'/access';
        $path = '/activities/'.$this->activity->id;
        $this->actingAs($admin)->post($access, [
            'action' => 'suspend', 'expected_version' => 1, 'reason' => 'Private review reason',
        ])->assertRedirect();
        $this->actingAs($first)->get('/activities')->assertOk()->assertDontSee('Junior Music');
        $this->get($path)->assertOk()->assertSee('No trial sessions are accepting interest.')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/parent')->assertOk()->assertSee('The provider is currently unavailable')->assertDontSee('Accept seat offer');
        $this->post($path.'/interest', [...$input, 'child_name' => 'New child', 'trial_session' => 2,
            'expected_activity_version' => 3, 'interest_terms' => 1])->assertStatus(409);
        $this->post($path.'/interest', [...$input, 'child_name' => 'New waitlist child', 'waitlist' => 1,
            'expected_activity_version' => 3, 'interest_terms' => 1])->assertStatus(409);
        $this->post('/parent/interests/'.$offer->id.'/waitlist', [
            'decision' => 'accept', 'expected_version' => 2, 'expected_activity_version' => 3,
        ])->assertStatus(409);
        $this->travel(24)->hours();
        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->assertSame('withdrawn', $offer->fresh()->status);
        $this->assertSame('waitlisted', $next->fresh()->status);
        $this->get('/notifications')->assertOk()->assertSee('Seat offer expired')->assertDontSee('Private review reason');

        $this->actingAs($admin)->post($access, [
            'action' => 'activate', 'expected_version' => 2, 'reason' => 'Review complete',
        ])->assertRedirect();
        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->actingAs($second)->get('/activities')->assertOk()->assertSee('Junior Music');
        $this->get('/notifications')->assertOk()->assertSee('Review seat offer')->assertDontSee('Seat offer expired');
        $this->get('/parent')->assertOk()->assertSee('Accept seat offer')->assertDontSee('The provider is currently unavailable');
        $this->post('/parent/interests/'.$next->id.'/waitlist', [
            'decision' => 'accept', 'expected_version' => 2, 'expected_activity_version' => 4,
        ])->assertRedirect('/parent');
        $this->assertSame('interested', $next->fresh()->status);
    }

    public function test_processing_expires_existing_offers_without_refilling_closed_sessions(): void
    {
        $service = app(ParentInterests::class);
        $holder = $this->user();
        $seat = $this->register($holder, 1);
        $input = ['child_name' => 'Waiting child', 'child_age' => 8, 'trial_session' => 1, 'expected_activity_version' => 2];
        $entry = $service->register($this->user(), $this->activity->id, $input, true);
        $next = $service->register($this->user(), $this->activity->id, $input, true);
        $service->change($holder, $seat->id, 'withdraw', ['expected_version' => 1]);
        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->activity->update(['status' => 'cancelled_by_admin']);
        $this->travel(24)->hours();
        $this->artisan('gotry:process-waitlists')->assertSuccessful();
        $this->assertSame('withdrawn', $entry->fresh()->status);
        $this->assertSame('waitlisted', $next->fresh()->status);
        $this->assertSame(1, OutboxMessage::where('kind', 'parent_offered')->count());
    }
}
