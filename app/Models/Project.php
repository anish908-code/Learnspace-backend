<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'required_skills',
        'difficulty',
        'deadline',
        'requirements',
        'status',
    ];
    protected $casts = [
        'deadline'=> 'date',
    ];
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
