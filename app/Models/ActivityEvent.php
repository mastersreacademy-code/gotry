<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityEvent extends Model
{
    protected $fillable = ['activity_id', 'trial_session', 'actor_id', 'action', 'from_status', 'to_status', 'reason'];
}
