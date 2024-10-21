<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tablero_Sae extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tablero_sae';
    protected $primaryKey = 'tablero_sae_id';

    protected $fillable = ['id_cliente', 'fecha', 'tablero_sae_id'];

    public function objetives()
    {
        return $this->hasMany(Objetive::class, 'tablero_id', 'tablero_id');
    }

    public function files()
    {
        return $this->hasMany(File::class, 'tablero_id', 'tablero_id');
    }

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id', 'meta_id');
    }
}
