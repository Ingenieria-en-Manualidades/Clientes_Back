<?php

namespace App\Models\survey;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyHasQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.survey_has_question';
    protected $primaryKey = 'survey_has_question_id';

    protected $fillable = [
        'survey_id',
        'question_id',
        'username',
        'active',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'survey_id', 'survey_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }
}
