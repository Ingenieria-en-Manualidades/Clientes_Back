<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{
    use HasFactory;

    protected $table = 'CLIENTS.user_permission';
    protected $primaryKey = 'id';

    protected $fillable = ['user_id', 'permission_id'];
}
