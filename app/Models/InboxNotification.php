<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxNotification extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','outbox_id','recipient_id','activity_id','kind','title','message','read_at','created_at'];

    protected function casts(): array
    {
        return ['recipient_id' => 'integer', 'activity_id' => 'integer', 'read_at' => 'datetime'];
    }

    public function destination(): string
    {
        return str_starts_with($this->kind, 'parent_') ? route('parent.dashboard')
            : route('provider.activity', ['activity' => $this->activity_id]);
    }
}
