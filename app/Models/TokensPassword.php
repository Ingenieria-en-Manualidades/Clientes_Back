<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokensPassword extends Model
{
    use HasFactory;

    protected $fillable = ['id_username','username', 'email', 'token', 'expires_at'];
}
