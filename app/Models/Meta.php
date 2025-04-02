<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meta extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'CLIENTS.meta';
    protected $primaryKey = 'meta_id';

    protected $fillable = [
        'cumplimiento',
        'eficiencia_productiva',
        'calidad',
        'desperdicio_me',
        'desperdicio_pp',
    ];

    public function tablero_sae()
    {
        return $this->hasMany(Tablero_Sae::class, 'meta_id', 'meta_id');
    }

    public function calidad()
    {
        return $this->hasMany(Calidad::class, 'meta_id', 'meta_id');
    }
}
