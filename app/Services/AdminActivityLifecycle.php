<?php
namespace App\Services;
use App\Models\{Activity, ActivityEvent, Interest, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Validator};

class AdminActivityLifecycle
{
    public function change(User $admin, int $id, array $input): Activity
    {
        abort_unless($admin->role === 'admin' && $admin->is_active,403);
        $data = Validator::make($input,['action'=>['required','in:confirm_minimum,cancel_low_numbers,cancel_admin'],
            'expected_version'=>['required','integer','min:1'],'reason'=>['required_if:action,cancel_admin','nullable','string','max:1000']])->validate();
        return DB::transaction(function () use ($admin,$id,$data) {
            $activity = Activity::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($activity->version === (int)$data['expected_version'] && in_array($activity->status,['pending_registrations','proceeding_pending_payments'],true),409,'Activity changed. Refresh before making a decision.');
            $count = Interest::where('activity_id',$id)->where('status','interested')->count();
            $details = $activity->details; $action = $data['action'];
            if ($action !== 'cancel_admin') {
                abort_unless($activity->status === 'pending_registrations' && ($details['trial_type'] ?? '') === 'group',422,'This action requires a group trial collecting registrations.');
                $minimum = (int)($details['trial_min'] ?? 0);
                abort_unless($minimum >= 1 && $minimum <= 100,409,'Review the trial group minimum first.');
                if ($action === 'confirm_minimum') {
                    abort_unless($count >= $minimum,409,'The trial has not reached its minimum interest.');
                    abort_unless(User::whereKey($activity->provider_id)->where('is_active',true)->exists(),409,'The provider account is suspended.');
                    try { $start = CarbonImmutable::createFromFormat('!Y-m-d H:i',($details['trial_date'] ?? '').' '.($details['trial_time'] ?? ''),'Australia/Sydney'); }
                    catch (\Throwable) { abort(409,'Review the trial schedule first.'); }
                    abort_unless($start->isFuture(),409,'The trial has already started.');
                } else {
                    abort_unless($count < $minimum && ($details['deadline'] ?? '9999-12-31') < CarbonImmutable::now('Australia/Sydney')->toDateString(),409,'Low-number cancellation requires the deadline to have passed below minimum.');
                }
            }
            $next = match($action) {'confirm_minimum'=>'proceeding_pending_payments','cancel_low_numbers'=>'cancelled_low_numbers',default=>'cancelled_by_admin'};
            $before = $activity->status;
            $reason = $data['reason'] ?? null;
            $activity->update(['status'=>$next,'version'=>$activity->version+1,'review_reason'=>$reason]);
            $event = ActivityEvent::create(['activity_id'=>$id,'actor_id'=>$admin->id,'action'=>$action,'from_status'=>$before,'to_status'=>$next,'reason'=>$reason]);
            app(NotificationOutbox::class)->activity($event,$activity);
            return $activity;
        },5);
    }
}
