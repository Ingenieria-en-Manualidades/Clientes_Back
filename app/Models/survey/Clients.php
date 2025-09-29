<?php

namespace App\Models\survey;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clients extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.clients';
    protected $primaryKey = 'clients_id';

    protected $fillable = [
        'name',
        'feed_value',
        'cost_center',
        'overtime',
        'deleted_at',
        'username',
        'city_id',
        'cost_center_type',
        'company_id',
        'purchasing_manager_id',
        'client_cg1_id',
        'active',
    ];

    public function type_operation_has_clients()
    {
        return $this->hasMany(TypeOperationHasClients::class, 'clients_id', 'clients_id');
    }
}
