<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Services\ActivityDraft;
use App\Services\ProgrammePricing;
use App\Services\NotificationOutbox;
use App\Services\AdminActivityLifecycle;
use App\Services\ParentInterests;
use App\Services\TrialSchedule;
use App\Models\User;
use App\Models\Interest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminActivityController
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q'=>['nullable','string','max:160'],'status'=>['nullable',Rule::in(array_keys(Activity::STATUSES))]]);
        $query = Activity::with('provider')->orderByDesc('id');
        if (!empty($filters['q'])) $query->where('title','like','%'.$filters['q'].'%');
        if (!empty($filters['status'])) $query->where('status',$filters['status']);
        return view('admin.activities', ['activities' => $query->paginate(30)->withQueryString()]);
    }

    public function show(Activity $activity, ActivityDraft $schema, ProgrammePricing $pricing, ProviderActivityController $provider, ParentInterests $interests)
    {
        return view('admin.review', ['activity' => $activity, 'sessions' => $interests->sessionSummary($activity), 'review' => $provider->review($schema, $activity->details), 'prices' => $pricing->preview($activity->details),
            'interestCount'=>Interest::where('activity_id',$activity->id)->where('status','interested')->count()]);
    }

    public function decide(Request $request, Activity $activity, NotificationOutbox $outbox)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve','reject'])],
            'expected_version' => ['required','integer','min:1'],
            'reason' => ['required_if:action,reject','nullable','string','max:1000']]);
        DB::transaction(function () use ($activity, $request, $data, $outbox): void {
            $locked = Activity::whereKey($activity->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending_approval' && $locked->version === (int) $data['expected_version'], 409, 'This activity has already changed. Refresh before reviewing.');
            $status = $data['action'] === 'approve' ? 'pending_registrations' : 'rejected';
            if ($data['action'] === 'approve') {
                abort_unless(User::whereKey($locked->provider_id)->where('is_active',true)->exists(),409,'The provider account is suspended.');
                $candidate = clone $locked; $candidate->status='pending_registrations';
                foreach (array_keys(TrialSchedule::sessions($candidate)) ?: [null] as $session) {
                    $available = app(ParentInterests::class)->availability($candidate, session: $session);
                    abort_unless($available['open'], 409, $available['reason'] ?? 'The trial cannot accept registrations.');
                }
            }
            $reason = $data['action'] === 'reject' ? $data['reason'] : null;
            $locked->update(['status' => $status, 'version' => $locked->version + 1, 'review_reason' => $reason, 'reviewed_at' => now()]);
            $event = ActivityEvent::create(['activity_id' => $locked->id, 'actor_id' => $request->user()->id,
                'action' => $data['action'], 'from_status' => 'pending_approval', 'to_status' => $status, 'reason' => $reason]);
            $outbox->activity($event, $locked);
        }, 5);
        return redirect()->route('admin.activity', $activity)->with('status', 'Review saved.');
    }
    public function lifecycle(Request $request, Activity $activity, AdminActivityLifecycle $service)
    {
        $service->change($request->user(),$activity->id,$request->all());
        return redirect()->route('admin.activity',$activity)->with('status','Activity status updated.');
    }
}
