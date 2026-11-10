<?php
namespace App\Services;
use App\Models\{AccountEvent, User};
use Illuminate\Support\Facades\{DB, Validator};

class AdminAccounts
{
    public function access(User $actor, int $targetId, array $input): User
    {
        $data = Validator::make($input,['action'=>['required','in:activate,suspend'],'expected_version'=>['required','integer','min:1'],'reason'=>['required','string','max:1000']])->validate();
        return DB::transaction(function () use ($actor,$targetId,$data) {
            // Lock all admin identities in one order: concurrent admins cannot suspend each other out of service.
            $admins = User::where('role','admin')->orderBy('id')->lockForUpdate()->get();
            $freshActor = $admins->firstWhere('id',$actor->id);
            abort_unless($freshActor && $freshActor->is_active,403);
            $target = User::whereKey($targetId)->lockForUpdate()->firstOrFail();
            abort_unless($target->access_version === (int)$data['expected_version'],409,'This account changed. Refresh and try again.');
            $active = $data['action'] === 'activate';
            if ($target->is_active === $active) return $target;
            abort_if(! $active && $target->id === $actor->id,422,'You cannot suspend your own admin account.');
            abort_if(! $active && $target->role === 'admin' && $admins->where('is_active',true)->count() <= 1,422,'At least one active super admin is required.');
            $before = $target->is_active;
            $target->forceFill(['is_active'=>$active,'access_version'=>$target->access_version+1])->save();
            if (! $active) DB::table('sessions')->where('user_id',$target->id)->delete();
            AccountEvent::create(['user_id'=>$target->id,'actor_id'=>$actor->id,'from_active'=>$before,'to_active'=>$active,'version'=>$target->access_version,'reason'=>$data['reason']]);
            return $target;
        },5);
    }
}
