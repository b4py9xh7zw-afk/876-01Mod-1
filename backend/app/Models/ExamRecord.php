<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_paper_id',
        'start_time',
        'end_time',
        'score',
        'status',
        'has_anomaly',
        'anomaly_status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'exam_paper_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'score' => 'decimal:2',
        'status' => 'string',
        'has_anomaly' => 'boolean',
    ];

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_GRADED = 'graded';

    public const STATUSES = [
        self::STATUS_IN_PROGRESS => '进行中',
        self::STATUS_SUBMITTED => '已提交',
        self::STATUS_GRADED => '已评分',
    ];

    public const ANOMALY_NONE = 'none';
    public const ANOMALY_FLAGGED = 'flagged';
    public const ANOMALY_APPEALED = 'appealed';
    public const ANOMALY_RESOLVED = 'resolved';

    public const ANOMALY_STATUSES = [
        self::ANOMALY_NONE => '无异常',
        self::ANOMALY_FLAGGED => '异常标记',
        self::ANOMALY_APPEALED => '已申诉',
        self::ANOMALY_RESOLVED => '已处理',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function examPaper()
    {
        return $this->belongsTo(ExamPaper::class, 'exam_paper_id');
    }

    public function answers()
    {
        return $this->hasMany(ExamRecordAnswer::class, 'exam_record_id');
    }

    public function proctorEvents()
    {
        return $this->hasMany(ProctorEvent::class, 'exam_record_id')->orderBy('event_time', 'asc');
    }

    public function appeals()
    {
        return $this->hasMany(AppealRecord::class, 'exam_record_id');
    }
}
