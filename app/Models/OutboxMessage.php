<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    protected $table = 'notification_outbox';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','recipient_id','activity_id','interest_event_id','activity_event_id','source_key','kind','title','message','delivered_at'];

    protected function casts(): array
    {
        return ['recipient_id' => 'integer', 'activity_id' => 'integer', 'delivered_at' => 'datetime'];
    }
}
