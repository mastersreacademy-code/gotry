<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Interest;
use App\Services\ParentInterests;
use Illuminate\Http\Request;

class ParentInterestController
{
    public function index(Request $request, ParentInterests $service)
    {
        $interests = Interest::where('parent_id', $request->user()->id)->with('activity')->latest('id')->paginate(20);
        $availability = [];
        foreach ($interests as $interest) $availability[$interest->activity_id] ??= $service->availability($interest->activity);
        return view('parent.interests', compact('interests', 'availability'));
    }

    public function store(Request $request, Activity $activity, ParentInterests $service)
    {
        $request->validate(['interest_terms' => ['accepted']]);
        $service->register($request->user(), $activity->id, $request->all());
        return redirect()->route('parent.dashboard')->with('status', 'Interest registered. This is not a confirmed booking or payment request.');
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
