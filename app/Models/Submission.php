<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    protected $fillable = [
        'student_id',
        'project_id',
        'github_link',
        'live_demo',
        'documentation',
        'description',
        'status',
        'feedback',
        'submitted_at',
        'reviewed_at',

    ];
    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
