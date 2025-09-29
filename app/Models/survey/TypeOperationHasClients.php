<?php

namespace App\Models\survey;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeOperationHasClients extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.type_operation_has_clients';
    protected $primaryKey = 'type_operation_has_clients_id';

    protected $fillable = [
        'type_operation_id',
        'clients_id',
        'deleted_at',
        'username',
        'active',
    ];

    public function type_operation()
    {
        return $this->belongsTo(TypeOperation::class, 'type_operation_id', 'type_operation_id');
    }

    public function clients()
    {
        return $this->belongsTo(Clients::class, 'clients_id', 'clients_id');
    }
}
