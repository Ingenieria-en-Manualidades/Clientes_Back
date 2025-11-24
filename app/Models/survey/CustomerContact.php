<?php

namespace App\Models\survey;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerContact extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.customer_contact';
    protected $primaryKey = 'customer_contact_id';

    protected $fillable = [
        'fullname',
        'cellphone',
        'email',
        'user_id',
        'username',
        'active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class, 'charge_id', 'charge_id');
    }

    public function customer_contact_has_survey()
    {
        return $this->hasMany(CustomerContactHasSurvey::class, 'customer_contact_id', 'customer_contact_id');
    }
}
