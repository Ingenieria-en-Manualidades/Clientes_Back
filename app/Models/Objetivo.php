<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Objetivo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'objetivos';
    protected $primaryKey = 'objetivos_id';

    protected $fillable = [
        'planificada',
        'modificada',
        'plan_armado',
        'calidad',
        'desperfecto_me',
        'desperfecto_pp',
        'tablero_sae_id',
    ];

    public function tablero_sae()
    {
        return $this->belongsTo(Tablero_Sae::class, 'tablero_sae_id', 'tablero_sae_id');
    }
}
