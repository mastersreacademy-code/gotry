<?php
namespace App\Http\Controllers;
use App\Models\{Activity, Interest, OutboxMessage, User};
use App\Services\AdminAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminWorkspaceController
{
    public function index()
    {
        return view('admin.dashboard',['counts'=>[
            'Pending approvals'=>Activity::where('status','pending_approval')->count(),
            'Collecting registrations'=>Activity::where('status','pending_registrations')->count(),
            'Proceeding, pending payments'=>Activity::where('status','proceeding_pending_payments')->count(),
            'Active interests'=>Interest::where('status','interested')->count(),
            'Providers'=>User::where('role','provider')->count(),'Parents'=>User::where('role','parent')->count(),
            'Suspended accounts'=>User::where('is_active',false)->count(),'Queued in-app notices'=>OutboxMessage::whereNull('delivered_at')->count()],
            'activities'=>Activity::with('provider')->where('status','pending_approval')->latest('id')->limit(5)->get()]);
    }
    public function users(Request $request)
    {
        $data=$request->validate(['q'=>['nullable','string','max:160'],'role'=>['nullable',Rule::in(['admin','provider','parent'])],'access'=>['nullable',Rule::in(['active','suspended'])]]);
        $query=User::orderByDesc('id');
        if(!empty($data['q'])) $query->where(fn($q)=>$q->where('name','like','%'.$data['q'].'%')->orWhere('email','like','%'.$data['q'].'%'));
        if(!empty($data['role'])) $query->where('role',$data['role']);
        if(!empty($data['access'])) $query->where('is_active',$data['access']==='active');
        return view('admin.users',['users'=>$query->paginate(20)->withQueryString()]);
    }
    public function access(Request $request, User $user, AdminAccounts $service)
    {
        $service->access($request->user(),$user->id,$request->all());
        return redirect()->route('admin.users')->with('status','Account access updated.');
    }
    public function audit(Request $request)
    {
        $data=$request->validate(['type'=>['nullable',Rule::in(['activity','interest','account'])]]); $type=$data['type']??'activity';
        $query=match($type) {
            'interest'=>DB::table('interest_events as e')->join('interests as i','i.id','=','e.interest_id')->join('activities as a','a.id','=','i.activity_id')
                ->select('e.id','e.action','e.from_status','e.to_status','e.created_at','e.actor_id','a.title as subject','i.trial_session','e.schedule_change','e.offer_expires_at',DB::raw('NULL as reason')),
            'account'=>DB::table('account_events as e')->join('users as target','target.id','=','e.user_id')
                ->select('e.id','e.created_at','e.actor_id','e.reason','target.email as subject',DB::raw("CASE WHEN e.to_active=1 THEN 'activated' ELSE 'suspended' END as action"),DB::raw("CASE WHEN e.from_active=1 THEN 'active' ELSE 'suspended' END as from_status"),DB::raw("CASE WHEN e.to_active=1 THEN 'active' ELSE 'suspended' END as to_status")),
            default=>DB::table('activity_events as e')->join('activities as a','a.id','=','e.activity_id')
                ->select('e.id','e.action','e.from_status','e.to_status','e.created_at','e.actor_id','e.reason','a.title as subject','e.trial_session','e.schedule_change'),
        };
        $events=$query->join('users as actor','actor.id','=','e.actor_id')->addSelect('actor.email as actor')->orderByDesc('e.id')->paginate(30)->withQueryString();
        return view('admin.audit',compact('events','type'));
    }
}
