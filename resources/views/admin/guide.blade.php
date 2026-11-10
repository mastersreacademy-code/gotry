@extends('layouts.app')
@section('title','Super admin guide · GoTry')
@section('content')
@include('admin.nav')<h1>Super admin guide</h1>
<section class="panel"><h2>Review and publish</h2><p>Open Activities, filter Pending Approval and review business, trial, regular lesson pricing and provider declarations. Approve only after checking the details. Rejection requires feedback for the provider.</p>
<h2>Manage group trials</h2><p>Once enough parents have registered, mark a future group trial as proceeding. This records minimum interest only; it does not charge parents or mark them paid. Below-minimum cancellation is available after the deadline. Admin cancellation requires a reason.</p><p>If a later withdrawal drops the group below minimum, the activity returns to registration review. Review it again before proceeding.</p>
<h2>Individual trials</h2><p>Individual trials collect enquiries. Group-minimum confirmation does not apply; providers arrange individual availability. Session appointment management is still being developed.</p>
<h2>Accounts and privacy</h2><p>Use Accounts to suspend or restore access. You cannot suspend your own admin account. Account roles and ownership are retained. A reason and a matching current version are required for access changes.</p><p>Registration notices to providers are anonymous. The audit history shows who changed a record without copying child names or ages.</p>
<h2>Notifications</h2><p>Updates are queued atomically with the change and audit record. Each user receives their own updates when opening Notifications. No email or push is sent. Queued notices do not mean a delivery failure.</p>
<h2>What remains</h2><p>Payment collection, paid-status reconciliation, multi-session appointments and transfers are not available. Parent/provider users register themselves. Additional admin accounts can be created through the interactive server command <code>php artisan gotry:create-admin</code>.</p></section>
@endsection
