<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'title',
        'description',
        'thumbnail',
        'duration',
        'difficulty',
        'category',
        'status',
    ];
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
