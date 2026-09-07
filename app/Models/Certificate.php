<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'project_id',
        'certificate_number',
        'certificate_code',
        'issue_date',
        'score',
        'grade',
        'certificate_file',
        'issued_by',
        'qr_code',
        'signatures',
        'verification_url',
    ];
    protected $casts = [
        'issue_date' => 'date',
        'score' => 'float',
        'signatures' => 'array',
    ];
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
