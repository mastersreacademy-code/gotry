<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\ProgrammePricing;
use App\Services\ParentInterests;
use App\Services\TrialSchedule;
use App\Models\Interest;
use Illuminate\Http\Request;

class ActivityDiscoveryController
{
    public function index()
    {
        return view('activities.index', ['activities' => Activity::where('status', 'pending_registrations')->whereHas('provider',fn($query)=>$query->where('is_active',true))->latest('id')->paginate(20)]);
    }

    public function show(Request $request, Activity $activity, ProgrammePricing $pricing, ParentInterests $interests)
    {
        abort_unless($activity->status === 'pending_registrations', 404);
        // A deliberately explicit public projection: business contact details never enter this view.
        $details = array_intersect_key($activity->details, array_flip(['description','venue','min_age','max_age','bring',
            'trial_type','trial_date','trial_time','trial_min','trial_availability','trial_duration','deadline','regular_format',
            'regular_day','regular_time','class_times','usual_availability','regular_min','regular_standard','regular_max','lessons_per_term']));
        $mine = $request->user()?->role === 'parent' ? Interest::where('activity_id', $activity->id)->where('parent_id', $request->user()->id)->get() : collect();
        $sessions = [];
        foreach (TrialSchedule::sessions($activity) as $number => $start) {
            $sessions[$number] = ['start' => $start, ...$interests->availability($activity, session: $number)];
        }
        return view('activities.show', ['title' => $activity->title, 'category' => $activity->category,
            'activityId' => $activity->id, 'activityVersion' => $activity->version, 'mine' => $mine,
            'sessions' => $sessions, 'availability' => $interests->availability($activity), 'details' => $details, 'prices' => $pricing->preview($activity->details)]);
    }
}
