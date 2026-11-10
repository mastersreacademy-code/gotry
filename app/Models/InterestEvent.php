<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterestEvent extends Model
{
    protected $fillable = ['interest_id', 'actor_id', 'action', 'from_status', 'to_status', 'version'];
}
