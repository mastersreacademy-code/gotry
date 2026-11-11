<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Models\User;
use App\Services\ActivityDraft;
use App\Services\ProgrammePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProviderActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-10 12:00:00', 'Australia/Sydney'));
    }

    private function user(string $role = 'provider'): User
    {
        return User::create(['name' => 'Test User', 'email' => Str::uuid().'@example.test', 'password' => 'long test password!', 'role' => $role]);
    }

    private function steps(): array
    {
        $date = now('Australia/Sydney')->addMonths(2)->format('Y-m-d');
        return [
            1 => ['business_name' => 'Junior Soccer Academy', 'contact_name' => 'Private Contact', 'contact_email' => 'private-contact@example.test', 'phone' => '0412345678'],
            2 => ['activity_name' => 'Junior Soccer Skills', 'category' => 'Sports & Fitness', 'min_age' => 6, 'max_age' => 10, 'description' => 'Learn teamwork and ball skills.', 'venue' => 'North Sydney', 'bring' => 'Water bottle'],
            3 => ['trial_type' => 'group', 'trial_date' => $date, 'trial_time' => '09:00', 'trial_min' => 8, 'trial_max' => 12, 'trial_duration' => 45, 'deadline' => $date],
            4 => ['regular_format' => 'group', 'multiple_days' => '0', 'regular_day' => 'Saturday', 'regular_time' => '09:00', 'regular_min' => 8, 'regular_standard' => 10, 'regular_max' => 12, 'standard_price' => '20', 'small_group_price' => '25', 'lessons_per_term' => 10, 'discount_type' => 'percent', 'discount_value' => '10', 'discount_applies' => 'term'],
        ];
    }

    private function draft(User $provider, ?array $steps = null): string
    {
        $this->actingAs($provider)->get('/provider/new')->assertRedirect('/provider/new/1');
        foreach ($steps ?? $this->steps() as $step => $data) {
            $this->get('/provider/new/'.$step)->assertOk();
            $this->post('/provider/new/'.$step, $data)->assertSessionHasNoErrors()->assertRedirect('/provider/new/'.($step + 1));
        }
        $this->get('/provider/new/5')->assertOk()->assertSee('Review your listing')->assertSee('$180.00');
        return session('provider_draft.'.$provider->id.'.token');
    }

    private function submit(string $token): array
    {
        return ['submission_token' => $token, 'child_safety' => 1, 'insurance' => 1, 'honour_terms' => 1];
    }

    public function test_provider_completes_wizard_submits_once_and_only_owner_sees_details(): void
    {
        $provider = $this->user();
        $token = $this->draft($provider);
        $this->post('/provider/submit', $this->submit($token))->assertRedirect('/provider/activities/1');
        $this->post('/provider/submit', $this->submit($token))->assertRedirect('/provider/activities/1');
        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseCount('activity_events', 1);
        $activity = Activity::firstOrFail();
        $this->assertSame('pending_approval', $activity->status);
        $this->assertTrue($activity->details['declarations']['child_safety']);
        $this->assertNull(session('provider_draft.'.$provider->id));
        $this->get('/provider/activities/1')->assertOk()->assertSee('Pending Approval');
        $this->get('/activities/1')->assertNotFound();
        $this->actingAs($this->user())->get('/provider/activities/1')->assertNotFound();
        $this->actingAs($this->user('parent'))->get('/provider/new')->assertForbidden();
    }

    public function test_provider_submits_multiple_trial_times_and_admin_approves_the_listing(): void
    {
        $provider = $this->user();
        $steps = $this->steps();
        $steps[3]['extra_trial_times'] = "2027-01-11 10:30\r\n2027-01-12 11:30";
        $token = $this->draft($provider, $steps);
        $this->get('/provider/new/5')->assertSee('2027-01-12 11:30');
        $this->post('/provider/submit', $this->submit($token))->assertRedirect('/provider/activities/1');
        $this->assertSame("2027-01-11 10:30\n2027-01-12 11:30", Activity::firstOrFail()->details['extra_trial_times']);
        $this->get('/provider/activities/1')->assertOk()->assertSee('Group trial sessions')->assertSee('2027-01-11 10:30');
        $this->actingAs($this->user('admin'))->get('/admin/activities/1')->assertOk()->assertSee('2027-01-12 11:30');
        $this->post('/admin/activities/1/decision', ['action' => 'approve', 'expected_version' => 1])->assertRedirect();
        $this->get('/activities/1')->assertOk()->assertSee('2027-01-12 11:30')->assertDontSee('private-contact@example.test')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->actingAs($this->user('parent'))->post('/activities/1/interest', [
            'child_name' => 'Private trial participant', 'child_age' => 8, 'trial_session' => 2,
            'expected_activity_version' => 2, 'interest_terms' => 1,
        ])->assertRedirect('/parent');
        $this->actingAs($provider)->post('/provider/activities/1/reschedule', [
            'trial_session' => 2, 'trial_date' => '2027-01-11', 'trial_time' => '11:30',
            'expected_activity_version' => 2, 'reschedule_confirm' => 1,
        ])->assertRedirect();
        $this->get('/provider/activities/1')->assertOk()->assertSeeInOrder(['2027-01-11 11:30', '1 active interests'])
            ->assertDontSee('Private trial participant');
    }

    public function test_provider_can_monitor_single_trial_interest_after_admin_approval(): void
    {
        $provider = $this->user();
        $token = $this->draft($provider);
        $this->post('/provider/submit', $this->submit($token))->assertRedirect('/provider/activities/1');
        $activity = Activity::sole();
        $this->get('/provider/activities/'.$activity->id)->assertOk()->assertSee('active registrations');

        $this->actingAs($this->user('admin'))->post('/admin/activities/'.$activity->id.'/decision', [
            'action' => 'approve', 'expected_version' => 1,
        ])->assertRedirect();
        $this->actingAs($this->user('parent'))->post('/activities/'.$activity->id.'/interest', [
            'child_name' => 'Private child', 'child_age' => 8, 'expected_activity_version' => 2, 'interest_terms' => 1,
        ])->assertRedirect('/parent');
        $this->actingAs($provider)->get('/provider/activities/'.$activity->id)->assertOk()
            ->assertSee('active registration')->assertSeeInOrder(['Trial interest', '1', 'active registration'])
            ->assertDontSee('Private child')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_steps_cannot_be_skipped_and_invalid_input_is_not_saved(): void
    {
        $provider = $this->user();
        $this->actingAs($provider)->get('/provider/new/5')->assertRedirect('/provider/new/1');
        $this->post('/provider/new/4', $this->steps()[4])->assertRedirect('/provider/new');
        $this->post('/provider/new/1', ['business_name' => 'Incomplete'])->assertSessionHasErrors(['phone','contact_email']);
        $this->assertSame([], session('provider_draft.'.$provider->id.'.data'));
        $this->post('/provider/submit', $this->submit((string) Str::uuid()))->assertStatus(409);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_all_declarations_are_required_and_expired_draft_is_rechecked(): void
    {
        $provider = $this->user();
        $token = $this->draft($provider);
        $this->post('/provider/submit', ['submission_token' => $token])->assertSessionHasErrors(['child_safety','insurance','honour_terms']);
        $this->travel(100)->days();
        $this->post('/provider/submit', $this->submit($token))->assertSessionHasErrors('trial_date');
        $this->assertDatabaseCount('activities', 0);
        $this->assertDatabaseCount('activity_events', 0);
    }

    public function test_individual_trial_regular_group_and_multiple_times_are_independent(): void
    {
        $schema = new ActivityDraft;
        $input = [...$this->steps()[3], 'trial_type' => 'individual', 'trial_availability' => 'Weekday afternoons'];
        $trial = $schema->validate(3, $input);
        $this->assertArrayNotHasKey('trial_date', $trial);
        $this->assertArrayNotHasKey('trial_min', $trial);
        $this->assertSame('Weekday afternoons', $trial['trial_availability']);
        $regular = $schema->validate(4, [...$this->steps()[4], 'multiple_days' => '1', 'class_times' => 'Monday 16:00; Saturday 09:00']);
        $this->assertArrayNotHasKey('regular_day', $regular);
        $this->assertSame('Monday 16:00; Saturday 09:00', $regular['class_times']);
        $regular = $schema->validate(4, [...$this->steps()[4], 'regular_format' => 'individual', 'usual_availability' => 'After school', 'individual_price' => '60', 'discount_type' => 'fixed', 'discount_value' => '10', 'discount_applies' => 'lesson']);
        $this->assertArrayNotHasKey('standard_price', $regular);
        $this->assertSame(5000, (new ProgrammePricing)->preview($regular)[0]['total']);
    }

    public function test_schema_rejects_invalid_dates_group_sizes_prices_and_discounts(): void
    {
        $cases = [
            [2, [...$this->steps()[2], 'max_age' => 5], 'max_age'],
            [3, [...$this->steps()[3], 'trial_max' => 7], 'trial_max'],
            [3, [...$this->steps()[3], 'extra_trial_times' => $this->steps()[3]['trial_date'].' 09:00'], 'extra_trial_times'],
            [3, [...$this->steps()[3], 'extra_trial_times' => '2027-01-09 09:00'], 'extra_trial_times'],
            [3, [...$this->steps()[3], 'extra_trial_times' => '2027-04-04 02:30'], 'extra_trial_times'],
            [3, [...$this->steps()[3], 'extra_trial_times' => '2027-01-32 09:00'], 'extra_trial_times'],
            [3, [...$this->steps()[3], 'deadline' => '2099-12-31'], 'deadline'],
            [3, [...$this->steps()[3], 'trial_date' => '2027-10-03', 'trial_time' => '02:30', 'deadline' => '2027-10-01'], 'trial_time'],
            [3, [...$this->steps()[3], 'trial_date' => '2027-04-04', 'trial_time' => '02:30', 'deadline' => '2027-04-01'], 'trial_time'],
            [4, [...$this->steps()[4], 'regular_standard' => 13], 'regular_max'],
            [4, [...$this->steps()[4], 'standard_price' => '20.001'], 'standard_price'],
            [4, [...$this->steps()[4], 'discount_value' => '101'], 'discount_value'],
            [4, [...$this->steps()[4], 'discount_type' => 'fixed', 'discount_value' => '201'], 'discount_value'],
        ];
        foreach ($cases as [$step, $input, $field]) {
            try { (new ActivityDraft)->validate($step, $input); $this->fail('Accepted invalid '.$field); }
            catch (ValidationException $exception) { $this->assertArrayHasKey($field, $exception->errors()); }
        }
    }

    public function test_price_previews_match_percentage_fixed_and_first_lesson_examples(): void
    {
        $pricing = new ProgrammePricing;
        $data = $this->steps()[4];
        $this->assertSame([18000,22500], array_column($pricing->preview($data), 'total'));
        $this->assertSame([19000,24000], array_column($pricing->preview([...$data, 'discount_type' => 'fixed', 'discount_value' => '10']), 'total'));
        $this->assertSame([19800,24750], array_column($pricing->preview([...$data, 'discount_applies' => 'lesson']), 'total'));
        $this->assertSame(30, $pricing->preview([...$data, 'standard_price' => '0.03', 'small_group_price' => '0.03', 'discount_value' => '0.01'])[0]['total']);
    }

    public function test_approval_and_rejection_are_audited_and_public_pages_do_not_leak_contacts(): void
    {
        $provider = $this->user();
        $token = $this->draft($provider);
        $this->post('/provider/submit', $this->submit($token));
        $this->post('/admin/activities/1/decision', ['action' => 'approve', 'expected_version' => 1])->assertForbidden();
        $this->actingAs($this->user('admin'));
        $this->get('/admin')->assertOk()->assertSee('Junior Soccer Skills');
        $this->get('/admin/activities/1')->assertOk()->assertSee('private-contact@example.test');
        $this->post('/admin/activities/1/decision', ['action' => 'reject', 'expected_version' => 1])->assertSessionHasErrors('reason');
        $this->post('/admin/activities/1/decision', ['action' => 'approve', 'expected_version' => 2])->assertStatus(409);
        $this->post('/admin/activities/1/decision', ['action' => 'approve', 'expected_version' => 1])->assertRedirect();
        $this->post('/admin/activities/1/decision', ['action' => 'approve', 'expected_version' => 1])->assertStatus(409);
        $this->assertDatabaseCount('activity_events', 2);
        $this->get('/activities/1')->assertOk()->assertSee('$180.00')->assertDontSee('private-contact@example.test')->assertDontSee('Private Contact')->assertDontSee('0412345678');
        Activity::firstOrFail()->update(['status' => 'pending_approval', 'version' => 3]);
        $this->post('/admin/activities/1/decision', ['action' => 'reject', 'expected_version' => 3, 'reason' => 'Please confirm venue permission.'])->assertRedirect();
        $this->get('/activities/1')->assertNotFound();
        $this->actingAs($provider)->get('/provider/activities/1')->assertOk()->assertSee('Please confirm venue permission.');
    }

    public function test_audit_failure_rolls_back_activity_submission(): void
    {
        $provider = $this->user();
        $token = $this->draft($provider);
        DB::statement("CREATE TRIGGER block_event BEFORE INSERT ON activity_events BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->post('/provider/submit', $this->submit($token))->assertStatus(500);
        $this->assertDatabaseCount('activities', 0);
        $this->assertDatabaseCount('activity_events', 0);
        DB::statement('DROP TRIGGER block_event');
        $this->post('/provider/submit', $this->submit($token))->assertRedirect();
        $this->assertDatabaseCount('activities', 1);
    }

    public function test_switching_formats_removes_stale_fields_and_drafts_are_account_scoped(): void
    {
        $provider = $this->user();
        $token = $this->draft($provider);
        $trial = [...$this->steps()[3], 'trial_type' => 'individual', 'trial_availability' => 'Arrange with parent'];
        $this->post('/provider/new/3', $trial)->assertSessionHasNoErrors();
        $regular = [...$this->steps()[4], 'regular_format' => 'individual', 'usual_availability' => 'After school', 'individual_price' => 60, 'discount_type' => 'none'];
        $this->post('/provider/new/4', $regular)->assertSessionHasNoErrors();
        $data = session('provider_draft.'.$provider->id.'.data');
        foreach (['trial_date','trial_min','regular_day','standard_price','discount_value','discount_applies'] as $key) $this->assertArrayNotHasKey($key, $data);
        $this->get('/provider/new/5')->assertOk()->assertSee('$60.00');
        $other = $this->user();
        $this->actingAs($other)->get('/provider/new/1')->assertOk()->assertDontSee('Junior Soccer Academy');
        $this->post('/provider/submit', $this->submit($token))->assertStatus(409);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_admin_audit_failure_rolls_back_status_change(): void
    {
        $token = $this->draft($this->user());
        $this->post('/provider/submit', $this->submit($token));
        $this->actingAs($this->user('admin'));
        DB::statement("CREATE TRIGGER block_review_event BEFORE INSERT ON activity_events BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->post('/admin/activities/1/decision', ['action' => 'approve', 'expected_version' => 1])->assertStatus(500);
        $this->assertSame('pending_approval', Activity::firstOrFail()->status);
        $this->assertSame(1, Activity::firstOrFail()->version);
        $this->assertDatabaseCount('activity_events', 1);
        DB::statement('DROP TRIGGER block_review_event');
        $this->post('/admin/activities/1/decision', ['action' => 'approve', 'expected_version' => 1])->assertRedirect();
        $this->assertSame('pending_registrations', Activity::firstOrFail()->status);
    }

    public function test_provider_pagination_and_public_listing_keep_ownership_and_status_filters(): void
    {
        $provider = $this->user();
        $details = array_merge(...array_values($this->steps()));
        for ($index = 1; $index <= 21; $index++) Activity::create([
            'provider_id' => $provider->id, 'submission_token' => (string) Str::uuid(), 'title' => 'Private listing '.$index,
            'category' => 'Music', 'status' => 'pending_approval', 'details' => $details, 'submitted_at' => now(),
        ]);
        $this->actingAs($provider)->get('/provider')->assertOk()->assertSee('Page 1 of 2');
        $this->get('/provider?page=2')->assertOk()->assertSee('Page 2 of 2')->assertSee('Private listing 1');
        $this->actingAs($this->user())->get('/provider')->assertOk()->assertDontSee('Private listing');
        Activity::whereKey(1)->update(['status' => 'pending_registrations', 'title' => 'Approved public listing']);
        $this->get('/activities')->assertOk()->assertSee('Approved public listing')->assertDontSee('Private listing');
        auth()->logout();
        $this->get('/activities?q=Approved&category=Music')->assertOk()->assertSee('Approved public listing')
            ->assertDontSee('Private listing')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/activities?q=Private&category=Music')->assertOk()->assertSee('No activities match these filters.')
            ->assertDontSee('Private listing');
        $this->get('/activities?category=Dance')->assertOk()->assertDontSee('Approved public listing');

    }
}
