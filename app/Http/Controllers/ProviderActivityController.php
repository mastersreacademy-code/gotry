<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Services\ActivityDraft;
use App\Services\ParentInterests;
use App\Services\ProviderTrialRescheduling;
use App\Services\ProgrammePricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProviderActivityController
{
    private function key(Request $request): string
    {
        return 'provider_draft.'.$request->user()->id;
    }

    public function index(Request $request)
    {
        return view('provider.index', ['activities' => Activity::where('provider_id', $request->user()->id)->latest('id')->paginate(20)]);
    }

    public function create(Request $request, ActivityDraft $schema, ProgrammePricing $pricing, ?int $step = null)
    {
        $key = $this->key($request);
        if (! $request->session()->has($key)) {
            $request->session()->put($key, ['token' => (string) Str::uuid(), 'completed' => 0, 'data' => []]);
        }
        $draft = $request->session()->get($key);
        $maximum = min(5, $draft['completed'] + 1);
        if ($step === null || $step > $maximum) return redirect()->route('provider.create', ['step' => $maximum]);
        $data = array_replace(['trial_type' => 'group', 'trial_duration' => 45, 'regular_format' => 'group',
            'multiple_days' => '0', 'regular_day' => 'Saturday', 'regular_time' => '09:00', 'lessons_per_term' => 10,
            'discount_type' => 'none', 'discount_applies' => 'term'], $draft['data']);
        return view('provider.wizard', ['step' => $step, 'steps' => ActivityDraft::STEPS, 'fields' => $schema->fields($step),
            'data' => $data, 'token' => $draft['token'], 'prices' => $step === 5 ? $pricing->preview($draft['data']) : [],
            'review' => $step === 5 ? $this->review($schema, $draft['data']) : []]);
    }

    public function save(Request $request, ActivityDraft $schema, int $step)
    {
        $key = $this->key($request);
        $draft = $request->session()->get($key);
        if (! $draft || $step > $draft['completed'] + 1) return redirect()->route('provider.create');
        $validated = $schema->validate($step, $request->all());
        // Remove obsolete values when switching group/individual or schedule/discount types.
        foreach ($schema->fields($step) as $field) unset($draft['data'][$field[0]]);
        $draft['data'] = array_replace($draft['data'], $validated);
        $draft['completed'] = max($draft['completed'], $step);
        $request->session()->put($key, $draft);
        return redirect()->route('provider.create', ['step' => $step + 1]);
    }

    public function submit(Request $request, ActivityDraft $schema)
    {
        $request->validate(['submission_token' => ['required', 'uuid']]);
        $token = $request->string('submission_token')->toString();
        $existing = Activity::where('provider_id', $request->user()->id)->where('submission_token', $token)->first();
        if ($existing) return redirect()->route('provider.activity', $existing);
        $key = $this->key($request);
        $draft = $request->session()->get($key);
        abort_unless($draft && $draft['completed'] === 4 && hash_equals($draft['token'], $token), 409, 'Your draft changed. Reopen the form and review it again.');
        $request->validate(['child_safety' => ['accepted'], 'insurance' => ['accepted'], 'honour_terms' => ['accepted']]);
        // Recheck every step: dates can expire while the draft is left open.
        $data = [];
        for ($step = 1; $step <= 4; $step++) $data = array_replace($data, $schema->validate($step, $draft['data']));
        $activity = DB::transaction(function () use ($request, $token, $data) {
            $activity = Activity::firstOrCreate(['provider_id' => $request->user()->id, 'submission_token' => $token], [
                'title' => $data['activity_name'], 'category' => $data['category'], 'status' => 'pending_approval',
                'details' => [...$data, 'declarations' => ['child_safety' => true, 'insurance' => true, 'honour_terms' => true, 'accepted_at' => now()->toIso8601String()]],
                'submitted_at' => now(),
            ]);
            if ($activity->wasRecentlyCreated) ActivityEvent::create(['activity_id' => $activity->id,
                'actor_id' => $request->user()->id, 'action' => 'submitted', 'to_status' => 'pending_approval']);
            return $activity;
        });
        $request->session()->forget($key);
        return redirect()->route('provider.activity', $activity)->with('status', 'Submitted for approval.');
    }

    public function show(Request $request, Activity $activity, ActivityDraft $schema, ProgrammePricing $pricing, ParentInterests $interests)
    {
        abort_unless($activity->provider_id === $request->user()->id, 404);
        return view('provider.show', ['activity' => $activity, 'sessions' => $interests->sessionSummary($activity), 'review' => $this->review($schema, $activity->details), 'prices' => $pricing->preview($activity->details)]);
    }

    public function reschedule(Request $request, Activity $activity, ProviderTrialRescheduling $service)
    {
        abort_unless($activity->provider_id === $request->user()->id, 404);
        $request->validate(['reschedule_confirm' => ['accepted']]);
        $service->reschedule($request->user(), $activity->id, $request->all());
        return redirect()->route('provider.activity', $activity)->with('status', 'Trial rescheduled. Affected parents will see an in-app notice.');
    }

    public function review(ActivityDraft $schema, array $data): array
    {
        $sections = [];
        for ($step = 1; $step <= 4; $step++) {
            foreach ($schema->fields($step) as $field) {
                [$name, $label] = $field;
                $options = $field[3] ?? [];
                // Labels/options come from our schema; all values are escaped by Blade.
                if (! isset($data[$name]) || $data[$name] === '') continue;
                $sections[ActivityDraft::STEPS[$step]][$label] = $options[$data[$name]] ?? $data[$name];
            }
        }
        return $sections;
    }
}
