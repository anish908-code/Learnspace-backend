<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'video_url',
        'notes',
        'notes_file',
        'lesson_order',
        'status',
    ];
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
