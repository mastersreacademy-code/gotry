<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SoccerDetailPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00', 'Australia/Sydney'));
    }

    private function activity(array $details = []): Activity
    {
        $provider = User::create([
            'name' => 'Soccer Provider',
            'email' => Str::uuid().'@example.test',
            'password' => 'test password',
            'role' => 'provider',
        ]);

        return Activity::create([
            'provider_id' => $provider->id,
            'submission_token' => Str::uuid(),
            'title' => 'Junior Soccer',
            'category' => 'Sports & Fitness',
            'status' => 'pending_registrations',
            'version' => 2,
            'submitted_at' => now(),
            'details' => array_replace([
                'description' => 'Introductory soccer',
                'venue' => 'Sydney',
                'min_age' => 6,
                'max_age' => 10,
                'trial_type' => 'group',
                'trial_date' => '2026-12-10',
                'trial_time' => '09:00',
                'trial_duration' => 45,
                'trial_min' => 1,
                'trial_max' => 1,
                'deadline' => '2026-12-09',
                'regular_format' => 'individual',
                'usual_availability' => 'After school',
                'individual_price' => '60',
                'discount_type' => 'none',
            ], $details),
        ]);
    }

    public function test_guest_sees_soccer_cta_deadline_and_sign_in_without_private_form(): void
    {
        $activity = $this->activity();
        $this->get('/activities/'.$activity->id)
            ->assertOk()
            ->assertSee('Junior Soccer')
            ->assertSee('activity-hero-photo', false)
            ->assertSee('href="#trial"', false)
            ->assertSee('data-trial-deadline=', false)
            ->assertSee('data-days', false)
            ->assertSee('data-hours', false)
            ->assertSee('data-minutes', false)
            ->assertSee('Sign in as a parent to continue')
            ->assertDontSee('data-trial-form', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_parent_sees_register_action_and_versioned_interest_form(): void
    {
        $activity = $this->activity();
        $parent = User::create([
            'name' => 'Test Parent',
            'email' => Str::uuid().'@example.test',
            'password' => 'test password',
            'role' => 'parent',
        ]);

        $this->actingAs($parent)->get('/activities/'.$activity->id)
            ->assertOk()
            ->assertSee('data-trial-form', false)
            ->assertSee('name="expected_activity_version"', false)
            ->assertSee('name="interest_terms"', false)
            ->assertSee('data-intent="register"', false)
            ->assertDontSee('data-intent="waitlist"', false);
    }


    public function test_full_session_offers_waitlist_without_register_action(): void
    {
        $activity = $this->activity(['extra_trial_times' => '2026-12-11 10:00']);
        $first = User::create([
            'name' => 'First Parent',
            'email' => Str::uuid().'@example.test',
            'password' => 'test password',
            'role' => 'parent',
        ]);
        $second = User::create([
            'name' => 'Second Parent',
            'email' => Str::uuid().'@example.test',
            'password' => 'test password',
            'role' => 'parent',
        ]);

        $this->actingAs($first)->post('/activities/'.$activity->id.'/interest', [
            'child_name' => 'First Child',
            'child_age' => 8,
            'expected_activity_version' => 2,
            'trial_session' => 1,
            'interest_terms' => 1,
        ])->assertRedirect();

        $this->actingAs($second)->get('/activities/'.$activity->id)
            ->assertOk()
            ->assertSee('data-intent="waitlist"', false)
            ->assertSee('data-intent="register"', false)
            ->assertSee('value="1" data-intent="waitlist"', false)
            ->assertSee('value="2" data-intent="register"', false)
            ->assertSee('href="#trial"', false);
    }

    public function test_expired_deadline_has_no_registration_cta_or_countdown(): void
    {
        $activity = $this->activity(['deadline' => '2026-11-09']);
        $this->get('/activities/'.$activity->id)
            ->assertOk()
            ->assertDontSee('data-trial-deadline=', false)
            ->assertDontSee('href="#trial"', false)
            ->assertSee('Trial availability');
    }
}
