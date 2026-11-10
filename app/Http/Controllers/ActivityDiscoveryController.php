<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\ProgrammePricing;

class ActivityDiscoveryController
{
    public function index()
    {
        return view('activities.index', ['activities' => Activity::where('status', 'pending_registrations')->latest('id')->paginate(20)]);
    }

    public function show(Activity $activity, ProgrammePricing $pricing)
    {
        abort_unless($activity->status === 'pending_registrations', 404);
        // A deliberately explicit public projection: business contact details never enter this view.
        $details = array_intersect_key($activity->details, array_flip(['description','venue','min_age','max_age','bring',
            'trial_type','trial_date','trial_time','trial_availability','trial_duration','deadline','regular_format',
            'regular_day','regular_time','class_times','usual_availability','regular_min','regular_standard','regular_max','lessons_per_term']));
        return view('activities.show', ['title' => $activity->title, 'category' => $activity->category,
            'details' => $details, 'prices' => $pricing->preview($activity->details)]);
    }
}
