<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tablero_Sae extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tablero_sae';
    protected $primaryKey = 'tablero_id';

    protected $fillable = ['mes'];

    public function calidad()
    {
        return $this->hasMany(Calidad::class, 'tablero_id', 'tablero_id');
    }

    public function accidentes()
    {
        return $this->hasMany(Accidente::class, 'tablero_id', 'tablero_id');
    }

    public function objetives()
    {
        return $this->hasMany(Objetive::class, 'tablero_id', 'tablero_id');
    }

    public function produccion()
    {
        return $this->hasMany(Produccion::class, 'tablero_id', 'tablero_id');
    }

    public function indicadores()
    {
        return $this->hasMany(Indicadores::class, 'tablero_id', 'tablero_id');
    }
}
