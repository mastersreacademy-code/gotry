<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    public const STATUSES = ['pending_approval'=>'Pending Approval','rejected'=>'Rejected','pending_registrations'=>'Pending Registrations',
        'proceeding_pending_payments'=>'Proceeding – Pending Payments','cancelled_low_numbers'=>'Cancelled – Low Numbers','cancelled_by_admin'=>'Cancelled by Admin'];
    protected $fillable = ['provider_id', 'submission_token', 'title', 'category', 'status', 'version', 'details', 'submitted_at', 'reviewed_at', 'review_reason'];

    protected function casts(): array
    {
        return ['details' => 'array', 'provider_id' => 'integer', 'version' => 'integer', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_',' ',$this->status));
    }
    public function provider(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class,'provider_id'); }
}
