<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Interest;
use App\Models\InterestEvent;
use App\Models\User;
use App\Services\ParentInterests;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParentInterestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00', 'Australia/Sydney'));
        config(['hashing.bcrypt.rounds' => 4]);
    }

    private function user(string $role = 'parent'): User
    {
        return User::create(['name' => 'Test Parent', 'email' => Str::uuid().'@example.test', 'password' => 'long test password!', 'role' => $role]);
    }

    private function activity(array $details = [], array $attributes = []): Activity
    {
        return Activity::create([...[
            'provider_id' => $this->user('provider')->id, 'submission_token' => Str::uuid(), 'title' => 'Junior Music', 'category' => 'Music',
            'status' => 'pending_registrations', 'version' => 2, 'submitted_at' => now(),
            'details' => [...[
                'description' => 'Discover rhythm.', 'venue' => 'Sydney', 'min_age' => 6, 'max_age' => 10,
                'trial_type' => 'group', 'trial_date' => '2026-12-10', 'trial_time' => '09:00', 'trial_duration' => 45, 'trial_min' => 1, 'trial_max' => 2, 'deadline' => '2026-12-09',
                'regular_format' => 'individual', 'usual_availability' => 'After school', 'individual_price' => '60', 'discount_type' => 'none',
            ], ...$details],
        ], ...$attributes]);
    }

    private function registration(string $name = 'Ha Nhu', int $age = 8): array
    {
        return ['child_name' => $name, 'child_age' => $age, 'expected_activity_version' => 2, 'interest_terms' => 1];
    }

    private function register(User $parent, Activity $activity, string $name = 'Ha Nhu'): Interest
    {
        $this->actingAs($parent)->post('/activities/'.$activity->id.'/interest', $this->registration($name))->assertRedirect('/parent');
        return Interest::where('parent_id', $parent->id)->where('activity_id', $activity->id)->latest('id')->firstOrFail();
    }

    public function test_dashboard_records_only_a_verified_sandbox_capture_and_replays_safely(): void
    {
        $parent = $this->user();
        $activity = $this->activity();
        $interest = $this->register($parent, $activity);
        $activity->update(['status' => 'proceeding_pending_payments']);
        config(['paypal.client_id' => 'sandbox-client', 'paypal.client_secret' => 'sandbox-secret']);

        $url = '/parent/interests/'.$interest->id.'/payment';
        $approval = 'https://www.sandbox.paypal.com/checkoutnow?token=TESTORDER';
        $api = 'https://api-m.sandbox.paypal.com';
        $currency = 'USD';
        Http::preventStrayRequests();
        Http::fake([
            $api.'/v1/oauth2/token' => Http::response(['access_token' => 'sandbox-token']),
            $api.'/v2/checkout/orders' => Http::response([
                'id' => 'TESTORDER',
                'links' => [['rel' => 'payer-action', 'href' => $approval]],
            ]),
            $api.'/v2/checkout/orders/TESTORDER/capture' => function () use (&$currency, $interest) {
                return Http::response([
                    'id' => 'TESTORDER', 'status' => 'COMPLETED',
                    'purchase_units' => [[
                        'reference_id' => DB::table('trial_payments')->where('interest_id', $interest->id)->value('attempt'),
                        'payments' => ['captures' => [[
                            'id' => 'TESTCAPTURE', 'status' => 'COMPLETED',
                            'amount' => ['currency_code' => $currency, 'value' => '2.00'],
                        ]]],
                    ]],
                ]);
            },
        ]);

        $this->get('/parent')->assertSee('Pay AUD 2.00 with PayPal Sandbox')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post($url)->assertRedirect($approval);
        $attempt = DB::table('trial_payments')->where('interest_id', $interest->id)->value('attempt');
        $this->get($url.'/return?token=OTHERORDER')->assertNotFound();
        $this->get($url.'/return?token=TESTORDER')->assertRedirect('/parent');
        $this->get('/parent')->assertSee('Confirm AUD 2.00 Sandbox payment');
        $this->actingAs($this->user())->post($url.'/capture', ['attempt' => $attempt])->assertNotFound();
        $this->actingAs($parent)->post($url.'/capture', ['attempt' => $attempt])->assertSessionHasErrors('payment');
        $this->assertDatabaseHas('trial_payments', ['interest_id' => $interest->id, 'paid_at' => null]);
        $currency = 'AUD';
        $this->post($url.'/capture', ['attempt' => $attempt])->assertRedirect('/parent');
        $this->post($url.'/capture', ['attempt' => $attempt])->assertRedirect('/parent');
        Http::assertSentCount(6);
        $this->assertDatabaseHas('trial_payments', ['interest_id' => $interest->id, 'capture_id' => 'TESTCAPTURE']);
        $this->get('/parent')->assertSee('Paid · AUD 2.00 · PayPal Sandbox')->assertDontSee('Pay AUD 2.00 with PayPal Sandbox');
    }

    public function test_registers_interest_and_retries_normalized_child_name_without_duplicate_audit(): void
    {
        $parent = $this->user(); $activity = $this->activity();
        $interest = $this->register($parent, $activity, '  Ha   Nhu  ');
        $this->post('/activities/'.$activity->id.'/interest', [...$this->registration('ha nhu'), 'parent_id' => 999, 'status' => 'paid'])->assertRedirect('/parent');
        $this->assertDatabaseCount('interests', 1);
        $this->assertDatabaseCount('interest_events', 1);
        $this->assertSame('Ha Nhu', $interest->child_name);
        $this->assertSame($parent->id, $interest->parent_id);
        $this->assertSame('interested', $interest->status);
        $this->assertSame(1, $interest->version);
        $this->assertDatabaseHas('interest_events', ['interest_id' => $interest->id, 'actor_id' => $parent->id, 'action' => 'registered', 'version' => 1]);
        $this->get('/parent')->assertOk()->assertSee('Ha Nhu')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_group_capacity_is_enforced_across_accounts_and_existing_retry_still_succeeds(): void
    {
        $activity = $this->activity(); $first = $this->user();
        $this->register($first, $activity);
        $this->register($this->user(), $activity, 'Ha My');
        $this->actingAs($this->user())->post('/activities/'.$activity->id.'/interest', $this->registration('Third child'))->assertStatus(409);
        $this->actingAs($first)->post('/activities/'.$activity->id.'/interest', $this->registration())->assertRedirect('/parent');
        $this->assertDatabaseCount('interests', 2);
        $this->assertDatabaseCount('interest_events', 2);
        $this->get('/activities/'.$activity->id)->assertOk()->assertSee('0 places remaining')->assertSee('This trial is full');
    }

    public function test_multi_session_capacity_duplicate_child_and_restoration_use_the_original_slot(): void
    {
        $activity = $this->activity(['trial_max' => 1, 'extra_trial_times' => '2026-12-11 10:00']);
        $url = '/activities/'.$activity->id.'/interest';
        $first = $this->user();
        $second = $this->user();
        $firstSlot = [...$this->registration('Private Alpha'), 'trial_session' => 1];
        $secondSlot = [...$this->registration('Private Beta'), 'trial_session' => 2];
        $this->actingAs($first)->get('/activities/'.$activity->id)->assertOk()->assertSee('Choose a session');
        $this->post($url, $this->registration())->assertStatus(409);
        $this->post($url, [...$firstSlot, 'trial_session' => 3])->assertStatus(409);
        $this->post($url, $firstSlot)->assertRedirect();
        $this->post($url, $firstSlot)->assertRedirect();
        $this->post($url, [...$firstSlot, 'trial_session' => 2])->assertStatus(409);
        $interest = Interest::firstOrFail();
        $this->actingAs($second)->post($url, [...$secondSlot, 'trial_session' => 1])->assertStatus(409);
        $this->post($url, $secondSlot)->assertRedirect();
        $this->get('/parent')->assertOk()->assertSee('2026-12-11 10:00')->assertDontSee('Private Alpha');
        $this->post('/parent/interests/'.$interest->id.'/withdraw', ['withdraw_confirm' => 1, 'expected_version' => 1])->assertNotFound();
        $this->actingAs($first)->post('/parent/interests/'.$interest->id.'/withdraw', ['withdraw_confirm' => 1, 'expected_version' => 1])->assertRedirect();
        $restore = ['interest_terms' => 1, 'expected_version' => 2, 'expected_activity_version' => 2, 'trial_session' => 2];
        $this->post('/parent/interests/'.$interest->id.'/restore', $restore)->assertRedirect();
        $this->post('/parent/interests/'.$interest->id.'/restore', $restore)->assertRedirect();
        $this->assertSame(1, $interest->fresh()->trial_session);
        $this->assertDatabaseCount('interests', 2);
        $this->assertDatabaseCount('interest_events', 4);
        $this->assertDatabaseCount('notification_outbox', 8);
        $notices = \App\Models\OutboxMessage::where('recipient_id', $activity->provider_id)->get();
        $this->assertStringContainsString('2026-12-10 09:00', $notices->firstWhere('kind', 'provider_restored')->message);
        foreach ($notices as $notice) {
            $this->assertStringNotContainsString('Private Alpha', $notice->message);
            $this->assertStringNotContainsString('Private Beta', $notice->message);
        }
    }

    public function test_session_confirmation_and_minimum_loss_notify_only_the_affected_session_atomically(): void
    {
        $activity = $this->activity(['trial_min' => 2, 'extra_trial_times' => '2026-12-11 10:00']);
        $a = $this->user();
        $b = $this->user();
        $c = $this->user();
        $service = app(ParentInterests::class);
        $interest = $service->register($a, $activity->id, [...$this->registration('First child'), 'trial_session' => 1]);
        $service->register($c, $activity->id, [...$this->registration('Other session'), 'trial_session' => 2]);
        $url = '/admin/activities/'.$activity->id.'/lifecycle';
        $admin = $this->user('admin');
        $confirm = ['action' => 'confirm_minimum', 'expected_version' => 2, 'trial_session' => 1];
        $this->actingAs($admin)->post($url, $confirm)->assertStatus(409);
        $this->post($url, [...$confirm, 'trial_session' => null])->assertStatus(422);
        $service->register($b, $activity->id, [...$this->registration('Second child'), 'trial_session' => 1]);
        $this->post($url, $confirm)->assertRedirect();
        $this->post($url, $confirm)->assertStatus(409);
        $this->assertDatabaseHas('activity_events', ['trial_session' => 1, 'action' => 'confirm_minimum']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], \App\Models\OutboxMessage::where('kind', 'parent_trial_proceeding')->pluck('recipient_id')->all());
        $this->get('/admin/audit')->assertOk()->assertSee('Trial session 1');
        $this->get('/admin/activities/'.$activity->id)->assertOk()->assertSee('Proceeding');
        $this->assertSame('pending_registrations', $activity->fresh()->status);
        $withdraw = '/parent/interests/'.$interest->id.'/withdraw';
        DB::statement("CREATE TRIGGER fail_session_notice BEFORE INSERT ON notification_outbox WHEN NEW.kind='parent_trial_review' BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END");
        $this->actingAs($a)->post($withdraw, ['withdraw_confirm' => 1, 'expected_version' => 1])->assertStatus(500);
        $this->assertSame('interested', $interest->fresh()->status);
        $this->assertSame(3, $activity->fresh()->version);
        $this->assertDatabaseCount('activity_events', 1);
        $this->assertDatabaseCount('interest_events', 3);
        $this->assertDatabaseCount('notification_outbox', 9);
        DB::statement('DROP TRIGGER fail_session_notice');
        $this->post($withdraw, ['withdraw_confirm' => 1, 'expected_version' => 1])->assertRedirect();
        $this->assertSame('pending_registrations', \App\Services\TrialSchedule::status($activity->fresh(), 1));
        $this->assertDatabaseHas('activity_events', ['trial_session' => 1, 'action' => 'minimum_lost']);
        $this->assertSame([$b->id], \App\Models\OutboxMessage::where('kind', 'parent_trial_review')->pluck('recipient_id')->all());
        $this->actingAs($admin)->post($url, ['action' => 'cancel_admin', 'reason' => 'Venue closed', 'expected_version' => 4, 'trial_session' => 1])->assertRedirect();
        $this->assertSame([$b->id], \App\Models\OutboxMessage::where('kind', 'parent_trial_cancelled')->pluck('recipient_id')->all());
        $this->actingAs($c)->get('/parent')->assertOk()->assertSee('2026-12-11 10:00')->assertDontSee('Cancelled by Admin');
        $this->actingAs($admin)->post($url, ['action' => 'cancel_admin', 'reason' => 'Venue closed', 'expected_version' => 5])->assertRedirect();
        $this->assertEqualsCanonicalizing([$b->id, $c->id], \App\Models\OutboxMessage::where('kind', 'parent_trial_cancelled')->pluck('recipient_id')->all());
    }

    public function test_individual_trials_collect_separate_enquiries_without_a_shared_one_child_limit(): void
    {
        $activity = $this->activity(['trial_type' => 'individual', 'trial_availability' => 'Weekdays']);
        foreach (['Child A','Child B','Child C'] as $name) $this->register($this->user(), $activity, $name);
        $this->assertDatabaseCount('interests', 3);
        $availability = (new ParentInterests)->availability($activity);
        $this->assertNull($availability['capacity']);
        $this->assertTrue($availability['open']);
    }

    public function test_withdraw_and_restore_are_versioned_idempotent_and_old_forms_cannot_undo_a_restore(): void
    {
        $parent = $this->user(); $activity = $this->activity();
        $interest = $this->register($parent, $activity);
        $url = '/parent/interests/'.$interest->id;
        $withdraw = ['expected_version' => 1, 'withdraw_confirm' => 1];
        $this->post($url.'/withdraw', $withdraw)->assertRedirect('/parent');
        $this->post($url.'/withdraw', $withdraw)->assertRedirect('/parent');
        $this->assertSame(2, $interest->fresh()->version);
        $this->assertNotNull($interest->fresh()->withdrawn_at);
        $this->post('/activities/'.$activity->id.'/interest', $this->registration())->assertStatus(409);
        $restore = ['expected_version' => 2, 'expected_activity_version' => 2, 'interest_terms' => 1];
        $this->post($url.'/restore', $restore)->assertRedirect('/parent');
        $this->post($url.'/restore', $restore)->assertRedirect('/parent');
        $this->post($url.'/withdraw', $withdraw)->assertStatus(409);
        $this->assertSame('interested', $interest->fresh()->status);
        $this->assertSame(3, $interest->fresh()->version);
        $this->assertNull($interest->fresh()->withdrawn_at);
        $this->assertSame(['registered','withdrawn','restored'], InterestEvent::orderBy('id')->pluck('action')->all());
    }

    public function test_withdrawal_frees_one_seat_and_restore_cannot_overbook_it(): void
    {
        $activity = $this->activity(['trial_max' => 1]); $parent = $this->user();
        $interest = $this->register($parent, $activity);
        $this->post('/parent/interests/'.$interest->id.'/withdraw', ['expected_version' => 1, 'withdraw_confirm' => 1])->assertRedirect();
        $other = $this->user(); $otherInterest = $this->register($other, $activity, 'Other child');
        $this->actingAs($parent)->post('/parent/interests/'.$interest->id.'/restore', ['expected_version' => 2, 'expected_activity_version' => 2, 'interest_terms' => 1])->assertStatus(409);
        $this->assertSame('withdrawn', $interest->fresh()->status);
        $this->actingAs($other)->post('/parent/interests/'.$otherInterest->id.'/withdraw', ['expected_version' => 1, 'withdraw_confirm' => 1]);
        $this->actingAs($parent)->post('/parent/interests/'.$interest->id.'/restore', ['expected_version' => 2, 'expected_activity_version' => 2, 'interest_terms' => 1])->assertRedirect();
        $this->assertSame(1, Interest::where('status', 'interested')->count());
    }

    public function test_parent_cannot_see_or_mutate_another_parents_registration(): void
    {
        $activity = $this->activity(); $owner = $this->user();
        $interest = $this->register($owner, $activity, 'Private Child Alpha');
        $this->actingAs($this->user());
        $this->get('/parent')->assertOk()->assertDontSee('Private Child Alpha');
        $this->get('/activities/'.$activity->id)->assertOk()->assertDontSee('Private Child Alpha');
        $this->post('/parent/interests/'.$interest->id.'/withdraw', ['expected_version' => 1, 'withdraw_confirm' => 1])->assertNotFound();
        $this->post('/parent/interests/'.$interest->id.'/restore', [])->assertNotFound();
        $this->assertSame('interested', $interest->fresh()->status);
        $this->assertDatabaseCount('interest_events', 1);
        $this->actingAs($this->user('provider'))->get('/provider/activities/'.$activity->id)->assertNotFound();
        $this->actingAs(User::findOrFail($activity->provider_id))->get('/provider/activities/'.$activity->id)->assertOk()->assertDontSee('Private Child Alpha');
        auth()->logout();
        $this->get('/activities/'.$activity->id)->assertOk()->assertDontSee('Private Child Alpha');
    }

    public function test_roles_validation_terms_age_and_overposted_fields_are_enforced(): void
    {
        $activity = $this->activity();
        foreach (['provider','admin'] as $role) $this->actingAs($this->user($role))->post('/activities/'.$activity->id.'/interest', $this->registration())->assertForbidden();
        $this->actingAs($this->user());
        $this->post('/activities/'.$activity->id.'/interest', [...$this->registration(), 'interest_terms' => 0])->assertSessionHasErrors('interest_terms');
        $this->post('/activities/'.$activity->id.'/interest', [...$this->registration(), 'child_age' => '8.5'])->assertSessionHasErrors('child_age');
        $this->post('/activities/'.$activity->id.'/interest', $this->registration('Too old', 11))->assertStatus(422);
        $this->post('/activities/'.$activity->id.'/interest', $this->registration('Too young', 5))->assertStatus(422);
        $this->post('/activities/'.$activity->id.'/interest', $this->registration('   '))->assertSessionHasErrors('child_name');
        $this->assertDatabaseCount('interests', 0);
    }

    public function test_registration_checks_approval_deadline_start_time_and_activity_version(): void
    {
        $this->actingAs($this->user());
        $cases = [
            [$this->activity([], ['status' => 'pending_approval']), 404],
            [$this->activity([], ['status' => 'rejected']), 404],
            [$this->activity(['deadline' => '2026-11-09']), 409],
            [$this->activity(['trial_date' => '2026-11-10', 'trial_time' => '09:00', 'deadline' => '2026-11-10']), 409],
            [$this->activity(['trial_max' => 0]), 409],
            [$this->activity([], ['version' => 3]), 409],
        ];
        foreach ($cases as [$activity, $status]) $this->post('/activities/'.$activity->id.'/interest', $this->registration())->assertStatus($status);
        $this->assertDatabaseCount('interests', 0);
        $today = $this->activity(['trial_date' => '2026-11-10', 'trial_time' => '18:00', 'deadline' => '2026-11-10']);
        $this->post('/activities/'.$today->id.'/interest', $this->registration())->assertRedirect('/parent');
    }

    public function test_withdraw_is_available_after_closure_but_restoration_rechecks_current_rules(): void
    {
        $activity = $this->activity(); $interest = $this->register($this->user(), $activity);
        $activity->update(['status' => 'cancelled_by_provider', 'version' => 3]);
        $url = '/parent/interests/'.$interest->id;
        $this->post($url.'/withdraw', ['expected_version' => 1, 'withdraw_confirm' => 1])->assertRedirect();
        $this->post($url.'/restore', ['expected_version' => 2, 'expected_activity_version' => 3, 'interest_terms' => 1])->assertNotFound();
        $activity->update(['status' => 'pending_registrations']);
        $this->post($url.'/restore', ['expected_version' => 2, 'expected_activity_version' => 2, 'interest_terms' => 1])->assertStatus(409);
        $activity->update(['details' => [...$activity->details, 'min_age' => 9]]);
        $this->post($url.'/restore', ['expected_version' => 2, 'expected_activity_version' => 3, 'interest_terms' => 1])->assertStatus(422);
        $activity->update(['details' => [...$activity->details, 'min_age' => 6, 'deadline' => '2026-11-09']]);
        $this->post($url.'/restore', ['expected_version' => 2, 'expected_activity_version' => 3, 'interest_terms' => 1])->assertStatus(409);
        $this->assertSame('withdrawn', $interest->fresh()->status);
    }

    public function test_missing_confirmation_or_csrf_cannot_mutate_interest(): void
    {
        $activity = $this->activity(); $interest = $this->register($this->user(), $activity);
        $this->post('/parent/interests/'.$interest->id.'/withdraw', ['expected_version' => 1])->assertSessionHasErrors('withdraw_confirm');
        $this->app->instance('env', 'local');
        $this->post('/parent/interests/'.$interest->id.'/withdraw', ['expected_version' => 1, 'withdraw_confirm' => 1])->assertStatus(419);
        $this->assertSame('interested', $interest->fresh()->status);
    }

    public function test_audit_failure_rolls_back_create_withdraw_and_restore(): void
    {
        $activity = $this->activity(); $this->actingAs($this->user());
        $block = fn () => DB::statement("CREATE TRIGGER block_interest_audit BEFORE INSERT ON interest_events BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $unblock = fn () => DB::statement('DROP TRIGGER block_interest_audit');
        $block();
        $this->post('/activities/'.$activity->id.'/interest', $this->registration())->assertStatus(500);
        $this->assertDatabaseCount('interests', 0); $this->assertDatabaseCount('interest_events', 0);
        $unblock();
        $this->post('/activities/'.$activity->id.'/interest', $this->registration())->assertRedirect();
        $interest = Interest::firstOrFail(); $url = '/parent/interests/'.$interest->id;
        $block();
        $this->post($url.'/withdraw', ['expected_version' => 1, 'withdraw_confirm' => 1])->assertStatus(500);
        $this->assertSame('interested', $interest->fresh()->status); $this->assertSame(1, $interest->fresh()->version);
        $unblock();
        $this->post($url.'/withdraw', ['expected_version' => 1, 'withdraw_confirm' => 1]);
        $block();
        $this->post($url.'/restore', ['expected_version' => 2, 'expected_activity_version' => 2, 'interest_terms' => 1])->assertStatus(500);
        $this->assertSame('withdrawn', $interest->fresh()->status); $this->assertSame(2, $interest->fresh()->version);
        $this->assertDatabaseCount('interest_events', 2);
        $unblock();
    }

    public function test_write_throttling_does_not_duplicate_records_and_child_names_are_escaped(): void
    {
        $activity = $this->activity(); $this->actingAs($this->user());
        $input = $this->registration('<script>alert(1)</script>');
        for ($attempt = 0; $attempt < 30; $attempt++) $this->post('/activities/'.$activity->id.'/interest', $input)->assertRedirect('/parent');
        $this->post('/activities/'.$activity->id.'/interest', $input)->assertStatus(429);
        $this->assertDatabaseCount('interests', 1); $this->assertDatabaseCount('interest_events', 1);
        $this->get('/parent')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_deployment_check_does_not_treat_sqlite_as_verified_innodb(): void
    {
        $this->artisan('gotry:check-database')->expectsOutput('Deployment requires MySQL/MariaDB with InnoDB. SQLite is for local tests.')->assertExitCode(1);
    }
}
