<?php

namespace App\Models\survey;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeOperation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.type_operation';
    protected $primaryKey = 'type_operation_id';

    protected $fillable = [
        'description',
        'deleted_at',
        'username',
        'active',
    ];

    public function type_operation_has_clients()
    {
        return $this->hasMany(TypeOperationHasClients::class, 'type_operation_id', 'type_operation_id');
    }
}
