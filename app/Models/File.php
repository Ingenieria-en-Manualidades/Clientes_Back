<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'CLIENTS.files';
    protected $primaryKey = 'files_id';

    protected $fillable = [
        'ruta',
        'tipo',
        'tablero_sae_id'
    ];

    public function tablero_sae()
    {
        return $this->belongsTo(Tablero_Sae::class, 'tablero_sae_id', 'tablero_sae_id');
    }
}
