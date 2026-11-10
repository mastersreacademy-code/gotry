<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = ['provider_id', 'submission_token', 'title', 'category', 'status', 'version', 'details', 'submitted_at', 'reviewed_at', 'review_reason'];

    protected function casts(): array
    {
        return ['details' => 'array', 'provider_id' => 'integer', 'version' => 'integer', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_approval' => 'Pending Approval',
            'rejected' => 'Rejected',
            'pending_registrations' => 'Pending Registrations',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
