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
