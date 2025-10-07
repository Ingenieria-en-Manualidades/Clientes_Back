<?php

namespace App\Models\survey;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charge extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.charge';
    protected $primaryKey = 'charge_id';

    protected $fillable = [
        'description',
        'deleted_at',
        'username',
        'active',
    ];

    public function survey()
    {
        return $this->hasMany(Survey::class, 'charge_id', 'charge_id');
    }

    public function customer_contact()
    {
        return $this->hasMany(CustomerContact::class, 'charge_id', 'charge_id');
    }
}
