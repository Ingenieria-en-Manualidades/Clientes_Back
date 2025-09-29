<?php

namespace App\Models\survey;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BooleanAnswer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.boolean_answer';
    protected $primaryKey = 'boolean_answer_id';

    protected $fillable = [
        'answer',
        'observation',
        'survey_has_question_id',
        'username',
        'active',
    ];

    public function survey_has_question()
    {
        return $this->belongsTo(SurveyHasQuestion::class, 'survey_has_question_id', 'survey_has_question_id');
    }
}
