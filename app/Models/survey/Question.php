<?php

namespace App\Models\survey;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.question';
    protected $primaryKey = 'question_id';

    protected $fillable = [
        'description',
        'type',
        'deleted_at',
        'username',
        'active',
    ];

    public function survey_has_question()
    {
        return $this->hasMany(SurveyHasQuestion::class, 'question_id', 'question_id');
    }
}
