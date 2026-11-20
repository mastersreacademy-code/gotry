<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Child extends Model
{
    protected $fillable = ['parent_id', 'first_name', 'surname', 'age', 'registration_key'];
    protected $hidden = ['registration_key'];

    protected function casts(): array
    {
        return ['parent_id' => 'integer', 'age' => 'integer'];
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->surname);
    }
}
