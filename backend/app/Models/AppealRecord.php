<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppealRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_record_id',
        'proctor_event_id',
        'user_id',
        'reviewer_id',
        'explanation',
        'screenshots',
        'status',
        'review_comment',
        'reviewed_at',
    ];

    protected $casts = [
        'screenshots' => 'array',
        'reviewed_at' => 'datetime',
        'exam_record_id' => 'integer',
        'proctor_event_id' => 'integer',
        'user_id' => 'integer',
        'reviewer_id' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => '待审核',
        self::STATUS_APPROVED => '申诉通过',
        self::STATUS_REJECTED => '申诉驳回',
    ];

    public function examRecord()
    {
        return $this->belongsTo(ExamRecord::class, 'exam_record_id');
    }

    public function proctorEvent()
    {
        return $this->belongsTo(ProctorEvent::class, 'proctor_event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
