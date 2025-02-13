<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TokensPassword extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tokens_passwords';
    protected $primaryKey = 'id';
    
    protected $fillable = ['id_username','username', 'email', 'token', 'expires_at'];
}
