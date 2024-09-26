<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produccion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'produccion';
    protected $primaryKey = 'produccion_id';

    protected $fillable = [
        'planificada',
        'plan_armado',
        'modificada',
        'tablero_id'
    ];

    public function tablero_sae()
    {
        return $this->belongsTo(Tablero_Sae::class, 'tablero_id', 'tablero_id')
    }
}
