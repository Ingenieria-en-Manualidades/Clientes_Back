<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class accidentes extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accidentes';
    protected $primaryKey = 'accidentes_id';

    protected $fillable = [
        'tipo_accidente',
        'cantidad',
        'fecha_ingreso',
        'tablero_id'
    ];

    public function tablero_sae()
    {
        return $this->belongsTo(Tablero_Sae::class, 'tablero_id', 'tablero_id')
    }
}
