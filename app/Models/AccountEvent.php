<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AccountEvent extends Model
{
    protected $fillable = ['user_id','actor_id','from_active','to_active','version','reason'];
}
