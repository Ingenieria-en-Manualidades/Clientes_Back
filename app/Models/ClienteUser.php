<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class ClienteUser extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cliente_user';
    protected $primaryKey = 'id';

    protected $fillable = ['user_id', 'cliente_id'];
}
