<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterestEvent extends Model
{
    protected $fillable = ['offer_expires_at', 'schedule_change', 'interest_id', 'actor_id', 'action', 'from_status', 'to_status', 'version'];

    protected function casts(): array
    {
        return ['offer_expires_at' => 'datetime', 'schedule_change' => 'array'];
    }
}
