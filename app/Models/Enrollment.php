<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'progress',
        'lessons_completed',
        'final_quiz_score',
        'final_quiz_passed',
        'completed',
        'started_at',
        'completed_at',
    ];
    protected $casts = [
        'final_quiz_passed' => 'boolean',
        'completed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessonCompletions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }
}
