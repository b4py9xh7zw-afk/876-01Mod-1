<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProctorEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_record_id',
        'event_type',
        'event_time',
        'detail',
    ];

    protected $casts = [
        'event_time' => 'datetime',
    ];

    public const TYPE_SCREEN_SWITCH = 'screen_switch';
    public const TYPE_CAMERA_DISCONNECT = 'camera_disconnect';
    public const TYPE_IDLE = 'idle';
    public const TYPE_NETWORK_RECOVER = 'network_recover';

    public const TYPES = [
        self::TYPE_SCREEN_SWITCH => '切屏',
        self::TYPE_CAMERA_DISCONNECT => '摄像头断开',
        self::TYPE_IDLE => '长时间未操作',
        self::TYPE_NETWORK_RECOVER => '网络恢复',
    ];

    public function examRecord()
    {
        return $this->belongsTo(ExamRecord::class, 'exam_record_id');
    }

    public function appeals()
    {
        return $this->hasMany(AppealRecord::class, 'proctor_event_id');
    }
}
