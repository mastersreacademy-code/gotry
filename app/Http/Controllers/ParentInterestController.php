<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Interest;
use App\Services\ParentInterests;
use App\Services\TrialSchedule;
use Illuminate\Http\Request;

class ParentInterestController
{
    public function index(Request $request, ParentInterests $service)
    {
        $activityFilter = filter_var($request->query('activity'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $interests = Interest::where('parent_id', $request->user()->id)
            ->when($activityFilter, fn ($query, $id) => $query->where('activity_id', $id))
            ->with(['activity.provider', 'latestEvent'])
            ->orderByRaw("CASE WHEN status = 'offered' AND offer_expires_at > ? THEN 0 WHEN status IN ('interested', 'waitlisted') THEN 1 ELSE 2 END", [now()])
            ->orderByRaw("CASE WHEN status = 'offered' AND offer_expires_at > ? THEN offer_expires_at END", [now()])
            ->latest('id')->paginate(20)->appends($activityFilter ? ['activity' => $activityFilter] : []);
        $availability = [];
        $openSessions = [];
        foreach ($interests as $interest) {
            $key = $interest->trial_session ?? 0;
            $availability[$interest->activity_id][$key] ??= $service->availability($interest->activity, session: $interest->trial_session);
        }
        foreach ($interests->getCollection()->pluck('activity')->unique('id') as $activity) {
            foreach (TrialSchedule::sessions($activity) as $number => $start) {
                if ($service->availability($activity, session: $number)['open']) $openSessions[$activity->id][$number] = $start;
            }
        }
        return view('parent.interests', compact('interests', 'availability', 'openSessions', 'activityFilter'));
    }

    public function store(Request $request, Activity $activity, ParentInterests $service)
    {
        $request->validate(['interest_terms' => ['accepted']]);
        $request->validate(['waitlist' => ['sometimes', 'in:1']]);
        $waitlist = $request->boolean('waitlist');
        $service->register($request->user(), $activity->id, $request->all(), $waitlist);
        if ($waitlist) return redirect()->route('parent.dashboard')->with('status', 'Joined the session waitlist.');
        return redirect()->route('parent.dashboard')->with('status', 'Interest registered. This is not a confirmed booking or payment request.');
    }

    public function waitlist(Request $request, Interest $interest, ParentInterests $service)
    {
        abort_unless($interest->parent_id === $request->user()->id, 404);
        $data = $request->validate([
            'decision' => ['required', 'in:accept,leave'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'expected_activity_version' => ['required', 'integer', 'min:1'],
        ]);
        $service->respondToWaitlist($request->user(), $interest->id, $data);
        return redirect()->route('parent.dashboard')->with('status', $data['decision'] === 'accept' ? 'Seat offer accepted.' : 'Waitlist entry closed.');
    }

    public function transfer(Request $request, Interest $interest, ParentInterests $service)
    {
        abort_unless($interest->parent_id === $request->user()->id, 404);
        $request->validate(['transfer_confirm' => ['accepted']]);
        $service->transfer($request->user(), $interest->id, $request->all());
        return redirect()->route('parent.dashboard')->with('status', 'Interest moved to the selected session.');
    }

    public function withdraw(Request $request, Interest $interest, ParentInterests $service)
    {
        // Authorize before validation so another account cannot probe registration details.
        abort_unless($interest->parent_id === $request->user()->id, 404);
        $request->validate(['withdraw_confirm' => ['accepted']]);
        $service->change($request->user(), $interest->id, 'withdraw', $request->all());
        return redirect()->route('parent.dashboard')->with('status', 'Registration withdrawn.');
    }

    public function restore(Request $request, Interest $interest, ParentInterests $service)
    {
        abort_unless($interest->parent_id === $request->user()->id, 404);
        $request->validate(['interest_terms' => ['accepted']]);
        $service->change($request->user(), $interest->id, 'restore', $request->all());
        return redirect()->route('parent.dashboard')->with('status', 'Interest restored. This is not a confirmed booking or payment request.');
    }
}
