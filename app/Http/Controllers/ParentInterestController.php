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
        $interests = Interest::where('parent_id', $request->user()->id)->with('activity')->latest('id')->paginate(20);
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
        return view('parent.interests', compact('interests', 'availability', 'openSessions'));
    }

    public function store(Request $request, Activity $activity, ParentInterests $service)
    {
        $request->validate(['interest_terms' => ['accepted']]);
        $service->register($request->user(), $activity->id, $request->all());
        return redirect()->route('parent.dashboard')->with('status', 'Interest registered. This is not a confirmed booking or payment request.');
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
