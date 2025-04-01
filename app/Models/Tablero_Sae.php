<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tablero_Sae extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'CLIENTS.tablero_sae';
    protected $primaryKey = 'tablero_sae_id';

    protected $fillable = ['fecha', 'meta_id', 'cliente_id'];

    public function objetivos()
    {
        return $this->hasMany(Objetivo::class, 'tablero_sae_id', 'tablero_sae_id');
    }

    public function files()
    {
        return $this->hasMany(File::class, 'tablero_sae_id', 'tablero_sae_id');
    }

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id', 'meta_id');
    }

    public function clientes()
    {
        return $this->belongsTo(Cliente::class, 'id', 'id');
    }
}
