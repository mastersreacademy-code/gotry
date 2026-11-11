<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interest extends Model
{
    protected $fillable = ['activity_id', 'trial_session', 'parent_id', 'child_name', 'child_key', 'child_age', 'status', 'version', 'activity_version', 'withdrawn_at'];
    protected $hidden = ['child_key'];

    protected function casts(): array
    {
        return ['activity_id' => 'integer', 'trial_session' => 'integer', 'parent_id' => 'integer', 'child_age' => 'integer',
            'version' => 'integer', 'activity_version' => 'integer', 'withdrawn_at' => 'datetime'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
