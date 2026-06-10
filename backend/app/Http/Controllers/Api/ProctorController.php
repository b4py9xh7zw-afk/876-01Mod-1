<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppealRecord;
use App\Models\ExamRecord;
use App\Models\ProctorEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProctorController extends Controller
{
    public function recordEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'event_type' => 'required|in:screen_switch,camera_disconnect,idle,network_recover',
            'event_time' => 'required|date',
            'detail' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$record) {
            return response()->json(['message' => '无权操作此考试记录'], 403);
        }

        $event = ProctorEvent::create([
            'exam_record_id' => $request->exam_record_id,
            'event_type' => $request->event_type,
            'event_time' => $request->event_time,
            'detail' => $request->detail,
        ]);

        if (!$record->has_anomaly) {
            $record->update([
                'has_anomaly' => true,
                'anomaly_status' => ExamRecord::ANOMALY_FLAGGED,
            ]);
        }

        return response()->json([
            'message' => '事件已记录',
            'event' => $event,
        ]);
    }

    public function getProctorEvents(Request $request, ExamRecord $record)
    {
        $user = $request->user();
        $isTeacherOrAdmin = $user->isAdmin() || $user->isTeacher();

        if ($record->user_id !== $user->id && !$isTeacherOrAdmin) {
            return response()->json(['message' => '无权查看此记录'], 403);
        }

        $events = $record->proctorEvents()->orderBy('event_time', 'asc')->get();

        $eventsWithAppeals = $events->map(function ($event) {
            $appeal = $event->appeals()->first();
            return [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'event_type_label' => ProctorEvent::TYPES[$event->event_type] ?? $event->event_type,
                'event_time' => $event->event_time,
                'detail' => $event->detail,
                'appeal' => $appeal ? [
                    'id' => $appeal->id,
                    'status' => $appeal->status,
                    'status_label' => AppealRecord::STATUSES[$appeal->status] ?? $appeal->status,
                    'explanation' => $appeal->explanation,
                    'screenshots' => $appeal->screenshots,
                    'review_comment' => $appeal->review_comment,
                    'reviewed_at' => $appeal->reviewed_at,
                ] : null,
            ];
        });

        return response()->json([
            'exam_record' => [
                'id' => $record->id,
                'user_id' => $record->user_id,
                'score' => $record->score,
                'status' => $record->status,
                'has_anomaly' => $record->has_anomaly,
                'anomaly_status' => $record->anomaly_status,
                'anomaly_status_label' => ExamRecord::ANOMALY_STATUSES[$record->anomaly_status] ?? $record->anomaly_status,
                'created_at' => $record->created_at,
            ],
            'user' => $record->user ? [
                'id' => $record->user->id,
                'username' => $record->user->username,
                'real_name' => $record->user->real_name,
            ] : null,
            'exam_paper' => $record->examPaper ? [
                'id' => $record->examPaper->id,
                'title' => $record->examPaper->title,
            ] : null,
            'events' => $eventsWithAppeals,
        ]);
    }

    public function submitAppeal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'proctor_event_id' => 'nullable|exists:proctor_events,id',
            'explanation' => 'required|string|min:5',
            'screenshots' => 'nullable|array',
            'screenshots.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$record) {
            return response()->json(['message' => '无权操作此考试记录'], 403);
        }

        if ($request->proctor_event_id) {
            $existingAppeal = AppealRecord::where('proctor_event_id', $request->proctor_event_id)
                ->where('user_id', $request->user()->id)
                ->first();
            if ($existingAppeal) {
                return response()->json(['message' => '该异常事件已提交过申诉'], 422);
            }
        }

        $appeal = AppealRecord::create([
            'exam_record_id' => $request->exam_record_id,
            'proctor_event_id' => $request->proctor_event_id,
            'user_id' => $request->user()->id,
            'explanation' => $request->explanation,
            'screenshots' => $request->screenshots,
            'status' => AppealRecord::STATUS_PENDING,
        ]);

        if ($record->anomaly_status !== ExamRecord::ANOMALY_RESOLVED) {
            $record->update([
                'anomaly_status' => ExamRecord::ANOMALY_APPEALED,
            ]);
        }

        return response()->json([
            'message' => '申诉已提交',
            'appeal' => $appeal,
        ]);
    }

    public function uploadScreenshot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $path = $request->file('file')->store('appeal_screenshots', 'public');

        return response()->json([
            'message' => '上传成功',
            'url' => Storage::url($path),
        ]);
    }

    public function pendingAppeals(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isTeacher()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $appeals = AppealRecord::with(['examRecord.examPaper', 'user', 'proctorEvent'])
            ->where('status', AppealRecord::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'appeals' => $appeals,
        ]);
    }

    public function allAppeals(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isTeacher()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $query = AppealRecord::with(['examRecord.examPaper', 'user', 'proctorEvent', 'reviewer'])
            ->orderBy('created_at', 'desc');

        if ($request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        $appeals = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'appeals' => $appeals,
        ]);
    }

    public function myAppeals(Request $request)
    {
        $appeals = AppealRecord::with(['examRecord.examPaper', 'proctorEvent', 'reviewer'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'appeals' => $appeals,
        ]);
    }

    public function reviewAppeal(Request $request, AppealRecord $appeal)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isTeacher()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        if ($appeal->status !== AppealRecord::STATUS_PENDING) {
            return response()->json(['message' => '该申诉已被处理'], 422);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'review_comment' => 'required|string|min:2',
            'new_score' => 'nullable|numeric|min:0|max:100',
            'clear_anomaly' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $examRecord = $appeal->examRecord;

        $appeal->update([
            'status' => $request->status,
            'review_comment' => $request->review_comment,
            'reviewer_id' => $user->id,
            'reviewed_at' => now(),
        ]);

        if ($request->status === AppealRecord::STATUS_APPROVED) {
            if ($request->has('new_score') && $request->new_score !== null) {
                $examRecord->update(['score' => $request->new_score]);
            }

            if ($request->clear_anomaly) {
                $examRecord->update([
                    'has_anomaly' => false,
                    'anomaly_status' => ExamRecord::ANOMALY_NONE,
                ]);
            } else {
                $hasPendingAppeals = $examRecord->appeals()
                    ->where('id', '!=', $appeal->id)
                    ->where('status', AppealRecord::STATUS_PENDING)
                    ->exists();

                if (!$hasPendingAppeals) {
                    $examRecord->update([
                        'anomaly_status' => ExamRecord::ANOMALY_RESOLVED,
                    ]);
                }
            }
        } else {
            $hasPendingAppeals = $examRecord->appeals()
                ->where('id', '!=', $appeal->id)
                ->where('status', AppealRecord::STATUS_PENDING)
                ->exists();

            if (!$hasPendingAppeals) {
                $examRecord->update([
                    'anomaly_status' => $examRecord->has_anomaly ? ExamRecord::ANOMALY_FLAGGED : ExamRecord::ANOMALY_NONE,
                ]);
            }
        }

        return response()->json([
            'message' => '申诉已处理',
            'appeal' => $appeal->fresh()->load(['examRecord', 'reviewer']),
        ]);
    }

    public function getAppealDetail(Request $request, AppealRecord $appeal)
    {
        $user = $request->user();
        $isTeacherOrAdmin = $user->isAdmin() || $user->isTeacher();

        if ($appeal->user_id !== $user->id && !$isTeacherOrAdmin) {
            return response()->json(['message' => '无权查看此申诉'], 403);
        }

        $appeal->load(['examRecord.examPaper', 'examRecord.user', 'proctorEvent', 'user', 'reviewer']);

        return response()->json([
            'appeal' => $appeal,
        ]);
    }
}
