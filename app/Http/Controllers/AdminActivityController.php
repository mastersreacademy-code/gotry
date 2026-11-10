<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Services\ActivityDraft;
use App\Services\ProgrammePricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminActivityController
{
    public function index()
    {
        return view('admin.activities', ['activities' => Activity::orderByDesc('id')->paginate(30)]);
    }

    public function show(Activity $activity, ActivityDraft $schema, ProgrammePricing $pricing, ProviderActivityController $provider)
    {
        return view('admin.review', ['activity' => $activity, 'review' => $provider->review($schema, $activity->details), 'prices' => $pricing->preview($activity->details)]);
    }

    public function decide(Request $request, Activity $activity)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve','reject'])],
            'expected_version' => ['required','integer','min:1'],
            'reason' => ['required_if:action,reject','nullable','string','max:1000']]);
        DB::transaction(function () use ($activity, $request, $data): void {
            $locked = Activity::whereKey($activity->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending_approval' && $locked->version === (int) $data['expected_version'], 409, 'This activity has already changed. Refresh before reviewing.');
            $status = $data['action'] === 'approve' ? 'pending_registrations' : 'rejected';
            $reason = $data['action'] === 'reject' ? $data['reason'] : null;
            $locked->update(['status' => $status, 'version' => $locked->version + 1, 'review_reason' => $reason, 'reviewed_at' => now()]);
            ActivityEvent::create(['activity_id' => $locked->id, 'actor_id' => $request->user()->id,
                'action' => $data['action'], 'from_status' => 'pending_approval', 'to_status' => $status, 'reason' => $reason]);
        });
        return redirect()->route('admin.activity', $activity)->with('status', 'Review saved.');
    }
}
